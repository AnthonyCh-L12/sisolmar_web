<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NotificacionModel;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportePersonalController extends Controller
{
    /**
     * Roles que pueden descargar el reporte de documentos:
     *   17 → Admins RRHH   ·   11 → Super Usuario
     * (catálogo: si_solm.dbo.sw_roles)
     */
    private const ROLES_REPORTE_DOCUMENTOS = [17, 11];

    /**
     * GET /api/reporte-personal-datos-generales
     *
     * Params:
     *   codSucursal   → SISO_SUCURSAL.SUCU_CODIGO  (vacío = todas)
     *   tipoPer       → '01'|'02'|'03'|'05'|'06'|'' (PERS_TIPOTRAB)
     *   vigente       → '1' PERS_VIGENCIA='SI' | '0' PERS_VIGENCIA='NO'
     *   apPaterno     → LIKE sobre APEL_1
     *   docIdentidad  → LIKE sobre NRO_DOCU_IDEN
     *
     * Joins:
     *   SISO_SUCURSAL       → SUCU_ABREVIATURA   (nombre sucursal)
     *   ADMI_TIPO_PERSONAL  → TIPE_DESCRIPCION   (tipo trabajador)
     *   CARGOS              → DESC_CARGO         (descripción cargo)
     *   ADMI_PAIS           → PAIS_DESCRIPCION   (nacionalidad)
     *   TIPO_DOCUMENTO      → NEMO               (DNI, CE, etc.)
     *
     * Subqueries:
     *   CONTRATOS_PERSONAL  → FECH_FIN_CONT      (último por USUA_FECHA_REG)
     *   REDO_CERTIFICADO    → CERT_FECHA_CADUCA  (último por FEC_REG, REQU_CODIGO=24)
     */
    public function proxyImagen(Request $request)
    {
        $url = trim($request->get('url', ''));
    
        // Validar que la URL sea del servidor permitido
        $dominioPermitido = 'http://190.116.178.163/Biblioteca_Grafica/';
    
        if (empty($url) || !str_starts_with($url, $dominioPermitido)) {
            return response()->json(['error' => 'URL no permitida.'], 403);
        }
    
        try {
            // Usar cURL para descargar la imagen desde el servidor interno
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
    
            $contenido  = curl_exec($ch);
            $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
    
            // Si no existe el archivo en el servidor
            if ($httpCode === 404 || $contenido === false || empty($contenido)) {
                return response()->json(['error' => 'Archivo no encontrado.'], 404);
            }
    
            if ($httpCode !== 200) {
                return response()->json(['error' => "Error al obtener el archivo (HTTP {$httpCode})."], 502);
            }
    
            return response($contenido, 200, [
                'Content-Type'        => $contentType ?? 'image/jpeg',
                'Content-Disposition' => 'inline',
                'Cache-Control'       => 'no-store',
            ]);
    
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }
    public function datosGenerales(Request $request)
    {
        $codSucursal  = trim($request->get('codSucursal',  ''));
        $tipoPer      = trim($request->get('tipoPer',      ''));
        $vigente      = $request->get('vigente', '');
        $apPaterno    = trim($request->get('apPaterno',    ''));
        $docIdentidad = trim($request->get('docIdentidad', ''));

        // ── Subquery: último contrato por persona ─────────────
        // Trae FECH_FIN_CONT del registro con mayor USUA_FECHA_REG
        $subContrato = DB::raw("(
            SELECT TOP 1 cp.FECH_FIN_CONT
            FROM si_solm.dbo.CONTRATOS_PERSONAL cp
            WHERE cp.CODI_PERS = p.CODI_PERS
             AND ESTA_CONT = 1
            ORDER BY cp.USUA_FECHA_REG DESC
        ) as FIN_CONTRATO");

        // ── Subquery: último EMO (REQU_CODIGO=24) por persona ─
        // Trae CERT_FECHA_CADUCA del registro con mayor FEC_REG
        $subEmo = DB::raw("(
            SELECT TOP 1 rc.CERT_FECHA_CADUCA
            FROM si_solm.dbo.REDO_CERTIFICADO rc
            WHERE rc.CODI_PERS   = p.CODI_PERS
              AND rc.REQU_CODIGO = 24 AND CERT_VIGENCIA = 'SI'
            ORDER BY rc.FEC_REG DESC
        ) as CADUCA_EMO");

        $query = DB::table('si_solm.dbo.PERSONAL as p')
            ->leftJoin('si_solm.dbo.SISO_SUCURSAL as s',
                's.SUCU_CODIGO', '=', 'p.SUCU_CODIGO')
            ->leftJoin('si_solm.dbo.ADMI_TIPO_PERSONAL as tp',
                'tp.TIPE_CODIGO', '=', 'p.PERS_TIPOTRAB')
            ->leftJoin('si_solm.dbo.CARGOS as c',
                'c.CODI_CARG', '=', 'p.CODI_CARG')
            ->leftJoin('si_solm.dbo.ADMI_PAIS as pa',
                'pa.PAIS_CODIGO', '=', 'p.NACIONALIDAD')
            ->leftJoin('si_solm.dbo.TIPO_DOCUMENTO as td',
                'td.CODI_TIPO_DOCU', '=', 'p.CODI_TIPO_DOCU')
            ->select([
                'p.CODI_PERS',
                'p.APEL_1',
                'p.APEL_2',
                'p.NOMB_1',
                'p.NOMB_2',
                'p.NRO_DOCU_IDEN',
                'p.PERS_FECHCADUCADNI',
                'p.PERS_SEXO',
                'p.FECH_NACI',
                'p.FECH_INGRE',
                'p.PERS_EMAIL',
                'p.PERS_TELEFONO',
                'p.DIRECCION',
                'p.PERS_TIPOTRAB',
                'p.SUCU_CODIGO',
                DB::raw("ISNULL(s.SUCU_ABREVIATURA, p.SUCU_CODIGO)    as SUCURSAL"),
                DB::raw("ISNULL(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) as TIPO_DESCRIPCION"),
                DB::raw("ISNULL(c.DESC_CARGO, p.CODI_CARG)            as CARGO_DESC"),
                DB::raw("ISNULL(pa.PAIS_DESCRIPCION, p.NACIONALIDAD)  as PAIS_DESC"),
                DB::raw("ISNULL(td.NEMO, p.CODI_TIPO_DOCU)            as TIPO_DOC_NEMO"),
                $subContrato,
                $subEmo,
            ]);

        // ── Vigente ───────────────────────────────────────────
        if ($vigente === '1' || $vigente === '0') {
            $query->where('p.PERS_VIGENCIA', $vigente === '1' ? 'SI' : 'NO');
        }

        // ── Sucursal ──────────────────────────────────────────
        if ($codSucursal !== '') {
            $query->where('p.SUCU_CODIGO', $codSucursal);
        }

        // ── Tipo personal ─────────────────────────────────────
        if ($tipoPer !== '') {
            $query->whereIn('p.PERS_TIPOTRAB', $this->mapTipoPerACodigos($tipoPer));
        }

        // ── Apellido Paterno ──────────────────────────────────
        if ($apPaterno !== '') {
            $query->where('p.APEL_1', 'like', '%' . $apPaterno . '%');
        }

        // ── Nro Documento ─────────────────────────────────────
        if ($docIdentidad !== '') {
            $query->where('p.NRO_DOCU_IDEN', 'like', '%' . $docIdentidad . '%');
        }

        $query->orderBy('p.APEL_1')
              ->orderBy('p.APEL_2')
              ->orderBy('p.NOMB_1');

        $personal = $query->get();

        $data = $personal->map(fn($r) => [
            'codPersonal'  => trim($r->CODI_PERS),
            'codSucursal'  => trim($r->SUCU_CODIGO      ?? ''),
            'sucursal'     => trim($r->SUCURSAL          ?? '—'),
            'nombre'       => trim(
                ($r->NOMB_1 ?? '') . ' ' .
                ($r->NOMB_2 ?? '') . ' ' .
                ($r->APEL_1 ?? '') . ' ' .
                ($r->APEL_2 ?? '')
            ),
            'nacionalidad' => trim($r->PAIS_DESC         ?? '—'),
            'tipoDoc'      => trim($r->TIPO_DOC_NEMO     ?? '—'),
            'nroDocIden'   => trim($r->NRO_DOCU_IDEN     ?? '—'),
            'cadDni'       => $this->formatFecha($r->PERS_FECHCADUCADNI),
            'sexo'         => trim($r->PERS_SEXO         ?? '—'),
            'edad'         => $this->calcularEdad($r->FECH_NACI),
            'email'        => trim($r->PERS_EMAIL        ?? '—'),
            'telefono'     => trim($r->PERS_TELEFONO     ?? '—'),
            'direccion'    => trim($r->DIRECCION         ?? '—'),
            'fechIngreso'  => $this->formatFecha($r->FECH_INGRE),
            'cargo'        => trim($r->CARGO_DESC        ?? '—'),
            'tipoPer'      => trim($r->TIPO_DESCRIPCION  ?? '—'),
            'caducaEmo'    => $this->formatFecha($r->CADUCA_EMO),
            'finContrato'  => $this->formatFecha($r->FIN_CONTRATO),
        ]);

        return response()->json([
            'success' => true,
            'total'   => $data->count(),
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/reporte/personal-documentos   (?refrescar=1&solo_faltantes=1)
     *
     * Devuelve un .xlsx con el personal que coincide con los filtros e indica:
     *   · Foto     → Sí / No / Sin verificar
     *   · DNI      → Completo / Solo anverso / Solo reverso / No / Sin verificar
     *   · Quién lo registró (y cuándo) y quién lo modificó (y cuándo)
     *
     * El estado de los archivos se revisa SIEMPRE en cada generación contra el
     * servidor de imágenes (Biblioteca_Grafica), en paralelo (~10-20 s para los
     * 3.405 archivos), de modo que una foto o un DNI recién subidos aparecen en
     * el reporte del momento. La caché solo conserva el avance de un escaneo que
     * se cortó (10 min) para retomarlo; nunca responde con datos guardados.
     * Nada de esto toca la base de datos.
     */
    public function documentosPendientes(Request $request)
    {
        // El reporte expone el DNI y la trazabilidad del personal filtrado:
        // solo ADMINS RRHH (17) y SUPERUSUARIO (11). Mismo criterio que el @if
        // del botón en gestion_dj.blade.php, para que ambos siempre concuerden.
        if (!in_array((int) session('tipo_rol'), self::ROLES_REPORTE_DOCUMENTOS, true)) {
            abort(403, 'Este reporte es exclusivo para ADMINS RRHH y SUPERUSUARIO.');
        }

        @set_time_limit(0);

        $forzar = in_array((string) $request->query('refrescar'), ['1', 'true'], true);
        $soloFaltantes = in_array((string) $request->query('solo_faltantes'), ['1', 'true'], true);

        $filtros = $request->validate([
            'codSucursal' => 'nullable|string|max:20',
            'tipoPer' => 'nullable|in:01,02,03,05',
            'cargo' => 'nullable|string|max:20',
            'vigente' => 'nullable|in:SI,NO',
        ]);
        $incremental = $request->boolean('incremental');
        $scanId = $request->query('scan_id');
        if ($incremental && (!is_string($scanId) || !preg_match('/^[a-f0-9-]{36}$/i', $scanId))) {
            abort(422, 'Identificador de escaneo inválido.');
        }

        // ── 1. Población ──────────────────────────────────────
        // Parte de la misma fuente que Gestión DJ y aplica los mismos filtros
        // elegidos en la pantalla antes de revisar archivos.
        $sql = <<<'SQL'
            SELECT
                LTRIM(RTRIM(p.CODI_PERS))  AS CODI_PERS,
                p.APEL_1, p.APEL_2, p.NOMB_1, p.NOMB_2,
                p.NRO_DOCU_IDEN,
                p.FECH_INGRE,
                p.FECH_INGRE_PLANILLA,
                ISNULL(s.SUCU_ABREVIATURA, p.SUCU_CODIGO)    AS SUCURSAL,
                ISNULL(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS TIPO_DESCRIPCION,
                ISNULL(c.DESC_CARGO, p.CODI_CARG)            AS CARGO_DESC,
                p.USUA_CODIGO_REG, p.USUA_FECHA_REG,
                p.USUA_CODIGO_MOD, p.USUA_FECHA_MOD,
                reg.NOMBRE_USUARIO AS REG_NOMBRE,
                mod.NOMBRE_USUARIO AS MOD_NOMBRE
            FROM si_solm.dbo.PERSONAL p
            INNER JOIN si_solm.dbo.SISO_SUCURSAL s
                    ON s.SUCU_CODIGO = p.SUCU_CODIGO
            LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp
                    ON tp.TIPE_CODIGO = p.PERS_TIPOTRAB
            LEFT JOIN si_solm.dbo.CARGOS c
                    ON c.CODI_CARG = p.CODI_CARG
            OUTER APPLY (
                SELECT TOP 1
                       LTRIM(RTRIM(ISNULL(u.NOMB_1,  ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.NOMB_2,  ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.APEL_1,  ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.APEL_2,  ''))) AS NOMBRE_USUARIO
                FROM si_solm.dbo.USUARIOS u
                WHERE u.LOGIN = LTRIM(RTRIM(p.USUA_CODIGO_REG))
            ) reg
            OUTER APPLY (
                SELECT TOP 1
                       LTRIM(RTRIM(ISNULL(u.NOMB_1,  ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.NOMB_2,  ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.APEL_1,  ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.APEL_2,  ''))) AS NOMBRE_USUARIO
                FROM si_solm.dbo.USUARIOS u
                WHERE u.LOGIN = LTRIM(RTRIM(p.USUA_CODIGO_MOD))
            ) mod
            SQL;

        $condiciones = ['p.EMPR_CODIGO = ?'];
        $parametros = ['01'];
        foreach ([
            'codSucursal' => 'p.SUCU_CODIGO',
            'tipoPer' => 'p.PERS_TIPOTRAB',
            'cargo' => 'p.CODI_CARG',
            'vigente' => 'p.PERS_VIGENCIA',
        ] as $filtro => $columna) {
            if (!empty($filtros[$filtro])) {
                $condiciones[] = $columna . ' = ?';
                $parametros[] = $filtros[$filtro];
            }
        }
        $sql .= " WHERE " . implode(' AND ', $condiciones)
              . ' ORDER BY p.APEL_1, p.APEL_2, p.NOMB_1';

        $personal = DB::select($sql, $parametros);

        $filas   = [];
        $codigos = [];

        foreach ($personal as $r) {
            $codi = trim((string) ($r->CODI_PERS ?? ''));
            if ($codi === '') {
                continue;
            }

            $codigos[]   = $codi;
            $filas[$codi] = [
                'codi'     => $codi,
                'nombre'   => trim(implode(' ', array_filter([
                    trim((string) ($r->APEL_1 ?? '')),
                    trim((string) ($r->APEL_2 ?? '')),
                    trim((string) ($r->NOMB_1 ?? '')),
                    trim((string) ($r->NOMB_2 ?? '')),
                ], fn ($v) => $v !== ''))),
                'doc'      => trim((string) ($r->NRO_DOCU_IDEN ?? '')),
                'sucursal' => trim((string) ($r->SUCURSAL         ?? '')),
                'tipo'     => trim((string) ($r->TIPO_DESCRIPCION ?? '')),
                'cargo'    => trim((string) ($r->CARGO_DESC       ?? '')),
                'ingreso_solmar' => $this->formatFecha($r->FECH_INGRE ?? null),
                'ingreso_planilla' => $this->formatFecha($r->FECH_INGRE_PLANILLA ?? null),
                'reg'      => $this->usuarioLegible($r->USUA_CODIGO_REG, $r->REG_NOMBRE),
                'freg'     => $this->formatFechaHora($r->USUA_FECHA_REG),
                'mod'      => $this->usuarioLegible($r->USUA_CODIGO_MOD, $r->MOD_NOMBRE),
                'fmod'     => $this->formatFechaHora($r->USUA_FECHA_MOD),
            ];
        }

        // ── 2. Escaneo del servidor de archivos (siempre, en paralelo) ──
        $estado = $this->estadoDocumentos($codigos, $forzar, $scanId, $incremental);
        if ($incremental && !$estado['completo']) {
            return response()->json([
                'completo' => false,
                'procesados' => $estado['procesados'],
                'total' => $estado['total'],
            ]);
        }

        // ── 3. Enriquecer filas + estadísticas ────────────────
        $fotoOk   = 0;
        $dniOk    = 0;
        $faltan   = 0;

        foreach ($filas as $codi => $fila) {
            $doc = $estado['datos'][$codi] ?? [
                'foto' => 'desconocido', 'anverso' => 'desconocido', 'reverso' => 'desconocido',
            ];

            $foto = $this->estadoFoto($doc['foto']);
            $dni  = $this->estadoDni($doc['anverso'], $doc['reverso']);

            $filas[$codi]['foto_txt']  = $foto;
            $filas[$codi]['dni_txt']   = $dni;
            $filas[$codi]['incompleto'] = ($foto !== 'Sí') || ($dni !== 'Completo');

            if ($foto === 'Sí') {
                $fotoOk++;
            }
            if ($dni === 'Completo') {
                $dniOk++;
            }
            if ($filas[$codi]['incompleto']) {
                $faltan++;
            }
        }

        $stats = [
            'generado' => date('d/m/Y H:i:s'),
            'personal' => count($filas),
            'foto_ok'  => $fotoOk,
            'dni_ok'   => $dniOk,
            'faltan'   => $faltan,
        ];

        if ($soloFaltantes) {
            $filas = array_values(array_filter(
                $filas,
                static fn (array $fila): bool => $fila['incompleto'] === true
            ));
        }

        if ($request->boolean('previsualizar')) {
            $muestra = array_map(static fn (array $fila, int $indice): array => [
                'numero' => $indice + 1,
                'codigo' => $fila['codi'],
                'personal' => $fila['nombre'],
                'dni' => $fila['doc'],
                'sucursal' => $fila['sucursal'],
                'tipo' => $fila['tipo'],
                'cargo' => $fila['cargo'],
                'ingreso_solmar' => $fila['ingreso_solmar'],
                'ingreso_planilla' => $fila['ingreso_planilla'],
                'foto' => $fila['foto_txt'],
                'dni_estado' => $fila['dni_txt'],
                'registrado_por' => $fila['reg'],
                'fecha_registro' => $fila['freg'],
                'modificado_por' => $fila['mod'],
                'fecha_modificacion' => $fila['fmod'],
            ], array_values($filas), array_keys(array_values($filas)));

            return response()->json([
                'preview' => $muestra,
                'total' => count($filas),
            ]);
        }

        if ($request->query('formato') === 'pdf') {
            $tituloPdf = $this->tituloExcelDocumentos($filtros, $soloFaltantes);
            return $this->descargarPdfReporte(
                'file_control.reportes_dj_pdf',
                $this->nombreArchivoExcel($tituloPdf, 'pdf'),
                $tituloPdf,
                array_values($filas),
                [
                    'N°' => 'numero', 'Código' => 'codi', 'Apellidos y nombres' => 'nombre',
                    'N° documento' => 'doc', 'Cargo' => 'cargo', 'Ingreso a Solmar' => 'ingreso_solmar',
                    'Ingreso a planillas' => 'ingreso_planilla', 'Foto' => 'foto_txt',
                    'DNI' => 'dni_txt', 'Registrado por' => 'reg', 'F. registro' => 'freg',
                    'Modificado por' => 'mod', 'F. modificación' => 'fmod',
                ],
                $stats
            );
        }

        // ── 4. Excel ──────────────────────────────────────────
        $tituloExcel = $this->tituloExcelDocumentos($filtros, $soloFaltantes);
        $nombreArchivo = $this->nombreArchivoExcel($tituloExcel);
        $rutaArchivo   = $this->generarExcelDocumentos(array_values($filas), $stats, $tituloExcel);

        // Copiar a storage/app/public/reportes para servir vía URL directa
        // (evita el problema de espacio prepended en respuestas AJAX del servidor built-in)
        $publicDir = storage_path('app/public/reportes');
        if (!is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        $rutaPublica = $publicDir . DIRECTORY_SEPARATOR . $nombreArchivo;
        copy($rutaArchivo, $rutaPublica);
        @unlink($rutaArchivo);

        // Retornar la URL de la ruta de descarga (autenticada + valida el nombre
        // del archivo). El frontend solo navega a data.url, así que no cambia nada.
        // OJO: antes apuntaba a reportes/download.php, un archivo que no existe
        // (caía en el catch-all → 403/500 y el navegador mostraba "archivo no
        // encontrado disponible en el sitio").
        return response()->json([
            'url' => route('reporte.descargar', ['archivo' => $nombreArchivo]),
            'nombre' => $nombreArchivo,
        ]);
    }

    private function tituloExcelDocumentos(array $filtros, bool $soloFaltantes): string
    {
        $sucursal = 'TODAS';
        if (!empty($filtros['codSucursal'])) {
            $registro = DB::selectOne(
                'SELECT TOP 1 SUCU_ABREVIATURA FROM si_solm.dbo.SISO_SUCURSAL WHERE SUCU_CODIGO = ?',
                [$filtros['codSucursal']]
            );
            $sucursal = trim((string) ($registro->SUCU_ABREVIATURA ?? $filtros['codSucursal']));
        }

        $tipo = 'TODOS';
        if (!empty($filtros['tipoPer'])) {
            $registro = DB::selectOne(
                'SELECT TOP 1 TIPE_DESCRIPCION FROM si_solm.dbo.ADMI_TIPO_PERSONAL WHERE TIPE_CODIGO = ?',
                [$filtros['tipoPer']]
            );
            $tipo = trim((string) ($registro->TIPE_DESCRIPCION ?? $filtros['tipoPer']));
        }

        $cargo = 'TODOS';
        if (!empty($filtros['cargo'])) {
            $registro = DB::selectOne(
                'SELECT TOP 1 DESC_CARGO FROM si_solm.dbo.CARGOS WHERE CODI_CARG = ?',
                [$filtros['cargo']]
            );
            $cargo = trim((string) ($registro->DESC_CARGO ?? $filtros['cargo']));
        }

        $estado = match ($filtros['vigente'] ?? '') {
            'SI' => 'ACTIVOS',
            'NO' => 'CESADOS',
            default => 'ACTIVOS Y CESADOS',
        };

        $reporte = $soloFaltantes ? 'REPORTE DE PENDIENTES DE FOTOS / DNI' : 'REPORTE DE DOCUMENTOS';

        return "{$reporte} | {$sucursal} | {$tipo} | {$cargo} | {$estado}";
    }

    private function nombreArchivoExcel(string $titulo, string $extension = 'xlsx'): string
    {
        $titulo = str_replace('|', ' ', $titulo);
        $titulo = preg_replace('/^PENDIENTES\b/u', 'REPORTE DE PENDIENTES', $titulo) ?? $titulo;
        $titulo = preg_replace('/([45])\s*[°º]/u', '$1TA', $titulo) ?? $titulo;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo);
        $nombre = strtoupper($ascii === false ? $titulo : $ascii);
        $nombre = preg_replace('/[^A-Z0-9]+/', '_', $nombre) ?? 'REPORTE';
        $nombre = trim(substr($nombre, 0, 210), '_');

        return $nombre . '_' . date('Y-m-d') . '.' . $extension;
    }

    private function descargarPdfReporte(string $vista, string $nombre, string $titulo, array $filas, array $columnas, array $stats)
    {
        // Dompdf conserva el árbol de estilos y las páginas completas en
        // memoria. El límite PHP predeterminado de 128 MB no alcanza para los
        // reportes de personal con cientos o miles de filas.
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $filas = array_values(array_map(static function (array $fila, int $indice): array {
            $fila['numero'] = $indice + 1;
            return $fila;
        }, $filas, array_keys($filas)));

        $rutaLogo = public_path('images/logo_sol.png');
        $logoDataUri = is_file($rutaLogo)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($rutaLogo))
            : null;

        $pdf = Pdf::loadView($vista, compact('titulo', 'filas', 'columnas', 'stats', 'logoDataUri'))
            ->setPaper('a3', 'landscape')
            ->setOption('dpi', 72);

        return $pdf->download($nombre);
    }

    public function descargarReporte(Request $request, string $archivo)
    {
        $ruta = storage_path('app/public/reportes/' . $archivo);

        if (!file_exists($ruta)) {
            abort(404, 'Reporte no encontrado');
        }

        // Verificar que el archivo sea un reporte válido (seguridad básica)
        if (!preg_match('/^[A-Z0-9]+(?:_[A-Z0-9]+)*_\d{4}-\d{2}-\d{2}\.xlsx$/', $archivo)) {
            abort(403, 'Archivo no válido');
        }

        return response()->download($ruta, $archivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Reporte de DNI de hijos ──────────────────────────────────────────

    // v2: la caché solo guarda avance de un escaneo interrumpido (SCAN_AVANCE_MIN).
    // La v1 guardaba el último escaneo completo por 6 h, así que se cambia de
    // clave para que ninguna lectura previa quede como "dato fresco".
    private const HIJOS_CACHE_KEY = 'reporte_dni_hijos_estado_v2';
    private const HIJOS_LOTE      = 10;

    /**
     * GET /api/dj/reporte/dni-hijos   (?refrescar=1)
     *
     * Devuelve un .xlsx con el personal vigente que tiene hijos menores de edad
     * sin el DNI cargado (anverso y/o reverso) en el servidor de imágenes.
     *
     * Las imágenes se guardan como DNI1_HIJOS/{CODI}_H{n}.jpg (anverso) y
     * DNI2_HIJOS/{CODI}_H{n}.jpg (reverso), donde el hijo i ocupa H(2i+1)/H(2i+2)
     * en el MISMO orden en que el formulario pinta las filas (ORDER BY TIPO_RELA,
     * ver DjController::getPersonalData), por eso se replica esa consulta y recién
     * después se filtran los menores de edad.
     *
     * El escaneo se hace SIEMPRE en cada generación, en paralelo (~5-10 s para
     * los ≈780 archivos), de modo que el DNI de un hijo recién cargado aparece
     * en el reporte del momento. La caché solo conserva el avance de un escaneo
     * que se cortó (10 min) para retomarlo; nunca responde con datos guardados.
     * Nada de esto toca la base de datos.
     */
    public function dniHijosPendientes(Request $request)
    {
        // Mismo criterio que el @if del botón en gestion_dj.blade.php:
        // solo ADMINS RRHH (17) y SUPERUSUARIO (11).
        if (!in_array((int) session('tipo_rol'), self::ROLES_REPORTE_DOCUMENTOS, true)) {
            abort(403, 'Este reporte es exclusivo para ADMINS RRHH y SUPERUSUARIO.');
        }

        @set_time_limit(0);

        $forzar = in_array((string) $request->query('refrescar'), ['1', 'true'], true);
        $filtros = $request->validate([
            'codSucursal' => 'nullable|string|max:20',
            'tipoPer' => 'nullable|in:01,02,03,05',
            'cargo' => 'nullable|string|max:20',
            'vigente' => 'nullable|in:SI,NO',
        ]);
        $incremental = $request->boolean('incremental');
        $scanId = $request->query('scan_id');
        if ($incremental && (!is_string($scanId) || !preg_match('/^[a-f0-9-]{36}$/i', $scanId))) {
            abort(422, 'Identificador de escaneo inválido.');
        }

        // ── 1. Personal vigente con al menos un hijo menor de edad ──────
        // Mismo universo que el reporte de documentos: todos los vigentes,
        // todos los tipos de personal y todas las sucursales (sin filtros).
        $sql = <<<'SQL'
            SELECT
                LTRIM(RTRIM(p.CODI_PERS))  AS CODI_PERS,
                p.APEL_1, p.APEL_2, p.NOMB_1, p.NOMB_2,
                p.NRO_DOCU_IDEN,
                p.FECH_INGRE,
                p.FECH_INGRE_PLANILLA,
                p.ASIG_FAMI,
                ISNULL(s.SUCU_ABREVIATURA, p.SUCU_CODIGO)    AS SUCURSAL,
                ISNULL(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS TIPO_DESCRIPCION,
                ISNULL(c.DESC_CARGO, p.CODI_CARG)            AS CARGO_DESC
            FROM si_solm.dbo.PERSONAL p
            INNER JOIN si_solm.dbo.SISO_SUCURSAL s
                    ON s.SUCU_CODIGO = p.SUCU_CODIGO
            LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp
                    ON tp.TIPE_CODIGO = p.PERS_TIPOTRAB
            LEFT JOIN si_solm.dbo.CARGOS c
                    ON c.CODI_CARG = p.CODI_CARG
            WHERE p.EMPR_CODIGO = '01'
              AND EXISTS (
                    SELECT 1
                    FROM si_solm.dbo.DERECHO_HABIENTE d
                    WHERE LTRIM(RTRIM(d.CODI_PERS)) = LTRIM(RTRIM(p.CODI_PERS))
                      AND d.TIPO_RELA LIKE 'HIJ%'
                      AND d.FECH_NACI IS NOT NULL
                      AND d.FECH_NACI > DATEADD(YEAR, -18, CAST(GETDATE() AS DATE))
                      AND ISNULL(d.DEHA_VIGENCIA, 'SI') = 'SI'
                      AND ISNULL(d.FALLECIDO, 0)        = 0
              )
            ORDER BY p.APEL_1, p.APEL_2, p.NOMB_1
            SQL;

        $parametros = [];
        $condiciones = [];
        foreach ([
            'codSucursal' => 'p.SUCU_CODIGO',
            'tipoPer' => 'p.PERS_TIPOTRAB',
            'cargo' => 'p.CODI_CARG',
            'vigente' => 'p.PERS_VIGENCIA',
        ] as $filtro => $columna) {
            if (!empty($filtros[$filtro])) {
                $condiciones[] = $columna . ' = ?';
                $parametros[] = $filtros[$filtro];
            }
        }
        if ($condiciones !== []) {
            $sql = str_replace('AND EXISTS (', 'AND ' . implode(' AND ', $condiciones) . ' AND EXISTS (', $sql);
        }
        $personal = DB::select($sql, $parametros);

        $filas   = [];
        $codigos = [];

        foreach ($personal as $r) {
            $codi = trim((string) ($r->CODI_PERS ?? ''));
            if ($codi === '') {
                continue;
            }

            $codigos[]    = $codi;
            $filas[$codi] = [
                'codi'     => $codi,
                'nombre'   => trim(implode(' ', array_filter([
                    trim((string) ($r->APEL_1 ?? '')),
                    trim((string) ($r->APEL_2 ?? '')),
                    trim((string) ($r->NOMB_1 ?? '')),
                    trim((string) ($r->NOMB_2 ?? '')),
                ], fn ($v) => $v !== ''))),
                'doc'      => trim((string) ($r->NRO_DOCU_IDEN ?? '')),
                'ingreso_solmar' => $this->formatFecha($r->FECH_INGRE ?? null),
                'ingreso_planilla' => $this->formatFecha($r->FECH_INGRE_PLANILLA ?? null),
                'asignacion_familiar' => match (strtoupper(trim((string) ($r->ASIG_FAMI ?? '')))) {
                    'SI', 'SÍ', '1' => 'Sí',
                    'NO', '0' => 'No',
                    default => 'Sin dato',
                },
                'sucursal' => trim((string) ($r->SUCURSAL         ?? '')),
                'tipo'     => trim((string) ($r->TIPO_DESCRIPCION ?? '')),
                'cargo'    => trim((string) ($r->CARGO_DESC       ?? '')),
            ];
        }

        // ── 2. Escaneo del servidor de archivos (siempre, en paralelo) ─────
        $estado = $this->estadoDniHijos($codigos, $forzar, $scanId, $incremental);
        if ($incremental && !$estado['completo']) {
            return response()->json([
                'completo' => false,
                'procesados' => $estado['procesados'],
                'total' => $estado['total'],
            ]);
        }

        // ── 3. Enriquecer filas + estadísticas ──────────────────────────
        $evaluados    = 0;
        $sinFaltantes = 0;
        $hijosTotal   = 0;
        $hijosOk      = 0;

        foreach (array_keys($filas) as $codi) {
            $hijos = $estado['datos'][$codi]['hijos'] ?? [];

            // Sin hijos menores evaluables (p. ej. fecha de nacimiento ausente)
            if ($hijos === []) {
                unset($filas[$codi]);
                continue;
            }

            $evaluados++;
            $total     = count($hijos);
            $ok        = 0;
            $parcial   = 0;
            $ninguno   = 0;
            $sinVerif  = 0;
            $sinAnverso = 0;
            $sinReverso = 0;
            $faltantes = [];

            foreach ($hijos as $h) {
                if ($h['estado'] === 'Completo') {
                    $ok++;
                } elseif ($h['estado'] === 'No') {
                    $ninguno++;
                } elseif ($h['estado'] === 'Sin verificar') {
                    // Fallo de red: NUNCA se reporta como faltante.
                    $sinVerif++;
                } else {
                    $parcial++;
                }

                // ¿Algún hijo le falta del lado A / lado B?
                if (in_array($h['estado'], ['Solo reverso', 'No'], true)) {
                    $sinAnverso++;
                }
                if (in_array($h['estado'], ['Solo anverso', 'No'], true)) {
                    $sinReverso++;
                }

                if ($h['faltante']) {
                    $faltantes[] = $h;
                }
            }

            $hijosTotal += $total;
            $hijosOk    += $ok;

            if ($faltantes === []) {
                $sinFaltantes++;
                unset($filas[$codi]);
                continue;
            }

            $filas[$codi]['total']      = $total;
            $filas[$codi]['ok']         = $ok;
            $filas[$codi]['parcial']    = $parcial;
            $filas[$codi]['sin']        = $ninguno;
            $filas[$codi]['verificar']  = $sinVerif;
            // Sí = todos los hijos menores tienen ese lado cargado; No = falta en al menos uno.
            $filas[$codi]['anverso_txt'] = $sinAnverso > 0 ? 'No' : 'Sí';
            $filas[$codi]['reverso_txt'] = $sinReverso > 0 ? 'No' : 'Sí';
            $filas[$codi]['detalle']    = implode(' · ', array_map(
                static fn (array $h): string => trim($h['nombre']) . ': ' . $h['estado'],
                $faltantes
            ));
        }

        $stats = [
            'generado'  => date('d/m/Y H:i:s'),
            'evaluados' => $evaluados,
            'ok'        => $sinFaltantes,
            'faltan'    => count($filas),
            'hijos'     => $hijosTotal,
            'hijos_ok'  => $hijosOk,
            'scan_at'   => $estado['scan_at'] ? $this->formatFechaHora($estado['scan_at']) : '—',
        ];

        if ($request->boolean('previsualizar')) {
            $muestra = array_map(static fn (array $fila, int $indice): array => [
                'numero' => $indice + 1,
                'codigo' => $fila['codi'],
                'personal' => $fila['nombre'],
                'dni' => $fila['doc'],
                'sucursal' => $fila['sucursal'],
                'tipo' => $fila['tipo'],
                'cargo' => $fila['cargo'],
                'ingreso_solmar' => $fila['ingreso_solmar'],
                'ingreso_planilla' => $fila['ingreso_planilla'],
                'asignacion_familiar' => $fila['asignacion_familiar'],
                'hijos_menores' => $fila['total'],
                'anverso' => $fila['anverso_txt'],
                'reverso' => $fila['reverso_txt'],
                'sin_imagen' => $fila['sin'],
                'detalle' => $fila['detalle'],
            ], array_values($filas), array_keys(array_values($filas)));

            return response()->json([
                'preview' => $muestra,
                'total' => count($filas),
            ]);
        }

        if ($filas === [] && $request->query('formato') !== 'pdf') {
            return response()->json([
                'success' => false,
                'message' => 'Sin faltantes: todos los hijos menores de edad cuentan con anverso y reverso del DNI cargados.',
            ]);
        }

        // ── 4. Excel ────────────────────────────────────────────────────
        $titulo = str_replace(
            'REPORTE DE PENDIENTES DE FOTOS / DNI',
            'PERSONAL CON ASIGNACIÓN FAMILIAR SIN SUSTENTO',
            $this->tituloExcelDocumentos($filtros, true)
        );

        if ($request->query('formato') === 'pdf') {
            return $this->descargarPdfReporte(
                'file_control.reportes_dj_pdf',
                $this->nombreArchivoExcel($titulo, 'pdf'),
                $titulo,
                array_values($filas),
                [
                    'N°' => 'numero', 'Código' => 'codi', 'Apellidos y nombres' => 'nombre',
                    'N° documento' => 'doc', 'Cargo' => 'cargo', 'Ingreso a Solmar' => 'ingreso_solmar',
                    'Ingreso a planillas' => 'ingreso_planilla', 'Asignación familiar' => 'asignacion_familiar',
                    'Hijos <18' => 'total', 'Anverso' => 'anverso_txt', 'Reverso' => 'reverso_txt',
                    'Sin imagen' => 'sin', 'Detalle de faltantes' => 'detalle',
                ],
                $stats
            );
        }
        $nombreArchivo = $this->nombreArchivoExcel($titulo);
        $rutaArchivo   = $this->generarExcelDniHijos(array_values($filas), $stats, $titulo);

        // Mismo mecanismo que documentosPendientes: se sirve desde
        // storage/app/public/reportes vía la ruta autenticada reporte.descargar.
        $publicDir = storage_path('app/public/reportes');
        if (! is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }
        $rutaPublica = $publicDir . DIRECTORY_SEPARATOR . $nombreArchivo;
        copy($rutaArchivo, $rutaPublica);
        @unlink($rutaArchivo);

        return response()->json([
            'url'    => route('reporte.descargar', ['archivo' => $nombreArchivo]),
            'nombre' => $nombreArchivo,
        ]);
    }

    /**
     * Personal con ASIG_FAMI = SI y exclusivamente hijos mayores de edad.
     * Excluye a cualquier persona con un hijo menor o sin fecha de nacimiento
     * verificable, para cumplir la condición de que TODOS sean mayores.
     */
    public function asignacionFamiliarHijosMayores(Request $request)
    {
        if (!in_array((int) session('tipo_rol'), self::ROLES_REPORTE_DOCUMENTOS, true)) {
            abort(403, 'Este reporte es exclusivo para ADMINS RRHH y SUPERUSUARIO.');
        }

        $filtros = $request->validate([
            'codSucursal' => 'nullable|string|max:20',
            'tipoPer' => 'nullable|in:01,02,03,05',
            'cargo' => 'nullable|string|max:20',
            'vigente' => 'nullable|in:SI,NO',
        ]);

        $condiciones = [
            "p.EMPR_CODIGO = '01'",
            "UPPER(LTRIM(RTRIM(ISNULL(p.ASIG_FAMI, '')))) IN ('SI', 'SÍ', '1')",
            "EXISTS (SELECT 1 FROM si_solm.dbo.DERECHO_HABIENTE dh WHERE LTRIM(RTRIM(dh.CODI_PERS)) = LTRIM(RTRIM(p.CODI_PERS)) AND dh.TIPO_RELA LIKE 'HIJ%' AND UPPER(LTRIM(RTRIM(ISNULL(dh.DEHA_VIGENCIA, 'SI')))) = 'SI')",
            "NOT EXISTS (SELECT 1 FROM si_solm.dbo.DERECHO_HABIENTE dm WHERE LTRIM(RTRIM(dm.CODI_PERS)) = LTRIM(RTRIM(p.CODI_PERS)) AND dm.TIPO_RELA LIKE 'HIJ%' AND UPPER(LTRIM(RTRIM(ISNULL(dm.DEHA_VIGENCIA, 'SI')))) = 'SI' AND (dm.FECH_NACI IS NULL OR dm.FECH_NACI > DATEADD(YEAR, -18, CAST(GETDATE() AS DATE))))",
        ];
        $parametros = [];
        foreach ([
            'codSucursal' => 'p.SUCU_CODIGO',
            'tipoPer' => 'p.PERS_TIPOTRAB',
            'cargo' => 'p.CODI_CARG',
            'vigente' => 'p.PERS_VIGENCIA',
        ] as $filtro => $columna) {
            if (!empty($filtros[$filtro])) {
                $condiciones[] = $columna . ' = ?';
                $parametros[] = $filtros[$filtro];
            }
        }

        $sql = <<<'SQL'
            SELECT LTRIM(RTRIM(p.CODI_PERS)) AS CODI_PERS,
                   p.APEL_1, p.APEL_2, p.NOMB_1, p.NOMB_2, p.NRO_DOCU_IDEN,
                   p.FECH_INGRE, p.FECH_INGRE_PLANILLA, p.ASIG_FAMI, p.PERS_VIGENCIA,
                   ISNULL(s.SUCU_ABREVIATURA, p.SUCU_CODIGO) AS SUCURSAL,
                   ISNULL(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS TIPO_DESCRIPCION,
                   ISNULL(c.DESC_CARGO, p.CODI_CARG) AS CARGO_DESC,
                   LTRIM(RTRIM(ISNULL(d.APEL_1, ''))) + ' ' + LTRIM(RTRIM(ISNULL(d.APEL_2, ''))) + ' ' +
                   LTRIM(RTRIM(ISNULL(d.NOMB_1, ''))) + ' ' + LTRIM(RTRIM(ISNULL(d.NOMB_2, ''))) AS HIJO_NOMBRE,
                   LTRIM(RTRIM(ISNULL(d.NRO_DOCU_IDEN, ''))) AS HIJO_DNI,
                   CONVERT(CHAR(10), d.FECH_NACI, 103) AS HIJO_FECHA_NACIMIENTO,
                   DATEDIFF(YEAR, d.FECH_NACI, GETDATE()) -
                       CASE WHEN DATEADD(YEAR, DATEDIFF(YEAR, d.FECH_NACI, GETDATE()), d.FECH_NACI) > CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END AS HIJO_EDAD
            FROM si_solm.dbo.PERSONAL p
            INNER JOIN si_solm.dbo.DERECHO_HABIENTE d
                    ON LTRIM(RTRIM(d.CODI_PERS)) = LTRIM(RTRIM(p.CODI_PERS))
                   AND d.TIPO_RELA LIKE 'HIJ%'
                   AND UPPER(LTRIM(RTRIM(ISNULL(d.DEHA_VIGENCIA, 'SI')))) = 'SI'
            LEFT JOIN si_solm.dbo.SISO_SUCURSAL s ON s.SUCU_CODIGO = p.SUCU_CODIGO
            LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp ON tp.TIPE_CODIGO = p.PERS_TIPOTRAB
            LEFT JOIN si_solm.dbo.CARGOS c ON c.CODI_CARG = p.CODI_CARG
            WHERE __CONDICIONES__
              AND d.FECH_NACI IS NOT NULL
              AND d.FECH_NACI <= DATEADD(YEAR, -18, CAST(GETDATE() AS DATE))
            ORDER BY p.APEL_1, p.APEL_2, p.NOMB_1, d.TIPO_RELA, d.APEL_1, d.APEL_2, d.NOMB_1
            SQL;
        $sql = str_replace('__CONDICIONES__', implode(' AND ', $condiciones), $sql);

        $registros = DB::select($sql, $parametros);
        $porPersonal = [];
        foreach ($registros as $r) {
            $codigo = trim((string) ($r->CODI_PERS ?? ''));
            if ($codigo === '') continue;
            if (!isset($porPersonal[$codigo])) {
                $porPersonal[$codigo] = [
                    'codi' => $codigo,
                    'nombre' => trim(implode(' ', array_filter([
                        trim((string) ($r->APEL_1 ?? '')), trim((string) ($r->APEL_2 ?? '')),
                        trim((string) ($r->NOMB_1 ?? '')), trim((string) ($r->NOMB_2 ?? '')),
                    ], static fn ($v) => $v !== ''))),
                    'doc' => trim((string) ($r->NRO_DOCU_IDEN ?? '')),
                    'sucursal' => trim((string) ($r->SUCURSAL ?? '')),
                    'tipo' => trim((string) ($r->TIPO_DESCRIPCION ?? '')),
                    'cargo' => trim((string) ($r->CARGO_DESC ?? '')),
                    'estado' => strtoupper(trim((string) ($r->PERS_VIGENCIA ?? ''))) === 'SI' ? 'Activo' : 'Cesado',
                    'ingreso_solmar' => $this->formatFecha($r->FECH_INGRE ?? null),
                    'ingreso_planilla' => $this->formatFecha($r->FECH_INGRE_PLANILLA ?? null),
                    'asignacion_familiar' => 'Sí',
                    'hijos' => [],
                ];
            }
            $nombreHijo = trim((string) ($r->HIJO_NOMBRE ?? ''));
            $porPersonal[$codigo]['hijos'][] = trim($nombreHijo
                . ' | DNI: ' . (trim((string) ($r->HIJO_DNI ?? '')) ?: 'Sin dato')
                . ' | Nac.: ' . (trim((string) ($r->HIJO_FECHA_NACIMIENTO ?? '')) ?: 'Sin dato')
                . ' | Edad: ' . (int) ($r->HIJO_EDAD ?? 0));
        }

        $filas = array_values(array_map(static function (array $fila): array {
            $fila['numero_hijos'] = count($fila['hijos']);
            $fila['detalle_hijos'] = implode("\n", $fila['hijos']);
            return $fila;
        }, $porPersonal));

        $titulo = str_replace(
            'REPORTE DE DOCUMENTOS',
            'ASIGNACIÓN FAMILIAR POR HIJOS MAYORES DE EDAD',
            $this->tituloExcelDocumentos($filtros, false)
        );
        $stats = [
            'generado' => date('d/m/Y H:i:s'),
            'personal' => count($filas),
            'hijos_mayores' => array_sum(array_column($filas, 'numero_hijos')),
        ];

        $columnas = [
            'N°' => 'numero', 'Código' => 'codi', 'Apellidos y nombres' => 'nombre', 'N° documento' => 'doc',
            'Cargo' => 'cargo', 'Estado' => 'estado',
            'Ingreso a Solmar' => 'ingreso_solmar', 'Ingreso a planillas' => 'ingreso_planilla',
            'N° hijos mayores' => 'numero_hijos',
            'Detalle de hijos mayores' => 'detalle_hijos',
        ];

        if ($request->boolean('previsualizar')) {
            $preview = array_map(static fn (array $fila, int $i): array => [
                'numero' => $i + 1, 'codigo' => $fila['codi'], 'personal' => $fila['nombre'],
                'dni' => $fila['doc'], 'sucursal' => $fila['sucursal'], 'tipo' => $fila['tipo'],
                'cargo' => $fila['cargo'], 'estado' => $fila['estado'],
                'ingreso_solmar' => $fila['ingreso_solmar'], 'ingreso_planilla' => $fila['ingreso_planilla'],
                'asignacion_familiar' => $fila['asignacion_familiar'], 'numero_hijos' => $fila['numero_hijos'],
                'detalle_hijos' => $fila['detalle_hijos'],
            ], $filas, array_keys($filas));
            return response()->json(['preview' => $preview, 'total' => count($preview)]);
        }

        if ($request->query('formato') === 'pdf') {
            return $this->descargarPdfReporte('file_control.reportes_dj_pdf', $this->nombreArchivoExcel($titulo, 'pdf'), $titulo, $filas, $columnas, $stats);
        }

        $nombreArchivo = $this->nombreArchivoExcel($titulo);
        $rutaArchivo = $this->generarExcelHijosMayoresAsignacion($filas, $stats, $titulo, $columnas);
        $publicDir = storage_path('app/public/reportes');
        if (!is_dir($publicDir)) @mkdir($publicDir, 0755, true);
        copy($rutaArchivo, $publicDir . DIRECTORY_SEPARATOR . $nombreArchivo);
        @unlink($rutaArchivo);
        return response()->json(['url' => route('reporte.descargar', ['archivo' => $nombreArchivo]), 'nombre' => $nombreArchivo]);
    }

    /** Reporte de hijos registrados en DJ que cumplen 18 dentro de los próximos 30 días. */
    public function asignacionFamiliarProximaVencer(Request $request)
    {
        if (!in_array((int) session('tipo_rol'), self::ROLES_REPORTE_DOCUMENTOS, true)) {
            abort(403, 'Este reporte es exclusivo para ADMINS RRHH y SUPERUSUARIO.');
        }

        $filtros = $request->validate([
            'codSucursal' => 'nullable|string|max:20',
            'tipoPer' => 'nullable|in:01,02,03,05',
            'cargo' => 'nullable|string|max:20',
            'vigente' => 'nullable|in:SI,NO',
        ]);

        $filas = [];
        $personasEvaluadas = [];
        foreach (NotificacionModel::hijosPorCumplirMayoriaEdad($filtros, false) as $r) {
            $personasEvaluadas[trim((string) $r->codigo_personal)] = true;
            $filas[] = [
                'sucursal' => trim((string) $r->sucursal),
                'personal' => trim((string) $r->personal),
                'dni' => trim((string) ($r->dni ?? '')),
                'hijo' => trim((string) $r->hijo),
                'fecha_nacimiento' => $r->fecha_nacimiento,
                'fecha_mayoria' => $r->fecha_mayoria,
                'dias_restantes' => (int) $r->dias_restantes,
                'aviso' => (int) $r->dias_aviso === 15
                    ? 'Recordatorio (15 días o menos)'
                    : 'Aviso inicial (hasta 30 días)',
            ];
        }

        $columnas = [
            'N°' => 'numero', 'Sucursal' => 'sucursal', 'Personal' => 'personal',
            'DNI del personal' => 'dni', 'Hijo(a)' => 'hijo',
            'Fecha de nacimiento' => 'fecha_nacimiento', 'Cumple 18 años' => 'fecha_mayoria',
            'Días restantes' => 'dias_restantes', 'Aviso' => 'aviso',
        ];

        if ($request->boolean('previsualizar')) {
            $preview = array_map(static fn (array $fila, int $i): array => ['numero' => $i + 1] + $fila, $filas, array_keys($filas));
            return response()->json(['preview' => $preview, 'total' => count($preview)]);
        }

        $titulo = str_replace(
            'REPORTE DE DOCUMENTOS',
            'REPORTE DE ASIGNACIÓN FAMILIAR PRÓXIMA A VENCER',
            $this->tituloExcelDocumentos($filtros, false)
        );
        $stats = [
            'generado' => date('d/m/Y H:i:s'),
            'personal' => count($personasEvaluadas),
            'mayoria_edad' => count($filas),
        ];

        if ($request->query('formato') === 'pdf') {
            return $this->descargarPdfReporte(
                'file_control.reportes_dj_pdf',
                $this->nombreArchivoExcel($titulo, 'pdf'),
                $titulo,
                $filas,
                $columnas,
                $stats
            );
        }

        $nombreArchivo = $this->nombreArchivoExcel($titulo);
        $rutaArchivo = $this->generarExcelHijosMayoresAsignacion($filas, $stats, $titulo, $columnas);
        $publicDir = storage_path('app/public/reportes');
        if (!is_dir($publicDir)) @mkdir($publicDir, 0755, true);
        copy($rutaArchivo, $publicDir . DIRECTORY_SEPARATOR . $nombreArchivo);
        @unlink($rutaArchivo);

        return response()->json([
            'url' => route('reporte.descargar', ['archivo' => $nombreArchivo]),
            'nombre' => $nombreArchivo,
        ]);
    }

    /** Personal sin DJ escaneada ni registrada en las ubicaciones de carga conocidas. */
    public function personalSinDjEscaneada(Request $request)
    {
        if (!in_array((int) session('tipo_rol'), self::ROLES_REPORTE_DOCUMENTOS, true)) {
            abort(403, 'Este reporte es exclusivo para ADMINS RRHH y SUPERUSUARIO.');
        }

        $filtros = $request->validate([
            'codSucursal' => 'nullable|string|max:20',
            'tipoPer' => 'nullable|in:01,02,03,05',
            'cargo' => 'nullable|string|max:20',
            'vigente' => 'nullable|in:SI,NO',
        ]);

        $condiciones = ["p.EMPR_CODIGO = '01'"];
        $parametros = [];
        foreach ([
            'codSucursal' => 'p.SUCU_CODIGO',
            'tipoPer' => 'p.PERS_TIPOTRAB',
            'cargo' => 'p.CODI_CARG',
            'vigente' => 'p.PERS_VIGENCIA',
        ] as $filtro => $columna) {
            if (!empty($filtros[$filtro])) {
                $condiciones[] = $columna . ' = ?';
                $parametros[] = $filtros[$filtro];
            }
        }

        $sql = <<<'SQL'
            SELECT LTRIM(RTRIM(p.CODI_PERS)) AS CODI_PERS,
                   p.APEL_1, p.APEL_2, p.NOMB_1, p.NOMB_2, p.NRO_DOCU_IDEN,
                   p.PERS_VIGENCIA, p.FECH_INGRE, p.FECH_INGRE_PLANILLA,
                   p.USUA_CODIGO_REG, p.USUA_FECHA_REG,
                   ISNULL(s.SUCU_ABREVIATURA, p.SUCU_CODIGO) AS SUCURSAL,
                   ISNULL(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS TIPO_DESCRIPCION,
                   ISNULL(c.DESC_CARGO, p.CODI_CARG) AS CARGO_DESC,
                   reg.NOMBRE_USUARIO AS REG_NOMBRE
            FROM si_solm.dbo.PERSONAL p
            LEFT JOIN si_solm.dbo.SISO_SUCURSAL s ON s.SUCU_CODIGO = p.SUCU_CODIGO
            LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp ON tp.TIPE_CODIGO = p.PERS_TIPOTRAB
            LEFT JOIN si_solm.dbo.CARGOS c ON c.CODI_CARG = p.CODI_CARG
            OUTER APPLY (
                SELECT TOP 1
                       LTRIM(RTRIM(ISNULL(u.NOMB_1, ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.NOMB_2, ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.APEL_1, ''))) + ' ' +
                       LTRIM(RTRIM(ISNULL(u.APEL_2, ''))) AS NOMBRE_USUARIO
                FROM si_solm.dbo.USUARIOS u
                WHERE u.LOGIN = LTRIM(RTRIM(p.USUA_CODIGO_REG))
            ) reg
            WHERE __CONDICIONES__
            ORDER BY p.APEL_1, p.APEL_2, p.NOMB_1
            SQL;
        $personal = DB::select(str_replace('__CONDICIONES__', implode(' AND ', $condiciones), $sql), $parametros);

        // El folio 25 registra las cargas de DJ. También se consideran PDFs
        // que aparezcan en las carpetas aunque no tengan registro asociado.
        $registrosDj = DB::table('sw_folios_detalles')
            ->where('codFolio', 25)
            ->get(['codPersonal']);
        $codigosRegistrados = [];
        foreach ($registrosDj as $registro) {
            $codigo = trim((string) ($registro->codPersonal ?? ''));
            if ($codigo === '') continue;
            $codigosRegistrados[$codigo] = true;
        }

        // Enumerar cada carpeta una sola vez evita comprobar cientos/miles de
        // archivos de forma serial contra la carpeta compartida de red.
        $codigosConArchivo = [];
        $directoriosDj = [
            storage_path('app/dj/2026'),
            '\\\\192.168.10.2\\Biblioteca_Grafica\\DOCUMENTOS_PERS\\DJ_2026',
            '\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dj\\2026',
        ];
        foreach ($directoriosDj as $directorio) {
            $archivos = @scandir($directorio);
            if ($archivos === false) continue;
            foreach ($archivos as $archivo) {
                if (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) !== 'pdf') continue;
                $codigosConArchivo[pathinfo($archivo, PATHINFO_FILENAME)] = true;
            }
        }

        $filas = [];
        foreach ($personal as $r) {
            $codigo = trim((string) ($r->CODI_PERS ?? ''));
            if ($codigo === '' || isset($codigosRegistrados[$codigo]) || isset($codigosConArchivo[$codigo])) continue;

            $filas[] = [
                'codi' => $codigo,
                'nombre' => trim(implode(' ', array_filter([
                    trim((string) ($r->APEL_1 ?? '')), trim((string) ($r->APEL_2 ?? '')),
                    trim((string) ($r->NOMB_1 ?? '')), trim((string) ($r->NOMB_2 ?? '')),
                ], static fn ($v) => $v !== ''))),
                'doc' => trim((string) ($r->NRO_DOCU_IDEN ?? '')),
                'sucursal' => trim((string) ($r->SUCURSAL ?? '')),
                'tipo' => trim((string) ($r->TIPO_DESCRIPCION ?? '')),
                'cargo' => trim((string) ($r->CARGO_DESC ?? '')),
                'estado' => strtoupper(trim((string) ($r->PERS_VIGENCIA ?? ''))) === 'SI' ? 'Activo' : 'Cesado',
                'ingreso_solmar' => $this->formatFecha($r->FECH_INGRE ?? null),
                'ingreso_planilla' => $this->formatFecha($r->FECH_INGRE_PLANILLA ?? null),
                'dj' => 'No encontrada',
                'usuario_registro' => $this->usuarioLegible($r->USUA_CODIGO_REG ?? null, $r->REG_NOMBRE ?? null),
                'fecha_registro' => $this->formatFechaHora($r->USUA_FECHA_REG ?? null),
            ];
        }

        $titulo = str_replace('REPORTE DE DOCUMENTOS', 'REPORTE DE PERSONAL SIN DJ ESCANEADA', $this->tituloExcelDocumentos($filtros, false));
        $columnas = [
            'N°' => 'numero', 'Código' => 'codi', 'Apellidos y nombres' => 'nombre', 'N° documento' => 'doc',
            'Cargo' => 'cargo',
            'Ingreso a Solmar' => 'ingreso_solmar', 'Ingreso a planillas' => 'ingreso_planilla', 'DJ escaneada' => 'dj',
            'Usuario de registro' => 'usuario_registro', 'Fecha de registro' => 'fecha_registro',
        ];
        $stats = ['generado' => date('d/m/Y H:i:s'), 'personal' => count($personal), 'sin_dj' => count($filas)];

        if ($request->boolean('previsualizar')) {
            $preview = array_map(static fn (array $fila, int $i): array => [
                'numero' => $i + 1, 'codigo' => $fila['codi'], 'personal' => $fila['nombre'], 'dni' => $fila['doc'],
                'sucursal' => $fila['sucursal'], 'tipo' => $fila['tipo'], 'cargo' => $fila['cargo'], 'estado' => $fila['estado'],
                'ingreso_solmar' => $fila['ingreso_solmar'], 'ingreso_planilla' => $fila['ingreso_planilla'], 'dj' => $fila['dj'],
                'usuario_registro' => $fila['usuario_registro'], 'fecha_registro' => $fila['fecha_registro'],
            ], $filas, array_keys($filas));
            return response()->json(['preview' => $preview, 'total' => count($preview)]);
        }

        if ($request->query('formato') === 'pdf') {
            return $this->descargarPdfReporte('file_control.reportes_dj_pdf', $this->nombreArchivoExcel($titulo, 'pdf'), $titulo, $filas, $columnas, $stats);
        }

        $nombreArchivo = $this->nombreArchivoExcel($titulo);
        $rutaArchivo = $this->generarExcelHijosMayoresAsignacion($filas, $stats, $titulo, $columnas);
        $publicDir = storage_path('app/public/reportes');
        if (!is_dir($publicDir)) @mkdir($publicDir, 0755, true);
        copy($rutaArchivo, $publicDir . DIRECTORY_SEPARATOR . $nombreArchivo);
        @unlink($rutaArchivo);
        return response()->json(['url' => route('reporte.descargar', ['archivo' => $nombreArchivo]), 'nombre' => $nombreArchivo]);
    }

    private function generarExcelHijosMayoresAsignacion(array $filas, array $stats, string $titulo, array $columnas): string
    {
        $ss = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle(isset($stats['sin_dj']) ? 'Sin DJ escaneada' : (isset($stats['mayoria_edad']) ? 'Mayoria de edad' : 'Hijos mayores'));
        $hoja->setShowGridlines(false);
        $totalColumnas = count($columnas);
        $ultima = Coordinate::stringFromColumnIndex($totalColumnas);

        $anchosPorColumna = [
            'numero' => 6, 'codi' => 16, 'nombre' => 34, 'doc' => 15,
            'sucursal' => 16, 'tipo' => 20, 'cargo' => 26, 'estado' => 13,
            'ingreso_solmar' => 20, 'ingreso_planilla' => 23, 'asignacion_familiar' => 20,
            'numero_hijos' => 16, 'detalle_hijos' => 78, 'dj' => 16,
            'usuario_registro' => 40, 'fecha_registro' => 20,
            'personal' => 48, 'dni' => 16, 'hijo' => 34, 'fecha_nacimiento' => 20,
            'fecha_mayoria' => 20, 'dias_restantes' => 16, 'aviso' => 30,
        ];
        foreach (array_values($columnas) as $i => $clave) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))
                ->setWidth($anchosPorColumna[$clave] ?? 16);
        }
        $rutaLogo = public_path('images/logo_sol.png');
        if (is_file($rutaLogo)) {
            $logo = new Drawing();
            $logo->setPath($rutaLogo);
            $logo->setResizeProportional(false);
            $logo->setCoordinates('A1');
            $logo->setWidth(130);
            $logo->setHeight(62);
            $logo->setWorksheet($hoja);
        }

        $hoja->getRowDimension(1)->setRowHeight(64);
        $hoja->mergeCells('C1:' . $ultima . '1');
        $hoja->setCellValue('C1', $titulo);
        $hoja->getStyle('C1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('990000');
        $hoja->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $hoja->mergeCells('C2:' . $ultima . '2');
        $hoja->getRowDimension(4)->setRowHeight(6);

        $bloquesStats = isset($stats['mayoria_edad'])
            ? ['A' => 'C', 'D' => 'F', 'G' => $ultima]
            : (isset($stats['sin_dj'])
                ? ['A' => 'B', 'C' => 'F', 'G' => $ultima]
                : ['A' => 'B', 'C' => 'H', 'I' => $ultima]);
        $etiquetasStats = isset($stats['mayoria_edad'])
            ? ['Generado', 'Personal', 'Hijos por cumplir 18']
            : (isset($stats['sin_dj'])
                ? ['Generado', 'Personal evaluado', 'Sin DJ escaneada']
                : ['Generado', 'Personal evaluado', 'Hijos mayores de edad']);
        $valoresStats = [
            $stats['generado'],
            number_format($stats['personal'], 0, ',', '.'),
            number_format($stats['sin_dj'] ?? $stats['mayoria_edad'] ?? $stats['hijos_mayores'], 0, ',', '.'),
        ];
        $indiceStats = 0;
        foreach ($bloquesStats as $desde => $hasta) {
            $hoja->mergeCells($desde . '5:' . $hasta . '5');
            $hoja->mergeCells($desde . '6:' . $hasta . '6');
            $hoja->setCellValue($desde . '5', $etiquetasStats[$indiceStats]);
            $hoja->setCellValue($desde . '6', $valoresStats[$indiceStats]);
            $indiceStats++;
        }
        $hoja->getStyle('A5:' . $ultima . '6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $hoja->getStyle('A5:' . $ultima . '5')->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('374151');
        $hoja->getStyle('A6:' . $ultima . '6')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('111827');
        $hoja->getStyle('A5:' . $ultima . '6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        $hoja->getStyle('A5:' . $ultima . '6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(7)->setRowHeight(6);

        $filaCabecera = 8;
        foreach (array_keys($columnas) as $i => $etiqueta) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $filaCabecera, $etiqueta);
        }
        $matriz = [];
        foreach ($filas as $i => $fila) {
            $fila['numero'] = $i + 1;
            $matriz[] = array_map(static fn (string $clave) => $fila[$clave] ?? '', array_values($columnas));
        }
        $filaInicial = $filaCabecera + 1;
        $filaFinal = $filaCabecera + count($matriz);
        if ($matriz !== []) {
            $hoja->fromArray($matriz, null, 'A' . $filaInicial, true);
            $base = $hoja->getStyle('A' . $filaInicial . ':' . $ultima . $filaFinal);
            $base->getFont()->setSize(9);
            $base->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $base->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            foreach (array_filter(['A', 'D', 'H', 'I', 'J', 'K', 'L'], static fn (string $col): bool => Coordinate::columnIndexFromString($col) <= $totalColumnas) as $col) {
                $hoja->getStyle($col . $filaInicial . ':' . $col . $filaFinal)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if (isset($stats['sin_dj'])) {
                $hoja->getStyle($ultima . $filaInicial . ':' . $ultima . $filaFinal)->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
            } else {
                $hoja->getStyle($ultima . $filaInicial . ':' . $ultima . $filaFinal)
                    ->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            }
            $hoja->getStyle('A' . $filaInicial . ':' . $ultima . $filaFinal)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(isset($stats['sin_dj']) ? 'FEE2E2' : 'FEF3C7');
        }
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4B5563');
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('4B5563');
        $hoja->getRowDimension($filaCabecera)->setRowHeight(30);
        if ($matriz !== []) {
            $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaFinal)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        }
        $hoja->setAutoFilter('A' . $filaCabecera . ':' . $ultima . $filaCabecera);
        $hoja->freezePane('A' . ($filaCabecera + 1));

        $dir = sys_get_temp_dir();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $prefijo = isset($stats['sin_dj']) ? 'rep_sin_dj_' : (isset($stats['mayoria_edad']) ? 'rep_mayoria_edad_' : 'rep_hijos_mayores_');
        $ruta = $dir . DIRECTORY_SEPARATOR . $prefijo . date('Ymd_His') . '_' . uniqid() . '.xlsx';
        (new Xlsx($ss))->save($ruta);
        $ss->disconnectWorksheets();
        unset($ss);
        return $ruta;
    }

    // ── Escaneo del servidor de archivos ───────────────────────

    /**
     * HEAD simultáneos contra el servidor de imágenes. 50 rinde ~330 req/s
     * (el escaneo secuencial hacía ~64): si el servidor empezara a responder
     * 429 por la carga, este es el número a bajar.
     */
    private const SCAN_CONCURRENCIA = 50;

    /**
     * Minutos que sobrevive en caché el avance de un escaneo interrumpido.
     * Es lo único que se guarda: los reportes nunca responden con caché.
     */
    private const SCAN_AVANCE_MIN = 10;

    /**
     * Verifica URLs en lotes paralelos. Si el servidor remoto no responde,
     * los archivos quedan como desconocidos y se detiene el escaneo tras tres
     * lotes fallidos; nunca se reintentan en serie, lo que bloquearía PHP.
     *
     * 200 = existe · 404 = no existe · cualquier otra cosa (timeout, 429,
     * 5xx) = desconocido: un fallo NUNCA se reporta como "faltante".
     */
    private function verificarUrlsEnLotes(array $urls, int $concurrencia = self::SCAN_CONCURRENCIA, ?bool &$servidorNoDisponible = null): array
    {
        $urls      = array_values(array_unique($urls));
        $salida    = [];
        $limitadas = 0;
        $sinLote   = 0;
        $servidorNoDisponible = false;

        foreach (array_chunk($urls, $concurrencia) as $lote) {
            try {
                $respuestas = Http::pool(fn ($pool) => array_map(
                    fn (string $url) => $pool->as($url)->timeout(5)->connectTimeout(2)->head($url),
                    $lote
                ));
            } catch (\Throwable $e) {
                // Una caída de red rechaza la promesa y tira el lote entero.
                $sinLote++;
                foreach ($lote as $url) {
                    $salida[$url] = 'desconocido';
                }

                // Si el servidor dejó de responder, no se sigue ocupando PHP
                // con peticiones en serie ni se le sigue martillando.
                if ($sinLote >= 2) {
                    $servidorNoDisponible = true;
                    Log::error('reportes: el servidor de imágenes dejó de responder; se interrumpe el escaneo.', [
                        'error'    => $e->getMessage(),
                        'quedaron' => count($urls) - count($salida),
                    ]);
                    break;
                }
                continue;
            }

            $sinVerificarLote = 0;

            foreach ($respuestas as $url => $respuesta) {
                $codigo = $respuesta instanceof \Illuminate\Http\Client\Response ? $respuesta->status() : 0;

                if ($codigo === 200) {
                    $salida[$url] = 'si';
                } elseif ($codigo === 404) {
                    $salida[$url] = 'no';
                } else {
                    $salida[$url] = 'desconocido';
                    $sinVerificarLote++;
                    if ($codigo === 429) {
                        $limitadas++;
                    }
                }
            }

            // Incluye respuestas de timeout, 429 y errores 5xx: si casi todo
            // el lote es inconcluso, cuenta como una caída del servidor remoto.
            if ($sinVerificarLote >= max(1, (int) ceil(count($lote) * 0.8))) {
                $sinLote++;
                if ($sinLote >= 2) {
                    $servidorNoDisponible = true;
                    Log::error('reportes: el servidor de imágenes no dio respuestas concluyentes; se interrumpe el escaneo.', [
                        'lote_inconcluso' => $sinVerificarLote,
                        'quedaron' => count($urls) - count($salida),
                    ]);
                    break;
                }
            } else {
                $sinLote = 0;
            }
        }

        if ($limitadas > 0) {
            Log::warning('reportes: el servidor de imágenes respondió 429 (demasiadas peticiones); esas imágenes quedaron como "Sin verificar".', [
                'cantidad' => $limitadas,
            ]);
        }

        // Lo que no llegó a verificarse (escaneo interrumpido) queda sin verificar.
        foreach ($urls as $url) {
            if (! isset($salida[$url])) {
                $salida[$url] = 'desconocido';
            }
        }

        return $salida;
    }

    /**
     * Devuelve ['datos' => [CODI => ['hijos' => [...]]], 'scan_at' => fecha]
     *
     * El escaneo se hace SIEMPRE de nuevo (los reportes tienen que reflejar al
     * instante un DNI recién subido); la caché solo conserva el avance de una
     * petición interrumpida (10 min) y se borra cuando el escaneo termina.
     */
    private function estadoDniHijos(array $codigos, bool $forzar, ?string $scanId = null, bool $incremental = false): array
    {
        $cacheKey = $scanId
            ? self::HIJOS_CACHE_KEY . ':' . hash('sha256', session()->getId() . '|' . $scanId)
            : self::HIJOS_CACHE_KEY;
        if ($forzar) {
            Cache::forget($cacheKey);
        }

        // Avance de un escaneo anterior que no llegó a terminar (si existe).
        $estado = Cache::get($cacheKey) ?? ['datos' => [], 'scan_at' => null];
        $estado['datos'] = array_intersect_key($estado['datos'], array_flip($codigos));

        $pendientes = array_values(array_diff($codigos, array_keys($estado['datos'])));
        $bloques = array_chunk($pendientes, self::HIJOS_LOTE);
        if ($incremental) {
            $bloques = array_slice($bloques, 0, 1);
        }

        foreach ($bloques as $bloque) {
            $planes = [];
            $urls   = [];

            foreach ($bloque as $codi) {
                $plan = $this->planificarDniHijos($codi);
                $planes[$codi] = $plan;

                foreach ($plan as $hijo) {
                    $urls[$hijo['anverso']] = true;
                    $urls[$hijo['reverso']] = true;
                }
            }

            // Si ya falló el servidor remoto en un lote anterior, no se vuelve
            // a esperar timeouts por cada bloque restante de personal.
            $servidorNoDisponible = (bool) ($estado['servidor_no_disponible'] ?? false);
            if ($servidorNoDisponible) {
                $verificados = array_fill_keys(array_keys($urls), 'desconocido');
            } else {
                $verificados = $this->verificarUrlsEnLotes(array_keys($urls), self::SCAN_CONCURRENCIA, $servidorNoDisponible);
            }
            $estado['servidor_no_disponible'] = $servidorNoDisponible;

            foreach ($planes as $codi => $plan) {
                $estado['datos'][$codi] = $this->evaluarDniHijos($plan, $verificados);
            }

            // Avance: si la petición se corta aquí, la siguiente continúa.
            Cache::put($cacheKey, $estado, now()->addMinutes(self::SCAN_AVANCE_MIN));
        }

        $completo = count($estado['datos']) >= count($codigos);
        if ($completo) {
            $estado['scan_at'] = now()->toDateTimeString();
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $estado, now()->addMinutes(self::SCAN_AVANCE_MIN));
        }

        $estado['completo'] = $completo;
        $estado['procesados'] = count($estado['datos']);
        $estado['total'] = count($codigos);
        return $estado;
    }

    /**
     * Arma el escaneo de un trabajador: sus hijos MENORES DE EDAD con las URLs
     * del anverso y del reverso, sin hacer todavía ninguna petición.
     *
     * El índice del hijo es su posición entre TODOS los hijos (igual que el
     * formulario), por eso se lee la lista completa antes de filtrar menores.
     */
    private function planificarDniHijos(string $codi): array
    {
        $plan = [];

        foreach ($this->hijosOrdenados($codi) as $i => $hijo) {
            if (! $this->esHijoMenor($hijo)) {
                continue;
            }

            $nro    = $i * 2 + 1;
            $nombre = trim((string) ($hijo->NOMBRES ?? ''));

            $plan[] = [
                'nombre'  => $nombre !== '' ? $nombre : ('Hijo ' . ($i + 1)),
                'dni'     => trim((string) ($hijo->NRO_DOCU_IDEN ?? '')),
                'anverso' => self::DOC_BASE . '/DNI1_HIJOS/' . rawurlencode($codi . '_H' . $nro) . '.jpg',
                'reverso' => self::DOC_BASE . '/DNI2_HIJOS/' . rawurlencode($codi . '_H' . ($nro + 1)) . '.jpg',
            ];
        }

        return $plan;
    }

    /**
     * Traduce el plan de un trabajador a su estado final según las URLs ya
     * verificadas (devueltas por verificarUrlsEnLotes()).
     */
    private function evaluarDniHijos(array $plan, array $verificados): array
    {
        $resultado = [];

        foreach ($plan as $hijo) {
            $anverso = $verificados[$hijo['anverso']] ?? 'desconocido';
            $reverso = $verificados[$hijo['reverso']] ?? 'desconocido';
            $estado  = $this->estadoDni($anverso, $reverso);

            $resultado[] = [
                'nombre'   => $hijo['nombre'],
                'dni'      => $hijo['dni'],
                'estado'   => $estado,
                'faltante' => in_array($estado, ['Solo anverso', 'Solo reverso', 'No'], true),
            ];
        }

        return ['hijos' => $resultado];
    }

    /**
     * Lista TODOS los hijos del trabajador en el mismo orden que usa la app para
     * numerar las imágenes (ORDER BY TIPO_RELA, ver DjController::getPersonalData).
     */
    private function hijosOrdenados(string $codi): array
    {
        try {
            return DB::select(
                "SELECT
                    ISNULL(APEL_1, '') + ' ' + ISNULL(APEL_2, '') + ' ' +
                    ISNULL(NOMB_1, '') + ' ' + ISNULL(NOMB_2, '') AS NOMBRES,
                    LTRIM(RTRIM(ISNULL(NRO_DOCU_IDEN, '')))       AS NRO_DOCU_IDEN,
                    CONVERT(CHAR(10), FECH_NACI, 120)             AS FECH_NACI,
                    ISNULL(DEHA_VIGENCIA, 'SI')                   AS DEHA_VIGENCIA,
                    ISNULL(FALLECIDO, 0)                          AS FALLECIDO
                 FROM si_solm.dbo.DERECHO_HABIENTE
                 WHERE LTRIM(RTRIM(CODI_PERS)) = ?
                   AND TIPO_RELA LIKE 'HIJ%'
                 ORDER BY TIPO_RELA",
                [$codi]
            );
        } catch (\Throwable $e) {
            Log::error('dniHijosPendientes: no se pudieron leer los hijos de ' . $codi, [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Mismo criterio que el formulario (gestion_dj.js::esMenorDeEdadDj):
     * hijo vigente, no fallecido y con menos de 18 años.
     */
    private function esHijoMenor(object $hijo): bool
    {
        if (strtoupper(trim((string) ($hijo->DEHA_VIGENCIA ?? 'SI'))) !== 'SI') {
            return false;
        }
        if ((int) ($hijo->FALLECIDO ?? 0) !== 0) {
            return false;
        }

        $fecha = trim((string) ($hijo->FECH_NACI ?? ''));
        if ($fecha === '') {
            return false;
        }

        try {
            $nac = \DateTime::createFromFormat('Y-m-d', substr($fecha, 0, 10));
            if ($nac === false) {
                $nac = new \DateTime($fecha);
            }

            return $nac->diff(new \DateTime())->y < 18;
        } catch (\Throwable) {
            return false;
        }
    }

    // v2: ver comentario de HIJOS_CACHE_KEY (antes guardaba el escaneo 6 h).
    private const DOC_CACHE_KEY = 'reporte_documentos_estado_v2';
    private const DOC_LOTE      = 20;
    private const DOC_BASE      = 'http://190.116.178.163/Biblioteca_Grafica';

    /**
     * Devuelve ['datos' => [CODI => ['foto'|'anverso'|'reverso' => si|no|desconocido]],
     *           'scan_at' => fecha]
     *
     * El escaneo se hace SIEMPRE completo en cada generación (una foto subida
     * recién tiene que salir en el reporte del momento). La caché solo sirve
     * para retomar un escaneo que se cortó a mitad de camino y se borra apenas
     * el escaneo termina.
     */
    private function estadoDocumentos(array $codigos, bool $forzar, ?string $scanId = null, bool $incremental = false): array
    {
        $cacheKey = $scanId
            ? self::DOC_CACHE_KEY . ':' . hash('sha256', session()->getId() . '|' . $scanId)
            : self::DOC_CACHE_KEY;
        if ($forzar) {
            Cache::forget($cacheKey);
        }

        // Avance de un escaneo anterior que no llegó a terminar (si existe).
        $estado = Cache::get($cacheKey) ?? ['datos' => [], 'scan_at' => null];
        $estado['datos'] = array_intersect_key($estado['datos'], array_flip($codigos));

        $pendientes = array_values(array_diff($codigos, array_keys($estado['datos'])));
        $bloques = array_chunk($pendientes, self::DOC_LOTE);
        if ($incremental) {
            $bloques = array_slice($bloques, 0, 1);
        }

        foreach ($bloques as $bloque) {
            $urls = [];

            foreach ($bloque as $codi) {
                foreach (['Fotos' => 'foto', 'DNI1_1' => 'anverso', 'DNI2_1' => 'reverso'] as $carpeta => $clave) {
                    $urls[self::DOC_BASE . '/' . $carpeta . '/' . rawurlencode($codi) . '.jpg'] = [$codi, $clave];
                }
            }

            $servidorNoDisponible = (bool) ($estado['servidor_no_disponible'] ?? false);
            if ($servidorNoDisponible) {
                $verificados = array_fill_keys(array_keys($urls), 'desconocido');
            } else {
                $verificados = $this->verificarUrlsEnLotes(array_keys($urls), self::SCAN_CONCURRENCIA, $servidorNoDisponible);
            }
            $estado['servidor_no_disponible'] = $servidorNoDisponible;

            foreach ($urls as $url => [$codi, $clave]) {
                $estado['datos'][$codi][$clave] = $verificados[$url] ?? 'desconocido';
            }

            // Avance: si la petición se corta aquí, la siguiente continúa.
            Cache::put($cacheKey, $estado, now()->addMinutes(self::SCAN_AVANCE_MIN));
        }

        $completo = count($estado['datos']) >= count($codigos);
        if ($completo) {
            $estado['scan_at'] = now()->toDateTimeString();
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $estado, now()->addMinutes(self::SCAN_AVANCE_MIN));
        }

        $estado['completo'] = $completo;
        $estado['procesados'] = count($estado['datos']);
        $estado['total'] = count($codigos);
        return $estado;
    }

    private function estadoFoto(string $valor): string
    {
        return match ($valor) {
            'si'          => 'Sí',
            'no'          => 'No',
            default       => 'Sin verificar',
        };
    }

    private function estadoDni(string $anverso, string $reverso): string
    {
        if ($anverso === 'si' && $reverso === 'si') return 'Completo';
        if ($anverso === 'si' && $reverso === 'no')  return 'Solo anverso';
        if ($anverso === 'no' && $reverso === 'si')  return 'Solo reverso';
        if ($anverso === 'no' && $reverso === 'no')  return 'No';

        return 'Sin verificar';
    }

    // ── Excel ─────────────────────────────────────────────────

    /**
     * Devuelve la ruta temporal del .xlsx generado.
     */
    private function generarExcelDocumentos(array $filas, array $stats, string $titulo): string
    {
        $columnas = [
            'N°', 'Código', 'Apellidos y Nombres', 'N° Documento', 'Cargo',
            'Fecha de ingreso a Solmar', 'Fecha de ingreso a planillas',
            'Foto', 'DNI', 'Registrado por', 'F. Registro',
            'Modificado por', 'F. Modificación',
        ];
        $ultima = 'M';

        $ss   = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle('Documentos');
        $hoja->setShowGridlines(false);

        // ── Anchos de columna ─────────────────────────────────
        // B = 16 deja A+B en ~164 px, por encima de los 130 px del logo
        // anclado en A1: así el logo no invade el título de C1.
        $anchos = [6, 16, 34, 15, 26, 20, 23, 9, 15, 26, 17, 26, 17];
        foreach ($anchos as $i => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($ancho);
        }

        // ── Logo ──────────────────────────────────────────────
        // logo_sol.png mide 500x238 (relación 2,10): se fija el tamaño
        // exacto sin redimensionado proporcional para no distorsionarlo.
        $rutaLogo = public_path('images/logo_sol.png');
        if (is_file($rutaLogo)) {
            $logo = new Drawing();
            $logo->setPath($rutaLogo);
            $logo->setResizeProportional(false);
            $logo->setCoordinates('A1');
            $logo->setWidth(130);
            $logo->setHeight(62);
            $logo->setWorksheet($hoja);
        }

        // ── Encabezado ────────────────────────────────────────
        $hoja->getRowDimension(1)->setRowHeight(64);
        $hoja->mergeCells('C1:' . $ultima . '1');
        $hoja->setCellValue('C1', $titulo);
        $hoja->getStyle('C1')->getFont()->setBold(true)->setSize(12)
              ->getColor()->setRGB('990000');
        $hoja->getStyle('C1')->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_LEFT)
              ->setVertical(Alignment::VERTICAL_CENTER)
              ->setWrapText(true);

        $hoja->mergeCells('C2:' . $ultima . '2');

        $hoja->getRowDimension(4)->setRowHeight(6);

        // ── Bloque de estadísticas ────────────────────────────
        $bloquesStats = [
            'A' => 'B',  // Generado
            'C' => 'D',  // Personal evaluado
            'E' => 'F',  // Foto OK
            'G' => 'H',  // DNI completo
            'I' => 'M',  // Incompletos
        ];
        $etiquetas = ['Generado', 'Personal evaluado', 'Foto OK', 'DNI completo', 'Incompletos'];

        $total = max($stats['personal'], 1);
        $valores = [
            $stats['generado'],
            number_format($stats['personal'], 0, ',', '.'),
            number_format($stats['foto_ok'], 0, ',', '.') . '  (' . number_format($stats['foto_ok'] / $total * 100, 1, ',', '.') . '%)',
            number_format($stats['dni_ok'],  0, ',', '.') . '  (' . number_format($stats['dni_ok'] / $total * 100, 1, ',', '.') . '%)',
            number_format($stats['faltan'],  0, ',', '.'),
        ];

        $indice = 0;
        foreach ($bloquesStats as $desde => $hasta) {
            $rango = $desde . '5:' . $hasta . '5';
            $hoja->mergeCells($rango);
            $hoja->setCellValue($desde . '5', $etiquetas[$indice]);

            $rangoValor = $desde . '6:' . $hasta . '6';
            $hoja->mergeCells($rangoValor);
            $hoja->setCellValue($desde . '6', $valores[$indice]);

            $indice++;
        }

        $hoja->getStyle('A5:' . $ultima . '6')->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('E5E7EB');
        $hoja->getStyle('A5:' . $ultima . '5')->getFont()->setBold(true)->setSize(9)
              ->getColor()->setRGB('374151');
        $hoja->getStyle('A6:' . $ultima . '6')->getFont()->setBold(true)->setSize(10)
              ->getColor()->setRGB('111827');
        $hoja->getStyle('A5:' . $ultima . '6')->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        $hoja->getStyle('A5:' . $ultima . '6')->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER);

        $hoja->getRowDimension(7)->setRowHeight(6);

        // ── Cabecera de la tabla ──────────────────────────────
        $filaCabecera = 8;
        foreach ($columnas as $i => $titulo) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $filaCabecera, $titulo);
        }
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
              ->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('4B5563');
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
              ->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
              ->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER)
              ->setWrapText(true);
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
              ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
              ->getColor()->setRGB('4B5563');
        $hoja->getRowDimension($filaCabecera)->setRowHeight(30);

        // ── Filas ─────────────────────────────────────────────
        // Se vuelcan con fromArray y se pintan por rangos (en vez de celda
        // a celda): con 1.135 registros pasa de ~16 s a ~3 s.
        $filaInicial = $filaCabecera + 1;
        $matriz      = [];
        $incompletas = [];

        $num = 0;
        foreach ($filas as $d) {
            $num++;
            $f = $filaCabecera + $num;

            $matriz[] = [
                $num,
                $d['codi'],
                $d['nombre'],
                $d['doc'],
                $d['cargo'],
                $d['ingreso_solmar'],
                $d['ingreso_planilla'],
                $d['foto_txt'],
                $d['dni_txt'],
                $d['reg'],
                $d['freg'],
                $d['mod'],
                $d['fmod'],
            ];

            if ($d['incompleto']) {
                $incompletas[$f] = $d;
            }
        }

        $filaFinal = $filaCabecera + count($matriz);

        if ($matriz !== []) {
            $hoja->fromArray($matriz, null, 'A' . $filaInicial, true);

            // Estilos base del bloque (una sola pasada)
            $base = $hoja->getStyle('A' . $filaInicial . ':' . $ultima . $filaFinal);
            $base->getFont()->setSize(9);
            $base->getBorders()->getAllBorders()
                 ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $base->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            // Columnas compactas con alineación centrada.
            foreach (['A', 'D', 'F', 'G', 'H', 'I', 'K', 'M'] as $col) {
                $hoja->getStyle($col . $filaInicial . ':' . $col . $filaFinal)->getAlignment()
                     ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Estados correctos en verde; abajo se sobrescriben las excepciones
            $hoja->getStyle('H' . $filaInicial . ':' . 'H' . $filaFinal)
                 ->getFont()->setBold(true)->getColor()->setRGB('15803D');
            $hoja->getStyle('I' . $filaInicial . ':' . 'I' . $filaFinal)
                 ->getFont()->setBold(true)->getColor()->setRGB('15803D');

            // Solo las filas con documentos faltantes: relleno ámbar + color
            foreach ($incompletas as $f => $d) {
                $hoja->getStyle('A' . $f . ':' . $ultima . $f)->getFill()
                     ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

                $colorFoto = $d['foto_txt'] === 'Sí' ? '15803D' : ($d['foto_txt'] === 'No' ? 'B91C1C' : 'B45309');
                $colorDni  = match ($d['dni_txt']) {
                    'Completo'                      => '15803D',
                    'Solo anverso', 'Solo reverso'   => 'B45309',
                    'No'                            => 'B91C1C',
                    default                         => 'B45309',
                };

                $hoja->getStyle('H' . $f)->getFont()->setBold(true)->getColor()->setRGB($colorFoto);
                $hoja->getStyle('I' . $f)->getFont()->setBold(true)->getColor()->setRGB($colorDni);
            }
        }

        // ── Filtros y panel congelado ─────────────────────────
        $hoja->setAutoFilter('A' . $filaCabecera . ':' . $ultima . $filaCabecera);
        $hoja->freezePane('A' . ($filaCabecera + 1));

        // sys_get_temp_dir() queda redirigido a <proyecto>/temp por AppServiceProvider;
        // nos aseguramos de que exista antes de escribir (si no, fopen da 500).
        $dir = sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ruta = $dir . DIRECTORY_SEPARATOR
              . 'rep_documentos_' . date('Ymd_His') . '_' . uniqid() . '.xlsx';

        (new Xlsx($ss))->save($ruta);
        $ss->disconnectWorksheets();
        unset($ss);

        return $ruta;
    }

    // ── Reporte de DNI de hijos ─────────────────────────────────────────

    /**
     * Devuelve la ruta temporal del .xlsx generado con el personal que tiene
     * hijos menores de edad sin el DNI (anverso/reverso) cargado.
     */
    private function generarExcelDniHijos(array $filas, array $stats, string $titulo): string
    {
        $columnas = [
            'N°', 'Código', 'Apellidos y Nombres', 'N° Documento', 'Cargo',
            'Fecha de ingreso a Solmar', 'Fecha de ingreso a planillas',
            'Asignación familiar', 'Hijos <18', 'Anverso', 'Reverso', 'Sin imagen',
            'Detalle de faltantes',
        ];
        $ultima = 'M';

        $ss   = new Spreadsheet();
        $hoja = $ss->getActiveSheet();
        $hoja->setTitle('DNI hijos');
        $hoja->setShowGridlines(false);

        // ── Anchos de columna ─────────────────────────────────────────────
        $anchos = [6, 16, 34, 15, 26, 20, 23, 20, 11, 11, 11, 11, 82];
        foreach ($anchos as $i => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($ancho);
        }

        // ── Logo ──────────────────────────────────────────────────────────
        $rutaLogo = public_path('images/logo_sol.png');
        if (is_file($rutaLogo)) {
            $logo = new Drawing();
            $logo->setPath($rutaLogo);
            $logo->setResizeProportional(false);
            $logo->setCoordinates('A1');
            $logo->setWidth(130);
            $logo->setHeight(62);
            $logo->setWorksheet($hoja);
        }

        // ── Encabezado ────────────────────────────────────────────────────
        $hoja->getRowDimension(1)->setRowHeight(64);
        $hoja->mergeCells('C1:' . $ultima . '1');
        $hoja->setCellValue('C1', $titulo);
        $hoja->getStyle('C1')->getFont()->setBold(true)->setSize(12)
              ->getColor()->setRGB('990000');
        $hoja->getStyle('C1')->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_LEFT)
              ->setVertical(Alignment::VERTICAL_CENTER)
              ->setWrapText(true);

        $hoja->mergeCells('C2:' . $ultima . '2');

        $hoja->getRowDimension(4)->setRowHeight(6);

        // ── Bloque de estadísticas ────────────────────────────────────────
        $bloquesStats = [
            'A' => 'B',  // Generado
            'C' => 'D',  // Personal evaluado
            'E' => 'F',  // Con DNI completo
            'G' => 'H',  // Con faltantes
            'I' => 'M',  // Hijos <18
        ];
        $etiquetas = [
            'Generado', 'Personal evaluado', 'Sin faltantes',
            'Con faltantes', 'Hijos <18',
        ];

        $totalEval = max($stats['evaluados'], 1);
        $valores = [
            $stats['generado'],
            number_format($stats['evaluados'], 0, ',', '.'),
            number_format($stats['ok'], 0, ',', '.') . '  (' . number_format($stats['ok'] / $totalEval * 100, 1, ',', '.') . '%)',
            number_format($stats['faltan'], 0, ',', '.'),
            number_format($stats['hijos'], 0, ',', '.'),
        ];

        $indice = 0;
        foreach ($bloquesStats as $desde => $hasta) {
            $rango = $desde . '5:' . $hasta . '5';
            $hoja->mergeCells($rango);
            $hoja->setCellValue($desde . '5', $etiquetas[$indice]);

            $rangoValor = $desde . '6:' . $hasta . '6';
            $hoja->mergeCells($rangoValor);
            $hoja->setCellValue($desde . '6', $valores[$indice]);

            $indice++;
        }

        $hoja->getStyle('A5:' . $ultima . '6')->getFill()
              ->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('E5E7EB');
        $hoja->getStyle('A5:' . $ultima . '5')->getFont()->setBold(true)->setSize(9)
              ->getColor()->setRGB('374151');
        $hoja->getStyle('A6:' . $ultima . '6')->getFont()->setBold(true)->setSize(10)
              ->getColor()->setRGB('111827');
        $hoja->getStyle('A5:' . $ultima . '6')->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        $hoja->getStyle('A5:' . $ultima . '6')->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER)
              ->setVertical(Alignment::VERTICAL_CENTER);

        $hoja->getRowDimension(7)->setRowHeight(6);

        // ── Cabecera de la tabla ──────────────────────────────────────────
        $filaCabecera = 8;
        foreach ($columnas as $i => $titulo) {
            $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $filaCabecera, $titulo);
        }
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4B5563');
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
            ->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $hoja->getStyle('A' . $filaCabecera . ':' . $ultima . $filaCabecera)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('D1D5DB');
        $hoja->getRowDimension($filaCabecera)->setRowHeight(30);

        // ── Filas ─────────────────────────────────────────────────────────
        $filaInicial = $filaCabecera + 1;
        $matriz      = [];

        $num = 0;
        foreach ($filas as $d) {
            $num++;

            $matriz[] = [
                $num,
                $d['codi'],
                $d['nombre'],
                $d['doc'],
                $d['cargo'],
                $d['ingreso_solmar'],
                $d['ingreso_planilla'],
                $d['asignacion_familiar'],
                $d['total'],
                $d['anverso_txt'],
                $d['reverso_txt'],
                $d['sin'],
                $d['detalle'],
            ];
        }

        $filaFinal = $filaCabecera + count($matriz);

        if ($matriz !== []) {
            $hoja->fromArray($matriz, null, 'A' . $filaInicial, true);

            // Estilos base del bloque (una sola pasada)
            $base = $hoja->getStyle('A' . $filaInicial . ':' . $ultima . $filaFinal);
            $base->getFont()->setSize(9);
            $base->getBorders()->getAllBorders()
                 ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $base->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            // Columnas cortas centradas
            foreach (['A', 'D', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                $hoja->getStyle($col . $filaInicial . ':' . $col . $filaFinal)->getAlignment()
                     ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Semáforo de Anverso/Reverso: Sí = verde, No = rojo.
            $hoja->getStyle('J' . $filaInicial . ':' . 'J' . $filaFinal)
                 ->getFont()->setBold(true)->getColor()->setRGB('15803D');
            $hoja->getStyle('K' . $filaInicial . ':' . 'K' . $filaFinal)
                 ->getFont()->setBold(true)->getColor()->setRGB('15803D');

            // Fondo: rojo claro si algún hijo quedó sin ninguna imagen,
            // ámbar si solo faltan anverso/reverso sueltos.
            foreach ($filas as $i => $d) {
                $f = $filaInicial + $i;

                $hoja->getStyle('A' . $f . ':' . $ultima . $f)->getFill()
                     ->setFillType(Fill::FILL_SOLID)
                     ->getStartColor()->setRGB($d['sin'] > 0 ? 'FEE2E2' : 'FEF3C7');

                $hoja->getStyle('J' . $f)->getFont()->setBold(true)
                     ->getColor()->setRGB($d['anverso_txt'] === 'No' ? 'B91C1C' : '15803D');

                $hoja->getStyle('K' . $f)->getFont()->setBold(true)
                     ->getColor()->setRGB($d['reverso_txt'] === 'No' ? 'B91C1C' : '15803D');

                if ((int) $d['sin'] > 0) {
                    $hoja->getStyle('L' . $f)->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
                }
            }
        }

        // ── Filtros y panel congelado ─────────────────────────────────────
        $hoja->setAutoFilter('A' . $filaCabecera . ':' . $ultima . $filaCabecera);
        $hoja->freezePane('A' . ($filaCabecera + 1));

        // sys_get_temp_dir() queda redirigido a <proyecto>/temp por AppServiceProvider
        $dir = sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ruta = $dir . DIRECTORY_SEPARATOR
              . 'rep_dni_hijos_' . date('Ymd_His') . '_' . uniqid() . '.xlsx';

        (new Xlsx($ss))->save($ruta);
        $ss->disconnectWorksheets();
        unset($ss);

        return $ruta;
    }

    // ── Helpers privados ──────────────────────────────────────

    private function usuarioLegible($codigo, $nombre): string
    {
        $nom = trim((string) ($nombre ?? ''));
        if ($nom !== '' && trim(preg_replace('/\s+/', ' ', $nom)) !== '') {
            return trim(preg_replace('/\s+/', ' ', $nom));
        }

        $cod = trim((string) ($codigo ?? ''));

        return $cod !== '' ? $cod : '—';
    }

    private function formatFechaHora($valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        try {
            $fecha = $valor instanceof \DateTimeInterface
                ? $valor
                : new \DateTime(trim((string) $valor));

            return $fecha->format('d/m/Y H:i');
        } catch (\Throwable) {
            return trim((string) $valor);
        }
    }

    private function mapTipoPerACodigos(string $valor): array
    {
        return match (strtoupper($valor)) {
            'OP', 'OPERATIVO'      => ['01', '03'],
            'AD', 'ADMINISTRATIVO' => ['02', '05'],
            default                => [$valor],
        };
    }

    private function formatFecha($valor): string
    {
        if ($valor === null || $valor === '') return '—';

        try {
            $fecha = $valor instanceof \DateTimeInterface
                ? $valor
                : new \DateTime(trim((string) $valor));

            return $fecha->format('d/m/Y');
        } catch (\Throwable) {
            return trim((string) $valor);
        }
    }

    private function calcularEdad($fechNaci): string
    {
        if (empty($fechNaci)) return '—';
        try {
            $naci = new \DateTime(trim((string) $fechNaci));
            return (string) (new \DateTime())->diff($naci)->y;
        } catch (\Exception) {
            return '—';
        }
    }
}
