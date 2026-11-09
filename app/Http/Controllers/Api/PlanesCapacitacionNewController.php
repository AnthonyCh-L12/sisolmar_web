<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CursoNew;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de la vista "Planes de Capacitación" respaldado por sw_cursos_new.
 *
 * Replica los contratos JSON de CapacitacionController (obtenerTiposDeCurso,
 * obtenerCursosPorPlan, obtenerPlanPCE y obtenerPlanPCA) para que el frontend
 * siga funcionando sin cambios de estructura. La versión antigua (sw_cursos)
 * se mantiene intacta.
 */
class PlanesCapacitacionNewController extends Controller
{
    /**
     * Tipos/planes disponibles para el modal de consultas.
     * En sw_cursos_plan la columna `nombre` guarda la abreviatura (PCE/PCA)
     * y `descripcion` el nombre visible.
     */
    public function obtenerTiposDeCursoNew(): JsonResponse
    {
        try {
            $planes = DB::table('sw_cursos_plan')
                ->select('codigo', 'nombre', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('codigo')
                ->get()
                ->map(function ($plan) {
                    return [
                        'Codigo' => $plan->codigo,
                        'Nombre' => $plan->descripcion ?? $plan->nombre ?? 'Sin nombre',
                        'Abreviatura' => $plan->nombre,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'Total' => $planes->count(),
                'Tipos' => $planes,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerTiposDeCursoNew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los tipos de cursos.',
            ], 500);
        }
    }

    /**
     * Cursos de un plan (PCE/PCA) para la tabla de consultas rápidas.
     */
    public function obtenerCursosPorPlanNew(Request $request): JsonResponse
    {
        try {
            $tipoCurso = $request->tipo_curso;

            $sistemas = $this->mapaSistemas();
            $areas = $this->mapaAreas();
            $dirigidos = $this->mapaDirigidos();
            $clientes = $this->mapaClientes();

            $plan = DB::table('sw_cursos_plan')
                ->where('codigo', $tipoCurso)
                ->first();

            $cursos = CursoNew::where('SW_CURS_PLAN_CAPAC_COD', $tipoCurso)
                ->where('habilitado', 1)
                ->get();

            $programaciones = $this->programacionesPorCurso(
                $cursos->pluck('CODIGO')->all(),
                null,
                'VIGENTE'
            );

            $cursos = $cursos
                ->map(function ($curso) use ($sistemas, $areas, $dirigidos, $clientes, $programaciones) {
                    $cliente = $clientes[(string) $curso->SW_CURS_CLIENTE_COD] ?? null;
                    $sistema = $sistemas[(string) $curso->SW_CURS_SIST_GESTION_COD] ?? null;
                    $area = $areas[(string) $curso->SW_CURS_AREA_COD] ?? null;
                    $dirigido = $dirigidos[(string) $curso->SW_CURS_DIRIGIDO_COD] ?? null;

                    $progVigente = $programaciones[$curso->CODIGO][0] ?? null;

                    return [
                        'Codigo' => $curso->SW_CURS_COD,
                        'Nombre' => mb_strtoupper((string) $curso->SW_CURS_NOMBRE),
                        'Cliente' => mb_strtoupper($cliente ?? 'Sin cliente'),
                        'Area' => mb_strtoupper($area ?? 'Sin área'),
                        'Sistema' => mb_strtoupper($sistema ?? 'Sin sistema'),
                        'Dirigido' => mb_strtoupper($dirigido ?? 'Otros'),
                        'Fecha_Inicio' => $progVigente ? Carbon::parse($progVigente->fecha_inicio)->format('d/m/Y') : null,
                        'Fecha_Cierre' => $progVigente ? Carbon::parse($progVigente->fecha_final)->format('d/m/Y') : null,
                        'Fecha_Creacion' => $curso->fecha_creacion
                            ? Carbon::parse($curso->fecha_creacion)->format('d/m/Y H:i:s')
                            : null,
                        'Vigente' => (bool) $progVigente,
                    ];
                })
                ->values();

            $tipoCursoDesc = $plan
                ? ($plan->descripcion ?? $plan->nombre ?? $tipoCurso)
                : $tipoCurso;

            return response()->json([
                'success' => true,
                'Tipo' => $tipoCursoDesc,
                'Total' => $cursos->count(),
                'Cursos' => $cursos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerCursosPorPlanNew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los cursos por plan.',
            ], 500);
        }
    }

    /**
     * Plan de Capacitación Estándar (PCE) agrupado por sistema de gestión.
     */
    public function obtenerPlanPCENew(): JsonResponse
    {
        try {
            $anio = now()->format('Y');

            $sistemas = DB::table('sw_capacitacion_areas')
                ->where('habilitado', 1)
                ->pluck('descripcion', 'codigo')
                ->mapWithKeys(fn ($v, $k) => [(string) $k => $v]);

            $dirigidos = $this->mapaDirigidos();
            $areasResp = $this->mapaAreas();

            $cursos = CursoNew::where('habilitado', 1)
                ->where('SW_CURS_PLAN_CAPAC_COD', 5)
                ->whereNotNull('SW_CURS_SIST_GESTION_COD')
                ->get();

            $programaciones = $this->programacionesPorCurso(
                $cursos->pluck('CODIGO')->all(),
                $anio
            );

            $sistemasData = [];

            foreach ($sistemas as $sistemaId => $sistemaNombre) {
                $cursosData = [];

                foreach ($cursos->where('SW_CURS_SIST_GESTION_COD', $sistemaId) as $curso) {
                    $programacionesData = $this->formatearProgramaciones(
                        $programaciones[$curso->CODIGO] ?? []
                    );

                    if (empty($programacionesData)) {
                        continue;
                    }

                    $cursosData[] = [
                        'Nombre' => mb_strtoupper((string) $curso->SW_CURS_NOMBRE),
                        'Dirigido_a' => $this->textoDirigido($curso->SW_CURS_DIRIGIDO_COD, $dirigidos),
                        'Area_Resp' => mb_strtoupper($areasResp[(string) $curso->SW_CURS_AREA_COD] ?? ''),
                        'Tiempo_Horas' => '1',
                        'Programaciones' => $programacionesData,
                    ];
                }

                if (empty($cursosData)) {
                    continue;
                }

                $sistemasData[] = [
                    'Nombre' => mb_strtoupper((string) $sistemaNombre),
                    'Cursos' => $cursosData,
                ];
            }

            return response()->json([
                'success' => true,
                'Anio' => $anio,
                'Sistemas' => $sistemasData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerPlanPCENew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el plan PCE.',
            ], 500);
        }
    }

    /**
     * Plan de Capacitación Aliado (PCA) de un cliente (código legacy).
     */
    public function obtenerPlanPCANew(int $codCliente): JsonResponse
    {
        try {
            $anio = now()->format('Y');

            $cliente = DB::table('sw_clientes')
                ->where('cod_legacy', $codCliente)
                ->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $dirigidos = $this->mapaDirigidos();
            $areasResp = $this->mapaAreas();

            $cursos = CursoNew::where('habilitado', 1)
                ->where('SW_CURS_PLAN_CAPAC_COD', 6)
                ->where('SW_CURS_CLIENTE_COD', (string) $codCliente)
                ->get();

            $programaciones = $this->programacionesPorCurso(
                $cursos->pluck('CODIGO')->all(),
                $anio
            );

            $cursosData = [];

            foreach ($cursos as $curso) {
                $programacionesData = $this->formatearProgramaciones(
                    $programaciones[$curso->CODIGO] ?? []
                );

                if (empty($programacionesData)) {
                    continue;
                }

                $cursosData[] = [
                    'Nombre' => mb_strtoupper((string) $curso->SW_CURS_NOMBRE),
                    'Dirigido_a' => $this->textoDirigido($curso->SW_CURS_DIRIGIDO_COD, $dirigidos),
                    'Area_Resp' => mb_strtoupper($areasResp[(string) $curso->SW_CURS_AREA_COD] ?? ''),
                    'Tiempo_Horas' => '20 MIN',
                    'Programaciones' => $programacionesData,
                ];
            }

            return response()->json([
                'success' => true,
                'Anio' => $anio,
                'Cliente' => mb_strtoupper((string) ($cliente->abreviatura ?? '')),
                'Cursos' => $cursosData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en obtenerPlanPCANew', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el plan PCA.',
            ], 500);
        }
    }

    /**
     * Programaciones habilitadas (opcionalmente de un año) agrupadas por curso.
     *
     * @param  array<int, int|string>  $codigosCurso
     * @return array<int|string, array<int, object>>
     */
    private function programacionesPorCurso(array $codigosCurso, ?string $anio = null, ?string $estado = null): array
    {
        if (empty($codigosCurso)) {
            return [];
        }

        $query = DB::table('sw_cursos_programacion')
            ->whereIn('cod_curso', $codigosCurso)
            ->where('habilitado', 1);

        if ($anio !== null) {
            $query->whereYear('fecha_inicio', $anio);
        }

        if ($estado !== null) {
            $query->where('estado_periodo', $estado);
        }

        return $query->orderBy('fecha_inicio')
            ->get()
            ->groupBy('cod_curso')
            ->map(fn ($grupo) => $grupo->values()->all())
            ->all();
    }

    /**
     * @param  array<int, object>  $programaciones
     * @return array<int, array<string, mixed>>
     */
    private function formatearProgramaciones(array $programaciones): array
    {
        $data = [];

        foreach ($programaciones as $prog) {
            if (empty($prog->fecha_inicio)) {
                continue;
            }

            $fechaInicio = Carbon::parse($prog->fecha_inicio);
            $mes = strtoupper($fechaInicio->translatedFormat('F'));

            if ($mes === 'SEPTIEMBRE') {
                $mes = 'SETIEMBRE';
            }

            $data[] = [
                'Mes' => $mes,
                'Bloque' => $fechaInicio->day <= 15 ? 1 : 2,
                'Ejecutado' => $fechaInicio->isPast(),
                'Programado' => $fechaInicio->isFuture(),
            ];
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $dirigidos
     */
    private function textoDirigido($codigo, array $dirigidos): string
    {
        if ((string) $codigo === '0') {
            return 'OTROS';
        }

        return mb_strtoupper($dirigidos[(string) $codigo] ?? (string) $codigo);
    }

    /**
     * @return array<string, string>
     */
    private function mapaSistemas(): array
    {
        $sistemas = DB::table('sw_capacitacion_areas')
            ->where('habilitado', 1)
            ->get(['codigo', 'abreviatura']);

        $mapa = [];
        foreach ($sistemas as $sistema) {
            if ($sistema->abreviatura !== null) {
                $mapa[(string) $sistema->codigo] = $sistema->abreviatura;
            }
        }

        // Respaldo: catálogo nuevo de sistemas de gestión.
        $nuevos = DB::table('sw_cursos_sistema')
            ->where('habilitado', 1)
            ->get(['codigo', 'nombre']);

        foreach ($nuevos as $sistema) {
            $llave = (string) $sistema->codigo;
            if (!isset($mapa[$llave]) && $sistema->nombre !== null) {
                $mapa[$llave] = $sistema->nombre;
            }
        }

        return $mapa;
    }

    /**
     * @return array<string, string>
     */
    private function mapaAreas(): array
    {
        return DB::table('sw_cursos_area')
            ->where('habilitado', 1)
            ->get(['codigo', 'nombre'])
            ->mapWithKeys(fn ($area) => [(string) $area->codigo => $area->nombre])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function mapaDirigidos(): array
    {
        return DB::table('sw_cursos_dirigido')
            ->where('habilitado', 1)
            ->get(['codigo', 'nombre'])
            ->mapWithKeys(fn ($dirigido) => [(string) $dirigido->codigo => $dirigido->nombre])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function mapaClientes(): array
    {
        return DB::table('sw_clientes')
            ->where('habilitado', 1)
            ->whereNotNull('cod_legacy')
            ->get(['cod_legacy', 'abreviatura'])
            ->mapWithKeys(fn ($cliente) => [(string) $cliente->cod_legacy => $cliente->abreviatura])
            ->all();
    }
}
