<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificacionModel extends Model
{
    use HasFactory;

    public static function updateNotificacion($codigo, $data){

        $updated = DB::table('sw_solicitud_cargo_comercial')
        ->where('codigo', $codigo)
        ->update($data);

        return $updated;
    }

    public static function foliosPorVencer($dias)
    {
        return DB::table('sw_folios_detalles as fd')
            ->join('sw_MIGRA_PERSONAL as p', function ($join) {
                $join->whereRaw("
                    (TRY_CAST(fd.codPersonal AS INT) = TRY_CAST(p.CODI_PERS AS INT) AND TRY_CAST(fd.codPersonal AS INT) IS NOT NULL)
                    OR
                    (fd.codPersonal COLLATE DATABASE_DEFAULT = p.CODI_PERS COLLATE DATABASE_DEFAULT AND TRY_CAST(fd.codPersonal AS INT) IS NULL)
                ");
            })
            ->join('sw_folios as f', 'fd.codFolio', '=', 'f.codigo')
            ->select(
                'fd.codPersonal',
                DB::raw("CONCAT(p.APEL_1 COLLATE DATABASE_DEFAULT, ' ', p.APEL_2 COLLATE DATABASE_DEFAULT, ' ', p.NOMB_1 COLLATE DATABASE_DEFAULT, ' ', p.NOMB_2 COLLATE DATABASE_DEFAULT) as personal"),
                'f.nombre as documento',
                'fd.fecha_caducidad',
                DB::raw("DATEDIFF(day, GETDATE(), fd.fecha_caducidad) as dias_restantes")
            )
            ->whereNotNull('fd.fecha_caducidad')
            ->whereRaw("DATEDIFF(day, GETDATE(), fd.fecha_caducidad) BETWEEN 0 AND ?", [$dias])
            ->get();
    }

    public static function pendientesEtapa2()
    {
        // SIN CACHÉ: Lo ejecutamos en vivo para evitar datos pegados
        try {
            $qAnual = DB::select("EXEC SW_LISTAR_PERSONAL_DJ_MIGRACION_ETAPA2_VIGENTES_DNI '0', '01', '00', '00', NULL, 'anual'");
            $qDemanda = DB::select("EXEC SW_LISTAR_PERSONAL_DJ_MIGRACION_ETAPA2_VIGENTES_DNI '0', '01', '00', '00', NULL, 'demanda'");
        } catch (\Exception $e) {
            $qAnual = DB::select("EXEC SW_LISTAR_PERSONAL_DJ_MIGRACION_ETAPA2_VIGENTES_DNI '00', '00', '00', '00', NULL, 'anual'");
            $qDemanda = DB::select("EXEC SW_LISTAR_PERSONAL_DJ_MIGRACION_ETAPA2_VIGENTES_DNI '00', '00', '00', '00', NULL, 'demanda'");
        }

        // Inyectamos las columnas para ambos
        $resAnual = array_map(function($i) { $i->tipo_actualizacion = 'anual'; return $i; }, $qAnual);
        $resDemanda = array_map(function($i) { $i->tipo_actualizacion = 'demanda'; return $i; }, $qDemanda);

        $resultados = array_merge($resAnual, $resDemanda);
        $conteos = [];

        foreach ($resultados as $item) {
            $row = array_change_key_case((array) $item, CASE_UPPER);
            $estado = strtoupper(trim($row['VERIFICADO_CAMBIO'] ?? $row['MIGRADO'] ?? ''));
            $tipoPersonal = strtoupper(trim($row['TIPOPER'] ?? $row['TIPO_PER'] ?? ''));

            if ($estado !== 'SI' && $estado !== 'VERIFICADO' && !str_contains($tipoPersonal, 'ESPECIAL')) {
                $sucursal = trim($row['SUCURSAL'] ?? 'SIN SUCURSAL');
                if (!isset($conteos[$sucursal])) {
                    $conteos[$sucursal] = 0;
                }
                $conteos[$sucursal]++;
            }
        }

        ksort($conteos);
        return $conteos;
    }

    /**
     * Hijos registrados en la DJ que cumplen 18 años dentro de los próximos 30 días.
     * La fecha de mayoría se calcula exclusivamente desde FECH_NACI de DERECHO_HABIENTE.
     */
    public static function hijosPorCumplirMayoriaEdad(array $filtros = [], bool $soloActivos = true)
    {
        $sql = "SELECT
                p.CODI_PERS AS codigo_personal,
                LTRIM(RTRIM(CONCAT(
                    p.APEL_1 COLLATE DATABASE_DEFAULT, ' ',
                    p.APEL_2 COLLATE DATABASE_DEFAULT, ' ',
                    p.NOMB_1 COLLATE DATABASE_DEFAULT, ' ',
                    p.NOMB_2 COLLATE DATABASE_DEFAULT
                ))) AS personal,
                p.NRO_DOCU_IDEN AS dni,
                COALESCE(s.SUCU_ABREVIATURA COLLATE DATABASE_DEFAULT,
                    CONVERT(NVARCHAR(100), p.SUCU_CODIGO) COLLATE DATABASE_DEFAULT, N'SIN SUCURSAL') AS sucursal,
                COALESCE(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS tipo_personal,
                COALESCE(c.DESC_CARGO, p.CODI_CARG) AS cargo,
                LTRIM(RTRIM(CONCAT(
                    dh.APEL_1 COLLATE DATABASE_DEFAULT, ' ',
                    dh.APEL_2 COLLATE DATABASE_DEFAULT, ' ',
                    dh.NOMB_1 COLLATE DATABASE_DEFAULT, ' ',
                    dh.NOMB_2 COLLATE DATABASE_DEFAULT
                ))) AS hijo,
                CONVERT(VARCHAR(10), nacimiento.fecha_nacimiento, 103) AS fecha_nacimiento,
                CONVERT(VARCHAR(10), fechas.fecha_mayoria, 103) AS fecha_mayoria,
                DATEDIFF(DAY, CONVERT(DATE, GETDATE()), fechas.fecha_mayoria) AS dias_restantes,
                CASE WHEN DATEDIFF(DAY, CONVERT(DATE, GETDATE()), fechas.fecha_mayoria) <= 15 THEN 15 ELSE 30 END AS dias_aviso
            FROM si_solm.dbo.PERSONAL AS p
            INNER JOIN si_solm.dbo.DERECHO_HABIENTE AS dh
                ON dh.CODI_PERS = p.CODI_PERS
            LEFT JOIN si_solm.dbo.SISO_SUCURSAL AS s
                ON s.SUCU_CODIGO = p.SUCU_CODIGO
            LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL AS tp
                ON tp.TIPE_CODIGO = p.PERS_TIPOTRAB
            LEFT JOIN si_solm.dbo.CARGOS AS c
                ON c.CODI_CARG = p.CODI_CARG
            CROSS APPLY (SELECT TRY_CONVERT(DATE, dh.FECH_NACI) AS fecha_nacimiento) AS nacimiento
            CROSS APPLY (SELECT CASE
                WHEN nacimiento.fecha_nacimiento <= CONVERT(DATE, '99811231', 112)
                THEN DATEADD(YEAR, 18, nacimiento.fecha_nacimiento)
                ELSE NULL
            END AS fecha_mayoria) AS fechas
            WHERE UPPER(LTRIM(RTRIM(dh.TIPO_RELA))) LIKE N'HIJO%'
                AND UPPER(LTRIM(RTRIM(ISNULL(dh.DEHA_VIGENCIA, 'SI')))) = 'SI'
                AND nacimiento.fecha_nacimiento IS NOT NULL
                AND fechas.fecha_mayoria IS NOT NULL
                AND UPPER(LTRIM(RTRIM(ISNULL(p.ASIG_FAMI, '')))) = 'SI'
                AND DATEDIFF(DAY, CONVERT(DATE, GETDATE()), fechas.fecha_mayoria) BETWEEN 0 AND 30
            ";

        $parametros = [];
        if ($soloActivos) {
            $sql .= " AND UPPER(LTRIM(RTRIM(ISNULL(p.PERS_VIGENCIA, '')))) = 'SI'";
        } elseif (!empty($filtros['vigente'])) {
            $sql .= ' AND p.PERS_VIGENCIA = ?';
            $parametros[] = $filtros['vigente'];
        }
        foreach ([
            'codSucursal' => 'p.SUCU_CODIGO',
            'tipoPer' => 'p.PERS_TIPOTRAB',
            'cargo' => 'p.CODI_CARG',
        ] as $filtro => $columna) {
            if (!empty($filtros[$filtro])) {
                $sql .= " AND {$columna} = ?";
                $parametros[] = $filtros[$filtro];
            }
        }
        $sql .= ' ORDER BY s.SUCU_ABREVIATURA, personal, hijo';

        return DB::select($sql, $parametros);
    }

public static function obtenerSolicitudesDjPendientes()
    {
        // Las bases usan collations diferentes. Normalizamos expresamente los
        // campos de texto tanto en las uniones como en COALESCE para que el
        // endpoint no falle al cruzar sisolm_web con si_solm.
        return DB::select("SELECT DISTINCT
                r.SOLI_CODIGO AS id,
                r.CODI_PERS AS codigoPers,
                r.SOLI_FECHA_REG AS fecha_creacion,
                COALESCE(
                    NULLIF(LTRIM(RTRIM(CONCAT(
                        p.APEL_1 COLLATE DATABASE_DEFAULT, N' ',
                        p.APEL_2 COLLATE DATABASE_DEFAULT, N' ',
                        p.NOMB_1 COLLATE DATABASE_DEFAULT, N' ',
                        p.NOMB_2 COLLATE DATABASE_DEFAULT
                    ))), N''),
                    N'Personal ' + LTRIM(RTRIM(r.CODI_PERS COLLATE DATABASE_DEFAULT))
                ) AS personal,
                COALESCE(s.SUCU_ABREVIATURA COLLATE DATABASE_DEFAULT, CONVERT(NVARCHAR(100), p.SUCU_CODIGO) COLLATE DATABASE_DEFAULT, N'—') AS sucursal,
                COALESCE(tp.TIPE_DESCRIPCION COLLATE DATABASE_DEFAULT, CONVERT(NVARCHAR(100), p.PERS_TIPOTRAB) COLLATE DATABASE_DEFAULT, N'—') AS tipo_trabajador,
                COALESCE(c.DESC_CARGO COLLATE DATABASE_DEFAULT, CONVERT(NVARCHAR(100), p.CODI_CARG) COLLATE DATABASE_DEFAULT, N'—') AS cargo,
                COALESCE(CONVERT(NVARCHAR(100), p.NRO_DOCU_IDEN) COLLATE DATABASE_DEFAULT, N'—') AS dni
            FROM sisolm_web.dbo.sw_DJ_SOLICITUD r
            LEFT JOIN si_solm.dbo.PERSONAL p
                ON LTRIM(RTRIM(r.CODI_PERS COLLATE DATABASE_DEFAULT)) = LTRIM(RTRIM(p.CODI_PERS COLLATE DATABASE_DEFAULT))
            LEFT JOIN si_solm.dbo.SISO_SUCURSAL s
                ON CONVERT(NVARCHAR(100), s.SUCU_CODIGO) COLLATE DATABASE_DEFAULT = CONVERT(NVARCHAR(100), p.SUCU_CODIGO) COLLATE DATABASE_DEFAULT
            LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp
                ON CONVERT(NVARCHAR(100), tp.TIPE_CODIGO) COLLATE DATABASE_DEFAULT = CONVERT(NVARCHAR(100), p.PERS_TIPOTRAB) COLLATE DATABASE_DEFAULT
            LEFT JOIN si_solm.dbo.CARGOS c
                ON CONVERT(NVARCHAR(100), c.CODI_CARG) COLLATE DATABASE_DEFAULT = CONVERT(NVARCHAR(100), p.CODI_CARG) COLLATE DATABASE_DEFAULT
            WHERE r.SOLI_ESTADO = 'PENDIENTE'
              AND r.EMPR_CODIGO <> '05'
            ORDER BY r.SOLI_FECHA_REG DESC");
    }

public static function obtenerDemandasNuevas()
    {
        // Cada fila de sw_dj_notificaciones_act es una DJ registrada desde el SIP
        // (tipo 'anual' o 'demanda'), o sea una NUEVA SOLICITUD de actualización de
        // datos. NO se filtra por `tipo` ni por `tipo_actualizacion`: antes lo hacía
        // y en la práctica el popup devolvía 0 notificaciones aunque hubiera filas
        // pendientes (en la BD todas eran tipo 'anual').
        //
        // 'n.codigo as id' (antes 'n.id'): la columna de la tabla es `codigo`, no
        // `id`. Seleccionar `n.id` lanzaba SQLSTATE[42S22] en CADA consulta y un
        // try/catch con una copia idéntica del query la tragaba en silencio.
        //
        // LEFT JOIN + COALESCE: si el personal no está en sw_MIGRA_PERSONAL, la
        // notificación AUN ASÍ se muestra, usando el código como nombre. Antes un
        // INNER JOIN la descartaba en silencio.
        return DB::table('sw_dj_notificaciones_act as n')
            ->leftJoin('sw_MIGRA_PERSONAL as m', function ($join) {
                $join->whereRaw("
                    (TRY_CAST(n.codigoPers AS INT) = TRY_CAST(m.CODI_PERS AS INT) AND TRY_CAST(n.codigoPers AS INT) IS NOT NULL)
                    OR
                    (n.codigoPers COLLATE DATABASE_DEFAULT = m.CODI_PERS COLLATE DATABASE_DEFAULT AND TRY_CAST(n.codigoPers AS INT) IS NULL)
                ");
            })
            ->leftJoin('si_solm.dbo.PERSONAL as p', function ($join) {
                $join->whereRaw("
                    (TRY_CAST(n.codigoPers AS INT) = TRY_CAST(p.CODI_PERS AS INT) AND TRY_CAST(n.codigoPers AS INT) IS NOT NULL)
                    OR
                    (n.codigoPers COLLATE DATABASE_DEFAULT = p.CODI_PERS COLLATE DATABASE_DEFAULT AND TRY_CAST(n.codigoPers AS INT) IS NULL)
                ");
            })
            ->leftJoin('si_solm.dbo.SISO_SUCURSAL as s', 's.SUCU_CODIGO', '=', 'p.SUCU_CODIGO')
            ->leftJoin('si_solm.dbo.ADMI_TIPO_PERSONAL as tp', 'tp.TIPE_CODIGO', '=', 'p.PERS_TIPOTRAB')
            ->leftJoin('si_solm.dbo.CARGOS as c', 'c.CODI_CARG', '=', 'p.CODI_CARG')
            // DISTINCT: sw_MIGRA_PERSONAL puede tener CODI_PERS repetidos y el
            // JOIN multiplicaría la misma notificación varias veces.
            ->distinct()
            ->select(
                'n.codigo as id',
                'n.codigoPers',
                // DISTINCT + SQL Server exige que el ORDER BY también esté en el SELECT
                'n.fecha_creacion',
                DB::raw("COALESCE(
                    NULLIF(LTRIM(RTRIM(CONCAT(p.APEL_1 COLLATE DATABASE_DEFAULT, ' ', p.APEL_2 COLLATE DATABASE_DEFAULT, ' ', p.NOMB_1 COLLATE DATABASE_DEFAULT, ' ', p.NOMB_2 COLLATE DATABASE_DEFAULT))), ''),
                    NULLIF(LTRIM(RTRIM(CONCAT(m.APEL_1 COLLATE DATABASE_DEFAULT, ' ', m.APEL_2 COLLATE DATABASE_DEFAULT, ' ', m.NOMB_1 COLLATE DATABASE_DEFAULT, ' ', m.NOMB_2 COLLATE DATABASE_DEFAULT))), ''),
                    'Personal ' + LTRIM(RTRIM(n.codigoPers))
                ) as personal"),
                DB::raw("COALESCE(s.SUCU_ABREVIATURA, p.SUCU_CODIGO, m.SUCU_CODIGO, '—') as sucursal"),
                DB::raw("COALESCE(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB, m.PERS_TIPOTRAB, '—') as tipo_trabajador"),
                DB::raw("COALESCE(c.DESC_CARGO, p.CODI_CARG, m.CODI_CARG, '—') as cargo"),
                DB::raw("COALESCE(p.NRO_DOCU_IDEN, m.NRO_DOCU_IDEN, '—') as dni")
            )
            // Solo las notificaciones vigentes: `habilitado = 0` significa que ya
            // fueron atendidas desde la campanita y no deben volver a aparecer.
            ->where('n.habilitado', 1)
            ->orderBy('fecha_creacion', 'desc')
            ->get();
    }

    /**
     * Marca una notificación como ATENDIDA (habilitado = 0).
     *
     * NO borra la fila: se conserva el historial. Simplemente deja de aparecer
     * en el popup y en la campanita. Antes aquí se hacía un DELETE sobre la
     * columna `id`, que no existe (es `codigo`), así que siempre lanzaba una
     * excepción y el catch repetía el borrado físico.
     */
    public static function archivarDemanda($id)
    {
        return DB::table('sw_dj_notificaciones_act')
            ->where('codigo', $id)
            ->update([
                'habilitado'         => 0,
                // Auditoría: quién la atendió (escape manual desde la campanita)
                'atendida_por'       => session('usuario'),
                // GETDATE() en vez de now(): con sesión SQL Server en español, el
                // string que arma Carbon para un datetime se interpreta mal y lanza
                // SQLSTATE[22007] ("valor fuera de intervalo").
                'fecha_modificacion' => DB::raw('GETDATE()'),
            ]);
    }

    /**
     * Archiva TODAS las notificaciones SIP pendientes de una persona.
     *
     * Se llama desde DjController::saveDjCompleto() cuando se procesa/guarda la
     * DJ: el trabajo ya se hizo, así que la notificación deja de popupear para
     * todo el equipo. Nadie tiene que recordar darle X a la campanita.
     *
     * Devuelve el número de notificaciones archivadas (0 si no había ninguna).
     */
    public static function archivarNotificacionesDePersonal($codiPers)
    {
        $codiPers = trim((string) $codiPers);
        if ($codiPers === '') {
            return 0;
        }

        return DB::table('sw_dj_notificaciones_act')
            ->where('habilitado', 1)
            // codigoPers es `char` y CODI_PERS no coincide de tipo, así que se
            // compara igual que en el LEFT JOIN de obtenerDemandasNuevas():
            // numérico si ambos se pueden convertir, COLLATE si no.
            ->whereRaw("
                (TRY_CAST(codigoPers AS INT) = TRY_CAST(? AS INT) AND TRY_CAST(codigoPers AS INT) IS NOT NULL)
                OR
                (codigoPers COLLATE DATABASE_DEFAULT = ? COLLATE DATABASE_DEFAULT AND TRY_CAST(codigoPers AS INT) IS NULL)
            ", [$codiPers, $codiPers])
            ->update([
                'habilitado'         => 0,
                'atendida_por'       => session('usuario'),
                'fecha_modificacion' => DB::raw('GETDATE()'),
            ]);
    }

}

