<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consulta extends Model
{
    use HasFactory;

    public static function getSucursalesPorCliente($codLegacy)
    {
        return DB::connection("sqlsrv_controlclientes")->select(
            "EXEC USP_LISTAR_SUCURSALES_X_CLIENTE :cod_legacy",
            ["cod_legacy" => $codLegacy],
        );
    }

    public static function getFoliosVigentes(
        $codCargo = null,
        $codCliente = null,
        $tipoFolio = null,
        $prioridad = null,
    ) {
        return DB::select("EXEC SW_LISTAR_FOLIOS_VIGENTES ?, ?, ?, ?", [
            $codCargo,
            $codCliente,
            $tipoFolio,
            $prioridad,
        ]);
    }

    public static function listarFoliosPendientes(
        $dni,
        $codCliente,
        $codSucursal,
    ) {
        return DB::select(
            "EXEC SW_LISTAR_FOLIOS_PENDIENTES_CONSULTAS ?, ?, ?",
            [$dni, $codCliente, $codSucursal],
        );
    }

    public static function listarFoliosProximosVencer(
        $dni,
        $codCliente,
        $codSucursal,
        $persona,
        $servicio,
        $dias,
        $fecha_desde,
        $fecha_hasta,
    ) {
        return DB::select(
            "EXEC SW_LISTAR_FOLIOS_PROXIMOS_VENCER_CONSULTAS ?, ?, ?, ?, ?, ?, ?, ?",
            [
                $dni,
                $codCliente,
                $codSucursal,
                $persona,
                $servicio,
                $dias,
                $fecha_desde,
                $fecha_hasta,
            ],
        );
    }

    public static function obtenerAreasPorSistema($sistemaId)
    {
        return DB::select("EXEC SW_LISTAR_AREAS_POR_SISTEMA ?", [$sistemaId]);
    }

    public static function obtenerAreas()
    {
        return DB::select('
        SELECT
            [nombre],
            [codigo_moodle] AS [codModdle]
        FROM [sisolm_web].[dbo].[sw_cursos_area]
    ');
    }

    public static function obtenerAreasEncargadas()
    {
        return DB::select(
            "SELECT AVAR_ID as codigo, AVAR_DESCRIPCION as descripcion FROM si_solm.dbo.AV_AREA WHERE AVAR_VIGENCIA = 1",
        );
    }

    public static function obtenerSucursales()
    {
        return DB::table("sw_MIGRA_SISO_SUCURSAL")
            ->select("SUCU_CODIGO as codigo", "SUCU_ABREVIATURA as sucursal")
            ->whereNotNull("SUCU_ABREVIATURA")
            ->orderBy("SUCU_ABREVIATURA")
            ->get();
    }

    public static function obtenerProgramacionesVigentes()
    {
        return DB::table("sw_cursos_programacion as cp")
            ->join("sw_cursos as c", "c.codigo", "=", "cp.cod_curso")
            ->select(
                "cp.*",
                "c.codigo_curso",
                "c.nombre as curso_nombre",
                "c.frecuencia",
                "c.es_periodico",
            )
            ->where("cp.habilitado", 1)
            ->where("cp.estado_periodo", "VIGENTE")
            ->where("c.habilitado", 1)
            ->where("c.es_periodico", 1)
            ->get();
    }

    public static function obtenerMatriculasPorCurso(int $cursoId)
    {
        return DB::table("sw_matriculas as m")
            ->leftJoin(
                "sw_cursos_programacion as prog",
                "m.cod_programacion",
                "=",
                "prog.codigo",
            )
            ->where("m.cod_curso", "=", $cursoId)
            ->select([
                "m.cod_personal",
                "m.fecha_matricula",
                "m.estado",
                "prog.fecha_inicio as prog_fecha_inicio",
                "prog.fecha_final as prog_fecha_final",
            ])
            ->get();
    }

    public static function obtenerTipoDeCurso(int $cursoId)
    {
        return DB::table("sw_cursos as c")
            ->join(
                "sw_capacitacion_tipo_curso as tc",
                "c.tipo_curso",
                "=",
                "tc.codigo",
            )
            ->where("c.codigo", $cursoId)
            ->select("c.tipo_curso", "tc.descripcion as tipo_descripcion")
            ->first();
    }

    public static function obtenerDirigidos()
    {
        return DB::table("sw_cursos_dirigido as d")
            ->select("codigo", "nombre as texto")
            ->where("habilitado", 1)
            ->orderBy("codigo")
            ->get();
    }

    public static function obtenerPersonalPorSucursal($codSucursal = null)
    {
        return DB::table('si_solm.dbo.PERSONAL as P')
            ->leftJoin('si_solm.dbo.CARGOS as C', 'C.CODI_CARG', '=', 'P.CODI_CARG')
            ->selectRaw("
            P.CODI_PERS,
            P.SUCU_CODIGO,

            LTRIM(RTRIM(
                P.APEL_1 + ' ' +
                ISNULL(P.APEL_2, '') + ' ' +
                P.NOMB_1 + ' ' +
                ISNULL(P.NOMB_2, '')
            )) AS NOMBRE_COMPLETO,

            P.NRO_DOCU_IDEN AS NRO_DOC,

            CASE
                WHEN P.PERS_TIPOTRAB = '01' THEN 'OPERATIVO 4°'
                WHEN P.PERS_TIPOTRAB = '02' THEN 'ADMINISTRATIVO 4°'
                WHEN P.PERS_TIPOTRAB = '03' THEN 'OPERATIVO 5°'
                WHEN P.PERS_TIPOTRAB = '05' THEN 'ADMINISTRATIVO 5°'
                WHEN P.PERS_TIPOTRAB = '06' THEN 'ESPECIAL'
                ELSE 'OTRO'
            END AS TIPO_TRABAJADOR,

            C.DESC_CARGO AS CARGO
        ")
            ->where('P.PERS_VIGENCIA', 'SI')
            ->where('P.EMPR_CODIGO', '01')
            ->when(!empty($codSucursal), function ($q) use ($codSucursal) {
                $q->where('P.SUCU_CODIGO', $codSucursal);
            })
            ->orderBy('NOMBRE_COMPLETO')
            ->get();
    }

    public static function catalogosCapacitacion(): array
    {
        return [
            'sistemas' => DB::table('sw_cursos_sistema')
                ->select('codigo', 'nombre', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
            'areas' => DB::table('sw_cursos_area')
                ->select('codigo as codArea', 'nombre as Area', 'codigo_moodle as codModdle')
                ->where('habilitado', 1)
                ->whereNotNull('codigo_moodle')
                ->where('codigo_moodle', '!=', '')
                ->orderBy('nombre')
                ->get(),
            'tipos' => DB::table('sw_cursos_tipo')
                ->select('codigo', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('descripcion')
                ->get(),
            'planes' => DB::table('sw_cursos_plan')
                ->select('codigo', 'nombre', 'descripcion')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
            'dirigidos' => DB::table('sw_cursos_dirigido')
                ->select('codigo', 'nombre')
                ->where('habilitado', 1)
                ->orderBy('nombre')
                ->get(),
        ];
    }

    public static function areasPorSistemaCapacitacion($sistemaId)
    {
        return DB::table('sw_cursos_area as a')
            ->join('sw_cursos_new as c', 'c.SW_CURS_AREA_COD', '=', 'a.codigo')
            ->where('c.SW_CURS_SIST_GESTION_COD', $sistemaId)
            ->where('c.habilitado', 1)
            ->where('a.habilitado', 1)
            ->whereNotNull('a.codigo_moodle')
            ->where('a.codigo_moodle', '!=', '')
            ->select('a.codigo as codArea', 'a.nombre as Area', 'a.codigo_moodle as codModdle')
            ->distinct()
            ->orderBy('a.nombre')
            ->get();
    }

    public static function cursosLocalesReporte()
    {
        return collect(
            DB::connection('sqlsrv')->select("
                SELECT
                    c.[CODIGO],
                    c.[SW_CURS_COD],
                    c.[SW_CURS_NOMBRE],
                    c.[SW_CURS_DESCRIPCION],
                    c.[SW_CURS_AV_COD],
                    c.[SW_CURS_TIPO_COD],
                    c.[SW_CURS_RESP_COD],
                    c.[SW_CURS_PLAN_CAPAC_COD],
                    c.[SW_CURS_SIST_GESTION_COD],
                    c.[SW_CURS_AREA_COD],
                    c.[SW_CURS_DIRIGIDO_COD],
                    c.[SW_CURS_CLIENTE_COD],
                    c.[habilitado],
                    c.[fecha_creacion],
                    area.[nombre] AS area_nombre,
                    area.[codigo_moodle] AS area_codigo_moodle,
                    sis.[nombre] AS sistema_nombre,
                    sis.[descripcion] AS sistema_descripcion,
                    tipo.[descripcion] AS tipo_descripcion,
                    plan.[nombre] AS plan_nombre,
                    plan.[descripcion] AS plan_descripcion,
                    dir.[nombre] AS dirigido_nombre,
                    cli.[abreviatura] AS cliente_abreviatura,
                    LTRIM(RTRIM(
                        ISNULL(per.[APEL_1], '') + ' ' +
                        ISNULL(per.[APEL_2], '') + ' ' +
                        ISNULL(per.[NOMB_1], '') + ' ' +
                        ISNULL(per.[NOMB_2], '')
                    )) AS responsable_nombre
                FROM [sisolm_web].[dbo].[sw_cursos_new] c
                LEFT JOIN [sisolm_web].[dbo].[sw_cursos_area] area
                    ON area.[codigo] = c.[SW_CURS_AREA_COD]
                LEFT JOIN [sisolm_web].[dbo].[sw_cursos_sistema] sis
                    ON sis.[codigo] = c.[SW_CURS_SIST_GESTION_COD]
                LEFT JOIN [sisolm_web].[dbo].[sw_cursos_tipo] tipo
                    ON tipo.[codigo] = c.[SW_CURS_TIPO_COD]
                LEFT JOIN [sisolm_web].[dbo].[sw_cursos_plan] plan
                    ON plan.[codigo] = c.[SW_CURS_PLAN_CAPAC_COD]
                LEFT JOIN [sisolm_web].[dbo].[sw_cursos_dirigido] dir
                    ON dir.[codigo] = c.[SW_CURS_DIRIGIDO_COD]
                LEFT JOIN [sw_clientes] cli
                    ON cli.[cod_legacy] = c.[SW_CURS_CLIENTE_COD]
                LEFT JOIN [si_solm].[dbo].[PERSONAL] per
                    ON TRY_CAST(per.[CODI_PERS] AS INT) = TRY_CAST(c.[SW_CURS_RESP_COD] AS INT)
                WHERE c.[SW_CURS_AV_COD] IS NOT NULL
                  AND TRY_CAST(c.[SW_CURS_AV_COD] AS INT) > 0
            ")
        )
            ->sortByDesc(fn($row) => (int) ($row->habilitado ?? 0) . '|' . (string) ($row->fecha_creacion ?? ''))
            ->unique(fn($row) => (string) $row->SW_CURS_AV_COD)
            ->keyBy(fn($row) => (string) $row->SW_CURS_AV_COD);
    }

    public static function programacionesVigentesReporte()
    {
        return collect(
            DB::connection('sqlsrv')->select("
                SELECT [cod_curso], [fecha_inicio], [fecha_final], [codigo_programacion]
                FROM [sisolm_web].[dbo].[sw_cursos_programacion]
                WHERE [habilitado] = 1
                  AND [estado_periodo] = 'VIGENTE'
            ")
        )
            ->sortByDesc('codigo_programacion')
            ->unique(fn($row) => (string) $row->cod_curso)
            ->keyBy(fn($row) => (string) $row->cod_curso);
    }
}
