<?php

namespace App\Jobs;

use App\Models\Matricula;
use App\Models\Personal;
use App\Models\Cursos;
use App\Models\CursoProgramacion;
use App\Models\NotificacionMatricula;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DispatchMatriculaBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $cursoCodigo;
    protected string $programacionCodigo;
    protected array $personalIds;
    protected int $usuarioId;

    /**
     * Create a new job instance.
     *
     * @param int $cursoCodigo
     * @param string $programacionCodigo
     * @param array $personalIds
     * @param int $usuarioId
     */
    public function __construct(int $cursoCodigo, string $programacionCodigo, array $personalIds, int $usuarioId)
    {
        $this->cursoCodigo = $cursoCodigo;
        $this->programacionCodigo = $programacionCodigo;
        $this->personalIds = $personalIds;
        $this->usuarioId = $usuarioId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Iniciando DispatchMatriculaBatchJob para curso {$this->cursoCodigo}", [
            'total_personas' => count($this->personalIds),
            'programacion' => $this->programacionCodigo
        ]);

        $curso = Cursos::find($this->cursoCodigo);
        $prog = CursoProgramacion::where('codigo', $this->programacionCodigo)
            ->orWhere('codigo_programacion', $this->programacionCodigo)
            ->first();

        if (!$curso || !$prog) {
            Log::error("No se encontró el curso o la programación para el Job", [
                'curso_id' => $this->cursoCodigo,
                'programacion_id' => $this->programacionCodigo
            ]);
            return;
        }

        $enviados = 0;
        $fallidos = 0;
        $totalPersonas = count($this->personalIds);

        try {
            $chunks = array_chunk($this->personalIds, 100);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $codPersonal) {
                    $codPersonal = str_pad(trim((string)$codPersonal), 5, '0', STR_PAD_LEFT);
                    if (empty($codPersonal) || $codPersonal === '00000') continue;

                    try {
                        $existe = Matricula::where('cod_curso', $this->cursoCodigo)
                            ->where('cod_programacion', $prog->codigo_programacion)
                            ->where('cod_personal', $codPersonal)
                            ->exists();

                        if (!$existe) {
                            Matricula::create([
                                'cod_curso' => $this->cursoCodigo,
                                'cod_programacion' => $prog->codigo_programacion,
                                'cod_personal' => $codPersonal,
                                'usuario_id' => $this->usuarioId,
                                'fecha_matricula' => now(),
                                'estado' => Matricula::ESTADO_MATRICULADO,
                                'tipo_matricula' => 'MASIVA',
                                'origen_matricula' => 'SISTEMA',
                            ]);
                        }

                        $personal = Personal::where('CODI_PERS', $codPersonal)->first();
                        if ($personal) {
                            $dni = trim($personal->NRO_DOCU_IDEN);
                            if ($dni) {
                                $moodleUser = DB::connection('mysql_grupoihb')->table('mdl_user')
                                    ->where('username', $dni)
                                    ->orWhere('idnumber', $dni)
                                    ->first();

                                if (!$moodleUser) {
                                    $firstname = trim(($personal->NOMB_1 ?? '') . ' ' . ($personal->NOMB_2 ?? ''));
                                    $lastname = trim(($personal->APEL_1 ?? '') . ' ' . ($personal->APEL_2 ?? ''));
                                    $email = !empty($personal->PERS_EMAIL) ? trim($personal->PERS_EMAIL) : "{$dni}@sisolmar.com";
                                    
                                    $resUser = DB::connection('mysql_grupoihb')->select(
                                        "SELECT F_USER_crear(?, ?, ?, ?, ?, ?, ?, ?, ?, ?) AS user_id",
                                        [
                                            $dni, 
                                            'Gpo$olSEE_1@', 
                                            $firstname, 
                                            $lastname, 
                                            $email, 
                                            '', 
                                            '', 
                                            '', 
                                            '', 
                                            ''
                                        ]
                                    );
                                    $moodleUserId = $resUser[0]->user_id;
                                } else {
                                    $moodleUserId = $moodleUser->id;
                                }

                                if ($moodleUserId <= 0) {
                                    Log::error("Error al crear/obtener usuario en Moodle para DNI {$dni}. Error code: {$moodleUserId}");
                                    $fallidos++;
                                    continue;
                                }

                                $fInic = Carbon::parse($prog->fecha_inicio)->format('Y-m-d H:i:s');
                                $fFin  = Carbon::parse($prog->fecha_final)->format('Y-m-d H:i:s');

                                $courseMoodle = $this->resolverCursoMoodle($curso);

                                if (!$courseMoodle) {
                                    Log::error('DispatchMatriculaBatchJob: no se encontró el curso en Moodle, no se matriculó.', [
                                        'curso_id'       => $curso->codigo,
                                        'codigo_moodle'  => $curso->codigo_moodle,
                                        'codigo_curso'   => $curso->codigo_curso,
                                        'moodle_user_id' => $moodleUserId,
                                    ]);
                                    $fallidos++;
                                    continue;
                                }

                                $moodleCourseIdNumber = trim((string) $courseMoodle->idnumber);

                                if ($moodleCourseIdNumber === '') {
                                    Log::error('DispatchMatriculaBatchJob: el curso de Moodle no tiene idnumber, no se matriculó.', [
                                        'moodle_course_id' => $courseMoodle->id,
                                        'curso_id'         => $curso->codigo,
                                        'moodle_user_id'   => $moodleUserId,
                                    ]);
                                    $fallidos++;
                                    continue;
                                }

                                $context = DB::connection('mysql_grupoihb')->table('mdl_context')
                                    ->where('contextlevel', 50) // 50 = Curso
                                    ->where('instanceid', $courseMoodle->id)
                                    ->first();

                                if (!$context) {
                                    $categoryContext = DB::connection('mysql_grupoihb')->table('mdl_context')
                                        ->where('contextlevel', 40)
                                        ->where('instanceid', $courseMoodle->category)
                                        ->first();

                                    $contextId = DB::connection('mysql_grupoihb')->table('mdl_context')->insertGetId([
                                        'contextlevel' => 50,
                                        'instanceid'   => $courseMoodle->id,
                                        'path'         => '',
                                        'depth'        => 0,
                                        'locked'       => 0
                                    ]);

                                    $path = ($categoryContext ? $categoryContext->path : '/1') . '/' . $contextId;
                                    $depth = ($categoryContext ? $categoryContext->depth : 1) + 1;

                                    DB::connection('mysql_grupoihb')->table('mdl_context')
                                        ->where('id', $contextId)
                                        ->update(['path' => $path, 'depth' => $depth]);

                                    Log::info("Contexto Moodle generado para curso ID: {$courseMoodle->id}");
                                }

                                $res = DB::connection('mysql_grupoihb')->select(
                                    "SELECT F_USER_matricula2(?, ?, ?, ?, ?, ?, ?, ?, ?) AS result",
                                    [
                                        $moodleUserId, 
                                        $moodleCourseIdNumber, 
                                        $fInic, 
                                        $fFin, 
                                        '', 
                                        '', 
                                        '', 
                                        '', 
                                        5
                                    ]
                                );

                                $resultado = (int) ($res[0]->result ?? 0);

                                if ($resultado <= 0) {
                                    Log::error('DispatchMatriculaBatchJob: Moodle no confirmó la matrícula.', [
                                        'moodle_user_id'   => $moodleUserId,
                                        'moodle_course_id' => $courseMoodle->id,
                                        'idnumber'         => $moodleCourseIdNumber,
                                        'resultado'        => $resultado,
                                    ]);
                                    $fallidos++;
                                    continue;
                                }

                                $enviados++;
                            } else {
                                Log::warning("Personal {$codPersonal} no tiene DNI registrado.");
                                $fallidos++;
                            }
                        } else {
                            Log::warning("No se encontró registro de Personal para ID: {$codPersonal}");
                            $fallidos++;
                        }

                    } catch (\Exception $e) {
                        $fallidos++;
                        Log::error("Error procesando persona {$codPersonal} en Job: " . $e->getMessage());
                    }
                }
            }

            NotificacionMatricula::crearNotificacionMultiplesFallos(
                $this->usuarioId,
                $this->cursoCodigo,
                $curso->nombre,
                $totalPersonas,
                $enviados,
                $fallidos
            );

            Log::info("Finalizado exitosamente DispatchMatriculaBatchJob para curso {$this->cursoCodigo}. Éxitos: {$enviados}, Fallos: {$fallidos}");
        } catch (\Exception $e) {
            Log::error("Error crítico en DispatchMatriculaBatchJob: " . $e->getMessage(), [
                'curso' => $this->cursoCodigo,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Resuelve el curso de Moodle sin ambigüedad.
     *
     * `codigo_moodle` es el id real del curso en `mdl_course` y debe buscarse
     * por `id`. Buscarlo además por `idnumber` con comparación numérica hacía
     * que ids como 148 coincidieran con el idnumber '0148' de otro curso y la
     * matrícula terminara en el curso equivocado. Para cursos antiguos sin
     * `codigo_moodle` se usa el `codigo_curso` contra `idnumber` como texto.
     */
    private function resolverCursoMoodle(Cursos $curso): ?object
    {
        $conn = DB::connection('mysql_grupoihb');

        if (!empty($curso->codigo_moodle)) {
            return $conn->table('mdl_course')
                ->where('id', (int) $curso->codigo_moodle)
                ->first(['id', 'idnumber', 'category']);
        }

        $ref = trim((string) $curso->codigo_curso);

        if ($ref === '') {
            return null;
        }

        return $conn->table('mdl_course')
            ->whereRaw('CAST(idnumber AS CHAR) = ?', [$ref])
            ->first(['id', 'idnumber', 'category']);
    }
}
