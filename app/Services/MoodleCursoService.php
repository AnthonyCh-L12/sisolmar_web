<?php

namespace App\Services;

use App\Models\Personal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Encapsula las operaciones contra Moodle relacionadas con cursos.
 *
 * Por ahora no se invoca desde NewCapacController@guardarCursoNew: la tabla
 * sw_cursos_new se llena solo con los datos del formulario. Este servicio queda
 * listo para cuando se active la creación automática del curso en Moodle.
 */
class MoodleCursoService
{
    /**
     * Crea el curso en Moodle y devuelve el id del curso creado.
     */
    public function crearCurso(
        string $nombre,
        int $codigoCurso,
        ?string $descripcion,
        ?int $codMoodleArea,
        ?string $codResponsable,
        int $aplicaEvaluacion,
        int $tiempo,
        int $intentos,
        float $nota,
        int $inicio,
        int $fin
    ): ?int {
        try {
            $moodleUserId = $this->getOrCreateMoodleUser($codResponsable);

            DB::connection('mysql_grupoihb')->statement(
                'CALL SP_CREAR_CURSO(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @resultado)',
                [
                    $nombre,
                    $codigoCurso,
                    $descripcion,
                    $codMoodleArea,
                    $moodleUserId,
                    $aplicaEvaluacion,
                    $tiempo,
                    $intentos,
                    $nota,
                    $inicio,
                    $fin,
                ]
            );

            $res = DB::connection('mysql_grupoihb')->select(
                'SELECT @resultado AS resultado'
            );

            $resultado = (int) ($res[0]->resultado ?? 0);

            if ($resultado <= 0) {
                Log::warning('SP_CREAR_CURSO devolvió un código de error', [
                    'resultado' => $resultado,
                    'codigo_curso' => $codigoCurso,
                ]);

                return null;
            }

            return $resultado;
        } catch (\Throwable $e) {
            Log::error('Error al crear curso en Moodle', [
                'error' => $e->getMessage(),
                'codigo_curso' => $codigoCurso,
                'responsable' => $codResponsable,
            ]);

            return null;
        }
    }

    /**
     * Actualiza las fechas de inicio y fin del curso en Moodle.
     */
    public function actualizarFechasCurso(int $courseId, int $inicio, int $fin): bool
    {
        try {
            $http = app()->isProduction()
                ? Http::asForm()
                : Http::withoutVerifying()->asForm();

            $response = $http->post(
                config('services.moodle.url').'/webservice/rest/server.php',
                [
                    'wstoken' => config('services.moodle.ws_token'),
                    'wsfunction' => 'core_course_update_courses',
                    'moodlewsrestformat' => 'json',
                    'courses[0][id]' => $courseId,
                    'courses[0][startdate]' => $inicio,
                    'courses[0][enddate]' => $fin,
                ]
            );

            $json = $response->json();

            if (! $response->successful() || (is_array($json) && isset($json['exception']))) {
                Log::warning('No se pudieron actualizar las fechas del curso en Moodle', [
                    'courseId' => $courseId,
                    'status' => $response->status(),
                    'response' => $json,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al actualizar fechas del curso en Moodle', [
                'error' => $e->getMessage(),
                'courseId' => $courseId,
            ]);

            return false;
        }
    }

    /**
     * Actualiza la fecha de fin de las inscripciones de los alumnos en Moodle.
     */
    public function actualizarFechasMatriculas(int $courseId, int $fin): bool
    {
        if ($courseId <= 0) {
            return true;
        }

        try {
            DB::connection('mysql_grupoihb')
                ->table('mdl_user_enrolments as ue')
                ->join('mdl_enrol as e', 'e.id', '=', 'ue.enrolid')
                ->join('mdl_role_assignments as ra', 'ra.userid', '=', 'ue.userid')
                ->join('mdl_context as ctx', function ($join) use ($courseId) {
                    $join->on('ctx.id', '=', 'ra.contextid')
                        ->where('ctx.contextlevel', 50)
                        ->where('ctx.instanceid', $courseId);
                })
                ->where('e.courseid', $courseId)
                ->where('ue.status', 0)
                ->where('ra.roleid', 5)
                ->update([
                    'ue.timeend' => $fin,
                    'ue.timemodified' => time(),
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al actualizar fechas de matrículas en Moodle', [
                'error' => $e->getMessage(),
                'courseId' => $courseId,
            ]);

            return false;
        }
    }

    /**
     * Sube la imagen de portada del curso a Moodle.
     *
     * Devuelve true si la imagen se envió o si no había imagen; false si
     * existiendo imagen no se pudo procesar.
     */
    public function subirPortada(int $courseId, ?UploadedFile $file): bool
    {
        if (! $file) {
            return true;
        }

        try {
            $contextRow = DB::connection('mysql_grupoihb')->select(
                'SELECT id FROM mdl_context
                 WHERE contextlevel = 50 AND instanceid = ? LIMIT 1',
                [$courseId]
            );

            if (empty($contextRow)) {
                Log::warning('No se encontró el contexto del curso para subir la portada', [
                    'courseId' => $courseId,
                ]);

                return false;
            }

            $draftItemId = $this->subirArchivoBorrador($file);

            if (! $draftItemId) {
                return false;
            }

            $http = app()->isProduction()
                ? Http::asForm()
                : Http::withoutVerifying()->asForm();

            $response = $http->post(
                config('services.moodle.url').'/webservice/rest/server.php',
                [
                    'wstoken' => config('services.moodle.ws_token'),
                    'wsfunction' => 'core_course_update_courses',
                    'moodlewsrestformat' => 'json',
                    'courses[0][id]' => $courseId,
                    'courses[0][courseformatoptions][0][name]' => 'overviewfiles_filemanager',
                    'courses[0][courseformatoptions][0][value]' => $draftItemId,
                ]
            );

            if ($response->failed() || $response->json('exception')) {
                Log::warning('Moodle rechazó la portada del curso', [
                    'courseId' => $courseId,
                    'response' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al subir imagen de portada a Moodle', [
                'error' => $e->getMessage(),
                'courseId' => $courseId,
            ]);

            return false;
        }
    }

    /**
     * Sube el afiche informativo del curso como una sección/etiqueta en Moodle.
     */
    public function subirAfiche(int $courseId, ?UploadedFile $file): bool
    {
        if (! $file) {
            return true;
        }

        try {
            $filename = $file->getClientOriginalName();

            $contextRow = DB::connection('mysql_grupoihb')->select(
                'SELECT id FROM mdl_context
                 WHERE contextlevel = 50 AND instanceid = ? LIMIT 1',
                [$courseId]
            );

            if (empty($contextRow)) {
                Log::warning('No se encontró el contexto del curso para subir el afiche', [
                    'courseId' => $courseId,
                ]);

                return false;
            }

            $contextid = $contextRow[0]->id;
            $draftItemId = $this->subirArchivoBorrador($file);

            if (! $draftItemId) {
                return false;
            }

            $seccionNombre = 'Afiche informativo';

            $seccionExistente = DB::connection('mysql_grupoihb')->select(
                'SELECT id, section FROM mdl_course_sections
                 WHERE course = ? AND name = ? LIMIT 1',
                [$courseId, $seccionNombre]
            );

            if (! empty($seccionExistente)) {
                $sectionId = $seccionExistente[0]->id;
            } else {
                DB::connection('mysql_grupoihb')->statement(
                    'UPDATE mdl_course_sections
                     SET section = section + 1
                     WHERE course = ? AND section >= 1',
                    [$courseId]
                );

                $sectionNumber = 1;

                DB::connection('mysql_grupoihb')
                    ->table('mdl_course_sections')
                    ->insert([
                        'course' => $courseId,
                        'section' => $sectionNumber,
                        'name' => $seccionNombre,
                        'summary' => '',
                        'summaryformat' => 1,
                        'sequence' => '',
                        'visible' => 1,
                        'timemodified' => time(),
                    ]);

                $sectionId = DB::connection('mysql_grupoihb')
                    ->getPdo()
                    ->lastInsertId();
            }

            DB::connection('mysql_grupoihb')
                ->table('mdl_label')
                ->insert([
                    'course' => $courseId,
                    'name' => $seccionNombre,
                    'intro' => '',
                    'introformat' => 1,
                    'timemodified' => time(),
                ]);

            $labelId = DB::connection('mysql_grupoihb')
                ->getPdo()
                ->lastInsertId();

            $moduleRow = DB::connection('mysql_grupoihb')->select(
                "SELECT id FROM mdl_modules WHERE name = 'label' LIMIT 1"
            );
            $moduleId = $moduleRow[0]->id ?? null;

            if (! $moduleId || ! $sectionId) {
                return false;
            }

            DB::connection('mysql_grupoihb')
                ->table('mdl_course_modules')
                ->insert([
                    'course' => $courseId,
                    'module' => $moduleId,
                    'instance' => $labelId,
                    'section' => $sectionId,
                    'added' => time(),
                    'visible' => 1,
                    'visibleold' => 1,
                    'completion' => 0,
                ]);

            $cmId = DB::connection('mysql_grupoihb')
                ->getPdo()
                ->lastInsertId();

            DB::connection('mysql_grupoihb')->statement(
                "UPDATE mdl_course_sections
                 SET sequence = CONCAT(IF(sequence = '' OR sequence IS NULL, '', CONCAT(sequence, ',')), ?)
                 WHERE id = ?",
                [$cmId, $sectionId]
            );

            DB::connection('mysql_grupoihb')
                ->table('mdl_context')
                ->insert([
                    'contextlevel' => 70,
                    'instanceid' => $cmId,
                    'depth' => 4,
                    'path' => '',
                ]);

            $cmContextId = DB::connection('mysql_grupoihb')
                ->getPdo()
                ->lastInsertId();

            DB::connection('mysql_grupoihb')->statement(
                "UPDATE mdl_context SET path = CONCAT('/1/', ?, '/', ?) WHERE id = ?",
                [$contextid, $cmContextId, $cmContextId]
            );

            $newPathnamehash = sha1(
                "/{$cmContextId}/mod_label/intro/0/{$filename}"
            );

            DB::connection('mysql_grupoihb')
                ->table('mdl_files')
                ->where('component', 'user')
                ->where('filearea', 'draft')
                ->where('itemid', $draftItemId)
                ->where('filename', $filename)
                ->update([
                    'component' => 'mod_label',
                    'filearea' => 'intro',
                    'itemid' => 0,
                    'filepath' => '/',
                    'contextid' => $cmContextId,
                    'pathnamehash' => $newPathnamehash,
                    'timemodified' => time(),
                ]);

            $modalId = 'afiche-modal-'.$courseId;

            $htmlAfiche = "
                <!-- Modal afiche -->
                <div id='{$modalId}' style='
                    display:none; position:fixed; z-index:99999;
                    inset:0; background:rgba(0,0,0,0.85);
                    align-items:center; justify-content:center;
                ' onclick='this.style.display=\"none\"'>
                    <div style='position:relative; max-width:90vw; max-height:90vh;'>
                        <img src='@@PLUGINFILE@@/{$filename}'
                            alt='Afiche del curso'
                            style='
                                display:block;
                                max-width:90vw; max-height:90vh;
                                width:auto; height:auto;
                                object-fit:contain;
                                border-radius:6px;
                                box-shadow:0 8px 32px rgba(0,0,0,0.6);
                            ' />
                    </div>
                </div>

                <script>
                (function () {
                    var sectionName = '{$seccionNombre}';
                    var modalId     = '{$modalId}';

                    function attachTrigger() {
                        var headers = document.querySelectorAll(
                            '.sectionname, .section-title, h3.sectionname a, ' +
                            '[data-sectionname], .course-section-header .sectionname'
                        );
                        for (var i = 0; i < headers.length; i++) {
                            var el = headers[i];
                            if (el.textContent.trim() === sectionName && !el.dataset.afficheReady) {
                                el.dataset.afficheReady = '1';
                                el.style.cursor = 'pointer';
                                el.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    document.getElementById(modalId).style.display = 'flex';
                                });
                            }
                        }
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', attachTrigger);
                    } else {
                        attachTrigger();
                    }

                    if (window.MutationObserver) {
                        new MutationObserver(attachTrigger).observe(document.body, { childList: true, subtree: true });
                    }
                })();
                </script>
            ";

            DB::connection('mysql_grupoihb')
                ->table('mdl_label')
                ->where('id', $labelId)
                ->update([
                    'intro' => $htmlAfiche,
                    'timemodified' => time(),
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al subir afiche a Moodle', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'courseId' => $courseId,
            ]);

            return false;
        }
    }

    /**
     * Inserta las preguntas del examen en el quiz de Moodle del curso.
     *
     * No persiste nada en la base local: el banco de preguntas queda
     * únicamente en Moodle hasta definir la tabla definitiva.
     */
    public function sincronizarPreguntas(
        int $courseId,
        array $preguntas,
        int $cantidadPreguntas = 0
    ): bool {
        if (empty($preguntas)) {
            return true;
        }

        try {
            $preguntas = $this->seleccionarPreguntas($preguntas, $cantidadPreguntas);

            if (empty($preguntas)) {
                Log::warning('No se pudieron seleccionar preguntas para el quiz de Moodle', [
                    'courseId' => $courseId,
                    'cantidad_preguntas' => $cantidadPreguntas,
                ]);

                return false;
            }

            $quizRow = DB::connection('mysql_grupoihb')->select(
                'SELECT id FROM mdl_quiz WHERE course = ? LIMIT 1',
                [$courseId]
            );

            if (empty($quizRow)) {
                Log::warning('El curso de Moodle no tiene un quiz asociado', [
                    'courseId' => $courseId,
                ]);

                return false;
            }

            $quizId = $quizRow[0]->id;
            $preguntasTransformadas = $this->transformarPreguntas($preguntas);
            $jsonPreguntas = json_encode($preguntasTransformadas, JSON_UNESCAPED_UNICODE);

            Log::info('JSON SP_INSERTAR_PREGUNTAS_R => '.$jsonPreguntas);

            DB::connection('mysql_grupoihb')->statement(
                'CALL SP_INSERTAR_PREGUNTAS_R(?, ?, @res_preguntas)',
                [$quizId, $jsonPreguntas]
            );

            $resultado = DB::connection('mysql_grupoihb')->selectOne(
                'SELECT @res_preguntas AS resultado'
            );

            Log::info('Resultado SP_INSERTAR_PREGUNTAS_R', [
                'quiz_id' => $quizId,
                'resultado' => $resultado->resultado ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al sincronizar preguntas con Moodle', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'courseId' => $courseId,
            ]);

            return false;
        }
    }

    /**
     * Selecciona la cantidad configurada de preguntas respetando la mezcla
     * histórica entre básicas (A) y complementarias (B).
     */
    private function seleccionarPreguntas(array $preguntas, int $cantidadPreguntas): array
    {
        if ($cantidadPreguntas <= 0 || count($preguntas) <= $cantidadPreguntas) {
            return array_values($preguntas);
        }

        $basicas = array_values(
            array_filter($preguntas, fn ($p) => ($p['tipo'] ?? '') === 'A')
        );
        $complementarias = array_values(
            array_filter($preguntas, fn ($p) => ($p['tipo'] ?? '') === 'B')
        );

        shuffle($basicas);
        shuffle($complementarias);

        $mitad = intdiv($cantidadPreguntas, 2);

        $seleccion = array_merge(
            array_slice($basicas, 0, $mitad),
            array_slice($complementarias, 0, $mitad)
        );

        if (count($seleccion) < $cantidadPreguntas) {
            $resto = array_merge(
                array_slice($basicas, $mitad),
                array_slice($complementarias, $mitad),
                array_values(
                    array_filter(
                        $preguntas,
                        fn ($p) => ! in_array($p['tipo'] ?? '', ['A', 'B'], true)
                    )
                )
            );

            shuffle($resto);

            $seleccion = array_merge(
                $seleccion,
                array_slice($resto, 0, $cantidadPreguntas - count($seleccion))
            );
        }

        return $seleccion;
    }

    /**
     * Adapta el formato del Word al contrato del SP de Moodle.
     */
    private function transformarPreguntas(array $preguntas): array
    {
        return array_map(function ($pregunta) {
            $opciones = $pregunta['opciones'] ?? [];
            $respuesta = $pregunta['respuesta_correcta'] ?? '';

            if (
                count($opciones) === 2 &&
                $opciones[0] === 'Verdadero' &&
                $opciones[1] === 'Falso'
            ) {
                return [
                    'tipo_pregunta' => 'true_or_false',
                    'texto' => $pregunta['texto'],
                    'respuesta_correcta' => $respuesta === 'A',
                ];
            }

            $indiceRespuesta = ord($respuesta) - ord('A');

            return [
                'tipo_pregunta' => 'multiple_choice',
                'texto' => $pregunta['texto'],
                'opciones' => $opciones,
                'respuesta_correcta' => $opciones[$indiceRespuesta] ?? null,
            ];
        }, $preguntas);
    }

    /**
     * Sube un archivo al área draft del usuario administrador de Moodle.
     */
    private function subirArchivoBorrador(UploadedFile $file): ?int
    {
        $http = app()->isProduction()
            ? Http::asForm()
            : Http::withoutVerifying()->asForm();

        $response = $http->post(
            config('services.moodle.url').'/webservice/rest/server.php',
            [
                'wstoken' => config('services.moodle.ws_token'),
                'wsfunction' => 'core_files_upload',
                'moodlewsrestformat' => 'json',
                'contextlevel' => 'user',
                'instanceid' => config('services.moodle.admin_id'),
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $file->getClientOriginalName(),
                'filecontent' => base64_encode(
                    file_get_contents($file->getRealPath())
                ),
            ]
        );

        return $response->json('itemid') ?? null;
    }

    /**
     * Devuelve el id del usuario Moodle del responsable; lo crea si no existe.
     */
    private function getOrCreateMoodleUser(?string $codResponsable): ?int
    {
        if (empty($codResponsable)) {
            return null;
        }

        $personal = Personal::where('CODI_PERS', $codResponsable)->first();
        if (! $personal) {
            Log::warning(
                "No se encontró personal con CODI_PERS: {$codResponsable}"
            );

            return null;
        }

        $dni = trim($personal->NRO_DOCU_IDEN);
        if (empty($dni)) {
            Log::warning("Personal {$codResponsable} no tiene DNI registrado.");

            return null;
        }

        $moodleUser = DB::connection('mysql_grupoihb')
            ->table('mdl_user')
            ->where('username', $dni)
            ->orWhere('idnumber', $dni)
            ->first();

        if ($moodleUser) {
            return (int) $moodleUser->id;
        }

        $firstname = trim(
            ($personal->NOMB_1 ?? '').' '.($personal->NOMB_2 ?? '')
        );
        $lastname = trim(
            ($personal->APEL_1 ?? '').' '.($personal->APEL_2 ?? '')
        );

        $email = ! empty(trim($personal->PERS_EMAIL ?? ''))
            && filter_var(trim($personal->PERS_EMAIL), FILTER_VALIDATE_EMAIL)
            ? trim($personal->PERS_EMAIL)
            : "{$dni}@sisolmar.com";

        DB::connection('mysql_grupoihb')->statement(
            'CALL SP_CREAR_USUARIO(?, ?, ?, ?, ?, @resultado)',
            [
                $dni,
                'Gpo$olSEE_1@',
                $firstname,
                $lastname,
                $email,
            ]
        );

        $res = DB::connection('mysql_grupoihb')->select(
            'SELECT @resultado AS user_id'
        );

        $moodleUserId = (int) ($res[0]->user_id ?? 0);

        if ($moodleUserId <= 0) {
            Log::error(
                "Error al crear usuario en Moodle para DNI {$dni}. Resultado: {$moodleUserId}"
            );

            return null;
        }

        return $moodleUserId;
    }
}
