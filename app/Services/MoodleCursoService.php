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
     * Sube la imagen de portada del curso a Moodle.
     */
    public function subirPortada(int $courseId, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        try {
            $contextRow = DB::connection('mysql_grupoihb')->select(
                'SELECT id FROM mdl_context
                 WHERE contextlevel = 50 AND instanceid = ? LIMIT 1',
                [$courseId]
            );

            if (empty($contextRow)) {
                return;
            }

            $draftItemId = $this->subirArchivoBorrador($file);

            if (! $draftItemId) {
                return;
            }

            $http = app()->isProduction()
                ? Http::asForm()
                : Http::withoutVerifying()->asForm();

            $http->post(
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
        } catch (\Throwable $e) {
            Log::error('Error al subir imagen de portada a Moodle', [
                'error' => $e->getMessage(),
                'courseId' => $courseId,
            ]);
        }
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
