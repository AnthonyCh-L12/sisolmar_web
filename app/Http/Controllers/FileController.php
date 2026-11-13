<?php

namespace App\Http\Controllers;

use App\Helpers\ImagenHelper;
use App\Helpers\PdfHelper;
use App\Models\Cargo;
use App\Models\FileControl;
use App\Models\Folio;
use App\Models\Matricula;
use App\Models\Reporte;
use Barryvdh\Snappy\Facades\SnappyPdf;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function indexReportesDj()
    {
        $sucursales = FileControl::getSucursales();
        $tipoPerLimitar = session('limitarTipoPer');
        $tiposPersonal = DB::select(
            'SELECT TIPE_CODIGO AS codigo, TIPE_DESCRIPCION AS nombre
             FROM si_solm.dbo.ADMI_TIPO_PERSONAL
             WHERE TIPE_CODIGO <> ?
             ORDER BY TIPE_DESCRIPCION'
            , ['06']
        );
        $cargosDj = DB::select(
            "SELECT CODI_CARG AS codigo, DESC_CARGO AS nombre, CARGO_TIPO AS tipo
             FROM si_solm.dbo.CARGOS WITH (NOLOCK)
             WHERE CARG_VIGENCIA = 'SI' AND DESC_CARGO IS NOT NULL
             ORDER BY DESC_CARGO"
        );

        return view('file_control.reportes_dj', compact('sucursales', 'tiposPersonal', 'cargosDj', 'tipoPerLimitar'));
    }

    public function index()
    {
        $personal = FileControl::getPersonal();
        //$cargos = FileControl::getCargos();
        $clientes = FileControl::getClientesLegajos();
        $sucursales = FileControl::getSucursales();

        $tipoPerLimitar = session('limitarTipoPer');
        $tipoUsuario = session('tipo_rol');
        $categoriasFolio = DB::table('sw_folios_categoria')
            ->where('habilitado', 1)
            ->orderBy('nombre')
            ->get(['codigo', 'nombre']);

        return view('file_control.chargefile', compact('personal', 'clientes', 'sucursales', 'tipoPerLimitar', 'tipoUsuario', 'categoriasFolio'));
    }

    public function indexGestionDj()
    {
        $grados = FileControl::getGradosInstruccionDJ();
        $carreras = FileControl::getCarrerasDJ();
        $instituciones = FileControl::getInstitucionesDJ();
        $bancos = FileControl::getBancosDJ();
        $sucursales = FileControl::getSucursales();

        // Lista completa de sucursales vigentes para el modal Nueva DJ
        // (llamada al SP sin filtro de usuario: @usuario = '0')
        $todasLasSucursales = DB::select('EXEC SW_LISTAR_SUCURSALES ?', ['0']);

        // Catálogo de tipos de zona para los formularios DJ
        $tiposZona = DB::select(
            'SELECT TIZO_CODIGO, TIZO_DESCRIPCION FROM si_solm.dbo.TIPO_ZONA WHERE TIZO_VIGENCIA = ? ORDER BY TIZO_CODIGO',
            ['1']
        );

        // Catálogo de vínculos familiares (parentescos) para Contacto de Emergencia
        $tiposVinculo = DB::select(
            'SELECT cod, descripcion FROM sisolm_web.dbo.TIPO_VINCULO_FAMILIAR ORDER BY cod'
        );

        $sucursalesAsignadas = $this->obtenerSucursalesAsignadasUsuario();
        $restringirSucursalesGestionDj = $this->usuarioTieneSucursalLimitada();

        // Cuando el usuario tiene asignaciones activas, el filtro solo debe
        // exponer esas sucursales. Los usuarios sin asignaciones mantienen
        // el comportamiento actual (todas las sucursales).
        if ($restringirSucursalesGestionDj) {
            $normalizarCodigoSucursal = static function ($codigo): string {
                $codigo = trim((string) $codigo);

                return ctype_digit($codigo) ? (string) ((int) $codigo) : strtoupper($codigo);
            };
            $sucursalesAsignadasNormalizadas = array_map($normalizarCodigoSucursal, $sucursalesAsignadas);

            $sucursales = array_values(array_filter($sucursales, function ($sucursal) use ($normalizarCodigoSucursal, $sucursalesAsignadasNormalizadas) {
                $codigo = trim((string) ($sucursal->codigo ?? ''));

                return in_array($normalizarCodigoSucursal($codigo), $sucursalesAsignadasNormalizadas, true);
            }));
        }

        $sucursalesGestionDj = array_values(array_filter($sucursales, function ($sucursal) {
            return ! in_array(trim((string) ($sucursal->codigo ?? '')), ['', '0', '00'], true);
        }));

        // Los usuarios con acceso global inician en Chimbote. Para los
        // usuarios limitados, el predeterminado solo aplica si Chimbote está
        // entre sus sucursales permitidas.
        $filtrosInicialesGestionDj = [
            'sucursal' => '',
            'tipo' => '',
            'vigencia' => '',
        ];

        foreach ($sucursalesGestionDj as $sucursal) {
            $datosSucursal = implode(' ', array_filter([
                $sucursal->codigo ?? null,
                $sucursal->abreviatura ?? null,
                $sucursal->nombre ?? null,
                $sucursal->descripcion ?? null,
            ]));

            if (str_contains(strtoupper(trim($datosSucursal)), 'CHIMBOTE')) {
                $filtrosInicialesGestionDj = [
                    'sucursal' => trim((string) $sucursal->codigo),
                    'tipo' => 'OPERATIVO 5°',
                    'vigencia' => 'SI',
                ];
                break;
            }
        }

        $mostrarTodosTiposGestionDj = $filtrosInicialesGestionDj['sucursal'] !== '';

        // 1. Obtener cargos
        $cargos = FileControl::getCargosDj();

        $tipoPerLimitar = session('limitarTipoPer');
        $tipoUsuario = session('tipo_rol');

        // 2. Añadir 'cargos' al compact
        return view('file_control.gestion_dj', compact(
            'grados',
            'carreras',
            'instituciones',
            'bancos',
            'sucursales',
            'todasLasSucursales',
            'sucursalesGestionDj',
            'restringirSucursalesGestionDj',
            'filtrosInicialesGestionDj',
            'mostrarTodosTiposGestionDj',
            'cargos',
            'tipoPerLimitar',
            'tipoUsuario',
            'tiposZona',
            'tiposVinculo'
        ));
    }

    /**
     * Obtiene las sucursales activas asignadas al usuario autenticado.
     * Una lista vacía mantiene el acceso global actual.
     */
    private function obtenerSucursalesAsignadasUsuario(): array
    {
        $codUsuario = Auth::id();

        if (! $codUsuario) {
            return [];
        }

        return DB::table('sw_permisos_usuario_sucursal')
            ->where('codUsuario', $codUsuario)
            ->where('habilitado', 1)
            ->pluck('codSucursal')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * La asignación de sucursales solo se aplica cuando el usuario tiene
     * la bandera limitarSucursal activada. Con valor 0 conserva acceso global.
     */
    private function usuarioTieneSucursalLimitada(): bool
    {
        return (int) (Auth::user()?->limitarSucursal ?? 0) === 1;
    }

    /**
     * Limita los datos de Gestión DJ a las sucursales activas del usuario.
     */
    private function filtrarDjPorSucursalesAsignadas(array $personal): array
    {
        if (! $this->usuarioTieneSucursalLimitada()) {
            return $personal;
        }

        $sucursalesAsignadas = $this->obtenerSucursalesAsignadasUsuario();

        if (empty($sucursalesAsignadas)) {
            return $personal;
        }

        // Los permisos se guardan como CHAR(2), por ejemplo "04", mientras
        // algunos procedimientos devuelven el código sin cero inicial ("4").
        // Se normalizan para mantener el filtro estricto sin perder registros.
        $normalizarCodigoSucursal = static function ($codigo): string {
            $codigo = trim((string) $codigo);

            return ctype_digit($codigo) ? (string) ((int) $codigo) : strtoupper($codigo);
        };

        $sucursalesPermitidas = array_map($normalizarCodigoSucursal, $sucursalesAsignadas);

        return array_values(array_filter($personal, function ($persona) use ($sucursalesPermitidas, $normalizarCodigoSucursal) {
            $codSucursal = $persona->codSucursal
                ?? $persona->COD_SUCURSAL
                ?? $persona->SUCU_CODIGO
                ?? $persona->sucursalCodigo
                ?? '';

            return in_array($normalizarCodigoSucursal($codSucursal), $sucursalesPermitidas, true);
        }));
    }

    public function indexActualizarDj()
    {
        $grados = FileControl::getGradosInstruccionDJ();
        $carreras = FileControl::getCarrerasDJ();
        $instituciones = FileControl::getInstitucionesDJ();
        $bancos = FileControl::getBancosDJ();
        $sucursales = FileControl::getSucursales();

        $tipoPerLimitar = session('limitarTipoPer');
        $tipoUsuario = session('tipo_rol');

        // Catálogo de tipos de zona
        $tiposZona = DB::select(
            'SELECT TIZO_CODIGO, TIZO_DESCRIPCION FROM si_solm.dbo.TIPO_ZONA WHERE TIZO_VIGENCIA = ? ORDER BY TIZO_CODIGO',
            ['1']
        );

        // Catálogo de vínculos familiares (parentescos) para Contacto de Emergencia
        $tiposVinculo = DB::select(
            'SELECT cod, descripcion FROM sisolm_web.dbo.TIPO_VINCULO_FAMILIAR ORDER BY cod'
        );

        $esRrhhMigracion = in_array($tipoUsuario, [8, 12]);
        $esAdmin = in_array($tipoUsuario, [5, 11]);

        return view('file_control.actualizar_dj', compact('grados', 'carreras', 'instituciones', 'bancos', 'sucursales', 'tipoPerLimitar', 'tipoUsuario', 'esRrhhMigracion', 'esAdmin', 'tiposZona', 'tiposVinculo'));
    }


     public function indexReportePersonal()
    {
        $sucursales = FileControl::getSucursales();

        $tipoPerLimitar = session('limitarTipoPer');
        $tipoUsuario = session('tipo_rol');
        $esRrhhMigracion = in_array($tipoUsuario, [8, 12]);
        $esAdmin = in_array($tipoUsuario, [5, 11]);

        return view('file_control.reporte_personal', compact('sucursales', 'tipoPerLimitar', 'tipoUsuario', 'esRrhhMigracion', 'esAdmin'));
    }


    public function ViewReportes()
      {
          $clientes = FileControl::getClientes();
          $sucursales = FileControl::getSucursales();
          $cargos = FileControl::getCargos();

          return view('file_control.reportes', compact('clientes', 'sucursales', 'cargos'));
      }

  

    public function getCargosXCliente(Request $request)
    {
        $cliente = $request->input('cliente');
        $cargos = FileControl::getCargosXCliente($cliente);

        return response()->json($cargos);
    }

    public function ViewDashboard()
    {
        /*$personal = FileControl::getPersonal();
        $cargos = FileControl::getCargos();
        $clientes = FileControl::getClientes();
        $sucursales = FileControl::getSucursales();*/

        return view('file_control.dashboard'/*, compact('personal', 'cargos', 'clientes', 'sucursales')*/);
    }

    public function getPersonal(Request $request)
    {
        try {
            // Usar el stored procedure original que ya tiene la lógica correcta
            $allPersonal = FileControl::getPersonal();

            $cursoId = $request->input('cursoId');

            // MODO: Paginación LOCAL vs REMOTA
            // Si el cliente pide "pagination=off", devolvemos TODO el array plano.
            $paginationMode = $request->input('pagination');

            if ($paginationMode === 'off') {
                // Si se especificó un curso, verificar quién ya está matriculado (Lógica compartida)
                if ($cursoId) {
                    if (! is_numeric($cursoId)) {
                        throw new \Exception('El ID del curso no es válido.');
                    }

                    $matriculados = Matricula::where('cod_curso', $cursoId)
                        ->pluck('cod_personal')
                        ->toArray();

                    $allPersonal = array_map(function ($persona) use ($matriculados) {
                        $persona->matriculado = in_array($persona->CODI_PERS, $matriculados);

                        return $persona;
                    }, $allPersonal);
                }

                return response()->json(array_values($allPersonal));
            }

            // --- LOGICA ANTIGUA (Paginación Remota) SOLO SI NO ES LOCAL ---
            $page = (int) $request->input('page', 1);
            $size = (int) $request->input('size', 50);

            // Filtrar por búsqueda si existe (paginación remota con filtro)
            $search = $request->input('filter', '');
            if (! empty($search)) {
                $searchLower = strtolower($search);
                $allPersonal = array_filter($allPersonal, function ($persona) use ($searchLower) {
                    $nombre = strtolower($persona->personal ?? '');
                    $doc = strtolower($persona->nroDoc ?? '');

                    return str_contains($nombre, $searchLower) || str_contains($doc, $searchLower);
                });
                $allPersonal = array_values($allPersonal); // Reindexar
            }

            $total = count($allPersonal);

            // Aplicar paginación en PHP
            $offset = ($page - 1) * $size;
            $personalPaginado = array_slice($allPersonal, $offset, $size);

            // Si se especificó un curso, verificar quién ya está matriculado
            if ($cursoId) {
                // Validación manual para evitar que falle el validate() con un 422 JSON que rompa Tabulator
                // O usamos un try-catch anidado, pero el try general ya lo cubre.
                if (! is_numeric($cursoId)) {
                    throw new \Exception('El ID del curso no es válido.');
                }

                $matriculados = Matricula::where('cod_curso', $cursoId)
                    ->pluck('cod_personal')
                    ->toArray();

                // Agregar campo 'matriculado' a cada registro
                $personalPaginado = array_map(function ($persona) use ($matriculados) {
                    $persona->matriculado = in_array($persona->CODI_PERS, $matriculados);

                    return $persona;
                }, $personalPaginado);
            }

            // Formato de respuesta compatible con Tabulator paginación remota
            return response()->json([
                'data' => $personalPaginado,
                'last_page' => (int) ceil($total / $size),
                'total' => $total,
                'status' => 'success',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en FileController@getPersonal: '.$e->getMessage());

            // Retornar estructura válida para Tabulator pero vacía y con error
            return response()->json([
                'data' => [],
                'last_page' => 1,
                'total' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function getPersonalLegajosPdf(Request $request)
    {
        try {
            $codSucursal = $request->input('codSucursal', '0');
            $vigencia    = $request->input('vigencia', 'SI');
            $clienteWeb  = $request->input('cliente', 'T');
            $cargoErp    = $request->input('cargo', 'T'); 

            $clienteErp = 'T';
            if ($clienteWeb !== 'T') {
                $clientesWeb = FileControl::getClientes();
                $abreviatura = '';
                foreach ($clientesWeb as $c) {
                    if ($c->codigo == $clienteWeb) {
                        $abreviatura = $c->abreviatura;
                        break;
                    }
                }
                if ($abreviatura !== '') {
                    $erpData = DB::table('si_solm.dbo.CLIENTE_PROVEEDOR')
                        ->where('ABREVIATURA', $abreviatura)
                        ->first();
                    if ($erpData) {
                        $clienteErp = $erpData->CODI_CLIE_PROV;
                    }
                }
            }

            $allPersonal = FileControl::getPersonalLegajosPdf($codSucursal, $vigencia, $clienteErp, $cargoErp);
            return response()->json(array_values($allPersonal));
        } catch (\Exception $e) {
            Log::error('Error en getPersonalLegajosPdf: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function getCargosErp(Request $request)
    {
        $clienteWeb = $request->input('cliente', 'T');
        $clienteErp = 'T';

        if ($clienteWeb !== 'T') {
            $clientesWeb = FileControl::getClientes();
            $abreviatura = '';
            foreach ($clientesWeb as $c) {
                if ($c->codigo == $clienteWeb) {
                    $abreviatura = $c->abreviatura;
                    break;
                }
            }
            if ($abreviatura !== '') {
                $erpData = DB::table('si_solm.dbo.CLIENTE_PROVEEDOR')->where('ABREVIATURA', $abreviatura)->first();
                if ($erpData) { $clienteErp = $erpData->CODI_CLIE_PROV; }
            }
        }

        if ($clienteErp !== 'T') {
            $cargos = DB::select("
                SELECT DISTINCT C.CODI_CARG AS codigo, C.DESC_CARGO AS nombre
                FROM si_solm.dbo.PERSONAL P WITH (NOLOCK)
                INNER JOIN si_solm.dbo.CARGOS C WITH (NOLOCK) ON C.CODI_CARG = P.CODI_CARG
                INNER JOIN (
                    SELECT DET.CODI_PERS, CAB.CODI_CLIE_PROV,
                           ROW_NUMBER() OVER (PARTITION BY DET.CODI_PERS ORDER BY CAB.ASCA_FECHA DESC) rn
                    FROM si_solm.dbo.OPER_ASITENCIA_DET DET WITH (NOLOCK)
                    INNER JOIN si_solm.dbo.OPER_ASISTENCIA_CAB CAB WITH (NOLOCK) ON CAB.ASCA_CODIGO = DET.ASCA_CODIGO
                ) U ON U.CODI_PERS COLLATE DATABASE_DEFAULT = P.CODI_PERS COLLATE DATABASE_DEFAULT AND U.rn = 1
                WHERE U.CODI_CLIE_PROV COLLATE DATABASE_DEFAULT = ?
                  AND C.DESC_CARGO IS NOT NULL
                ORDER BY C.DESC_CARGO
            ", [$clienteErp]);
        } else {
            $cargos = DB::select("SELECT CODI_CARG AS codigo, DESC_CARGO AS nombre FROM si_solm.dbo.CARGOS WITH (NOLOCK) WHERE DESC_CARGO IS NOT NULL ORDER BY DESC_CARGO");
        }

        return response()->json($cargos);
    }


    public function ViewEscaneoDJ()
    {
        $sucursales = FileControl::getSucursales();
        $tipoPerLimitar = session('limitarTipoPer');
        return view('file_control.carga_escaneo_dj', compact('sucursales', 'tipoPerLimitar'));
    }

    public function getPersonalTotal(Request $request)
    {
        $page = $request->get('page', 1);
        $size = $request->get('size', 50);
        $search = $request->get('search', null);
        $tipo_per = $request->get('tipo_per', null);
        $vigencia = $request->get('vigencia', null);
        $codSucursal = $request->get('codSucursal', '0');
        $tieneFolio = $request->get('tiene_folio_25', null); // null = TODOS
        $usuario = session('usuario');

        // ─── Códigos con folio 25 ───
        $conFolio25 = DB::table('sw_folios_detalles')
            ->where('codFolio', '25')
            ->where('habilitado', '1')
            ->pluck('codPersonal')
            ->toArray();

        // ─── Traer TODOS sin paginar para poder filtrar ───
        // Solo si hay filtro activo de DJ, traemos todo y filtramos
        if ($tieneFolio !== null) {
            $todosDatos = DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_CARGA_DE_ARCHIVOS ?, ?, ?, ?, ?, ?, ?', [
                $codSucursal, 1, 99999, $search, $tipo_per, $vigencia, $usuario,
            ]);

            // Filtrar por tiene_folio_25
            $todosDatos = array_filter($todosDatos, function ($persona) use ($conFolio25, $tieneFolio) {
                $tiene = in_array($persona->CODI_PERS, $conFolio25) ? 1 : 0;

                return $tiene == $tieneFolio;
            });
            $todosDatos = array_values($todosDatos);

            // Paginar manualmente
            $total = count($todosDatos);
            $offset = ($page - 1) * $size;
            $data = array_slice($todosDatos, $offset, $size);

            // Agregar campo
            foreach ($data as $persona) {
                $persona->tiene_folio_25 = in_array($persona->CODI_PERS, $conFolio25) ? 1 : 0;
            }

        } else {
            // Sin filtro DJ — SP nuevo que ya trae el TotalRows integrado y limpieza de guiones
            $data = DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_CARGA_DE_ARCHIVOS ?, ?, ?, ?, ?, ?, ?', [
                $codSucursal, $page, $size, $search, $tipo_per, $vigencia, $usuario,
            ]);

            // Leemos el total de la primera fila. Si no hay registros, el total es 0.
            $total = count($data) > 0 ? $data[0]->TotalRows : 0;

            foreach ($data as $persona) {
                $persona->tiene_folio_25 = in_array($persona->CODI_PERS, $conFolio25) ? 1 : 0;
            }
        }

        return response()->json([
            'data' => $data,
            'last_page' => ceil($total / $size),
            'total' => (int) $total,
        ]);
    }


    public function getPersonalTotalReporte(Request $request)
    {
        $page = $request->get('page', 1);
        $size = $request->get('size', 50);
        $search = $request->get('search', null);
        $tipo_per = $request->get('tipo_per', null);
        $vigencia = $request->get('vigencia', null);
        $codSucursal = $request->get('codSucursal', '0');
        $tieneFolio = $request->get('tiene_folio_25', null); // null = TODOS
        $usuario = session('usuario');

        // ─── Códigos con folio 25 ───
        $conFolio25 = DB::table('sw_folios_detalles')
            ->where('codFolio', '25')
            ->where('habilitado', '1')
            ->pluck('codPersonal')
            ->toArray();

        // ─── Traer TODOS sin paginar para poder filtrar ───
        // Solo si hay filtro activo de DJ, traemos todo y filtramos
        if ($tieneFolio !== null) {
            $todosDatos = DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_CARGA_DE_ARCHIVOS ?, ?, ?, ?, ?, ?, ?', [
                $codSucursal, 1, 99999, $search, $tipo_per, $vigencia, $usuario,
            ]);

            // Filtrar por tiene_folio_25
            $todosDatos = array_filter($todosDatos, function ($persona) use ($conFolio25, $tieneFolio) {
                $tiene = in_array($persona->CODI_PERS, $conFolio25) ? 1 : 0;

                return $tiene == $tieneFolio;
            });
            $todosDatos = array_values($todosDatos);

            // Paginar manualmente
            $total = count($todosDatos);
            $offset = ($page - 1) * $size;
            $data = array_slice($todosDatos, $offset, $size);

            // Agregar campo
            foreach ($data as $persona) {
                $persona->tiene_folio_25 = in_array($persona->CODI_PERS, $conFolio25) ? 1 : 0;
            }

        } else {
            // Sin filtro DJ — flujo normal con SP de conteo
            $data = DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_REPORTE ?, ?, ?, ?, ?, ?, ?', [
                $codSucursal, $page, $size, $search, $tipo_per, $vigencia, $usuario,
            ]);

            $total = DB::select('EXEC SW_CONTAR_PERSONAL ?, ?, ?, ?, ?', [
                $codSucursal, $search, $tipo_per, $vigencia, $usuario,
            ])[0]->total;

            foreach ($data as $persona) {
                $persona->tiene_folio_25 = in_array($persona->CODI_PERS, $conFolio25) ? 1 : 0;
            }
        }

        return response()->json([
            'data' => $data,
            'last_page' => ceil($total / $size),
            'total' => (int) $total,
        ]);
    }

    public function getPersonalTotalPrueba(Request $request)
    {
        $page = $request->get('page', 1);
        $size = $request->get('size', 50);
        $search = $request->get('search', null);
        $tipo_per = $request->get('tipo_per', null);
        $vigencia = $request->get('vigencia', null);
        $codSucursal = $request->get('codSucursal', '0');

        // SP de datos
        $data = DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_PRUEBA ?, ?, ?, ?, ?, ?', [
            $codSucursal, $page, $size, $search, $tipo_per, $vigencia,
        ]);

        // SP de total
        $total = DB::select('EXEC SW_CONTAR_PERSONAL ?, ?, ?, ?', [
            $codSucursal, $search, $tipo_per, $vigencia,
        ])[0]->total;

        return response()->json([
            'data' => $data,
            'last_page' => ceil($total / $size),
            'total' => (int) $total,
        ]);
    }

    public function getPersonalReportePersonal(Request $request)
    {
        session()->save(); // libera el lock de sesión para no bloquear otras requests

        $page        = (int) $request->get('page', 1);
        $size        = (int) $request->get('size', 20);
        $search      = trim((string) $request->get('search', ''));
        $tipo_per    = $request->get('tipo_per', null);
        $vigencia    = $request->get('vigencia', null);   // 'SI', 'NO', o null = TODOS
        $codSucursal = $request->get('codSucursal', '0');
        $usuario     = session('usuario') ?? '0';

        $isTodos = !$vigencia;

        // Etiqueta del filtro (del blade) -> código crudo de PERS_TIPOTRAB.
        // Se compara sin el carácter 'grados' para no depender de la codificación.
        $tipoCod = match (true) {
            str_starts_with((string) $tipo_per, 'OPER 4') => '01',
            str_starts_with((string) $tipo_per, 'OPER 5') => '03',
            (string) $tipo_per === 'ESP'                  => '06',
            default                                       => '',
        };

        // Helper: llama al SP V4 con todos sus parámetros
        $exec = fn($vig, $pag, $fils) => DB::select(
            'EXEC [dbo].[SW_LISTAR_REPORTE_PERSONAL_DJ_2026_V4]
                @usuario=?, @codEmpresa=?, @vigencia=?, @codSucursal=?, @tipo_cod=?, @pagina=?, @filasPorPag=?',
            [$usuario, '01', $vig, $codSucursal, $tipoCod, $pag, $fils]
        );

        // ── Cards: UNA sola llamada ligera (@filasPorPag = 0) que devuelve
        //    totalVigentes y totalCesados juntos, ya filtrados por tipo/sucursal.
        //    Antes costaba 2 llamadas completas de datos (una de ellas descartada). ──
        $cards         = $exec($vigencia ?: 'SI', 1, 0);
        $totalVigentes = !empty($cards) ? (int) ($cards[0]->totalVigentes ?? 0) : 0;
        $totalCesados  = !empty($cards) ? (int) ($cards[0]->totalCesados  ?? 0) : 0;

        // ── Caso ideal: sin búsqueda y con vigencia elegida → el SP pagina solo.
        //    Ya no se excluye cuando hay tipo_per: el filtro de tipo va en SQL. ──
        if (!$isTodos && $search === '') {
            $rows     = $exec($vigencia, $page, $size);
            $total    = !empty($rows) ? (int) $rows[0]->totalRegistros : 0;
            $lastPage = max(1, (int) ceil($total / $size));
            return response()->json([
                'data'          => $rows,
                'last_page'     => $lastPage,
                'total'         => $total,
                'totalVigentes' => $totalVigentes,
                'totalCesados'  => $totalCesados,
            ]);
        }

        // ── Solo cuando hay búsqueda (o TODOS): traer todo y filtrar en PHP ──
        $vigencias = $isTodos ? ['SI', 'NO'] : [$vigencia];
        $data      = [];
        foreach ($vigencias as $v) {
            $data = array_merge($data, $exec($v, 1, 99999));
        }

        // Filtro búsqueda (PHP — no afecta los cards)
        if ($search !== '') {
            $s = strtolower($search);
            $data = array_values(array_filter($data, fn($d) =>
                str_contains(strtolower($d->apellido1   ?? ''), $s) ||
                str_contains(strtolower($d->apellido2   ?? ''), $s) ||
                str_contains(strtolower($d->NOMB_1      ?? ''), $s) ||
                str_contains(strtolower($d->NOMB_2      ?? ''), $s) ||
                str_contains(strtolower($d->dni         ?? ''), $s) ||
                str_contains(strtolower($d->codPersonal ?? ''), $s)
            ));
        }

        $total    = count($data);
        $paged    = array_slice($data, ($page - 1) * $size, $size);
        $lastPage = max(1, (int) ceil($total / $size));

        return response()->json([
            'data'          => array_values($paged),
            'last_page'     => $lastPage,
            'total'         => $total,
            'totalVigentes' => $totalVigentes,
            'totalCesados'  => $totalCesados,
        ]);
    }

    public function getDetallePersonalHistorial($codPersonal)
    {
        session()->save();

        // Solo ceses normales (MOCE_TIPO = '01')
        $ceses = DB::select("
            SELECT TOP 200
                CS.FEC_INGRESO,
                CS.FEC_CESE,
                CS.OBSE_CESE
            FROM [si_solm].[dbo].[ADMI_CESE_PERSONAL] AS CS WITH (NOLOCK)
            INNER JOIN [si_solm].[dbo].[MOTIVO_CESE]   AS M  WITH (NOLOCK)
                ON M.MOCE_CODIGO = CS.MOCE_CODIGO
            WHERE CS.CODI_PERS = ?
              AND M.MOCE_TIPO  = '01'
            ORDER BY CS.FEC_INGRESO DESC
        ", [$codPersonal]);

        $tareajes = DB::select("
            SELECT DISTINCT TOP 200
                CAB.ASCA_FECHA      AS fecha,
                CL.PUCL_DESCRIPCION AS puesto,
                CC.ABREVIATURA      AS cliente
            FROM [si_solm].[dbo].[OPER_ASITENCIA_DET]        AS DET WITH (NOLOCK)
            INNER JOIN [si_solm].[dbo].[OPER_ASISTENCIA_CAB]  AS CAB WITH (NOLOCK) ON CAB.ASCA_CODIGO  = DET.ASCA_CODIGO
            INNER JOIN [si_solm].[dbo].[OPER_PUESTOV_CLIENTE] AS CL  WITH (NOLOCK) ON CL.PUCL_CODIGO   = DET.PUCL_CODIGO
            INNER JOIN [si_solm].[dbo].[CLIENTE_PROVEEDOR]    AS CC  WITH (NOLOCK) ON CC.CODI_CLIE_PROV = CAB.CODI_CLIE_PROV
            WHERE DET.CODI_PERS = ?
              AND CAB.ASCA_FECHA >= DATEADD(YEAR, -2, GETDATE())
            ORDER BY CAB.ASCA_FECHA DESC
        ", [$codPersonal]);

        // Lista negra (MOCE_TIPO <> '01')
        $listaNegra = DB::select("
            SELECT TOP 100
                CS.FEC_CESE,
                M.MOCE_DESCRIPCION AS motivo,
                CS.OBSE_CESE
            FROM [si_solm].[dbo].[ADMI_CESE_PERSONAL] AS CS WITH (NOLOCK)
            INNER JOIN [si_solm].[dbo].[MOTIVO_CESE]   AS M  WITH (NOLOCK)
                ON M.MOCE_CODIGO = CS.MOCE_CODIGO
            WHERE CS.CODI_PERS  = ?
              AND M.MOCE_TIPO  <> '01'
              AND CS.ESTA_CESE  = '1'
            ORDER BY CS.FEC_CESE DESC
        ", [$codPersonal]);

        return response()->json([
            'success'     => true,
            'ceses'       => $ceses,
            'tareajes'    => $tareajes,
            'lista_negra' => $listaNegra,
        ]);
    }

    public function verDjPdf($codPersonal)
    {
        $ruta = '\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dj\\2026\\'.$codPersonal.'.pdf';

        if (! file_exists($ruta)) {
            abort(404, 'Documento no encontrado');
        }

        return response()->file($ruta, [
            'Content-Type' => 'application/pdf',
        ]);
    }


    public function verDjPdfExterno($codPersonal)
    {
        $ruta = '\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dj\\2026\\'.$codPersonal.'.pdf';

        if (!file_exists($ruta)) {
            return response()->view('errors.documento_no_encontrado', [
                'mensaje' => 'El documento DJ del personal <strong>#'.$codPersonal.'</strong> no está disponible.'
            ], 404);
        }

        return response()->file($ruta, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function getDocumentosXPersonal($codPersonal)
    {
        $usuario = session('usuario');
        $docs_personal = FileControl::getDocsXPersona($codPersonal, $usuario);

        return response()->json($docs_personal);
    }

    public function getFoliosXPersonas(Request $request)
    {
        $personas = $request->personas;
        $folios   = $request->folios;
        $resultados = [];

        foreach ($personas as $persona) {
            try {
                $sucursal = FileControl::getSucursalXPersona($persona['CODI_PERS']);
                $tieneDocumentos = false;

                foreach ($folios as $folio) {
                    try {
                        $datosFolioPersona = FileControl::getFoliosInfoPersona($persona['CODI_PERS'], $folio['codigo']);
                        foreach ($datosFolioPersona as $dato) {
                            $tieneDocumentos = true;
                            $resultados[] = [
                                'persona'     => $dato->personal       ?? ($persona['personal'] ?? null),
                                'nroDoc'      => $dato->nroDoc         ?? ($persona['nroDoc']   ?? null),
                                'codPersonal' => $persona['CODI_PERS'],
                                'folio'       => $folio['nombre'],
                                'sucursal'    => $sucursal             ?? ($persona['sucursal'] ?? null),
                                'ruta'        => $dato->ruta_archivo   ?? null,
                                'ancho'       => $dato->ancho          ?? null,
                                'hojas'       => $dato->cantidad_hojas ?? null,
                                'documento'   => $dato->documento      ?? null,
                                'cargo'       => $dato->cargo          ?? null,
                                'es_formato'  => $dato->es_formato     ?? null,
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::warning("Folio {$folio['codigo']} - persona {$persona['CODI_PERS']}: " . $e->getMessage());
                    }
                }

                // Sin documentos → entrada mínima para que siempre aparezca la carátula
                if (!$tieneDocumentos) {
                    $resultados[] = [
                        'persona'     => $persona['personal'] ?? null,
                        'nroDoc'      => $persona['nroDoc']   ?? null,
                        'codPersonal' => $persona['CODI_PERS'],
                        'folio'       => null,
                        'sucursal'    => $sucursal ?? ($persona['sucursal'] ?? null),
                        'ruta'        => null,
                        'ancho'       => null,
                        'hojas'       => null,
                        'documento'   => null,
                        'cargo'       => null,
                        'es_formato'  => null,
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Error persona {$persona['CODI_PERS']}: " . $e->getMessage());
            }
        }

        return response()->json($resultados);
    }

    public function getFoliosXPersona_uno(Request $request)
    {
        $codPersona  = $request->input('codPersona');
        $personaData = $request->input('persona', []);
        $folios      = $request->folios;
        $resultados  = [];

        $sucursal = FileControl::getSucursalXPersona($codPersona);
        $tieneDocumentos = false;

        foreach ($folios as $folio) {
            try {
                $datosFolioPersona = FileControl::getFoliosInfoPersona($codPersona, $folio['codigo']);
                foreach ($datosFolioPersona as $dato) {
                    $tieneDocumentos = true;
                    $resultados[] = [
                        'persona'     => $dato->personal       ?? ($personaData['personal'] ?? null),
                        'nroDoc'      => $dato->nroDoc         ?? ($personaData['nroDoc']   ?? null),
                        'codPersonal' => $codPersona,
                        'folio'       => $folio['nombre'],
                        'sucursal'    => $sucursal             ?? ($personaData['sucursal'] ?? null),
                        'ruta'        => $dato->ruta_archivo   ?? null,
                        'ancho'       => $dato->ancho          ?? null,
                        'hojas'       => $dato->cantidad_hojas ?? null,
                        'documento'   => $dato->documento      ?? null,
                        'cargo'       => $dato->cargo          ?? null,
                        'es_formato'  => $dato->es_formato     ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Folio {$folio['codigo']} - persona {$codPersona}: " . $e->getMessage());
            }
        }

        // Sin documentos → entrada mínima para que siempre aparezca la carátula
        if (!$tieneDocumentos) {
            $resultados[] = [
                'persona'     => $personaData['personal'] ?? null,
                'nroDoc'      => $personaData['nroDoc']   ?? null,
                'codPersonal' => $codPersona,
                'folio'       => null,
                'sucursal'    => $sucursal ?? ($personaData['sucursal'] ?? null),
                'ruta'        => null,
                'ancho'       => null,
                'hojas'       => null,
                'documento'   => null,
                'cargo'       => null,
                'es_formato'  => null,
            ];
        }

        return response()->json($resultados);
    }

    // public function generarPDFsPorPersona(Request $request){
    //     $resultados = $request->input('resultados');

    //     // Agrupar todos los datos por persona
    //     $agrupados = [];
    //     foreach ($resultados as $item) {
    //         $cod = $item['codPersonal'];
    //         $agrupados[$cod][] = $item;
    //     }

    //     $carpetaTemporal = storage_path('app/temp_pdfs');
    //     if (!File::exists($carpetaTemporal)) {
    //         File::makeDirectory($carpetaTemporal, 0777, true, true);
    //     }

    //     $pdfsGenerados = [];

    //     foreach ($agrupados as $codPersonal => $documentosPersona) {
    //         $persona = $documentosPersona[0]['persona'] ?? 'persona_sin_nombre';

    //         // Carátula: datos únicos de persona
    //         $caratula = [
    //             'persona' => $persona,
    //             'codPersonal' => $codPersonal,
    //             'sucursal' => $documentosPersona[0]['sucursal'] ?? '',
    //             'cargo' => $documentosPersona[0]['cargo'] ?? '',
    //         ];

    //         // Separar documentos: escaneados vs formatos Blade
    //         $urls = [];
    //         $itemsFinales = [];

    //         foreach ($documentosPersona as $doc) {
    //             if (!empty($doc['ruta'])) {
    //                 $urls[] = [
    //                     'ruta' => $doc['ruta'],
    //                     'codPersonal' => $codPersonal,
    //                     'ancho' => $doc['ancho'],
    //                     'hojas' => $doc['hojas'],
    //                     'documento' => $doc['documento'],
    //                     'es_formato' => $doc['es_formato'],
    //                 ];
    //             }

    //             if ($doc['es_formato'] == 1) {
    //                 $itemsFinales[] = [
    //                     'es_formato' => 1,
    //                     'codPersonal' => $codPersonal,
    //                     'documento' => $doc['documento'],
    //                     'nombre_vista' => $this->obtenerNombreVista($doc),
    //                     'datos' => $doc,
    //                     'firma' => public_path('temp_legajos') . '/FIRMAS/PERSONAL/' . $codPersonal . '.jpg',
    //                     'huella' => public_path('temp_legajos') . '/HUELLAS_DIGITALES/PERSONAL/' . $codPersonal . '.jpg',
    //                 ];

    //                 // Descargar imágenes del formato (firma y huella)
    //                 ImagenHelper::descargarImagenesFormato($codPersonal);
    //             }
    //         }

    //         // Descargar imágenes escaneadas
    //         $rutasLocales = PdfHelper::descargarImagenesLegajo($urls);

    //         foreach ($rutasLocales as $img) {
    //             $itemsFinales[] = [
    //                 'es_formato' => 0,
    //                 'codPersonal' => $codPersonal,
    //                 'ruta' => $img['ruta'],
    //                 'documento' => $img['documento'],
    //                 'hojas' => $img['hojas'],
    //                 'ancho' => $img['ancho'],
    //             ];
    //         }

    //         // Render PDF de esta persona
    //         $pdf = SnappyPdf::loadView('file_control.pdf.reporte', [
    //             'personas' => [$caratula], // vista espera array
    //             'resultados' => $documentosPersona,
    //             'imagenes' => $rutasLocales,
    //             'items' => $itemsFinales,
    //         ])->setOption('enable-local-file-access', true);

    //         $nombreArchivo = 'PDF_' . Str::slug($persona) . '_' . $codPersonal . '.pdf';
    //         $rutaArchivo = $carpetaTemporal . '/' . $nombreArchivo;

    //         $pdf->save($rutaArchivo);
    //         $pdfsGenerados[] = $rutaArchivo;
    //     }

    //     // Comprimir todo en ZIP
    //     $zipPath = storage_path('app/documentos_por_persona.zip');
    //     $zip = new \ZipArchive();

    //     if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
    //         foreach ($pdfsGenerados as $archivo) {
    //             $zip->addFile($archivo, basename($archivo));
    //         }
    //         $zip->close();
    //     }

    //     return response()->download($zipPath)->deleteFileAfterSend(true);
    // }

    public function generarPDF_env(Request $request)
    {
        echo 'Hola';
    }

    public function pdf_vacio()
    {
        // Aquí cargas una vista muy simple (puedes crear un blade vacío o con texto simple)
        $pdf = SnappyPdf::loadView('file_control.pdf.reporte_vacio');

        // Lo envías para que se descargue (o inline si quieres verlo en navegador)
        return $pdf->inline('reporte_vacio.pdf');
    }

    public function generarPDF(Request $request)
    {
        set_time_limit(0);
        $resultados  = $request->input('resultados');
        $sinCaratula = (bool) $request->input('sinCaratula', false);
        //Agrupar los datos para mostrar en la carátula
        $unicos = [];
        $nombreNuevo = 'Reporte';

        foreach ($resultados as $item) {
            $clave = $item['codPersonal'];
            $nombreNuevo = $item['codPersonal'] . '_' . ($item['persona'] ?? '') . '_' . date('Ymd_Hi');

            if (!isset($unicos[$clave])) {
                $unicos[$clave] = [
                    'persona'     => $item['persona'],
                    'codPersonal' => $item['codPersonal'],
                    'sucursal'    => $item['sucursal'],
                    'cargo'       => $item['cargo'],
                ];
            } else {
                if (!empty($item['persona']))  $unicos[$clave]['persona']  = $item['persona'];
                if (!empty($item['sucursal'])) $unicos[$clave]['sucursal'] = $item['sucursal'];
                if (!empty($item['cargo']))    $unicos[$clave]['cargo']    = $item['cargo'];
            }
        }

        $personasUnicas = array_values($unicos);

        //Para los FORMATOS
        // $formatosBlade = [
        //     'ACTA DE COMPROMISO' => 'file_control.pdf.acta_compromiso',
        //     'DECLARACION JURADA ' => 'file_control.pdf.declaracion_jurada',
        // ];

        $urls = [];

        foreach ($resultados as $resultado) {
            if (! empty($resultado['ruta']) && ! empty($resultado['codPersonal'])) {
                $urls[] = [
                    'ruta' => $resultado['ruta'],
                    'codPersonal' => $resultado['codPersonal'],
                    'ancho' => $resultado['ancho'],
                    'hojas' => $resultado['hojas'],
                    'documento' => $resultado['documento'],
                    'es_formato' => $resultado['es_formato'],
                ];
            }
        }

        try {
            $rutasLocales = PdfHelper::descargarImagenesLegajo($urls);
        } catch (\Exception $e) {
            Log::error('Error descargando imágenes legajo: ' . $e->getMessage());
            $rutasLocales = [];
        }

        $itemsFinales = [];

        foreach ($rutasLocales as $item) {
            $itemsFinales[] = [
                'es_formato'  => $item['es_formato'],
                'codPersonal' => $item['codPersonal'],
                'ruta'        => $item['ruta'],
                'documento'   => $item['documento'],
                'hojas'       => $item['hojas'],
                'ancho'       => $item['ancho'],
            ];
        }

        foreach ($resultados as $resultado) {
            if ($resultado['es_formato'] == 1) {
                try {
                    $itemsFinales[] = [
                        'es_formato'   => $resultado['es_formato'],
                        'codPersonal'  => $resultado['codPersonal'],
                        'documento'    => $resultado['documento'],
                        'nombre_vista' => $this->obtenerNombreVista($resultado),
                        'datos'        => $resultado,
                        'firma'        => public_path('temp_legajos') . '/FIRMAS/PERSONAL/'  . $resultado['codPersonal'] . '.jpg',
                        'huella'       => public_path('temp_legajos') . '/HUELLAS_DIGITALES/PERSONAL/' . $resultado['codPersonal'] . '.jpg',
                    ];
                    ImagenHelper::descargarImagenesFormato($resultado['codPersonal']);
                } catch (\Exception $e) {
                    Log::warning('Error formato ' . ($resultado['documento'] ?? '') . ' - persona ' . $resultado['codPersonal'] . ': ' . $e->getMessage());
                }
            }
        }

        //print_r($itemsFinales);
        $pdf = SnappyPdf::loadView('file_control.pdf.reporte', [
            'personas'    => $personasUnicas,
            'resultados'  => $resultados,
            'imagenes'    => $rutasLocales,
            'items'       => $itemsFinales,
            'sinCaratula' => $sinCaratula,
        ])->setOption('enable-local-file-access', true);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $nombreNuevo.'.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Nombre-Archivo' => $nombreNuevo.'.pdf', // <- aquí
        ]);
    }

    public function obtenerNombreVista($resultado)
    {
        $mapa = [
            'ACTA DE COMPROMISO' => 'file_control.pdf.acta_compromiso',
            'DECLARACION JURADA DE CUMPLIMIENTO DE DISPOSICIONES' => 'file_control.pdf.declaracion_jurada',
            'EVALUACION DEL POSTULANTE' => 'file_control.pdf.evaluacion_postulante',
            'COMPROMISO DE CONFIABILIDAD' => 'file_control.pdf.compromiso_confidencialidad',
            'CUMPLIMIENTO DE cumpliDISPOSICION' => 'file_control.pdf.dj_complumiento_disposicion',
            'ESTUDIO DE SEGURIDAD DE TRABAJADORES' => 'file_control.pdf.dj_complumiento_disposicion',
            'VISITA DOMICILIARIA' => 'file_control.pdf.visita_domiciliaria_concluciones',
        ];

        return $mapa[$resultado['documento']] ?? null;
    }

    public function generarReporteConsulta(Request $request)
    {
        $codigo = $request->input('valor');
        $data = FileControl::getReporteFiltro($codigo);

        return response()->json($data);
    }

    public function generarPDF2(Request $request)
    {
        $resultados = $request->input('resultados');
        $html = '';

        $vistas = [
            'file_control.pdf.evaluacion-postulante',
            'file_control.pdf.declaracion-jurada',
            'file_control.pdf.acta_compromiso',
            'file_control.pdf.compromiso_confidencialidad',
            'file_control.pdf.dj_cumplimiento_disposicion',
            'file_control.pdf.estudio_seguridad_empleado',
            'file_control.pdf.estudio_seguridad_empleado_2',
            'file_control.pdf.visita_domiciliaria',
            'file_control.pdf.visita_domiciliaria_concluciones',
        ];

        foreach ($vistas as $vista) {

            $html .= view($vista, [
                'resultados' => $resultados,
            ])->render();

            $html .= '<div style="page-break-after: always;"></div>';
        }

        $pdf = SnappyPdf::loadHTML($html)->setOption('enable-local-file-access', true);

        return response($pdf->inline('reporte_completo.pdf'))->header('Content-Type', 'application/pdf');
    }

    public function getAllCargo()
    {
        $data = FileControl::getAllCargo();

        return response()->json($data);
    }

    public function getClientes()
    {
        $data = FileControl::getClientes();

        return response()->json($data);
    }

    public function getClientesLegajos()
    {
        $data = FileControl::getClientesLegajos();

        return response()->json($data);
    }

    public function getLegajos(Request $request)
    {
        $cliente = $request->input('cliente');
        $cargo = $request->input('cargo');
        $codPersonal = $request->input('codigo');

        $legajos = FileControl::getLegajos($cliente, $cargo, $codPersonal);

        return response()->json($legajos);
    }

    public function getFoliosClienteCargo(Request $request)
    {
        $cliente = $request->input('cliente');
        $cargo = $request->input('cargo');

        $legajos = FileControl::getFoliosClienteCargo($cliente, $cargo);

        return response()->json($legajos);
    }

    public function getFoliosXLegajo_comercial($codCliente, $codCargo)
    {
        $folios = FileControl::getFoliosXLegajo_comercial($codCliente, $codCargo);

        return response()->json($folios);
    }

    public function getCargos()
    {
        $cargos = FileControl::getCargos();

        return response()->json($cargos);
    }

    public function getFolios()
    {
        $folios = DB::select('EXEC SW_LISTAR_FOLIOS');

        $ids = array_column($folios, 'codigo');
        
        // Sacamos responsable y cod_categoria directo de la tabla por si tu SP no lo trae
        $foliosExtra = DB::table('sw_folios')
            ->whereIn('codigo', $ids)
            ->select('codigo', 'codResponsable', 'cod_categoria')
            ->get()
            ->keyBy('codigo');

        // Sacamos los nombres de las categorías de tu otra tabla
        $categorias = DB::table('sw_folios_categoria')->pluck('nombre', 'codigo');

        foreach ($folios as $folio) {
            $extra = $foliosExtra->get($folio->codigo);
            
            $folio->cod_responsable = $extra ? $extra->codResponsable : null;
            $folio->cod_categoria = $extra ? $extra->cod_categoria : null; // Para que el botón editar lo lea
            
            // Le inyectamos el nombre de la categoría para pintarlo en la tabla
            $folio->nombre_categoria = ($extra && $extra->cod_categoria) 
                                        ? ($categorias->get($extra->cod_categoria) ?? 'SIN CATEGORÍA') 
                                        : 'SIN CATEGORÍA';
        }

        return response()->json($folios);
    }

    public function getFoliosPorCategoria($codCategoria)
    {
        $folios = DB::table('sw_folios')
            ->where('cod_categoria', $codCategoria)
            ->where('habilitado', 1)
            ->orderBy('nombre')
            ->get(['codigo', 'nombre']);

        return response()->json($folios);
    }

    public function getListaDJ(Request $request)
    {
        // Atrapamos la vigencia que viene del switch ('SI' o 'TD'). Si no viene, por defecto es 'SI'
        $vigencia = $request->input('vigencia', 'SI');
        $usuario = session('usuario') ?? '0';

        // Pasamos los parámetros nombrados explícitamente al Stored Procedure
        $DJ = DB::select('EXEC [dbo].[SW_LISTAR_PERSONAL_DJ_V2] @usuario = ?, @vigencia = ?', [
            $usuario,
            $vigencia
        ]);

        return response()->json($DJ);
    }

    public function getListaDJXusuario(Request $request)
    {
        $vigencia = $request->input('vigencia', 'SI');
        $usuario = session('usuario') ?? '0';

        $DJ = DB::select('EXEC [dbo].[SW_LISTAR_PERSONAL_DJ_V2] @usuario = ?, @vigencia = ?', [
            $usuario,
            $vigencia
        ]);

        return response()->json($DJ);
    }

    public function getListaDJ2026(Request $request)
    {
        $vigencia = $request->input('vigencia', 'SI');
        $usuario = session('usuario') ?? '0';

        $DJ = DB::select('EXEC [dbo].[SW_LISTAR_PERSONAL_DJ_2026_GESTION] @usuario = ?, @vigencia = ?', [
            $usuario,
            $vigencia
        ]);

        return response()->json($this->filtrarDjPorSucursalesAsignadas($DJ));
    }

    public function getListaDJMigracion()
    {
        $DJ = DB::select('EXEC [dbo].[SW_LISTAR_PERSONAL_DJ_MIGRACION_V2]');

        return response()->json($DJ);
    }

    public function ViewCargo()
    {
        // MIGRACIÓN A ELOQUENT: Cambié DB::table() por modelo Cargo
        // Uso de scopes (habilitado, operativo, administrativo) para código más limpio y seguro
        // Esto previene queries crudas que pueden ser vulnerables a SQL injection
        $todos = Cargo::habilitado()->count();
        $operativo = Cargo::operativo()->habilitado()->count();
        $administrativo = Cargo::administrativo()->habilitado()->count();

        return view('file_control.cargo', compact('todos', 'operativo', 'administrativo'));
    }

    public function ViewLegajo()
    {
        $notif = FileControl::listarNotificaciones();

        return view('file_control.legajos', [
            'notify' => $notif,
        ]);
    }

     public function getNotificaciones()
    {
        $notify = FileControl::listarNotificaciones();
        return response()->json($notify);
    }

    public function ViewFolios()
    {
        $periodos = FileControl::getPeriodos();

        $todos = Folio::habilitado()->count();
        $principal = Folio::habilitado()->where('obligatorio', 1)->count();
        $adicional = Folio::habilitado()->where('obligatorio', 0)->count();
        $documento = Folio::habilitado()->where('tipo', 1)->count();
        $formato = Folio::habilitado()->where('tipo', 2)->count();
        $certificado = Folio::habilitado()->where('tipo', 3)->count();

        $responsables = DB::table('si_solm.dbo.TIPO_CARGO')
            ->select('CODI_TIPO_CARG as codigo', 'DESC_TIPO_CARG as nombre')
            ->orderBy('CODI_TIPO_CARG')
            ->get();

        $categorias = DB::table('sw_folios_categoria')->where('habilitado', 1)->get();

        return view('file_control.folios', compact('periodos', 'todos', 'principal', 'adicional', 'documento', 'formato', 'certificado', 'responsables', 'categorias'));
    }

    public function ViewBusquedaLegajo()
    {
        $personal = FileControl::getPersonalLegajos();
        $cargos = FileControl::getCargos();
        //$clientes = FileControl::getClientes();
        $clientes = FileControl::getClientesLegajos();
        $clientes = FileControl::getClientes();

        return view('file_control.search_legajos', compact('personal', 'cargos', 'clientes'));
    }

    public function ViewLegajoPdf()
    {
        $sucursales = FileControl::getSucursales();
        $clientes = FileControl::getClientes(); 
        
        $cargos = DB::select("SELECT CODI_CARG AS codigo, DESC_CARGO AS nombre FROM si_solm.dbo.CARGOS WITH (NOLOCK) WHERE DESC_CARGO IS NOT NULL ORDER BY DESC_CARGO");

        return view('file_control.legajos_pdf', compact('sucursales', 'cargos', 'clientes'));
    }

    public function getPersonalLegajos(Request $request)
    {
        $personal = FileControl::getPersonalLegajos();

        return response()->json($personal);
    }

    public function getCoincidencias(Request $request)
    {
        $cliente = $request->input('cliente');
        $cargo = $request->input('cargo');

        $legajos = FileControl::getCoincidencias($cliente, $cargo);

        return response()->json($legajos);
    }

    //GUARDAR DATOS
    public function saveFolioPersona(Request $request)
    {
        // Validar los datos del formulario
        $validated = $request->validate([
            'fecha_emision' => 'required|date',
            'fecha_caducidad' => 'nullable|date',
            'imagenes.*' => 'required|file|mimes:jpg,jpeg|max:1228', // 1.2 MB
            'codPersonal' => 'required|string',
            'codFolio' => 'required|string',
        ]);

        $codPersonal = $request->input('codPersonal');
        $codFolio = $request->input('codFolio');
        $rutas = FileControl::getRutaFolio($codFolio); // siempre array
        $archivos = $request->file('imagenes');

        if (! $archivos || count($archivos) === 0) {
            return response()->json(['error' => 'No se recibieron archivos.'], 400);
        }

        $totalArchivos = count($archivos);
        $totalRutas = count($rutas);

        // Validar que la cantidad de rutas sea 1 o coincida con archivos
        if ($totalRutas !== 1 && $totalRutas !== $totalArchivos) {
            return response()->json([
                'error' => 'La cantidad de rutas no coincide con la cantidad de archivos.',
            ], 400);
        }

        // Recorrer y enviar cada archivo al microservicio
        foreach ($archivos as $index => $archivo) {
            if ($totalRutas === $totalArchivos) {
                // Un archivo por ruta → sin sufijo
                $nameFile = $codPersonal.'.jpg';
                $rutaArchivo = $rutas[$index];
            } else {
                // Múltiples archivos para una sola ruta → con sufijo
                $nameFile = $totalArchivos === 1
                    ? $codPersonal.'.jpg'
                    : $codPersonal.'_'.($index + 1).'.jpg';
                $rutaArchivo = $rutas[0];
            }

            $response = Http::withToken('457862h45hj7u5126h58d2s51s2s')
                ->attach('archivo', file_get_contents($archivo), $archivo->getClientOriginalName())
                ->post('http://190.116.178.163/apps/api/file-control/charge_file.php', [
                    'nameFile' => $nameFile,
                    'ruta' => $rutaArchivo,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'No se pudo guardar el archivo en el servidor remoto.',
                    'detalle' => $response->body(),
                ], 500);
            }
        }

        // Guardar en BD
        $inserted = FileControl::saveFolioPersonal(
            $validated['fecha_emision'],
            $validated['fecha_caducidad'],
            $codFolio,
            $codPersonal
        );

        if (! $inserted) {
            return response()->json(['error' => 'No se pudo guardar en base de datos.'], 500);
        }

        return response()->json(['message' => 'Folios guardados correctamente.']);
    }

    // public function getViewDocumentsPer($codPersonal, $codFolio)
    // {
    //     $result = FileControl::getViewPerDocs($codPersonal, $codFolio);

    //     if (empty($result)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No se encontraron rutas'
    //         ], 404);
    //     }

    //     $rutas = [];

    //     foreach ($result as $item) {

    //         if (!empty($item->ruta_aux)) {
    //             $rutas[] = $item->ruta_aux;
    //         }

    //         if (!empty($item->ruta)) {
    //             $rutas[] = $item->ruta;
    //         }
    //     }

    //     // limpiar nulls y duplicados
    //     $rutas = array_values(array_unique(array_filter($rutas)));

    //     return response()->json([
    //         'success' => true,
    //         'rutas' => $rutas
    //     ]);
    // }

    public function getViewDocumentsPer($codPersonal, $codFolio)
    {
        $result = FileControl::getViewPerDocs($codPersonal, $codFolio);

        if (empty($result)) {
            return response()->json(['success' => false, 'message' => 'No se encontraron rutas'], 404);
        }

        $extensiones = ['jpg', 'jpeg', 'png', 'pdf'];
        $rutasValidas = [];

        foreach ($result as $item) {
            $rutaEncontrada = false;

            // Probar primero con ruta_aux
            if (isset($item->ruta_aux)) {
                $rutaBase = str_replace('//', 'http://', $item->ruta_aux);

                foreach ($extensiones as $ext) {
                    $rutaConExt = $rutaBase . '.' . $ext;

                    if (self::urlExiste($rutaConExt)) {
                        $rutasValidas[] = $rutaConExt;
                        $rutaEncontrada = true;
                        break;
                    }
                }
            }

            // Si no se encontró nada en ruta_aux, probar con ruta
            if (!$rutaEncontrada && isset($item->ruta)) {
                $rutaBase = str_replace('//', 'http://', $item->ruta);

                foreach ($extensiones as $ext) {
                    $rutaConExt = $rutaBase . '.' . $ext;

                    if (self::urlExiste($rutaConExt)) {
                        $rutasValidas[] = $rutaConExt;
                        break;
                    }
                }
            }
        }

        if (empty($rutasValidas)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron archivos accesibles desde el servidor'
            ]);
        }

        return response()->json([
            'success' => true,
            'rutas' => $rutasValidas
        ]);
    }

    //  public function getViewDocumentsPer($codPersonal, $codFolio)
    // {
    //     $result = FileControl::getViewPerDocs($codPersonal, $codFolio);

    //     if (empty($result)) {
    //         return response()->json(['success' => false, 'message' => 'No se encontraron rutas'], 404);
    //     }

    //     $extensiones = ['jpg', 'jpeg', 'png', 'pdf'];
    //     $rutasValidas = [];

    //     foreach ($result as $item) {
    //         $rutaEncontrada = false;

    //         // Probar primero con ruta_aux
    //         if (isset($item->ruta_aux)) {
    //             $rutaBase = str_replace('//', 'http://', $item->ruta_aux);

    //             foreach ($extensiones as $ext) {
    //                 $rutaConExt = $rutaBase . '.' . $ext;

    //                 if (self::urlExiste($rutaConExt)) {
    //                     $rutasValidas[] = $rutaConExt;
    //                     $rutaEncontrada = true;
    //                     break;
    //                 }
    //             }
    //         }

    //         // Si no se encontró nada en ruta_aux, probar con ruta
    //         if (!$rutaEncontrada && isset($item->ruta)) {
    //             $rutaBase = str_replace('//', 'http://', $item->ruta);

    //             foreach ($extensiones as $ext) {
    //                 $rutaConExt = $rutaBase . '.' . $ext;

    //                 if (self::urlExiste($rutaConExt)) {
    //                     $rutasValidas[] = $rutaConExt;
    //                     break;
    //                 }
    //             }
    //         }
    //     }

    //     if (empty($rutasValidas)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No se encontraron archivos accesibles desde la red'
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'rutas' => $rutasValidas
    //     ]);
    // }

    public function saveDjFolio(Request $request)
    {
        $request->validate([
            'fecha_emision' => 'required|date',
            'codPersonal' => 'required',
            'pdf' => 'required|file|mimes:pdf|max:5120',
        ]);

        try {
            $codPersonal = $request->input('codPersonal');
            $archivo = $request->file('pdf');
            $nameFile = $codPersonal.'.pdf';

            $dirDestino = '\\\\192.168.10.2\\Biblioteca_Grafica\\DOCUMENTOS_PERS\\DJ_2026';
            $rutaFinal = $dirDestino.'\\'.$nameFile;

            if (! is_dir($dirDestino)) {
                if (! @mkdir($dirDestino, 0777, true)) {
                    return response()->json([
                        'error' => 'No se pudo crear el directorio destino',
                        'ruta' => $dirDestino,
                    ], 500);
                }
            }

            $contenido = file_get_contents($archivo->getRealPath());
            if ($contenido === false) {
                return response()->json(['error' => 'No se pudo leer el archivo subido'], 200);
            }

            $resultado = @file_put_contents($rutaFinal, $contenido);
            if ($resultado === false) {
                return response()->json([
                    'error' => 'No se pudo guardar el archivo en el servidor de archivos',
                    'ruta' => $rutaFinal,
                ], 500);
            }

            $rutaArchivo = '//190.116.178.163/Biblioteca_Grafica/DOCUMENTOS_PERS/DJ_2026/'.$codPersonal.'.pdf';

            FileControl::saveDjFolioPersonal(
                $request->input('fecha_emision'),
                $codPersonal,
                $rutaArchivo
            );

            return response()->json(['message' => 'DJ guardado correctamente']);
        } catch (\Exception $e) {
            Log::error('saveDjFolio error: '.$e->getMessage());

            return response()->json([
                'error' => 'Error interno al guardar DJ',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }

    // public function saveDjFolioAux(Request $request)
    // {
    //     $request->validate([
    //         'fecha_emision' => 'required|date',
    //         'codPersonal'   => 'required',
    //         'pdf'           => 'required|file|mimes:pdf|max:5120',
    //     ]);

    //     try {
    //         $codPersonal = $request->input('codPersonal');
    //         $usuario = session('usuario');
    //         $archivo     = $request->file('pdf');
    //         $nameFile    = $codPersonal. '.pdf';

    //         $dirDestino = '\\\\192.168.10.5\\Extranet_2024\\storage_app\\sisolmar\\DJ';
    //         $rutaFinal  = $dirDestino . '\\' . $nameFile;

    //         if (!is_dir($dirDestino)) {
    //             if (!@mkdir($dirDestino, 0777, true)) {
    //                 return response()->json([
    //                     'error' => 'No se pudo crear el directorio destino',
    //                     'ruta'  => $dirDestino,
    //                 ], 500);
    //             }
    //         }

    //         $contenido = file_get_contents($archivo->getRealPath());
    //         if ($contenido === false) {
    //             return response()->json(['error' => 'No se pudo leer el archivo subido'], 500);
    //         }

    //         $resultado = @file_put_contents($rutaFinal, $contenido);
    //         if ($resultado === false) {
    //             return response()->json([
    //                 'error' => 'No se pudo guardar el archivo en el servidor de archivos',
    //                 'ruta'  => $rutaFinal,
    //             ], 500);
    //         }

    //         $rutaArchivo = '//190.116.178.163:86/storage_app/sisolmar/DJ/' . $codPersonal . '.pdf';

    //         FileControl::saveDjFolioPersonalAux(
    //             $request->input('fecha_emision'),
    //             $codPersonal,
    //             $rutaArchivo,
    //             $usuario
    //         );

    //         return response()->json(['message' => 'DJ guardado correctamente']);
    //     } catch (\Exception $e) {
    //         Log::error('saveDjFolio error: ' . $e->getMessage());
    //         return response()->json([
    //             'error'   => 'Error interno al guardar DJ',
    //             'detalle' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function saveDjFolioAux(Request $request)
    {
        try {
            $request->validate([
                'fecha_emision' => 'required|date',
                'codPersonal' => 'required|digits:5',
                'pdf' => 'required|file|mimes:pdf|max:5120',
            ]);

            $codPersonal = $request->input('codPersonal');
            $usuario = session('usuario');
            $archivo = $request->file('pdf');

            if (! $archivo) {
                return response()->json([
                    'error' => 'No se recibió el archivo',
                ], 400);
            }

            $nameFile = $codPersonal.'.pdf';

            // Guardar en la carpeta compartida del servidor de producción.
            $directorioDj = config('firmahuella.destinos.dj');
            File::ensureDirectoryExists($directorioDj, 0770, true);
            $rutaFisica = $directorioDj . DIRECTORY_SEPARATOR . $nameFile;
            $contenido = file_get_contents($archivo->getRealPath());
            if ($contenido === false || file_put_contents($rutaFisica, $contenido, LOCK_EX) !== strlen($contenido)) {
                return response()->json(['error' => 'No se pudo guardar el archivo PDF en la carpeta compartida del servidor.'], 500);
            }

            $rutaStorage = 'dj/2026/'.$nameFile;
            $rutaArchivo = 'file://192.168.10.5/Extranet_2024/apps/sisolmar/storage/app/'.$rutaStorage;

            // Guardar en BD
            FileControl::saveDjFolioPersonalAux(
                $request->input('fecha_emision'),
                $codPersonal,
                $rutaArchivo,
                $usuario
            );

            return response()->json([
                'message' => 'Archivo guardado en storage correctamente',
                'ruta_storage' => $rutaStorage,
                'ruta_fisica' => $rutaFisica,
            ]);

        } catch (\Throwable $e) {

            \Log::error('Error saveDjFolioAux STORAGE', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'error' => 'Error al guardar en storage',
                'detalle' => $e->getMessage(),
            ], 500);
        }
        //     try {
        //     Log::info('=== INICIO saveDjFolioAux ===', [
        //         'ip' => $request->ip(),
        //         'fecha_emision' => $request->input('fecha_emision'),
        //         'codPersonal' => $request->input('codPersonal'),
        //         'has_file_pdf' => $request->hasFile('pdf'),
        //         'session_usuario' => session('usuario'),
        //     ]);

        //     $request->validate([
        //         'fecha_emision' => 'required|date',
        //         'codPersonal'   => 'required',
        //         'pdf'           => 'required|file|mimes:pdf|max:5120',
        //     ]);

        //     $codPersonal = $request->input('codPersonal');
        //     $usuario     = session('usuario');
        //     $archivo     = $request->file('pdf');
        //     $nameFile    = $codPersonal . '.pdf';

        //     $dirDestino = '\\\\192.168.10.2\\Biblioteca_Grafica\\DOCUMENTOS_PERS\\DJ_2026';
        //     $rutaFinal  = $dirDestino . '\\' . $nameFile;

        //     Log::info('Datos principales', [
        //         'codPersonal' => $codPersonal,
        //         'usuario' => $usuario,
        //         'nameFile' => $nameFile,
        //         'dirDestino' => $dirDestino,
        //         'rutaFinal' => $rutaFinal,
        //         'archivo_original' => $archivo ? $archivo->getClientOriginalName() : null,
        //         'archivo_mime' => $archivo ? $archivo->getMimeType() : null,
        //         'archivo_size' => $archivo ? $archivo->getSize() : null,
        //         'archivo_tmp' => $archivo ? $archivo->getRealPath() : null,
        //     ]);

        //     if (!$archivo) {
        //         throw new \Exception('No se recibió el archivo PDF en el request.');
        //     }

        //     $existeDir = is_dir($dirDestino);
        //     $escribibleDir = @is_writable($dirDestino);

        //     Log::info('Estado carpeta destino', [
        //         'existeDir' => $existeDir,
        //         'escribibleDir' => $escribibleDir,
        //         'dirDestino' => $dirDestino,
        //     ]);

        //     if (!$existeDir) {
        //         Log::warning('La carpeta no existe, se intentará crear', [
        //             'dirDestino' => $dirDestino
        //         ]);

        //         if (!file_exists($dirDestino)) {
        //             $mk = mkdir($dirDestino, 0777, true);

        //             if (!$mk) {
        //                 throw new \Exception('No se pudo crear directorio: ' . $dirDestino);
        //                 }
        //         }

        //         Log::info('Resultado mkdir', [
        //             'resultado' => $mk,
        //             'dirDestino' => $dirDestino,
        //             'error_get_last' => error_get_last(),
        //         ]);

        //         if (!$mk) {
        //             return response()->json([
        //                 'error' => 'No se pudo crear el directorio destino',
        //                 'ruta'  => $dirDestino,
        //                 'debug' => [
        //                     'error_get_last' => error_get_last(),
        //                     'is_dir_post' => is_dir($dirDestino),
        //                     'is_writable_post' => @is_writable($dirDestino),
        //                 ]
        //             ], 500);
        //         }
        //     }

        //     $tmpPath = $archivo->getRealPath();

        //     if (!$tmpPath || !file_exists($tmpPath)) {
        //         throw new \Exception('El archivo temporal no existe o no se puede acceder.');
        //     }

        //     Log::info('Archivo temporal OK', [
        //         'tmpPath' => $tmpPath,
        //         'tmp_exists' => file_exists($tmpPath),
        //         'tmp_readable' => is_readable($tmpPath),
        //     ]);

        //     $contenido = file_get_contents($tmpPath);

        //     Log::info('Resultado lectura archivo temporal', [
        //         'contenido_false' => ($contenido === false),
        //         'bytes_leidos' => ($contenido !== false ? strlen($contenido) : 0),
        //         'error_get_last' => error_get_last(),
        //     ]);

        //     if ($contenido === false) {
        //         return response()->json([
        //             'error' => 'No se pudo leer el archivo subido',
        //             'debug' => [
        //                 'tmpPath' => $tmpPath,
        //                 'error_get_last' => error_get_last(),
        //             ]
        //         ], 500);
        //     }

        //     $resultado = file_put_contents($rutaFinal, $contenido);

        //     Log::info('Resultado file_put_contents', [
        //         'resultado' => $resultado,
        //         'rutaFinal' => $rutaFinal,
        //         'archivo_existe_final' => file_exists($rutaFinal),
        //         'error_get_last' => error_get_last(),
        //     ]);

        //     if ($resultado === false) {
        //         return response()->json([
        //             'error' => 'No se pudo guardar el archivo en el servidor de archivos',
        //             'ruta'  => $rutaFinal,
        //             'debug' => [
        //                 'dirExiste' => is_dir($dirDestino),
        //                 'dirEscribible' => @is_writable($dirDestino),
        //                 'archivo_final_existe' => file_exists($rutaFinal),
        //                 'error_get_last' => error_get_last(),
        //             ]
        //         ], 500);
        //     }

        //     $rutaArchivo = '//190.116.178.163/Biblioteca_Grafica/DOCUMENTOS_PERS/DJ_2026/' . $codPersonal . '.pdf';

        //     Log::info('Antes de guardar en BD', [
        //         'fecha_emision' => $request->input('fecha_emision'),
        //         'codPersonal' => $codPersonal,
        //         'rutaArchivo' => $rutaArchivo,
        //         'usuario' => $usuario,
        //     ]);

        //     $resultadoBD = FileControl::saveDjFolioPersonalAux(
        //         $request->input('fecha_emision'),
        //         $codPersonal,
        //         $rutaArchivo,
        //         $usuario
        //     );

        //     Log::info('Resultado saveDjFolioPersonalAux', [
        //         'resultadoBD' => $resultadoBD
        //     ]);

        //     Log::info('=== FIN saveDjFolioAux OK ===', [
        //         'codPersonal' => $codPersonal,
        //         'rutaFinal' => $rutaFinal,
        //     ]);

        //     return response()->json([
        //         'message' => 'DJ guardado correctamente',
        //         'debug' => [
        //             'rutaFinal' => $rutaFinal,
        //             'rutaArchivo' => $rutaArchivo,
        //             'bytes_guardados' => $resultado,
        //         ]
        //     ]);
        // } catch (\Illuminate\Validation\ValidationException $e) {
        //     Log::error('VALIDATION ERROR saveDjFolioAux', [
        //         'errors' => $e->errors(),
        //         'request' => $request->all(),
        //     ]);

        //     return response()->json([
        //         'error' => 'Error de validación',
        //         'detalle' => $e->errors(),
        //     ], 422);
        // } catch (\Throwable $e) {
        //     Log::error('ERROR GENERAL saveDjFolioAux', [
        //         'message' => $e->getMessage(),
        //         'file' => $e->getFile(),
        //         'line' => $e->getLine(),
        //         'trace' => $e->getTraceAsString(),
        //         'request' => $request->all(),
        //         'error_get_last' => error_get_last(),
        //     ]);

        //     return response()->json([
        //         'error'   => 'Error interno al guardar DJ',
        //         'detalle' => $e->getMessage(),
        //         'linea'   => $e->getLine(),
        //         'archivo' => $e->getFile(),
        //         'debug'   => [
        //             'error_get_last' => error_get_last(),
        //         ]
        //     ], 500);
        // }
    }

    // Función para validar si la URL existe
    private static function urlExiste($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $statusCode === 200;
    }


    // public function saveFolioPersona(Request $request)
    // {
    //     $validated = $request->validate([
    //         'fecha_emision'     => 'required|date',
    //         'fecha_caducidad'   => 'nullable|date',
    //         'imagenes.*'        => 'required|file|mimes:jpg,jpeg,png|max:2048',
    //         'codPersonal'       => 'required|string',
    //         'codFolio'          => 'required|string',
    //     ]);

    //     try {
    //         $codPersonal = $validated['codPersonal'];
    //         $codFolio    = $validated['codFolio'];
    //         $archivos    = $request->file('imagenes');
    //         $total       = count($archivos);

    //         // Obtener ruta lógica relativa
    //         $subCarpeta = FileControl::getRutaFolio($codFolio); // Ejemplo: "FOLIOS/2025/F123"

    //         foreach ($archivos as $index => $archivo) {
    //             $nombreArchivo = ($total === 1)
    //                 ? "{$codPersonal}.jpg"
    //                 : "{$codPersonal}_" . ($index + 1) . ".jpg";

    //             $rutaRelativa = $subCarpeta . '/' . $nombreArchivo;

    //             if (App::environment('local')) {
    //                 // Guardado directo en ruta de red en entorno local
    //                 $rutaFinal = "\\\\192.168.10.5:86\\sisolmar\\DATA_SISOLMAR\\" . $subCarpeta . "\\" . $nombreArchivo;

    //                 if (!file_exists(dirname($rutaFinal))) {
    //                     mkdir(dirname($rutaFinal), 0777, true);
    //                 }

    //                 file_put_contents($rutaFinal, file_get_contents($archivo->getRealPath()));
    //             }

    //             if (App::environment('production')) {
    //                 // Guardado en D:\ mediante Storage
    //                 Storage::disk('disk_d')->put($rutaRelativa, file_get_contents($archivo->getRealPath()));
    //             }
    //         }

    //         // Lógica adicional de BD
    //         FileControl::saveFolioPersonal(
    //             $validated['fecha_emision'],
    //             $validated['fecha_caducidad'],
    //             $codFolio,
    //             $codPersonal
    //         );

    //         return response()->json(['message' => 'Folios del personal guardados correctamente.']);

    //     } catch (\Exception $e) {
    //         Log::error('Error al guardar folios: ' . $e->getMessage());
    //         return response()->json(['error' => 'Error interno al guardar archivos.'], 500);
    //     }
    // }

    public function saveFolioPersona3(Request $request)
    {
        \Log::info('TEMP DIR: '.sys_get_temp_dir());
        \Log::info('_FILES', $_FILES);
        \Log::info('REQUEST', $request->all());

        return response()->json(['message' => 'Revisado en log.'], 200);
    }

    public function saveFolioPersona2(Request $request)
    {
        /*$fechaEmision = $request->input('fecha_emision');
        $fechaCaducidad = $request->input('fecha_caducidad');
        $codFolio = $request->input('codFolio');
        $codPersonal = $request->input('codPersonal');

        $inserted = FileControl::saveFolioPersonal($fechaEmision,$fechaCaducidad,$codFolio,$codPersonal);

        if ($inserted) {
            return response()->json(['success' => true, 'message' => 'El folio del personal, guardado correctamente']);
        } else {
            return response()->json(['success' => false, 'message' => 'Error al guardar el folio'], 500);
        }*/

        $cant = 0;
        if ($request->hasFile('imagenes')) {
            foreach ($request->file('imagenes') as $archivo) {
                //$nombre = time() . '_' . $archivo->getClientOriginalName();
                //$archivo->move(public_path('archivos_folio'), $nombre);
                $cant = $cant + 1;
            }
        }

        return response()->json('hay '.$cant.' archivos');

    }

    //GUARDAR EN PUBLIC
    public function saveFolioPersona0(Request $request)
    {
        $request->validate([
            'fecha_emision' => 'required|date',
            'fecha_caducidad' => 'nullable|date',
            'codFolio' => 'required|integer',
            'codPersonal' => 'required|string',
            'imagenes' => 'required|array',
            'imagenes.*' => 'image|max:5120', // máximo 5MB por imagen (ajusta según tu necesidad)
        ]);

        $fechaEmision = $request->input('fecha_emision');
        $fechaCaducidad = $request->input('fecha_caducidad');
        $codFolio = $request->input('codFolio');
        $codPersonal = $request->input('codPersonal');
        $imagenes = $request->file('imagenes');

        // Obtiene las rutas de carpeta según el folio
        $subCarpetas = FileControl::getRutaFolio($codFolio); // devuelve un array de rutas

        if (count($subCarpetas) !== count($imagenes)) {
            return response()->json([
                'error' => 'El número de imágenes no coincide con el número de rutas esperadas para el folio.',
            ], 400);
        }

        foreach ($imagenes as $index => $imagen) {
            $rutaSubcarpeta = $subCarpetas[$index] ?? null;

            if (! $rutaSubcarpeta) {
                continue; // O lanza un error si lo prefieres
            }

            $rutaDestino = public_path('Biblioteca_Grafica/'.$rutaSubcarpeta);

            // Crea la carpeta si no existe
            if (! file_exists($rutaDestino)) {
                mkdir($rutaDestino, 0777, true);
            }

            // Guarda la imagen con el nombre del codPersonal
            $nombreArchivo = $codPersonal.'.'.$imagen->getClientOriginalExtension();

            $imagen->move($rutaDestino, $nombreArchivo);
        }

        // Aquí podrías guardar los datos en la BD si lo necesitas
        // ...

        return response()->json([
            'message' => 'Folio guardado exitosamente.',
        ]);

    }

    //DE EVER
    /*public function saveFolioPersona(Request $request)
    {
        $validated = $request->validate([
            'fecha_emision'     => 'required|date',
            'fecha_caducidad'   => 'nullable|date',
            'imagenes.*'        => 'required|file|mimes:jpg,jpeg,png|max:2048',
            'codPersonal'       => 'required|string',
            'codFolio'          => 'required|string',
        ]);

        try {
            $codPersonal = $validated['codPersonal'];
            $codFolio    = $validated['codFolio'];
            $archivos    = $request->file('imagenes');
            $total       = count($archivos);

            // Subcarpeta relativa dentro de public/
            $subCarpeta = FileControl::getRutaFolio($codFolio); // ejemplo: "folios/F123"
            $rutaBase = public_path($subCarpeta);

            if (!File::exists($rutaBase)) {
                File::makeDirectory($rutaBase, 0777, true);
            }

            foreach ($archivos as $index => $archivo) {
                $nombreArchivo = ($total === 1)
                    ? "{$codPersonal}.jpg"
                    : "{$codPersonal}_" . ($index + 1) . ".jpg";

                $rutaFinal = $rutaBase . '/' . $nombreArchivo;

                // Guardar archivo en public/
                file_put_contents($rutaFinal, file_get_contents($archivo->getRealPath()));
            }

            // Guardar en BD
            FileControl::saveFolioPersonal(
                $validated['fecha_emision'],
                $validated['fecha_caducidad'],
                $codFolio,
                $codPersonal
            );

            return response()->json(['message' => 'Folios guardados correctamente.']);

        } catch (\Exception $e) {
            Log::error('Error al guardar folios en public/: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al guardar archivos.'], 500);
        }
    }
        */

    public function saveFolio(Request $request)
    {
        // 1. AGREGA LA VALIDACIÓN PARA QUE NO TE METAN BASURA
        $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|integer',
            'responsable' => 'required|integer',
            'cod_categoria' => 'required|integer' // <-- ESTO ES NUEVO
        ]);

        $codigo = $request->input('codigo');
        $nombre = $request->input('nombre');
        $tipo = $request->input('tipo');
        $obligatorio = $request->input('obligatorio');
        $vencimiento = $request->input('vencimiento');
        $tipo_fecha = $request->input('periodo');
        $plataforma = $request->input('plataforma');
        $responsable = $request->input('responsable');

        $solo_lectura = $request->input('solo_lectura');
        
        // 2. ATRAPA EL DATO QUE VIENE DEL FRONT
        $cod_categoria = $request->input('cod_categoria'); 
        $usuario = session('usuario');

        // 3. PÁSALE LA VARIABLE AL MODELO (Lo agregué al final de los parámetros)
        if (empty($codigo)) {
            $result = FileControl::saveFolio($nombre, $tipo, $obligatorio, $vencimiento, $tipo_fecha, $plataforma, $responsable, $usuario, $cod_categoria, $solo_lectura);
        } else {
            $result = FileControl::updateFolio($codigo, $nombre, $tipo, $obligatorio, $vencimiento, $tipo_fecha, $plataforma, $responsable, $usuario, $cod_categoria, $solo_lectura);
        }

        if ($result) {
            return response()->json(['success' => true, 'message' => 'Folio guardado correctamente']);
        }

        return response()->json(['success' => false, 'message' => 'Error al guardar el folio'], 500);
    }

    public function disabledFolio(Request $request)
    {
        $codigo = $request->codigo;
        $result = FileControl::disabledFolio($codigo);

        if ($result) {
            return response()->json(['message' => 'Folio deshabilitado exitosamente'], 200);
        } else {
            return response()->json(['message' => 'Folio no encontrado o error al deshabilitar'], 404);
        }
    }

    public function activarFolio(Request $request)
    {
        $codigo = $request->codigo;
        $result = FileControl::activarFolio($codigo);

        if ($result) {
            return response()->json(['message' => 'Folio activado exitosamente'], 200);
        } else {
            return response()->json(['message' => 'Folio no encontrado o error al activar'], 404);
        }
    }

    public function saveCargo(Request $request)
      {
          $request->validate([
              'nombre'    => 'required|string|max:50',
              'tipoCargo' => 'required',
          ]);

          $descripcion     = $request->input('descripcion');
          $nombre          = $request->input('nombre');
          $cod_tipo        = $request->input('tipoCargo');
          $abreviatura     = $request->input('abreviatura');
          $cod_servicio    = $request->input('codPosicion');
          $cod_subservicio = $request->input('codGrupo');
          $cod_area        = $request->input('codArea');
          $usuario         = session('usuario');

          $result = FileControl::saveCargo($descripcion, $nombre, $abreviatura, $cod_servicio, $cod_subservicio, $cod_tipo, $cod_area, $usuario);

          if ($result) {
              return response()->json(['success' => true, 'message' => 'Cargo creado correctamente']);
          }

          return response()->json(['success' => false, 'message' => 'Error al crear el cargo'], 500);
      }

    public function dashboard()
    {
        return view('file_control.dashboard');
    }

    //-----------------

    public function getFoliosXLegajo($codCliente, $codCargo)
    {
        $folios = FileControl::getFoliosXLegajo($codCliente, $codCargo);

        return response()->json($folios);
    }

    public function getAreas()
    {
        $areas = FileControl::getAreas();

        return response()->json($areas);
    }

    public function getPosicion()
    {
        $data = FileControl::getPosicion();

        return response()->json($data);
    }

    public function getGrupo()
    {
        $data = FileControl::getGrupo();

        return response()->json($data);
    }

    public function getGrupoId($codigo)
    {
        //$codigo = $request->input('codigo');
        $data = FileControl::getGrupoId($codigo);

        return response()->json($data);
    }

    public function saveLegajo(Request $request)
    {
        $folios = $request->input('folios');
        $codCliente = $request->input('codCliente');
        $codCargo = $request->input('codCargo');
        $codLegajo = $request->input('codLegajo');
        $nombre = $request->input('nombre');

        $usuario = session('usuario');

        if ($codLegajo != '0') {//MODIFICAR LEGAJO
            $codigos = FileControl::QuitarTodosLegajos($codLegajo, $usuario);

            for ($i = 0; $i < count($codigos); $i++) {
                if (! in_array($codigos[$i], $folios)) {
                    FileControl::actualizarNotificacionDes($codigos[$i], $codCliente, $codCargo);
                }
            }

            for ($i = 0; $i < count($folios); $i++) {
                $folio = $folios[$i];
                $validar = FileControl::validarLegajo($folio, $codCliente, $codCargo, $codLegajo);
                FileControl::actualizarNotificacion($folio, $codCliente, $codCargo);

                if (empty($validar)) {
                    FileControl::saveLegajo($folio, $codCliente, $codCargo, $codLegajo, $usuario);
                } else {
                    FileControl::updateLegajo($folio, $codCliente, $codCargo, $codLegajo, $usuario);
                }
            }
        } else {//CREAR NUEVO LEGAJO
            $legajo = FileControl::saveLegajoMain($nombre);

            for ($i = 0; $i < count($folios); $i++) {
                $folio = $folios[$i];
                $validar = FileControl::validarLegajo($folio, $codCliente, $codCargo, $legajo);
                FileControl::actualizarNotificacion($folio, $codCliente, $codCargo);
                if (empty($validar)) {
                    FileControl::saveLegajo($folio, $codCliente, $codCargo, $legajo, $usuario);
                }

            }
        }

         return response()->json(['success' => true, 'message' => 'Legajo guardado correctamente']);
    }

    /* GESTION DE CARGOS */
    public function insertarCargo(Request $request)
    {
        $tipo = $request->input('tipoCargo');
        $codArea = $request->input('codArea');
        $nombre = $request->input('nombre');
        $descripcion = $request->input('descripcion');
        $abreviatura = $request->input('abreviatura');
        $codPosicion = $request->input('codPosicion');
        $codGrupo = $request->input('codGrupo');

        $data = [
            $tipo,
            $codArea,
            $nombre,
            $descripcion,
            $abreviatura,
            $codPosicion,
            $codGrupo,
            'SISTEMA',
        ];

        $inserted = FileControl::insertarCargo($data);

        return response()->json(['message' => 'Cargo creado']);
    }


     public function checkNombreFolio(Request $request)
      {
          $nombre  = trim($request->input('nombre', ''));
          $excluir = $request->input('excluir');

          if (!$nombre) {
              return response()->json(['existe' => false]);
          }

          $existe = FileControl::existeNombreFolio($nombre, $excluir);

          return response()->json(['existe' => $existe]);
      }

    public function checkNombreCargo(Request $request)
      {
          $nombre  = trim($request->input('nombre', ''));
          $excluir = $request->input('excluir');

          if (!$nombre) {
              return response()->json(['existe' => false]);
          }

          $existe = FileControl::existeNombreCargo($nombre, $excluir);

          return response()->json(['existe' => $existe]);
      }

    public function updateCargo(Request $request)
      {
          $codigo      = $request->input('codigo');
          $tipo        = $request->input('tipoCargo');
          $codArea     = $request->input('codArea');
          $nombre      = $request->input('nombre');
          $descripcion = $request->input('descripcion');
          $abreviatura = $request->input('abreviatura');
          $codPosicion = $request->input('codPosicion');
          $codGrupo    = $request->input('codGrupo');
          $usuario     = session('usuario');

          FileControl::updateCargo($codigo, $tipo, $codArea, $nombre, $descripcion, $abreviatura, $codPosicion, $codGrupo, $usuario);

          return response()->json(['success' => true, 'message' => 'Cargo modificado correctamente']);
      }

    public function getCargosXCodigo($codigo)
    {
        $data = FileControl::getCargosXCodigo($codigo);

        return response()->json($data);
    }

    public function eliminarCargo(Request $request)
    {
        $codigo = $request->input('codigo');
        $data = 0;
        $inserted = FileControl::activarCargo($codigo, $data);

        return response()->json(['message' => 'Cargo modificdo']);
    }

    public function activarCargo(Request $request)
    {
        $codigo = $request->input('codigo');
        $data = 1;
        $inserted = FileControl::activarCargo($codigo, $data);

        return response()->json(['message' => 'Cargo modificdo']);
    }

    public function ViewLegajo_comercial()
    {

        return view('file_control.legajos_comercial');
    }

    public function saveSolicitud(Request $request)
    {
        $codigo = $request->input('codigo');
        $tiene = $request->input('tiene');
        $cargo = $request->input('cargo');
        $cliente = $request->input('cliente');

        $usuario = session('usuario');

        FileControl::saveSolicitud($codigo, $tiene, $cargo, $cliente, $usuario);

        return response()->json(['message' => 'Solicitud creado']);
    }

    public function getPostulantes()
    {
        try {
            // 1. Obtener postulantes APTOS de la BD de reclutamiento (reclusol)
            $postulantes = DB::connection('sqlsrv_prueba1')
                ->table('dbo.postulantes as p')
                ->join('dbo.estado_postulantes as ep', 'p.estado', '=', 'ep.id')
                ->select('p.*', 'ep.nombre as estado_nombre')
                ->where('ep.nombre', 'APTO')
                ->get();

            $ids = $postulantes->pluck('id')->toArray();

            // 2. Obtener datos ya guardados en la BD principal (sisolm_web)
            $personalData = DB::table('sw_MIGRA_PERSONAL')
                ->whereIn('CODI_PERS', $ids)
                ->get()
                ->keyBy('CODI_PERS');

            // 3. Fusionar datos: Priorizar lo que ya se guardó en la DJ de sisolm_web
            $data = $postulantes->map(function ($p) use ($personalData) {
                if (isset($personalData[$p->id])) {
                    $saved = $personalData[$p->id];

                    // Actualizar campos con lo guardado en la DJ
                    $p->dni = $saved->NRO_DOCU_IDEN ?? $p->dni;
                    $p->nombres = $saved->NOMB_1;
                    $p->apellido1 = $saved->APEL_1;
                    $p->apellido2 = $saved->APEL_2;
                    $p->direccion = $saved->DIRECCION ?? $p->direccion;
                    $p->correo = $saved->PERS_EMAIL ?? $p->correo;
                    $p->fecha_nacimiento = $saved->FECH_NACI ?? $p->fecha_nacimiento;

                    // Campos adicionales que no vienen de reclusol original pero se necesitan en el form
                    $p->departamento = $saved->DEPARTAMENTO;
                    $p->provincia = $saved->PROVINCIA;
                    $p->distrito = $saved->DISTRITO;
                    $p->sucamec = $saved->PERS_CONDISCAMEC;
                    $p->licencia_arma = $saved->PERS_NROLICENCIA;
                    $p->grado_instruccion = $saved->PERS_GRADO_INSTRUCCION;

                    // Para depuración
                    $p->source = 'sisolm_web';
                } else {
                    $p->source = 'reclusol';
                }

                return $p;
            });

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error en getPostulantes: '.$e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function getEstadoLegajos(Request $request)
    {
        $sucursal   = $request->input('sucursal', '');
        $cliente    = $request->input('cliente', '');
        $parametros = $request->input('parametros', '');

        $data = DB::select("
            SELECT CODIGO, PERSONAL, FOTO, DNI1, DNI2, HUELLA,
                    FIRMA, HUELLAS5, FOTOCONTROL1, FOTOCONTROL2, CD1, CD2,
                    LA, BREVETE1, BREVETE2, ESTUDIOS, LABORAL,
                    CROQUIS, FACHADA, ENTORNO, POLICIAL, PENAL, JUDICIAL,
                    TOXI_SOLMAR, TOXI_EXTERNO, MEDICO, PSICO, VACUNA, CV01, FIASINTO, CUL, DJ,
                    (SELECT SUCU_ABREVIATURA FROM si_solm.dbo.SISO_SUCURSAL WITH (NOLOCK) WHERE SUCU_CODIGO = ?) AS SUCURSAL,
                    INGRESO_PLANILLA, CARGO, TIPO, CLIENTE
            FROM si_solm.dbo.UF_LISTAR_ESTADO_LEGAJOS(?, ?, ?)
            ORDER BY PERSONAL
        ", [$sucursal, $sucursal, $cliente, $parametros]);

        return response()->json($data);
    }
}
