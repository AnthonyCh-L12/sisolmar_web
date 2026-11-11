<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Services\MoodleCursoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seguimiento de matrículas respaldado por sw_cursos_new.
 *
 * Replica los contratos JSON de CapacitacionController:
 *  - getCursosSeguimiento()
 *  - obtenerDetalleCurso()
 *  - obtenerEstadoCursosAlumno()
 *
 * Los datos de participantes, notas, estados y último acceso se siguen
 * obteniendo de Moodle (mysql_grupoihb → grupoihb_see). La relación entre
 * Moodle y la base local ahora es sw_cursos_new.SW_CURS_AV_COD.
 */
class SeguimientoMatriculasNewController extends Controller
{
    /**
     * Listado de cursos para la pestaña "Cursos registrados".
     *
     * Moodle aporta course_id, responsable, total_matriculados y created_at.
     * sw_cursos_new aporta código, nombre, plan, fecha de creación y vigencia.
     */
    public function getCursosSeguimientoNew(): JsonResponse
    {
        try {
            $cursosHabilitados = collect(
                DB::connection('sqlsrv')->select(
                    "SELECT c.[CODIGO],
                c.[SW_CURS_COD],
                c.[SW_CURS_NOMBRE],
                c.[SW_CURS_RESP_COD],
                c.[SW_CURS_AV_COD],
                c.[SW_CURS_PLAN_CAPAC_COD],
                c.[fecha_creacion],
                p.[nombre] AS plan_abrev,
                ISNULL(
                    NULLIF(
                        LTRIM(RTRIM(
                            ISNULL(per.[APEL_1], '') + ' ' +
                            ISNULL(per.[APEL_2], '') + ' ' +
                            ISNULL(per.[NOMB_1], '') + ' ' +
                            ISNULL(per.[NOMB_2], '')
                        )),
                        ''
                    ),
                    'SIN RESPONSABLE'
                ) AS RESPONSABLE
            FROM [sisolm_web].[dbo].[sw_cursos_new] c
            LEFT JOIN [sisolm_web].[dbo].[sw_cursos_plan] p
                ON p.[codigo] = c.[SW_CURS_PLAN_CAPAC_COD]
            LEFT JOIN [si_solm].[dbo].[PERSONAL] per
                ON TRY_CAST(per.[CODI_PERS]      AS INT)
                    = TRY_CAST(c.[SW_CURS_RESP_COD] AS INT)
            WHERE c.[habilitado] = 1
            AND c.[SW_CURS_AV_COD] IS NOT NULL"
                )
            )->keyBy('SW_CURS_AV_COD');

            $cursosConProgramacionVigente = collect(
                DB::connection('sqlsrv')->select(
                    "SELECT DISTINCT [cod_curso]
                     FROM [sisolm_web].[dbo].[sw_cursos_programacion]
                     WHERE [habilitado] = 1
                       AND [estado_periodo] = 'VIGENTE'"
                )
            )->pluck('cod_curso')->map(fn($v) => (int) $v)->flip();

            $moodleCursos = collect(
                DB::connection('mysql_grupoihb')->select(
                    'CALL grupoihb_see.SP_OBTENER_CURSOS(?)',
                    [date('Y')]
                )
            );

            $result = $moodleCursos
                ->filter(fn($curso) => isset($cursosHabilitados[$curso->course_id]))
                ->map(function ($curso) use ($cursosHabilitados, $cursosConProgramacionVigente) {
                    $cursoHabilitado = $cursosHabilitados[$curso->course_id];

                    $nombreLocal = trim((string) ($cursoHabilitado->SW_CURS_NOMBRE ?? ''));

                    return [
                        'cod_av'             => $curso->course_id,
                        'codigo'             => $cursoHabilitado->SW_CURS_COD,
                        'nombre'             => $nombreLocal !== ''
                            ? mb_strtoupper($nombreLocal)
                            : mb_strtoupper((string) $curso->course_name),
                        'tipo_curso'         => $cursoHabilitado->plan_abrev ?? null,
                        'responsable'        => mb_strtoupper((string) ($cursoHabilitado->RESPONSABLE ?? 'SIN RESPONSABLE')),
                        'total_matriculados' => (int) $curso->total_matriculados,
                        'fecha_creacion'     => $cursoHabilitado->fecha_creacion ?? $curso->created_at,
                        'vigente'            => isset($cursosConProgramacionVigente[(int) $cursoHabilitado->CODIGO]),
                    ];
                })
                ->values();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error en getCursosSeguimientoNew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos de seguimiento.',
            ], 500);
        }
    }

    /**
     * Detalle del curso para el modal.
     *
     * El parámetro recibido es el código de negocio (SW_CURS_COD), igual que
     * antes se recibía sw_cursos.codigo_curso.
     */
    public function obtenerDetalleCursoNew(Request $request): JsonResponse
    {
        try {
            $codigoCurso = $request->course_id;

            $cursoSistema = DB::selectOne(
                'SELECT * FROM sw_cursos_new WHERE SW_CURS_COD = ?',
                [$codigoCurso]
            );

            if (! $cursoSistema) {
                return response()->json([
                    'success' => false,
                    'message' => 'Curso no encontrado.',
                ], 404);
            }

            $programaciones = DB::select(
                'SELECT * FROM sw_cursos_programacion WHERE cod_curso = ?',
                [$cursoSistema->CODIGO]
            );

            $programacionVigente = collect($programaciones)->firstWhere('estado_periodo', 'VIGENTE');
            $programacionPendiente = collect($programaciones)->firstWhere('estado_periodo', 'PENDIENTE');

            $estadisticas = [
                'total' => 0,
                'aprobados' => 0,
                'desaprobados' => 0,
                'sin_acceder' => 0,
                'en_curso' => 0,
            ];

            if ((int) $cursoSistema->SW_CURS_AV_COD > 0) {
                $estadisticas = $this->resumenParticipantes((int) $cursoSistema->SW_CURS_AV_COD);
            }

            $sistemaGestion = null;
            if ($cursoSistema->SW_CURS_SIST_GESTION_COD) {
                $sistema = DB::connection('sqlsrv')->selectOne(
                    'SELECT nombre FROM sw_cursos_sistema WHERE codigo = ?',
                    [$cursoSistema->SW_CURS_SIST_GESTION_COD]
                );
                $sistemaGestion = $sistema->nombre ?? null;
            }

            $areaResponsable = null;
            if ($cursoSistema->SW_CURS_AREA_COD) {
                $areaResponsable = DB::table('sw_cursos_area')
                    ->where('codigo', $cursoSistema->SW_CURS_AREA_COD)
                    ->value('nombre');
            }

            $nombreResponsable = null;
            if ($cursoSistema->SW_CURS_RESP_COD) {
                $responsable = DB::connection('sqlsrv')->selectOne(
                    "SELECT LTRIM(RTRIM(APEL_1 + ' ' + ISNULL(APEL_2, '') + ' ' + NOMB_1 + ' ' + ISNULL(NOMB_2, ''))) as nombre
                 FROM si_solm.dbo.PERSONAL
                 WHERE CODI_PERS = ?",
                    [str_pad((string) $cursoSistema->SW_CURS_RESP_COD, 5, '0', STR_PAD_LEFT)]
                );
                $nombreResponsable = $responsable->nombre ?? null;
            }

            return response()->json([
                'success'                => true,
                'nombre'                 => $cursoSistema->SW_CURS_NOMBRE ?? 'Sin nombre',
                'descripcion'            => $cursoSistema->SW_CURS_DESCRIPCION ?? 'Sin descripción.',
                'codigo'                 => $cursoSistema->SW_CURS_COD ?? null,
                'codigo_interno'         => $cursoSistema->CODIGO ?? null,
                'codigo_moodle'          => $cursoSistema->SW_CURS_AV_COD ?? null,
                'fecha_creacion'         => $cursoSistema->fecha_creacion ?? null,
                'sistema_gestion'        => $sistemaGestion,
                'area_responsable'       => $areaResponsable,
                'responsable'            => $nombreResponsable,
                'programacion_actual'    => $programacionVigente,
                'programacion_pendiente' => $programacionPendiente,
                'estadisticas'           => $estadisticas,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@obtenerDetalleCursoNew', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el detalle del curso.',
            ], 500);
        }
    }

    /**
     * Conteo de participantes de un curso de Moodle por estado.
     * Con $soloVigentes solo cuenta al personal vigente según SP_OBTENER_PERSONAL_BETA.
     */
    private function resumenParticipantes(int $courseId, bool $soloVigentes = true): array
    {
        $participantes = collect(
            app(MoodleCursoService::class)->obtenerEstadoParticipantes($courseId)
        );

        if ($soloVigentes) {
            $dnisVigentes = collect(
                DB::connection('sqlsrv')->select('EXEC SP_OBTENER_PERSONAL_BETA @VIGENTE = 1')
            )
                ->map(fn($p) => trim((string) ($p->NRO_DOC ?? '')))
                ->filter()
                ->flip();

            $participantes = $participantes->filter(fn(array $p) => $dnisVigentes->has($p['dni']));
        }

        $conteo = $participantes->countBy('estado');

        return [
            'total'        => $participantes->count(),
            'aprobados'    => $conteo->get('aprobado', 0),
            'desaprobados' => $conteo->get('desaprobado', 0),
            'sin_acceder'  => $conteo->get('sin_acceder', 0),
            'en_curso'     => $conteo->get('en_curso', 0),
        ];
    }

    /**
     * Cursos y estado de un alumno, cruzando Moodle con sw_cursos_new.
     */
    public function obtenerEstadoCursosAlumnoNew(Request $request): JsonResponse
    {
        try {
            $cursosMoodle = DB::connection('mysql_grupoihb')->select(
                'CALL SP_OBTENER_CURSOS_POR_USUARIO(?, ?)',
                [$request->dni, date('Y')]
            );

            $planes = DB::connection('sqlsrv')
                ->table('sw_cursos_plan')
                ->where('habilitado', 1)
                ->pluck('nombre', 'codigo');

            $cursosLocal = DB::table('sw_cursos_new')
                ->where('habilitado', 1)
                ->whereNotNull('SW_CURS_AV_COD')
                ->get()
                ->keyBy('SW_CURS_AV_COD');

            $resultado = [];

            foreach ($cursosMoodle as $cursoMoodle) {
                if (!isset($cursosLocal[$cursoMoodle->course_id])) {
                    continue;
                }

                $cursoLocal = $cursosLocal[$cursoMoodle->course_id];

                $resultado[] = [
                    'codigo' => $cursoLocal->SW_CURS_COD,
                    'descripcion' => $cursoLocal->SW_CURS_DESCRIPCION,
                    'tipo_curso' => $planes[$cursoLocal->SW_CURS_PLAN_CAPAC_COD] ?? null,
                    'nombre_curso' => $cursoMoodle->course_name,
                    'fecha_creacion_curso' => $cursoMoodle->course_created_date,
                    'fecha_creacion_matricula' => $cursoMoodle->enrolment_start_date,
                    'fecha_ultimo_acceso' => $cursoMoodle->last_access_date ?? null,
                    'nota_final' => $cursoMoodle->final_grade ?? null,
                    'estado' => $cursoMoodle->estado,
                ];
            }

            $totales = [
                'Total' => count($resultado),
                'aprobado' => count(array_filter($resultado, fn($c) => $c['estado'] === 'Aprobado')),
                'desaprobado' => count(array_filter($resultado, fn($c) => $c['estado'] === 'Desaprobado')),
                'en_curso' => count(array_filter($resultado, fn($c) => $c['estado'] === 'En curso')),
                'sin_acceder' => count(array_filter($resultado, fn($c) => $c['estado'] === 'Sin acceder')),
            ];

            return response()->json([
                'success' => true,
                'Totales' => $totales,
                'Cursos' => $resultado,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerEstadoCursosAlumnoNew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos del alumno',
            ], 500);
        }
    }
}
