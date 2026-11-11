<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\MatriculaMasivaJob;
use App\Models\CursoNew;
use App\Models\CursoProgramacion;
use App\Models\Personal;
use App\Services\MoodleCursoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NewCapacController extends Controller
{
    public function obtenerCursos(): JsonResponse
    {
        try {
            $cursos = DB::table('sw_cursos_new as c')
                ->leftJoin('sw_cursos_plan as p', 'p.codigo', '=', 'c.SW_CURS_PLAN_CAPAC_COD')
                ->leftJoin('sw_cursos_sistema as s', 's.codigo', '=', 'c.SW_CURS_SIST_GESTION_COD')
                ->leftJoin('sw_capacitacion_areas as sa', 'sa.codigo', '=', 'c.SW_CURS_SIST_GESTION_COD')
                ->select(
                    'c.CODIGO',
                    'c.SW_CURS_COD',
                    'c.SW_CURS_NOMBRE',
                    'c.SW_CURS_TIPO_COD',
                    'c.SW_CURS_AV_COD',
                    'p.nombre as plan_nombre',
                    DB::raw('COALESCE(sa.abreviatura, s.nombre) as sistema_nombre'),
                    'c.fecha_creacion',
                    DB::raw("CONVERT(varchar(10), c.fecha_creacion, 103) as fecha_creacion_dmy"),
                    DB::raw("CONVERT(varchar(10), c.fecha_creacion, 23) as fecha_creacion_iso"),
                    'c.habilitado',
                )
                ->orderBy('c.SW_CURS_COD')
                ->get();

            $vigentes = CursoProgramacion::query()
                ->habilitados()
                ->vigentes()
                ->whereIn('cod_curso', $cursos->pluck('CODIGO')->all())
                ->pluck('cod_curso')
                ->map(fn ($v) => (int) $v)
                ->flip();

            $cursos = $cursos
                ->map(function ($row) use ($vigentes) {
                    return [
                        'CURS_COD' => (string) $row->SW_CURS_COD,
                        'CURS_NOMBRE' => $row->SW_CURS_NOMBRE,
                        'CURS_TIPO' => $this->mapearCategoriaCurso(
                            isset($row->SW_CURS_TIPO_COD) ? (int) $row->SW_CURS_TIPO_COD : null,
                            $row->SW_CURS_AV_COD,
                        ),
                        'CURS_PLAN_CAPAC_NOMBRE' => $row->plan_nombre,
                        'CURS_SIST_GESTION_NOMBRE' => $row->sistema_nombre,
                        'CURS_CREADO_FECHA' => $row->fecha_creacion_dmy ?: null,
                        'CURS_CREADO_FECHA_ISO' => $row->fecha_creacion_iso ?: null,
                        'CURS_ANIO' => $row->fecha_creacion_iso
                            ? (int) substr($row->fecha_creacion_iso, 0, 4)
                            : null,
                        'CURS_HABILITADO' => (bool) $row->habilitado,
                        'CURS_SUBIDO_AV' => (int) $row->SW_CURS_AV_COD != 0,
                        'CURS_TIENE_VIGENTE' => isset($vigentes[(int) $row->CODIGO]),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'total' => $cursos->count(),
                'data' => $cursos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@obtenerCursos', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos.',
            ], 500);
        }
    }

    public function obtenerCursosMatriculas(): JsonResponse
    {
        try {
            $matriculas = DB::table('sw_matriculas as sm')
                ->join('sw_cursos_programacion as sp', 'sp.codigo_programacion', '=', 'sm.cod_programacion')
                ->where('sp.habilitado', 1)
                ->where('sp.estado_periodo', 'VIGENTE')
                ->select('sm.cod_curso', DB::raw('COUNT(DISTINCT sm.cod_personal) as total'))
                ->groupBy('sm.cod_curso');

            $cursos = DB::table('sw_cursos_new as c')
                ->leftJoin('sw_cursos_plan as p', 'p.codigo', '=', 'c.SW_CURS_PLAN_CAPAC_COD')
                ->leftJoin('sw_cursos_sistema as s', 's.codigo', '=', 'c.SW_CURS_SIST_GESTION_COD')
                ->leftJoin('sw_cursos_area as ar', 'ar.codigo', '=', 'c.SW_CURS_AREA_COD')
                ->leftJoin('sw_clientes as cli', 'cli.cod_legacy', '=', 'c.SW_CURS_CLIENTE_COD')
                ->leftJoin('si_solm.dbo.PERSONAL as pr', function ($join) {
                    $join->on(
                        'pr.CODI_PERS',
                        '=',
                        DB::raw("RIGHT('00000' + CAST(c.SW_CURS_RESP_COD AS varchar(5)), 5)")
                    );
                })
                ->leftJoinSub($matriculas, 'm', 'm.cod_curso', '=', 'c.CODIGO')
                ->where('c.habilitado', 1)
                ->select(
                    'c.CODIGO',
                    'c.SW_CURS_COD',
                    'c.SW_CURS_NOMBRE',
                    'c.SW_CURS_DESCRIPCION',
                    'c.SW_CURS_AV_COD',
                    'c.SW_CURS_RESP_COD',
                    'c.fecha_creacion',
                    DB::raw("CONVERT(varchar(19), c.fecha_creacion, 120) as fecha_creacion_iso"),
                    'p.nombre as plan_nombre',
                    'p.descripcion as plan_descripcion',
                    's.nombre as sistema_nombre',
                    'ar.nombre as area_nombre',
                    'cli.abreviatura as cliente_abreviatura',
                    DB::raw("LTRIM(RTRIM(ISNULL(pr.APEL_1, '') + ' ' + ISNULL(pr.APEL_2, '') + ' ' + ISNULL(pr.NOMB_1, '') + ' ' + ISNULL(pr.NOMB_2, ''))) as responsable_nombre"),
                    DB::raw('COALESCE(m.total, 0) as total_matriculados')
                )
                ->orderBy('c.SW_CURS_COD')
                ->get();

            $vigentes = CursoProgramacion::query()
                ->habilitados()
                ->vigentes()
                ->whereIn('cod_curso', $cursos->pluck('CODIGO')->all())
                ->pluck('cod_curso')
                ->map(fn ($v) => (int) $v)
                ->flip();

            $cursos = $cursos
                ->map(function ($row) use ($vigentes) {
                    $responsable = trim((string) ($row->responsable_nombre ?? ''));
                    $responsableCodigo = (int) ($row->SW_CURS_RESP_COD ?? 0);
                    $plan = trim((string) ($row->plan_nombre ?? ''));

                    return [
                        'Id' => (int) $row->SW_CURS_AV_COD > 0
                            ? (int) $row->SW_CURS_AV_COD
                            : (int) $row->CODIGO,
                        'LocalId' => (int) $row->CODIGO,
                        'Codigo' => (string) $row->SW_CURS_COD,
                        'Nombre' => mb_strtoupper((string) $row->SW_CURS_NOMBRE),
                        'Tipo' => $plan !== ''
                            ? $plan
                            : (trim((string) ($row->plan_descripcion ?? '')) ?: 'Sin tipo'),
                        'Area' => $row->area_nombre ?: 'Sin area',
                        'Sistema' => $row->sistema_nombre ?: 'Sin sistema',
                        'Cliente' => $row->cliente_abreviatura ?: 'Sin cliente',
                        'Cod_Responsable' => $responsableCodigo > 0
                            ? str_pad((string) $responsableCodigo, 5, '0', STR_PAD_LEFT)
                            : null,
                        'Responsable' => $responsable !== ''
                            ? mb_strtoupper($responsable)
                            : 'Sin responsable',
                        'Descripcion' => $row->SW_CURS_DESCRIPCION ?: 'Sin descripcion',
                        'Total_Matriculados' => (int) $row->total_matriculados,
                        'Fecha_Creacion' => $row->fecha_creacion_iso
                            ? strtotime((string) $row->fecha_creacion_iso)
                            : null,
                        'Vigente' => isset($vigentes[(int) $row->CODIGO]),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'Cursos' => $cursos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@obtenerCursosMatriculas', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos para matriculas.',
            ], 500);
        }
    }

    public function obtenerCurso(string $codigoCurso): JsonResponse
    {
        try {
            $row = DB::table('sw_cursos_new as c')
                ->leftJoin('sw_cursos_plan as p', 'p.codigo', '=', 'c.SW_CURS_PLAN_CAPAC_COD')
                ->leftJoin('sw_cursos_sistema as s', 's.codigo', '=', 'c.SW_CURS_SIST_GESTION_COD')
                // Compatibilidad temporal: el JSON actual expone la abreviatura histórica (ej. SGC).
                ->leftJoin('sw_capacitacion_areas as sa', 'sa.codigo', '=', 'c.SW_CURS_SIST_GESTION_COD')
                ->leftJoin('sw_cursos_area as ar', 'ar.codigo', '=', 'c.SW_CURS_AREA_COD')
                ->select(
                    'c.CODIGO',
                    'c.SW_CURS_COD',
                    'c.SW_CURS_NOMBRE',
                    'c.SW_CURS_DESCRIPCION',
                    'c.SW_CURS_TIPO_COD',
                    'c.SW_CURS_AV_COD',
                    'c.SW_CURS_PLAN_CAPAC_COD',
                    'c.SW_CURS_SIST_GESTION_COD',
                    'c.SW_CURS_AREA_COD',
                    'c.SW_CURS_RESP_COD',
                    'c.SW_CURS_PERIODICIDAD',
                    'c.SW_CURS_CANT_PERIOD',
                    'c.SW_CURS_DIRIGIDO_COD',
                    'c.SW_CURS_SUCURSAL_COD',
                    'c.habilitado',
                    'c.fecha_creacion',
                    DB::raw("CONVERT(varchar(10), c.fecha_creacion, 103) as fecha_creacion_dmy"),
                    DB::raw("CONVERT(varchar(10), c.fecha_creacion, 23) as fecha_creacion_iso"),
                    'p.nombre as plan_nombre',
                    DB::raw('COALESCE(sa.abreviatura, s.nombre) as sistema_nombre'),
                    'ar.nombre as area_responsable_nombre',
                    'ar.codigo_moodle as area_codigo_moodle',
                )
                ->where('c.SW_CURS_COD', $codigoCurso)
                ->first();

            if (! $row) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el curso.',
                ], 404);
            }

            $programaciones = CursoProgramacion::query()
                ->habilitados()
                ->delCurso($row->CODIGO)
                ->orderBy('codigo_programacion')
                ->get([
                    'codigo_programacion',
                    'periodo',
                    'fecha_inicio',
                    'fecha_final',
                    'estado_periodo',
                    'habilitado',
                ])
                ->map(function ($p) {
                    return [
                        'codigo_programacion' => (string) $p->codigo_programacion,
                        'periodo' => $p->periodo,
                        'fecha_inicio' => $p->fecha_inicio,
                        'fecha_final' => $p->fecha_final,
                        'estado_periodo' => $p->estado_periodo,
                        'habilitado' => (bool) $p->habilitado,
                    ];
                })
                ->values();

            $estados = $programaciones
                ->map(fn ($p) => strtoupper((string) ($p['estado_periodo'] ?? '')))
                ->all();

            $sucursales = [];
            try {
                $sucursales = DB::table('sw_curso_sucursales')
                    ->where('curso_codigo', $row->CODIGO)
                    ->pluck('sucursal')
                    ->all();
            } catch (\Exception $e) {
                $sucursales = [];
            }

            $responsableCodigo = (int) $row->SW_CURS_RESP_COD;
            $responsableNombre = null;
            if ($responsableCodigo > 0) {
                try {
                    $resp = Personal::query()
                        ->where('CODI_PERS', str_pad((string) $responsableCodigo, 5, '0', STR_PAD_LEFT))
                        ->first([
                            DB::raw(
                                "LTRIM(RTRIM(APEL_1 + ' ' + ISNULL(APEL_2, '') + ' ' + NOMB_1 + ' ' + ISNULL(NOMB_2, ''))) as nombre"
                            ),
                        ]);
                    $responsableNombre = $resp->nombre ?? null;
                } catch (\Exception $e) {
                    $responsableNombre = null;
                }
            }

            $dirigidoCodigo = (int) $row->SW_CURS_DIRIGIDO_COD;
            $dirigidoNombre = null;
            if ($dirigidoCodigo === 0) {
                $dirigidoNombre = 'Otros';
            } else {
                try {
                    $dirigidoNombre = DB::table('sw_cursos_dirigido')
                        ->where('codigo', $dirigidoCodigo)
                        ->value('nombre');
                } catch (\Exception $e) {
                    $dirigidoNombre = null;
                }
            }

            $categoria = (int) $row->SW_CURS_TIPO_COD;
            $planCodigo = (int) $row->SW_CURS_PLAN_CAPAC_COD;
            $sistemaCodigo = (int) $row->SW_CURS_SIST_GESTION_COD;
            $areaCodigo = (int) $row->SW_CURS_AREA_COD;

            return response()->json([
                'success' => true,
                'data' => [
                    'CURS_COD' => (string) $row->SW_CURS_COD,
                    'CURS_PK' => (int) $row->CODIGO,
                    'CURS_NOMBRE' => $row->SW_CURS_NOMBRE,
                    'CURS_DESCRIPCION' => $row->SW_CURS_DESCRIPCION,
                    'CURS_CATEGORIA' => $categoria > 0 ? (string) $categoria : null,
                    'CURS_TIPO' => $this->mapearCategoriaCurso($categoria > 0 ? $categoria : null, $row->SW_CURS_AV_COD),
                    'CURS_PLAN_COD' => $planCodigo > 0 ? (string) $planCodigo : null,
                    'CURS_PLAN_CAPAC_NOMBRE' => $row->plan_nombre,
                    'CURS_SIST_GESTION_COD' => $sistemaCodigo > 0 ? (string) $sistemaCodigo : null,
                    'CURS_SIST_GESTION_NOMBRE' => $row->sistema_nombre,
                    'CURS_AREA_RESPONSABLE' => $areaCodigo > 0 ? (string) $areaCodigo : null,
                    'CURS_AREA_RESPONSABLE_NOMBRE' => $row->area_responsable_nombre,
                    'CURS_COD_MOODLE_AREA' => $row->area_codigo_moodle !== null ? (string) $row->area_codigo_moodle : null,
                    'CURS_AV_COD' => $row->SW_CURS_AV_COD !== null && (int) $row->SW_CURS_AV_COD > 0
                        ? (string) $row->SW_CURS_AV_COD
                        : null,
                    'CURS_COD_RESPONSABLE' => $responsableCodigo > 0
                        ? str_pad((string) $responsableCodigo, 5, '0', STR_PAD_LEFT)
                        : null,
                    'CURS_RESPONSABLE_NOMBRE' => $responsableNombre,
                    'CURS_ES_PERIODICO' => (bool) $row->SW_CURS_PERIODICIDAD,
                    'CURS_FRECUENCIA' => $this->mapearFrecuencia((int) $row->SW_CURS_CANT_PERIOD),
                    'CURS_DIRIGIDO_A' => (string) $dirigidoCodigo,
                    'CURS_DIRIGIDO_NOMBRE' => $dirigidoNombre,
                    'CURS_SUCURSAL' => $this->formatearSucursal((int) $row->SW_CURS_SUCURSAL_COD),
                    'CURS_SUCURSALES' => $sucursales,
                    'CURS_HABILITADO' => (bool) $row->habilitado,
                    'CURS_CREADO_FECHA' => $row->fecha_creacion_dmy ?: null,
                    'CURS_CREADO_FECHA_ISO' => $row->fecha_creacion_iso ?: null,
                    'CURS_TIENE_VIGENTE' => in_array('VIGENTE', $estados, true),
                    'CURS_TIENE_PENDIENTE' => in_array('PENDIENTE', $estados, true),
                    'CURS_PROGRAMACIONES' => $programaciones,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@obtenerCurso', [
                'codigo_curso' => $codigoCurso,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el curso.',
            ], 500);
        }
    }

    private function mapearCategoriaCurso(?int $categoria, $courseId): string
    {
        return match ($categoria) {
            1 => 'INDUCCIÓN',
            2 => 'CHARLA',
            3 => 'CAPACITACIÓN',
            4 => 'ENTRENAMIENTO',
            5 => 'SIMULACROS DE EMERGENCIA',
            default => in_array($courseId, [77, 120]) ? 'INDUCCIÓN' : 'CAPACITACIÓN',
        };
    }

    /**
     * Traduce la cantidad de periodos al texto histórico que consume el frontend.
     */
    private function mapearFrecuencia(int $cantPeriod): ?string
    {
        return match ($cantPeriod) {
            1 => 'MENSUAL',
            2 => 'BIMESTRAL',
            3 => 'TRIMESTRAL',
            4 => 'CUATRIMESTRAL',
            6 => 'SEMESTRAL',
            12 => 'ANUAL',
            default => null,
        };
    }

    /**
     * Mantiene el formato histórico de sucursal: "TODAS" o código de 2 dígitos.
     */
    private function formatearSucursal(int $codigo): string
    {
        if ($codigo === 0) {
            return 'TODAS';
        }

        return str_pad((string) $codigo, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene los participantes inscritos en un curso del Aula Virtual (Moodle),
     * completando los datos del personal desde la tabla si_solm.dbo.PERSONAL.
     */
    public function obtenerParticipantesInscritos(string $SW_CURS_AV_COD): JsonResponse
    {
        $courseId = (int) $SW_CURS_AV_COD;

        try {
            if ($courseId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El curso indicado no es valido.',
                ], 422);
            }

            $participantes = app(MoodleCursoService::class)->obtenerParticipantesInscritos($courseId);

            $rawPersonal = DB::connection('sqlsrv')
                ->select('EXEC SP_OBTENER_PERSONAL_BETA @VIGENTE = NULL');

            $personal = collect($rawPersonal)->keyBy(fn ($p) => trim((string) ($p->NRO_DOC ?? '')));

            $lista = collect($participantes)->map(function (array $part) use ($personal) {
                $dni = trim((string) ($part['dni'] ?? ''));
                $p   = $personal->get($dni);
                $fmt = fn ($ts) => (int) $ts > 0
                ? \Carbon\Carbon::createFromTimestamp((int) $ts, 'America/Lima')->format('Y-m-d H:i:s')
                : null;

                return [
                    'dni'             => $dni,
                    'nombre_completo' => $p ? trim($p->NOMBRE_COMPLETO ?? '') : ($part['fullname'] ?? null),
                    'cargo'           => $p ? trim($p->CARGO ?? '') : null,
                    'tipo_trabajador' => $p ? trim($p->TIPO_TRABAJADOR ?? '') : null,
                    'cliente'         => $p->CLIENTE ?? null,
                    'sucursal'        => $p ? trim($p->SUCURSAL ?? '') : null,
                    'codigo'          => $p ? trim($p->CODIGO_PERSONAL ?? '') : null,
                    'email'           => $p ? trim($p->CORREO ?? '') : ($part['email'] ?? null),
                    'num_tel'         => $p ? trim($p->TELEFONO ?? '') : null,
                    'fecha_inicio' => $fmt($part['timestart'] ?? 0),
                    'fecha_fin'    => $fmt($part['timeend'] ?? 0),
                ];
            })->values();

            return response()->json([
                'success'          => true,
                'total'            => $lista->count(),
                'matriculados'    => $lista,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@obtenerParticipantesInscritos', [
                'courseId' => $courseId,
                'error'    => $e->getMessage(),
                'line'     => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los participantes inscritos.',
            ], 500);
        }
    }

    public function ultimoCodigoCurso(): JsonResponse
    {
        try {
            $last = CursoNew::query()
                ->orderByRaw('TRY_CAST(SW_CURS_COD AS int) DESC')
                ->value('SW_CURS_COD');
            $next = $last ? str_pad(((int) $last) + 1, 5, '0', STR_PAD_LEFT) : '10001';

            return response()->json(['success' => true, 'ultimo' => (string) $last, 'siguiente' => (string) $next]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@ultimoCodigoCurso', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'No se pudo obtener el último código.'], 500);
        }
    }

    public function dirigidos(): JsonResponse
    {
        try {
            $rows = DB::table('sw_cursos_dirigido as d')
                ->select('codigo', 'nombre as texto')
                ->where('habilitado', 1)
                ->orderBy('codigo')
                ->get();

            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'No se pudo cargar dirigidos.'], 500);
        }
    }

    public function obtenerDirigidosNew(): JsonResponse
    {
        return $this->listarCatalogo(
            'obtenerDirigidosNew',
            fn () => DB::table('sw_cursos_dirigido')
                ->select('codigo', 'nombre')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
            'No se pudo cargar el catálogo de dirigidos.'
        );
    }

    public function obtenerAreasNew(): JsonResponse
    {
        return $this->listarCatalogo(
            'obtenerAreasNew',
            fn () => DB::table('sw_cursos_area')
                ->select('codigo', 'codigo_moodle as codMoodle', 'nombre')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
            'No se pudo cargar el catálogo de áreas.'
        );
    }

    public function obtenerSistemasNew(): JsonResponse
    {
        return $this->listarCatalogo(
            'obtenerSistemasNew',
            fn () => DB::table('sw_cursos_sistema')
                ->select('codigo', 'nombre', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
            'No se pudo cargar el catálogo de sistemas de gestión.'
        );
    }

    public function obtenerPlanesNew(): JsonResponse
    {
        return $this->listarCatalogo(
            'obtenerPlanesNew',
            fn () => DB::table('sw_cursos_plan')
                ->select('codigo', 'nombre', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
            'No se pudo cargar el catálogo de planes.'
        );
    }

    public function obtenerTiposCursoNew(): JsonResponse
    {
        return $this->listarCatalogo(
            'obtenerTiposCursoNew',
            fn () => DB::table('sw_cursos_tipo')
                ->select('codigo', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('descripcion')
                ->get(),
            'No se pudo cargar el catálogo de tipos de curso.'
        );
    }

    public function actualizarCursoNew(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'SW_CURS_COD' => 'required|string|max:40',
                'SW_CURS_NOMBRE' => 'nullable|string|max:400',
                'SW_CURS_DESCRIPCION' => 'nullable|string',
                'SW_CURS_TIPO_COD' => 'nullable|integer|exists:sw_cursos_tipo,codigo',
                'SW_CURS_RESP_COD' => 'nullable|string|min:0',
                'SW_CURS_PERIODICIDAD' => 'nullable|boolean',
                'SW_CURS_CANT_PERIOD' => 'nullable|integer|min:1',
                'SW_CURS_SIST_GESTION_COD' => 'nullable|integer|min:0',
                'SW_CURS_AREA_COD' => 'nullable|integer|min:0',
                'SW_CURS_DIRIGIDO_COD' => 'nullable|integer|min:0',
                'SW_CURS_SUCURSAL_COD' => 'nullable|integer|min:0',
            ]);

            $curso = CursoNew::where('SW_CURS_COD', $request->input('SW_CURS_COD'))->first();

            if (! $curso) {
                return response()->json([
                    'success' => false,
                    'message' => "El curso {$request->input('SW_CURS_COD')} no existe.",
                ], 404);
            }

            $usuario = auth()->user()?->usuario ?? 'SISTEMA';
            $ahora = Carbon::now()->format('Y-m-d H:i:s');

            $data = [];

            if ($request->filled('SW_CURS_NOMBRE')) {
                $data['SW_CURS_NOMBRE'] = trim((string) $request->input('SW_CURS_NOMBRE'));
            }

            if ($request->has('SW_CURS_DESCRIPCION')) {
                $desc = trim((string) $request->input('SW_CURS_DESCRIPCION'));
                $data['SW_CURS_DESCRIPCION'] = $desc !== '' ? $desc : 'Sin descripción.';
            }

            if ($request->filled('SW_CURS_TIPO_COD')) {
                $data['SW_CURS_TIPO_COD'] = (int) $request->input('SW_CURS_TIPO_COD');
            }

            if ($request->filled('SW_CURS_RESP_COD')) {
                $data['SW_CURS_RESP_COD'] = (int) $request->input('SW_CURS_RESP_COD');
            }

            if ($request->filled('SW_CURS_PERIODICIDAD')) {
                $data['SW_CURS_PERIODICIDAD'] = $request->boolean('SW_CURS_PERIODICIDAD') ? 1 : 0;
            }

            if ($request->filled('SW_CURS_CANT_PERIOD')) {
                $data['SW_CURS_CANT_PERIOD'] = (int) $request->input('SW_CURS_CANT_PERIOD');
            }

            if ($request->filled('SW_CURS_SIST_GESTION_COD')) {
                $data['SW_CURS_SIST_GESTION_COD'] = (int) $request->input('SW_CURS_SIST_GESTION_COD');
            }

            if ($request->filled('SW_CURS_AREA_COD')) {
                $data['SW_CURS_AREA_COD'] = (int) $request->input('SW_CURS_AREA_COD');
            }

            // Sucursal y dirigido usan "has" porque 0 es un valor válido (TODAS / Otros).
            if ($request->has('SW_CURS_DIRIGIDO_COD')) {
                $data['SW_CURS_DIRIGIDO_COD'] = (int) $request->input('SW_CURS_DIRIGIDO_COD');
            }

            if ($request->has('SW_CURS_SUCURSAL_COD')) {
                $data['SW_CURS_SUCURSAL_COD'] = (int) $request->input('SW_CURS_SUCURSAL_COD');
            }

            $data['modificado_por'] = $usuario;
            $data['fecha_modificacion'] = $ahora;

            $curso->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Curso actualizado correctamente.',
                'codigo' => $curso->CODIGO,
                'codigo_curso' => $curso->SW_CURS_COD,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@actualizarCursoNew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => 'Error al actualizar: '.$e->getMessage()], 500);
        }
    }

    /**
     * Envoltorio común para los catálogos: conserva el contrato {success, total, data}.
     */
    private function listarCatalogo(string $metodo, callable $consulta, string $mensajeError): JsonResponse
    {
        try {
            $rows = $consulta();

            return response()->json([
                'success' => true,
                'total' => $rows->count(),
                'data' => $rows->values(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error en NewCapacController@{$metodo}", [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $mensajeError,
            ], 500);
        }
    }

    /**
     * Registra un curso en sw_cursos_new.
     *
     * Por ahora solo crea la fila maestra: no genera examen, programaciones,
     * sucursales/clientes ni curso en Moodle.
     */
    public function guardarCursoNew(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'SW_CURS_COD' => 'nullable|string|max:40',
                'SW_CURS_NOMBRE' => 'required|string|max:400',
                'SW_CURS_DESCRIPCION' => 'nullable|string',
                'SW_CURS_TIPO_COD' => 'required|integer|exists:sw_cursos_tipo,codigo',
                'SW_CURS_PLAN_CAPAC_COD' => 'required|integer|exists:sw_cursos_plan,codigo',
                'SW_CURS_SIST_GESTION_COD' => 'required|integer|min:0',
                'SW_CURS_AREA_COD' => 'required|integer|min:0',
                'SW_CURS_RESP_COD' => 'required|integer|min:0',
                'SW_CURS_DIRIGIDO_COD' => 'required|integer|min:0',
                'SW_CURS_SUCURSAL_COD' => 'required|integer|min:0',
                'SW_CURS_CANT_PERIOD' => 'required|integer|min:0',
                'SW_CURS_AV_COD' => 'required|integer|min:0',
                'SW_CURS_PERIODICIDAD' => 'required|boolean',
                'SW_CURS_EVALUACION' => 'required|boolean',
                'SW_CURS_CLIENTE_COD' => 'nullable|string|max:20',
                'habilitado' => 'nullable|boolean',
            ]);

            // Código de negocio: si no viene, se genera el siguiente.
            $codigoCurso = trim((string) $request->input('SW_CURS_COD', ''));
            if ($codigoCurso === '') {
                $ultimo = CursoNew::query()
                    ->orderByRaw('TRY_CAST(SW_CURS_COD AS int) DESC')
                    ->value('SW_CURS_COD');
                $codigoCurso = $ultimo ? str_pad(((int) $ultimo) + 1, 5, '0', STR_PAD_LEFT) : '10001';
            }

            if (CursoNew::query()->where('SW_CURS_COD', $codigoCurso)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "El curso {$codigoCurso} ya existe.",
                ], 422);
            }

            $usuario = auth()->user()?->usuario ?? 'SISTEMA';
            $ahora = Carbon::now()->format('Y-m-d H:i:s');
            $descripcion = trim((string) $request->input('SW_CURS_DESCRIPCION', ''));

            $curso = CursoNew::create([
                'SW_CURS_COD' => $codigoCurso,
                'SW_CURS_NOMBRE' => trim((string) $request->input('SW_CURS_NOMBRE')),
                'SW_CURS_DESCRIPCION' => $descripcion !== '' ? $descripcion : 'Sin descripción.',
                'SW_CURS_TIPO_COD' => (int) $request->input('SW_CURS_TIPO_COD'),
                'SW_CURS_RESP_COD' => (int) $request->input('SW_CURS_RESP_COD'),
                'SW_CURS_PLAN_CAPAC_COD' => (int) $request->input('SW_CURS_PLAN_CAPAC_COD'),
                'SW_CURS_SIST_GESTION_COD' => (int) $request->input('SW_CURS_SIST_GESTION_COD'),
                'SW_CURS_AREA_COD' => (int) $request->input('SW_CURS_AREA_COD'),
                'SW_CURS_SUCURSAL_COD' => (int) $request->input('SW_CURS_SUCURSAL_COD'),
                'SW_CURS_CANT_PERIOD' => (int) $request->input('SW_CURS_CANT_PERIOD'),
                'SW_CURS_AV_COD' => (int) $request->input('SW_CURS_AV_COD'),
                'SW_CURS_DIRIGIDO_COD' => (int) $request->input('SW_CURS_DIRIGIDO_COD'),
                'SW_CURS_PERIODICIDAD' => (int) $request->boolean('SW_CURS_PERIODICIDAD'),
                'SW_CURS_EVALUACION' => (int) $request->boolean('SW_CURS_EVALUACION'),
                'habilitado' => $request->has('habilitado') ? (int) $request->boolean('habilitado') : 1,
                'creado_por' => $usuario,
                'modificado_por' => $usuario,
                'fecha_creacion' => $ahora,
                'fecha_modificacion' => $ahora,
                'SW_CURS_CLIENTE_COD' => $request->filled('SW_CURS_CLIENTE_COD')
                    ? (string) $request->input('SW_CURS_CLIENTE_COD')
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Curso registrado correctamente.',
                'codigo' => $curso->CODIGO,
                'codigo_curso' => $curso->SW_CURS_COD,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@guardarCursoNew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sube al Aula Virtual un curso registrado en sw_cursos_new.
     *
     * Solo crea el curso en Moodle, sube las imágenes y sincroniza las
     * preguntas con el quiz cuando aplica evaluación. No persiste exámenes
     * ni preguntas en las tablas locales: esa información quedará en Moodle
     * hasta definir la tabla definitiva.
     */
    public function subirCursoAv(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'codigo_curso' => 'required|string|max:40',
                'aplica_evaluacion' => 'required|boolean',
                'tiempo' => 'nullable|integer|min:1',
                'nota' => 'nullable|numeric|min:0|max:20',
                'intentos' => 'nullable|integer|min:1',
                'cantidad_preguntas' => 'nullable|integer|min:1',
                'preguntas_balotario' => 'nullable|integer|min:1',
                'preguntas' => 'nullable',
                'image_portada' => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
                'image_afiche' => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $codigoCurso = trim((string) $request->input('codigo_curso'));
            $aplicaEvaluacion = $request->boolean('aplica_evaluacion');
            $tiempo = (int) $request->input('tiempo', 0);
            $nota = (float) $request->input('nota', 0);
            $intentos = (int) $request->input('intentos', 0);
            $cantidadPreguntas = (int) $request->input('cantidad_preguntas', 0);

            if ($aplicaEvaluacion && ($tiempo < 1 || $intentos < 1 || $cantidadPreguntas < 1)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Completa el tiempo, los intentos y la cantidad de preguntas del examen.',
                ], 422);
            }

            $curso = CursoNew::where('SW_CURS_COD', $codigoCurso)->first();

            if (! $curso) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el curso solicitado.',
                ], 404);
            }

            if ((int) $curso->SW_CURS_AV_COD > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El curso ya fue subido al AV.',
                    'moodle_course_id' => (int) $curso->SW_CURS_AV_COD,
                ], 422);
            }

            $preguntas = $this->decodificarPreguntas($request->input('preguntas'));

            // Fechas provisionales: la apertura del curso definirá las reales.
            $meses = in_array((int) $curso->SW_CURS_CANT_PERIOD, [1, 2, 3, 4, 6, 12], true)
                ? (int) $curso->SW_CURS_CANT_PERIOD
                : 1;

            $inicio = Carbon::now()->startOfDay();
            $fin = Carbon::now()->startOfDay()->addMonthsNoOverflow($meses)->endOfDay();

            $codMoodleArea = null;
            if ((int) $curso->SW_CURS_AREA_COD > 0) {
                $areaMoodle = DB::table('sw_cursos_area')
                    ->where('codigo', $curso->SW_CURS_AREA_COD)
                    ->value('codigo_moodle');

                $codMoodleArea = $areaMoodle !== null ? (int) $areaMoodle : null;
            }

            $codResponsable = (int) $curso->SW_CURS_RESP_COD > 0
                ? str_pad((string) $curso->SW_CURS_RESP_COD, 5, '0', STR_PAD_LEFT)
                : null;

            $moodle = app(MoodleCursoService::class);

            $courseId = $moodle->crearCurso(
                (string) $curso->SW_CURS_NOMBRE,
                (int) $curso->CODIGO,
                $curso->SW_CURS_DESCRIPCION ?: null,
                $codMoodleArea,
                $codResponsable,
                $aplicaEvaluacion ? 1 : 0,
                $tiempo,
                $intentos,
                $nota,
                $inicio->timestamp,
                $fin->timestamp
            );

            if (! $courseId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo crear el curso en el Aula Virtual. Inténtalo nuevamente o contacta al administrador.',
                ], 502);
            }

            // Guardamos el id de Moodle de inmediato para evitar duplicados
            // si fallara un paso posterior.
            $usuario = auth()->user()?->usuario ?? 'SISTEMA';

            try {
                $curso->update([
                    'SW_CURS_AV_COD' => $courseId,
                    'SW_CURS_EVALUACION' => $aplicaEvaluacion ? 1 : 0,
                    'modificado_por' => $usuario,
                    'fecha_modificacion' => Carbon::now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Curso creado en Moodle pero no se pudo guardar SW_CURS_AV_COD', [
                    'codigo_curso' => $codigoCurso,
                    'moodle_course_id' => $courseId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "El curso se creó en el AV (ID {$courseId}) pero no se pudo actualizar la base local. Contacta al administrador.",
                    'moodle_course_id' => $courseId,
                ], 500);
            }

            $warnings = [];

            if ($request->hasFile('image_portada')
                && ! $moodle->subirPortada($courseId, $request->file('image_portada'))) {
                $warnings[] = 'El curso se creó, pero no se pudo subir la portada.';
            }

            if ($request->hasFile('image_afiche')
                && ! $moodle->subirAfiche($courseId, $request->file('image_afiche'))) {
                $warnings[] = 'El curso se creó, pero no se pudo subir el afiche.';
            }

            if ($aplicaEvaluacion) {
                if (empty($preguntas)) {
                    $warnings[] = 'El examen se creó sin preguntas.';
                } elseif (! $moodle->sincronizarPreguntas($courseId, $preguntas, $cantidadPreguntas)) {
                    $warnings[] = 'El curso se creó, pero no se pudieron cargar las preguntas en el examen.';
                }
            }

            $mensaje = 'Curso subido al AV correctamente.';
            if (! empty($warnings)) {
                $mensaje .= ' '.implode(' ', $warnings);
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'moodle_course_id' => $courseId,
                'warnings' => $warnings,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en NewCapacController@subirCursoAv', [
                'codigo_curso' => $request->input('codigo_curso'),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al subir el curso al AV. Inténtalo nuevamente.',
            ], 500);
        }
    }

    /**
     * Apertura un curso registrado en sw_cursos_new.
     *
     * Crea (o activa) la programación VIGENTE en sw_cursos_programacion,
     * actualiza las fechas del curso en Moodle y, si el usuario lo solicita,
     * encola la matrícula automática usando los datos de la tabla nueva.
     */
    public function aperturarCurso(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cod_curso' => 'required|integer|min:1',
                'fecha_inicio' => 'required|date_format:Y-m-d',
                'fecha_final' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'incluir_automatico' => 'nullable|boolean',
                'matricular_automatico' => 'nullable|boolean',
                'notificar_email' => 'nullable|boolean',
                'correo_notificacion' => 'nullable|email|max:150',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $curso = CursoNew::where('CODIGO', (int) $request->input('cod_curso'))->first();

            if (! $curso) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el curso solicitado.',
                ], 404);
            }

            if ((int) $curso->SW_CURS_AV_COD <= 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El curso debe subirse al AV antes de aperturarse.',
                ], 422);
            }

            $yaVigente = CursoProgramacion::query()
                ->habilitados()
                ->delCurso($curso->CODIGO)
                ->vigentes()
                ->exists();

            if ($yaVigente) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El curso ya tiene una programación VIGENTE activa.',
                ], 422);
            }

            $fechaInicio = Carbon::parse($request->input('fecha_inicio'));
            $cantPeriod = (int) $curso->SW_CURS_CANT_PERIOD;
            $frecuenciaValida = in_array($cantPeriod, [1, 2, 3, 4, 6, 12], true);

            if ($curso->SW_CURS_PERIODICIDAD) {
                $meses = $frecuenciaValida ? $cantPeriod : 1;
                $fechaFin = $fechaInicio->copy()->addMonthsNoOverflow($meses);
            } else {
                if (! $request->filled('fecha_final')) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Debe indicar la fecha de fin del curso.',
                    ], 422);
                }

                $fechaFin = Carbon::parse($request->input('fecha_final'));

                if ($fechaFin->lessThanOrEqualTo($fechaInicio)) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
                    ], 422);
                }
            }

            $pendiente = CursoProgramacion::query()
                ->habilitados()
                ->delCurso($curso->CODIGO)
                ->where('estado_periodo', 'PENDIENTE')
                ->first();

            $toSqlDate = fn (Carbon $d) => DB::raw("CONVERT(datetime, '{$d->format('Y-m-d H:i:s')}', 120)");

            if ($pendiente) {
                DB::table('sw_cursos_programacion')
                    ->where('codigo_programacion', $pendiente->codigo_programacion)
                    ->update([
                        'periodo' => $fechaInicio->format('Y-m'),
                        'fecha_inicio' => $toSqlDate($fechaInicio),
                        'fecha_final' => $toSqlDate($fechaFin),
                        'estado_periodo' => 'VIGENTE',
                        'fecha_modificacion' => $toSqlDate(Carbon::now()),
                    ]);

                $codigoProgramacion = (string) $pendiente->codigo_programacion;
            } else {
                $last = DB::table('sw_cursos_programacion')
                    ->orderBy('codigo_programacion', 'desc')
                    ->first();

                $codigoProgramacion = $last
                    ? str_pad(((int) $last->codigo_programacion) + 1, 4, '0', STR_PAD_LEFT)
                    : '1001';

                DB::table('sw_cursos_programacion')->insert([
                    'codigo_programacion' => $codigoProgramacion,
                    'cod_curso' => $curso->CODIGO,
                    'periodo' => $fechaInicio->format('Y-m'),
                    'tipo' => 'REGULAR',
                    'fecha_inicio' => $toSqlDate($fechaInicio),
                    'fecha_final' => $toSqlDate($fechaFin),
                    'estado_periodo' => 'VIGENTE',
                    'fecha_creacion' => $toSqlDate(Carbon::now()),
                    'habilitado' => 1,
                ]);
            }

            $quiereMatricula = $request->has('incluir_automatico') || $request->has('matricular_automatico')
                ? ($request->boolean('incluir_automatico', true) && $request->boolean('matricular_automatico', true))
                : true;

            $dirigido = trim((string) $curso->SW_CURS_DIRIGIDO_COD);
            $puedeMatricular = $quiereMatricula && $dirigido !== '0';
            $notificarEmail = $puedeMatricular && $request->boolean('notificar_email');
            $correoNotificacion = $notificarEmail
                ? trim((string) $request->input('correo_notificacion'))
                : null;

            if ($notificarEmail && ! filter_var($correoNotificacion, FILTER_VALIDATE_EMAIL)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Debe indicar un correo válido para enviar el aviso.',
                ], 422);
            }

            $warnings = [];

            if ((int) $curso->SW_CURS_AV_COD > 0) {
                $moodleActualizado = app(MoodleCursoService::class)->actualizarFechasCurso(
                    (int) $curso->SW_CURS_AV_COD,
                    $fechaInicio->timestamp,
                    $fechaFin->timestamp
                );

                if (! $moodleActualizado) {
                    $warnings[] = 'No se pudieron actualizar las fechas en el Aula Virtual.';
                }
            }

            $jobId = $puedeMatricular
                ? $this->despacharMatriculaCursoNuevo($curso, $codigoProgramacion, $notificarEmail, $correoNotificacion)
                : null;

            DB::commit();

            $mensaje = 'Curso aperturado correctamente.';
            if (! $quiereMatricula) {
                $mensaje .= ' Programación creada sin matrícula automática.';
            } elseif (! $puedeMatricular) {
                $mensaje .= ' La matrícula deberá realizarse manualmente (dirigido a Otros).';
            }
            if ($notificarEmail) {
                $mensaje .= ' El resumen se enviará a '.$correoNotificacion.'.';
            }
            if (! empty($warnings)) {
                $mensaje .= ' '.implode(' ', $warnings);
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'job_id' => $jobId,
                'codigo_programacion' => $codigoProgramacion,
                'warnings' => $warnings,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en NewCapacController@aperturarCurso', [
                'cod_curso' => $request->input('cod_curso'),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aperturar el curso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Encola la matrícula masiva para un curso de sw_cursos_new.
     */
    private function despacharMatriculaCursoNuevo(CursoNew $curso, string $codigoProgramacion, bool $notificarEmail, ?string $correoNotificacion): ?string
    {
        try {
            $sucursal = (int) $curso->SW_CURS_SUCURSAL_COD;
            $sucursalId = $sucursal > 0
                ? str_pad((string) $sucursal, 2, '0', STR_PAD_LEFT)
                : null;

            dispatch(MatriculaMasivaJob::porTipoCursoNuevo(
                (int) $curso->CODIGO,
                $codigoProgramacion,
                (int) (auth()->id() ?? 0),
                $sucursalId,
                $notificarEmail,
                $correoNotificacion
            ))->onQueue('training');

            return $curso->CODIGO.'_'.$codigoProgramacion;
        } catch (\Throwable $e) {
            Log::error('Error al encolar matrícula del curso nuevo', [
                'curso_id' => $curso->CODIGO,
                'programacion' => $codigoProgramacion,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function saveMatriculaNew(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cursoId' => 'required|integer',
                'programacionId' => 'required',
                'personalIds' => 'required|array|min:1',
                'personalIds.*' => 'required|string|max:20',
                'notificar_email' => 'nullable|boolean',
                'correo_notificacion' => 'nullable|email|max:150',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validacion.',
                'errors' => $e->errors(),
            ], 422);
        }

        $usuarioId = auth()->id();

        if (is_null($usuarioId)) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $curso = CursoNew::where('CODIGO', (int) $request->input('cursoId'))->first();

        if (! $curso) {
            return response()->json([
                'success' => false,
                'message' => 'Curso no encontrado.',
            ], 404);
        }

        if ((int) $curso->SW_CURS_AV_COD <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'El curso no esta vinculado al aula virtual.',
            ], 422);
        }

        $programacionId = (string) $request->input('programacionId');
        $prog = CursoProgramacion::query()
            ->habilitados()
            ->delCurso($curso->CODIGO)
            ->where(function ($query) use ($programacionId) {
                $query->where('codigo_programacion', $programacionId)
                    ->orWhere('codigo', $programacionId);
            })
            ->first();

        if (! $prog) {
            return response()->json([
                'success' => false,
                'message' => 'Programacion no encontrada para el curso.',
            ], 404);
        }

        if (strtoupper((string) $prog->estado_periodo) !== 'VIGENTE') {
            return response()->json([
                'success' => false,
                'message' => 'La programacion seleccionada no esta vigente.',
            ], 422);
        }

        $notificarEmail = $request->boolean('notificar_email');
        $correoNotificacion = trim((string) $request->input('correo_notificacion', ''));

        if ($notificarEmail && ! filter_var($correoNotificacion, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar un correo valido para enviar el aviso.',
            ], 422);
        }

        $personalIds = collect($request->input('personalIds', []))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values()
            ->all();

        if (empty($personalIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos un personal.',
            ], 422);
        }

        $sucursal = (int) $curso->SW_CURS_SUCURSAL_COD;
        $sucursalId = $sucursal > 0
            ? str_pad((string) $sucursal, 2, '0', STR_PAD_LEFT)
            : null;
        $codigoProgramacion = (string) ($prog->codigo_programacion ?: $prog->codigo);

        dispatch(MatriculaMasivaJob::estandarNuevo(
            (int) $curso->CODIGO,
            $codigoProgramacion,
            $personalIds,
            (int) $usuarioId,
            $sucursalId,
            $notificarEmail,
            $notificarEmail ? $correoNotificacion : null
        ))->onQueue('training');

        return response()->json([
            'success' => true,
            'message' => 'La matriculacion fue enviada a procesamiento.',
            'job_id' => $curso->CODIGO.'_'.$codigoProgramacion,
        ]);
    }

    public function desmatricularUsuarioNew(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cursoId' => 'required|integer',
                'codPersonal' => 'required|string|max:20',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validacion.',
                'errors' => $e->errors(),
            ], 422);
        }

        $curso = CursoNew::where('CODIGO', (int) $request->input('cursoId'))->first();

        if (! $curso) {
            return response()->json([
                'success' => false,
                'message' => 'Curso no encontrado.',
            ], 404);
        }

        $codPersonal = str_pad(trim((string) $request->input('codPersonal')), 5, '0', STR_PAD_LEFT);
        $warnings = [];

        $matricula = DB::table('sw_matriculas')
            ->where('cod_curso', $curso->CODIGO)
            ->where('cod_personal', $codPersonal)
            ->first();

        if (! $matricula) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontro una matricula para el personal indicado.',
            ], 404);
        }

        DB::table('sw_matriculas')
            ->where('cod_curso', $curso->CODIGO)
            ->where('cod_personal', $codPersonal)
            ->delete();

        $moodleCourseId = (int) $curso->SW_CURS_AV_COD;

        if ($moodleCourseId <= 0) {
            $warnings[] = 'El curso no esta vinculado al aula virtual; solo se elimino la matricula local.';

            return response()->json([
                'success' => true,
                'message' => implode(' ', $warnings),
                'warnings' => $warnings,
            ]);
        }

        $personal = Personal::where('CODI_PERS', $codPersonal)->first();
        $dni = trim((string) ($personal->NRO_DOCU_IDEN ?? ''));

        if ($personal === null || $dni === '') {
            $warnings[] = 'No se pudo desmatricular en Moodle: personal sin DNI registrado.';

            return response()->json([
                'success' => true,
                'message' => implode(' ', $warnings),
                'warnings' => $warnings,
            ]);
        }

        $moodleUser = DB::connection('mysql_grupoihb')
            ->table('mdl_user')
            ->where('username', $dni)
            ->orWhere('idnumber', $dni)
            ->first(['id']);

        if (! $moodleUser) {
            $warnings[] = 'No se pudo desmatricular en Moodle: usuario no encontrado.';

            return response()->json([
                'success' => true,
                'message' => implode(' ', $warnings),
                'warnings' => $warnings,
            ]);
        }

        $moodleCourse = DB::connection('mysql_grupoihb')
            ->table('mdl_course')
            ->where('id', $moodleCourseId)
            ->first(['idnumber']);

        if (! $moodleCourse) {
            $warnings[] = 'No se pudo desmatricular en Moodle: curso no encontrado.';

            return response()->json([
                'success' => true,
                'message' => implode(' ', $warnings),
                'warnings' => $warnings,
            ]);
        }

        DB::connection('mysql_grupoihb')->select(
            'SELECT F_USER_matricula_eliminar2(?, ?, ?, ?, ?) AS result',
            [$moodleUser->id, $moodleCourse->idnumber, '00001', 'Desmatriculacion desde Intranet', 5]
        );

        return response()->json([
            'success' => true,
            'message' => 'Personal desmatriculado correctamente.',
        ]);
    }

    /**
     * Extiende el plazo de la programación VIGENTE de un curso de sw_cursos_new.
     *
     * Actualiza la fecha final local, las fechas del curso en Moodle y la
     * fecha de fin de las inscripciones de los alumnos.
     */
    public function aplazarCurso(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cod_curso' => 'required|integer|min:1',
                'nueva_fecha_final' => 'required|date_format:Y-m-d',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $curso = CursoNew::query()
                ->where('CODIGO', (int) $request->input('cod_curso'))
                ->where('habilitado', 1)
                ->first();

            if (! $curso) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Curso no encontrado o no habilitado.',
                ], 404);
            }

            $programacion = CursoProgramacion::query()
                ->habilitados()
                ->delCurso($curso->CODIGO)
                ->vigentes()
                ->first();

            if (! $programacion) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El curso no tiene una programación vigente.',
                ], 404);
            }

            $fechaInicioActual = Carbon::parse($programacion->fecha_inicio);
            $fechaFinActual = $programacion->fecha_final
                ? Carbon::parse($programacion->fecha_final)
                : null;
            $nuevaFechaFin = Carbon::parse($request->input('nueva_fecha_final'));

            if ($nuevaFechaFin->lessThanOrEqualTo($fechaInicioActual)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La nueva fecha de fin no puede ser menor o igual a la fecha de inicio.',
                ], 422);
            }

            if ($fechaFinActual && $nuevaFechaFin->lessThanOrEqualTo($fechaFinActual)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La nueva fecha de fin debe ser posterior a la fecha de fin actual.',
                ], 422);
            }

            DB::table('sw_cursos_programacion')
                ->where('codigo_programacion', $programacion->codigo_programacion)
                ->update([
                    'fecha_final' => DB::raw("CONVERT(datetime, '{$nuevaFechaFin->format('Y-m-d H:i:s')}', 120)"),
                    'fecha_modificacion' => DB::raw("CONVERT(datetime, '".now()->format('Y-m-d H:i:s')."', 120)"),
                ]);

            $warnings = [];
            $moodleCourseId = (int) $curso->SW_CURS_AV_COD;

            if ($moodleCourseId > 0) {
                $moodleService = app(MoodleCursoService::class);

                if (! $moodleService->actualizarFechasCurso(
                    $moodleCourseId,
                    $fechaInicioActual->timestamp,
                    $nuevaFechaFin->timestamp
                )) {
                    $warnings[] = 'No se pudo actualizar la fecha del curso en el Aula Virtual.';
                }

                if (! $moodleService->actualizarFechasMatriculas(
                    $moodleCourseId,
                    $nuevaFechaFin->timestamp
                )) {
                    $warnings[] = 'No se pudieron actualizar las inscripciones en el Aula Virtual.';
                }
            }

            DB::commit();

            $mensaje = 'Curso aplazado correctamente.';
            if (! empty($warnings)) {
                $mensaje .= ' '.implode(' ', $warnings);
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'warnings' => $warnings,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en NewCapacController@aplazarCurso', [
                'cod_curso' => $request->input('cod_curso'),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aplazar el curso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza las fechas de inicio/fin de la programación vigente de un curso.
     * Opcionalmente sincroniza el aula virtual y las inscripciones existentes.
     */
    public function actualizarFechasProgramacion(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'cod_curso' => 'required|integer|min:1',
                'codigo_programacion' => 'required',
                'fecha_inicio' => 'required|date_format:Y-m-d',
                'fecha_final' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'actualizar_matriculas' => 'sometimes|boolean',
                'actualizar_curso_av' => 'sometimes|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $curso = CursoNew::query()
                ->where('CODIGO', (int) $request->input('cod_curso'))
                ->where('habilitado', 1)
                ->first();

            if (! $curso) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Curso no encontrado o no habilitado.',
                ], 404);
            }

            $programacion = CursoProgramacion::query()
                ->habilitados()
                ->delCurso($curso->CODIGO)
                ->vigentes()
                ->where('codigo_programacion', (string) $request->input('codigo_programacion'))
                ->first();

            if (! $programacion) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'El curso no tiene la programación vigente indicada.',
                ], 404);
            }

            $fechaInicio = Carbon::parse($request->input('fecha_inicio'))->startOfDay();
            $fechaFinal = Carbon::parse($request->input('fecha_final'))->endOfDay();

            if ($fechaFinal->lessThanOrEqualTo($fechaInicio)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
                ], 422);
            }

            $nuevoPeriodo = $fechaInicio->format('Y-m');

            $existe = CursoProgramacion::query()
                ->where('cod_curso', $curso->CODIGO)
                ->where('periodo', $nuevoPeriodo)
                ->where('tipo', $programacion->tipo)
                ->where('habilitado', 1)
                ->where('codigo_programacion', '!=', $programacion->codigo_programacion)
                ->exists();

            if ($existe) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Ya existe otra programación {$programacion->tipo} para este curso en el periodo {$nuevoPeriodo}.",
                ], 409);
            }

            DB::table('sw_cursos_programacion')
                ->where('codigo_programacion', $programacion->codigo_programacion)
                ->update([
                    'fecha_inicio' => DB::raw("CONVERT(datetime, '{$fechaInicio->format('Y-m-d H:i:s')}', 120)"),
                    'fecha_final' => DB::raw("CONVERT(datetime, '{$fechaFinal->format('Y-m-d H:i:s')}', 120)"),
                    'periodo' => $nuevoPeriodo,
                    'fecha_modificacion' => DB::raw("CONVERT(datetime, '".now()->format('Y-m-d H:i:s')."', 120)"),
                ]);

            $warnings = [];
            $moodleCourseId = (int) $curso->SW_CURS_AV_COD;
            $actualizarCursoAv = $request->boolean('actualizar_curso_av');
            $actualizarMatriculas = $request->boolean('actualizar_matriculas');

            if (($actualizarCursoAv || $actualizarMatriculas) && $moodleCourseId <= 0) {
                $warnings[] = 'El curso no tiene un aula virtual asociada; no se pudo sincronizar Moodle.';
            } elseif ($moodleCourseId > 0) {
                $moodleService = app(MoodleCursoService::class);

                if ($actualizarCursoAv && ! $moodleService->actualizarFechasCurso(
                    $moodleCourseId,
                    $fechaInicio->timestamp,
                    $fechaFinal->timestamp
                )) {
                    $warnings[] = 'No se pudieron actualizar las fechas del curso en el Aula Virtual.';
                }

                if ($actualizarMatriculas && ! $moodleService->actualizarFechasMatriculas(
                    $moodleCourseId,
                    $fechaFinal->timestamp,
                    $fechaInicio->timestamp
                )) {
                    $warnings[] = 'No se pudieron actualizar las inscripciones en el Aula Virtual.';
                }
            }

            DB::commit();

            $mensaje = 'Fechas de la programación actualizadas correctamente.';
            if (! empty($warnings)) {
                $mensaje .= ' '.implode(' ', $warnings);
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'warnings' => $warnings,
                'data' => [
                    'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                    'fecha_final' => $fechaFinal->format('Y-m-d'),
                    'periodo' => $nuevoPeriodo,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en NewCapacController@actualizarFechasProgramacion', [
                'cod_curso' => $request->input('cod_curso'),
                'codigo_programacion' => $request->input('codigo_programacion'),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar las fechas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normaliza las preguntas recibidas (JSON o array) al formato del Word.
     */
    private function decodificarPreguntas($preguntas): array
    {
        if (is_string($preguntas)) {
            $preguntas = json_decode($preguntas, true);
        }

        if (! is_array($preguntas)) {
            return [];
        }

        return array_values(array_filter($preguntas, function ($pregunta) {
            return is_array($pregunta)
                && ! empty($pregunta['texto'])
                && isset($pregunta['opciones'])
                && is_array($pregunta['opciones']);
        }));
    }
}
