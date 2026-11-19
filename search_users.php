<?php
// Lee usernames.txt y genera consulta.sql (personal SIN los aprobados).
// Uso: php generar_consulta.php   -> luego pega consulta.sql en tu cliente MySQL

$courseId = 120;   // <-- ID del curso Moodle a consultar
$archivo  = __DIR__ . '/usernames.txt';

if (!is_file($archivo)) { exit("No existe el archivo: $archivo\n"); }

$usernames = array_values(array_unique(array_filter(
    array_map('trim', file($archivo, FILE_IGNORE_NEW_LINES))
)));

// Solo dígitos/letras, por seguridad
$usernames = array_values(array_filter($usernames, fn($u) => preg_match('/^[A-Za-z0-9._-]+$/', $u)));

$lista = implode("\n  UNION ALL ", array_map(
    fn($i, $u) => ($i === 0 ? "SELECT '$u' AS username" : "SELECT '$u'"),
    array_keys($usernames), $usernames
));

$sql = <<<'SQL'
SELECT x.username, x.nombre_completo, x.estado
FROM (
  SELECT
      t.username,
      CONCAT(u.firstname, ' ', u.lastname) AS nombre_completo,
      CASE
          WHEN u.id IS NULL THEN 'No existe en Moodle'
          WHEN aprob.userid IS NOT NULL THEN 'Aprobado'
          WHEN fin.userid   IS NOT NULL THEN 'Desaprobado'
          WHEN enr.userid   IS NULL     THEN 'No matriculado'
          WHEN prog.userid  IS NOT NULL OR la.timeaccess IS NOT NULL THEN 'En curso'
          ELSE 'Sin acceder'
      END AS estado
  FROM (
  {LISTA}
  ) t
  LEFT JOIN mdl_user u ON u.username = t.username
  LEFT JOIN (
      SELECT DISTINCT ue.userid
      FROM mdl_user_enrolments ue
      JOIN mdl_enrol e ON e.id = ue.enrolid
      WHERE e.courseid = {CURSO}
  ) enr ON enr.userid = u.id
  LEFT JOIN mdl_user_lastaccess la ON la.userid = u.id AND la.courseid = {CURSO}
  LEFT JOIN (
      SELECT DISTINCT qa.userid
      FROM mdl_quiz_attempts qa
      JOIN mdl_quiz q ON q.id = qa.quiz
      WHERE q.course = {CURSO} AND qa.state = 'finished'
  ) fin ON fin.userid = u.id
  LEFT JOIN (
      SELECT DISTINCT qa.userid
      FROM mdl_quiz_attempts qa
      JOIN mdl_quiz q ON q.id = qa.quiz
      WHERE q.course = {CURSO} AND qa.state = 'inprogress'
  ) prog ON prog.userid = u.id
  LEFT JOIN (
      SELECT DISTINCT qg.userid
      FROM mdl_quiz_grades qg
      JOIN mdl_quiz q ON q.id = qg.quiz
      JOIN mdl_grade_items gi
            ON gi.iteminstance = q.id AND gi.itemmodule = 'quiz' AND gi.courseid = {CURSO}
      WHERE q.course = {CURSO} AND qg.grade >= gi.gradepass
  ) aprob ON aprob.userid = u.id
) x
WHERE x.estado <> 'Aprobado'
ORDER BY x.estado, x.nombre_completo;
SQL;

$sql = str_replace(['{LISTA}', '{CURSO}'], [$lista, (string)$courseId], $sql);

file_put_contents(__DIR__ . '/consulta.sql', $sql);
echo "Usernames leídos: " . count($usernames) . " | Curso: $courseId\n";
echo "Generado: consulta.sql\n";