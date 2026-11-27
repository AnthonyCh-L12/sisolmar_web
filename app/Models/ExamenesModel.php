<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ExamenesModel extends Model
{
    protected $connection = 'mysql_grupoihb';

    public function obtenerCursosAV()
    {
        return DB::connection($this->connection)->select("
            SELECT
                c.id            AS course_id,
                c.fullname      AS course_name,
                q.id            AS quiz_id,
                q.name          AS quiz_name,
                FROM_UNIXTIME(c.timecreated) AS course_created
            FROM mdl_course c
            JOIN mdl_course_modules cm ON cm.course = c.id
            JOIN mdl_modules m         ON m.id = cm.module AND m.name = 'quiz'
            JOIN mdl_quiz q            ON q.id = cm.instance
            WHERE c.visible = 1
              AND cm.visible = 1
              AND cm.deletioninprogress = 0
            ORDER BY c.fullname, q.name
        ");
    }

    public function obtenerDetallesExamenesAV(array $dnis, $quizId): array
    {
        if (empty($dnis)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($dnis), '?'));

        $sql = "
        SELECT
            u.username                                                     AS dni,
            c.fullname                                                     AS course_name,
            DATE_FORMAT(FROM_UNIXTIME(qa.timestart), '%d/%m/%y %H:%i:%s')  AS attempt_date,
            qa.id                                                          AS attempt_id,
            ROUND(qa.sumgrades, 2)                                         AS obtained_grade,
            qa.attempt                                                     AS attempt_number,
            FROM_UNIXTIME(qa.timestart, '%H:%i:%s')                        AS start_time,
            FROM_UNIXTIME(qa.timefinish, '%H:%i:%s')                       AS end_time,
            SEC_TO_TIME(qa.timefinish - qa.timestart)                      AS duration,
            ROUND(qa.sumgrades / NULLIF(quiz.sumgrades, 0) * 100, 2)       AS passing_percentage,
            ROUND(quiz.sumgrades, 2)                                       AS max_grade,
            COALESCE(ans.correct_answers, 0)                               AS correct_answers,
            COALESCE(ans.incorrect_answers, 0)                             AS incorrect_answers,
            COALESCE(ans.total_questions, 0)                               AS total_questions,
            CONCAT(u.firstname, ' ', u.lastname)                           AS full_name
        FROM mdl_quiz_attempts qa
        JOIN mdl_user   u    ON u.id  = qa.userid
        JOIN mdl_quiz   quiz ON quiz.id = qa.quiz
        JOIN mdl_course c    ON c.id  = quiz.course
        JOIN (
            /* último intento por usuario en este quiz */
            SELECT qa2.userid, MAX(qa2.timestart) AS max_start
            FROM mdl_quiz_attempts qa2
            JOIN mdl_user u2 ON u2.id = qa2.userid
            WHERE qa2.quiz = ?
              AND u2.username IN ($placeholders)
            GROUP BY qa2.userid
        ) last ON last.userid = qa.userid AND last.max_start = qa.timestart
        LEFT JOIN (
            /* correctas/incorrectas solo de los intentos de este quiz y de los usuarios pedidos */
            SELECT
                qat.questionusageid,
                COUNT(*)                                                                  AS total_questions,
                SUM(CASE WHEN ls.fraction = 1 THEN 1 ELSE 0 END)                          AS correct_answers,
                SUM(CASE WHEN ls.fraction IS NULL OR ls.fraction < 1 THEN 1 ELSE 0 END)   AS incorrect_answers
            FROM mdl_quiz_attempts qa4
            JOIN mdl_user u4
                   ON u4.id = qa4.userid
            JOIN mdl_question_attempts qat
                   ON qat.questionusageid = qa4.uniqueid
            JOIN mdl_question_attempt_steps ls
                   ON ls.questionattemptid = qat.id
                  AND ls.sequencenumber = (
                        SELECT MAX(s2.sequencenumber)
                        FROM mdl_question_attempt_steps s2
                        WHERE s2.questionattemptid = qat.id
                  )
            WHERE qa4.quiz = ?
              AND u4.username IN ($placeholders)
            GROUP BY qat.questionusageid
        ) ans ON ans.questionusageid = qa.uniqueid
        WHERE qa.quiz = ?
    ";

        $bindings = array_merge(
            [$quizId],
            $dnis,
            [$quizId],
            $dnis,
            [$quizId]
        );

        $rows = DB::connection($this->connection)->select($sql, $bindings);

        $indexado = [];
        foreach ($rows as $row) {
            $key = strtolower($row->dni);

            if (!isset($indexado[$key]) || $row->attempt_id > $indexado[$key]->attempt_id) {
                $indexado[$key] = $row;
            }
        }

        return $indexado;
    }

    public function obtenerPreguntasYRespuestasLoteAV(array $attemptIds): array
    {
        if (empty($attemptIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));

        $sql = "
            SELECT
                qa.id               AS attempt_id,
                q.id                AS question_id,
                q.questiontext      AS question,
                qat.responsesummary AS response
            FROM mdl_quiz_attempts qa
            JOIN mdl_question_attempts qat
                    ON qat.questionusageid = qa.uniqueid
            JOIN mdl_question q
                    ON q.id = qat.questionid
            WHERE qa.id IN ($placeholders)
            ORDER BY qa.id, qat.slot
        ";

        $rows = DB::connection($this->connection)->select($sql, $attemptIds);

        if (empty($rows)) {
            return [];
        }

        $questionIds = collect($rows)->pluck('question_id')->unique()->values()->all();
        $qPlaceholders = implode(',', array_fill(0, count($questionIds), '?'));

        $optionRows = DB::connection($this->connection)->select("
            SELECT
                qa.question AS question_id,
                qa.answer   AS option_text
            FROM mdl_question_answers qa
            WHERE qa.question IN ($qPlaceholders)
            ORDER BY qa.id
        ", $questionIds);

        $optionsByQuestion = [];
        foreach ($optionRows as $opt) {
            $optionsByQuestion[(int) $opt->question_id][] = html_entity_decode(
                strip_tags($opt->option_text),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        $agrupado = [];
        foreach ($rows as $row) {
            $agrupado[(int) $row->attempt_id][] = (object) [
                'question' => html_entity_decode((string) $row->question, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'options'  => $optionsByQuestion[(int) $row->question_id] ?? [],
                'response' => html_entity_decode((string) $row->response, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        }

        return $agrupado;
    }

    public function obtenerDetallesExamenAV($dni, $quizId)
    {
        $sql = "
        SELECT
            c.fullname                                                     AS course_name,
            DATE_FORMAT(FROM_UNIXTIME(qa.timestart), '%d/%m/%y %H:%i:%s')  AS attempt_date,
            qa.id                                                          AS attempt_id,
            ROUND(qa.sumgrades, 2)                                         AS obtained_grade,
            qa.attempt                                                     AS attempt_number,
            FROM_UNIXTIME(qa.timestart, '%H:%i:%s')                        AS start_time,
            FROM_UNIXTIME(qa.timefinish, '%H:%i:%s')                       AS end_time,
            SEC_TO_TIME(qa.timefinish - qa.timestart)                      AS duration,
            ROUND(qa.sumgrades / NULLIF(quiz.sumgrades, 0) * 100, 2)       AS passing_percentage,
            ROUND(quiz.sumgrades, 2)                                       AS max_grade,
            COALESCE(ans.correct_answers, 0)                               AS correct_answers,
            COALESCE(ans.incorrect_answers, 0)                             AS incorrect_answers,
            COALESCE(ans.total_questions, 0)                               AS total_questions,
            CONCAT(u.firstname, ' ', u.lastname)                           AS full_name
        FROM mdl_quiz_attempts qa
        JOIN mdl_user   u    ON u.id  = qa.userid
        JOIN mdl_quiz   quiz ON quiz.id = qa.quiz
        JOIN mdl_course c    ON c.id  = quiz.course
        LEFT JOIN (
            SELECT
                qat.questionusageid,
                COUNT(*)                                                                  AS total_questions,
                SUM(CASE WHEN ls.fraction = 1 THEN 1 ELSE 0 END)                          AS correct_answers,
                SUM(CASE WHEN ls.fraction IS NULL OR ls.fraction < 1 THEN 1 ELSE 0 END)   AS incorrect_answers
            FROM mdl_question_attempts qat
            JOIN mdl_question_attempt_steps ls
                   ON ls.questionattemptid = qat.id
                  AND ls.sequencenumber = (
                        SELECT MAX(s2.sequencenumber)
                        FROM mdl_question_attempt_steps s2
                        WHERE s2.questionattemptid = qat.id
                  )
            GROUP BY qat.questionusageid
        ) ans ON ans.questionusageid = qa.uniqueid
        WHERE qa.quiz    = ?
          AND u.username = ?
          AND qa.id = (
                SELECT qa3.id
                FROM mdl_quiz_attempts qa3
                JOIN mdl_user u3 ON u3.id = qa3.userid
                WHERE qa3.quiz   = ?
                  AND u3.username = ?
                ORDER BY qa3.timestart DESC
                LIMIT 1
          )
        LIMIT 1
    ";

        $result = DB::connection($this->connection)->select($sql, [
            $quizId,
            $dni,
            $quizId,
            $dni,
        ]);

        return $result[0] ?? null;
    }

    public function obtenerPreguntasYRespuestasAV($dni, $attemptId)
    {
        $sql = "
        SELECT
            q.id                 AS question_id,
            q.questiontext       AS question,
            qat.responsesummary  AS response
        FROM mdl_quiz_attempts qa
        JOIN mdl_user u
                ON u.id = qa.userid
        JOIN mdl_question_usages qu
                ON qu.id = qa.uniqueid
        JOIN mdl_question_attempts qat
                ON qat.questionusageid = qu.id
        JOIN mdl_question q
                ON q.id = qat.questionid
        JOIN mdl_question_attempt_steps laststep
                ON laststep.questionattemptid = qat.id
            AND laststep.sequencenumber = (
                    SELECT MAX(s2.sequencenumber)
                    FROM mdl_question_attempt_steps s2
                    WHERE s2.questionattemptid = qat.id
            )
        WHERE qa.id       = ?
        AND u.username  = ?
        ORDER BY qat.slot
    ";

        $rows = DB::connection($this->connection)->select($sql, [
            $attemptId,
            strtolower(trim($dni)),
        ]);

        if (empty($rows)) {
            return [];
        }

        $questionIds = array_unique(array_map(fn($r) => $r->question_id, $rows));
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

        $optionRows = DB::connection($this->connection)->select("
        SELECT
            qa.question AS question_id,
            qa.answer   AS option_text
        FROM mdl_question_answers qa
        WHERE qa.question IN ($placeholders)
        ORDER BY qa.id
    ", $questionIds);

        $optionsByQuestion = [];
        foreach ($optionRows as $opt) {
            $optionsByQuestion[$opt->question_id][] = html_entity_decode(
                strip_tags($opt->option_text),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        return array_map(function ($row) use ($optionsByQuestion) {
            return (object) [
                'question' => html_entity_decode($row->question, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'response' => html_entity_decode($row->response, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'options'  => $optionsByQuestion[$row->question_id] ?? [],
            ];
        }, $rows);
    }
}
