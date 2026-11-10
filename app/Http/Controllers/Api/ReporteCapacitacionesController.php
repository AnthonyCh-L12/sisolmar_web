<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ReporteFormatoHelpers;
use App\Http\Controllers\Controller;
use App\Models\Consulta;
use App\Models\CursoNew;
use App\Services\FirmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReporteCapacitacionesController extends Controller
{
    use ReporteFormatoHelpers;

    private ?Collection $cursosLocalesCache = null;
    private ?Collection $programacionesVigentesCache = null;
    private ?Collection $cursosPorNombreCache = null;

    public function cursos(Request $request): JsonResponse
    {
        try {
            $areaId = $request->areaId ?? null;
            $systemId = $request->systemId ?? null;

            $cursosRaw = DB::connection('mysql_grupoihb')
                ->select('CALL grupoihb_see.SP_OBTENER_CURSOS(NULL)');

            $categorias = collect(Consulta::obtenerAreas())
                ->keyBy(fn($area) => (string) ($area->codModdle ?? ''));

            $locales = $this->cursosLocales();
            $programaciones = $this->programacionesVigentes();
            $responsables = $this->responsablesMoodle();

            $cursos = collect($cursosRaw)
                ->map(function ($curso) use ($categorias, $locales, $programaciones, $responsables) {
                    $local = $locales->get((string) ($curso->course_id ?? ''));
                    $programacion = $local
                        ? $programaciones->get((string) ($local->CODIGO ?? ''))
                        : null;
                    $areaMoodle = $categorias->get((string) ($curso->category_id ?? ''));
                    $responsableMoodle = $responsables->get((string) ($curso->course_id ?? ''));

                    $nombreLocal = trim((string) ($local?->SW_CURS_NOMBRE ?? ''));
                    $descripcionLocal = trim((string) ($local?->SW_CURS_DESCRIPCION ?? ''));
                    $responsableLocal = trim((string) ($local?->responsable_nombre ?? ''));
                    $tipoLocal = trim((string) ($local?->tipo_descripcion ?? ''));
                    $planLocal = trim((string) ($local?->plan_nombre ?? ''));
                    $dirigidoLocal = trim((string) ($local?->dirigido_nombre ?? ''));
                    $clienteLocal = trim((string) ($local?->cliente_abreviatura ?? ''));

                    $fechaInicio = $this->toTimestamp($programacion?->fecha_inicio)
                        ?? $this->toTimestamp($curso->startdate ?? null);
                    $fechaFin = $this->toTimestamp($programacion?->fecha_final)
                        ?? $this->toTimestamp($curso->enddate ?? null);
                    $fechaCreacion = $local
                        ? $this->toTimestamp($local->fecha_creacion ?? null)
                        : null;
                    if ($fechaCreacion === null) {
                        $fechaCreacion = $this->toTimestamp($curso->created_at ?? null);
                    }

                    return [
                        'Id' => $curso->course_id ?? null,
                        'Avpr_Id' => $responsableMoodle?->AVPR_ID,
                        'LocalId' => $local?->CODIGO ?? $curso->course_idnumber ?? null,
                        'Codigo' => $local?->SW_CURS_COD ?? $curso->course_idnumber ?? null,
                        'AreaId' => ($local?->area_codigo_moodle ?: $areaMoodle?->codModdle) ?: null,
                        'SistemaId' => $local?->SW_CURS_SIST_GESTION_COD ?: null,
                        'Nombre' => $nombreLocal !== '' ? $nombreLocal : ($curso->course_name ?? 'Sin nombre'),
                        'Area' => ($local?->area_nombre ?? null) ?: ($areaMoodle?->nombre ?? 'Sin área'),
                        'Sistema' => ($local?->sistema_descripcion ?? null) ?: 'Sin sistema',
                        'Responsable' => $responsableLocal !== ''
                            ? $responsableLocal
                            : ($responsableMoodle?->RESPONSABLE ?? $curso->responsable ?? 'Sin responsable'),
                        'Descripcion' => $descripcionLocal !== ''
                            ? $descripcionLocal
                            : ($curso->course_summary ?? 'Sin descripción'),
                        'Tipo' => $tipoLocal !== '' ? $tipoLocal : 'Sin tipo',
                        'Plan' => $planLocal !== '' ? $planLocal : 'Sin plan',
                        'Dirigido' => $dirigidoLocal !== '' ? $dirigidoLocal : 'Sin dirigido',
                        'Cliente' => $clienteLocal !== '' ? $clienteLocal : 'Sin cliente',
                        'Total_Matriculados' => (int) ($curso->total_matriculados ?? 0),
                        'Fecha_Inicio' => $fechaInicio,
                        'Fecha_Fin' => $fechaFin,
                        'Fecha_Creacion' => $fechaCreacion,
                        'Anio_Curso' => $fechaCreacion !== null
                            ? (int) date('Y', $fechaCreacion)
                            : ($curso->course_year ?? null),
                        'Vigente' => $programacion !== null,
                    ];
                })
                ->when($areaId, fn($col) => $col->where('AreaId', $areaId))
                ->when($systemId, fn($col) => $col->where('SistemaId', $systemId))
                ->values();

            return response()->json([
                'success' => true,
                'Cursos' => $cursos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en ReporteCapacitacionesController@cursos', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cursos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function personal(Request $request): JsonResponse
    {
        try {
            $courseIds = $request->courseIds ?? null;
            $sucursalId = $request->sucursalId ?? null;
            $estadoId = $request->estadoId ?? 0;

            $courseIdsCsv = null;
            if (!empty($courseIds)) {
                $courseIdsCsv = implode(',', array_map('intval', (array) $courseIds));
            }

            $personal = Consulta::obtenerPersonalPorSucursal($sucursalId);

            $personalPorDni = collect($personal)
                ->unique('NRO_DOC')
                ->keyBy(fn($p) => trim($p->NRO_DOC));

            if ($courseIdsCsv) {
                $matriculados = collect(
                    DB::connection('mysql_grupoihb')->select(
                        'CALL grupoihb_see.SP_OBTENER_MATRICULADOS_CON_ESTADO(?, ?)',
                        [$courseIdsCsv, (int) $estadoId]
                    )
                );

                $cursos = $matriculados
                    ->groupBy('course_id')
                    ->map(function ($grupo) use ($personalPorDni) {
                        $primero = $grupo->first();

                        return [
                            'Curso' => $this->nombreCursoLocal(
                                $primero->course_id ?? null,
                                $primero->course_name ?? null
                            ),
                            'Personales' => $this->mapearPersonales($grupo, $personalPorDni),
                        ];
                    })
                    ->filter(fn($c) => count($c['Personales']) > 0)
                    ->values();

                return response()->json([
                    'success' => true,
                    'Cursos' => $cursos,
                ]);
            }

            $todosCursos = collect(
                DB::connection('mysql_grupoihb')->select(
                    'CALL grupoihb_see.SP_OBTENER_ESTADO_CURSOS_POR_USUARIOS(NULL, NULL, ?)',
                    [(int) $estadoId]
                )
            );

            $cursos = $todosCursos->map(function ($curso) use ($personalPorDni) {
                $matriculados = collect(json_decode($curso->usuarios, true))
                    ->map(fn($u) => (object) $u);

                return [
                    'Curso' => $this->nombreCursoLocal(
                        $curso->course_id ?? null,
                        $curso->course_name ?? null
                    ),
                    'Personales' => $this->mapearPersonales($matriculados, $personalPorDni, true),
                ];
            })->filter(fn($c) => count($c['Personales']) > 0)->values();

            return response()->json([
                'success' => true,
                'Cursos' => $cursos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en ReporteCapacitacionesController@personal', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function recordPersonal(Request $request): JsonResponse
    {
        try {
            $usernames = $request->usernames ?? [];
            $courseIds = $request->courseIds ?? [];
            $estadoId = $request->estadoId ?? 0;
            $desde = $request->desde ?? null;
            $hasta = $request->hasta ?? null;

            $usernamesStr = !empty($usernames) ? implode(',', $usernames) : null;
            $courseIdsStr = !empty($courseIds) ? implode(',', $courseIds) : null;

            $personal = collect(DB::select('EXEC SP_OBTENER_PERSONAL_BETA'))
                ->unique('NRO_DOC')
                ->keyBy(fn($p) => trim($p->NRO_DOC));

            $cursosRaw = collect(
                DB::connection('mysql_grupoihb')->select(
                    'CALL grupoihb_see.SP_OBTENER_ESTADO_CURSOS_POR_USUARIOS(?, ?, ?)',
                    [$usernamesStr, $courseIdsStr, (int) $estadoId]
                )
            );

            $cursosMatricula = $cursosRaw;

            if ((int) $estadoId !== 0) {
                $cursosMatricula = collect(
                    DB::connection('mysql_grupoihb')->select(
                        'CALL grupoihb_see.SP_OBTENER_ESTADO_CURSOS_POR_USUARIOS(?, ?, ?)',
                        [$usernamesStr, $courseIdsStr, 0]
                    )
                );
            }

            $cursos = $cursosRaw
                ->when($desde, fn($col) => $col->filter(
                    fn($c) => $c->course_timecreated >= strtotime($desde)
                ))
                ->when($hasta, fn($col) => $col->filter(
                    fn($c) => $c->course_timecreated <= strtotime($hasta . ' 23:59:59')
                ));

            $cursosPorUsuario = [];
            $inscritosPorUsuario = [];
            $inscritosPorUsuarioNombre = [];
            $nombresCursos = [];

            foreach ($cursosMatricula as $curso) {
                $courseId = (string) ($curso->course_id ?? '');
                $nombreCurso = $this->nombreCursoLocal(
                    $curso->course_id ?? null,
                    $curso->course_name ?? null
                );

                if ($courseId !== '') {
                    $nombresCursos[$courseId] = $nombreCurso;
                }

                foreach ((json_decode($curso->usuarios, true) ?: []) as $u) {
                    $username = trim((string) ($u['username'] ?? ''));

                    if ($username === '') continue;

                    if ($courseId !== '') {
                        $inscritosPorUsuario[$username][$courseId] = true;
                    }

                    $inscritosPorUsuarioNombre[$username][mb_strtoupper(trim($nombreCurso))] = true;
                }
            }

            foreach ($cursos as $curso) {
                $courseId = (string) ($curso->course_id ?? '');
                $usuarios = json_decode($curso->usuarios, true) ?: [];
                $nombreCurso = $this->nombreCursoLocal(
                    $curso->course_id ?? null,
                    $curso->course_name ?? null
                );

                foreach ($usuarios as $u) {
                    $cursosPorUsuario[trim($u['username'])][] = [
                        'CourseId' => $courseId,
                        'Nombre' => $nombreCurso,
                        'Estado' => strtoupper($u['estado']),
                        'Nota_Final' => $u['final_grade'] ?? 'Sin nota',
                        'Fecha_Nota' => $u['fecha_nota'] ?? null,
                        'Fecha_Ultimo_Acceso' => $u['ultimo_acceso'] ?? null,
                    ];
                }
            }

            foreach ((array) $courseIds as $courseId) {
                $key = (string) $courseId;

                if (!isset($nombresCursos[$key])) {
                    $nombresCursos[$key] = $this->nombreCursoLocal($courseId);
                }
            }

            $usernamesObjetivo = !empty($usernames)
                ? array_values(array_unique(array_map(
                    fn($u) => trim((string) $u),
                    (array) $usernames
                )))
                : array_keys($cursosPorUsuario);

            $personales = collect($usernamesObjetivo)
                ->map(function ($username) use (
                    $personal,
                    $cursosPorUsuario,
                    $courseIds,
                    $inscritosPorUsuario,
                    $inscritosPorUsuarioNombre,
                    $nombresCursos
                ) {
                    $persona = $personal->get($username);

                    if (!$persona) return null;

                    $cursosList = $cursosPorUsuario[$username] ?? [];
                    $idsEnLista = collect($cursosList)
                        ->pluck('CourseId')
                        ->filter()
                        ->map(fn($id) => (string) $id)
                        ->all();
                    $nombresEnLista = collect($cursosList)
                        ->pluck('Nombre')
                        ->map(fn($nombre) => mb_strtoupper(trim((string) $nombre)))
                        ->all();

                    foreach ((array) $courseIds as $courseId) {
                        $key = (string) $courseId;

                        if (in_array($key, $idsEnLista, true)) continue;

                        $nombreCurso = $nombresCursos[$key]
                            ?? $this->nombreCursoLocal($courseId);
                        $nombreKey = mb_strtoupper(trim($nombreCurso));

                        if (!empty($inscritosPorUsuario[$username][$key])) continue;
                        if (!empty($inscritosPorUsuarioNombre[$username][$nombreKey])) continue;
                        if (in_array($nombreKey, $nombresEnLista, true)) continue;

                        $cursosList[] = [
                            'CourseId' => $courseId,
                            'Nombre' => $nombreCurso,
                            'Estado' => 'NO MATRICULADO',
                            'Nota_Final' => null,
                            'Fecha_Nota' => null,
                            'Fecha_Ultimo_Acceso' => null,
                        ];

                        $idsEnLista[] = $key;
                        $nombresEnLista[] = $nombreKey;
                    }

                    return [
                        'CodigoPersonal' => $persona->CODIGO_PERSONAL ?? null,
                        'NombreCompleto' => $persona->NOMBRE_COMPLETO ?? null,
                        'NroDoc' => $persona->NRO_DOC ?? null,
                        'Sucursal' => $persona->SUCURSAL ?? null,
                        'TipoTrabajador' => $persona->TIPO_TRABAJADOR ?? null,
                        'Correo' => $persona->CORREO ?? null,
                        'Cargo' => $persona->CARGO ?? null,
                        'Cliente' => $persona->CLIENTE ?? 'Sin cliente',
                        'Cursos' => $cursosList,
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'Personales' => $personales,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en ReporteCapacitacionesController@recordPersonal', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function general(Request $request): JsonResponse
    {
        try {
            $usernames = $request->usernames ?? [];
            $courseIds = $request->courseIds ?? [];
            $estado = $request->estado ?? '';
            $desde = $request->desde ?? null;
            $hasta = $request->hasta ?? null;
            $cliente = $request->cliente ?? null;

            $usernamesStr = !empty($usernames) ? implode(',', $usernames) : null;
            $courseIdsStr = !empty($courseIds) ? implode(',', $courseIds) : null;

            $personal = collect(
                DB::connection('sqlsrv')->select('EXEC [dbo].[SP_OBTENER_PERSONAL_TODAS_EMPRESAS]')
            )
                ->unique('NRO_DOC')
                ->keyBy(fn($p) => trim($p->NRO_DOC));

            $cursos = collect(
                DB::connection('mysql_grupoihb')->select(
                    'CALL grupoihb_see.SP_OBTENER_ESTADO_CURSOS_POR_USUARIOS(?, ?, ?)',
                    [$usernamesStr, $courseIdsStr, 0]
                )
            )
                ->when($desde, fn($col) => $col->filter(
                    fn($c) => $c->course_timecreated >= strtotime($desde)
                ))
                ->when($hasta, fn($col) => $col->filter(
                    fn($c) => $c->course_timecreated <= strtotime($hasta . ' 23:59:59')
                ));

            $cursosPorUsuario = [];
            foreach ($cursos as $curso) {
                $usuarios = json_decode($curso->usuarios, true);
                $nombreCurso = $this->nombreCursoLocal(
                    $curso->course_id ?? null,
                    $curso->course_name ?? null
                );

                foreach ($usuarios as $u) {
                    $estadoUpper = strtoupper($u['estado'] ?? '');
                    if ($estado && $estadoUpper !== strtoupper($estado)) {
                        continue;
                    }

                    $cursosPorUsuario[trim($u['username'])][] = [
                        'Nombre' => $nombreCurso,
                        'Estado' => $estadoUpper,
                        'Nota_Final' => $u['final_grade'] ?? 'Sin nota',
                        'Fecha_Nota' => $u['fecha_nota'] ?? null,
                        'Fecha_Ultimo_Acceso' => $u['ultimo_acceso'] ?? null,
                    ];
                }
            }

            $personales = collect($cursosPorUsuario)
                ->map(function ($cursosList, $username) use ($personal) {
                    $persona = $personal->get($username);
                    if (!$persona) return null;

                    $empresa = trim(
                        $persona->EMPRESA
                            ?? $persona->EMPRESA_NOMBRE
                            ?? $persona->EMPR_CODIGO
                            ?? $persona->CLIENTE
                            ?? ''
                    );

                    return [
                        'CodigoPersonal' => trim($persona->CODIGO_PERSONAL ?? ''),
                        'NombreCompleto' => trim($persona->NOMBRE_COMPLETO ?? ''),
                        'NroDoc' => trim($persona->NRO_DOC ?? ''),
                        'Sucursal' => trim($persona->SUCURSAL ?? ''),
                        'Empresa' => $empresa,
                        'Cargo' => trim($persona->CARGO ?? ''),
                        'TipoTrabajador' => trim($persona->TIPO_TRABAJADOR ?? ''),
                        'Cursos' => $cursosList,
                    ];
                })
                ->filter()
                ->values();

            if ($cliente) {
                $clienteSel = collect(DB::select('EXEC SW_LISTAR_CLIENTES'))
                    ->firstWhere('codigo', $cliente);
                if ($clienteSel) {
                    $desc = strtolower(trim($clienteSel->abreviatura ?? $clienteSel->razon_social ?? ''));
                    $personales = $personales->filter(function ($p) use ($desc) {
                        $emp = strtolower(trim($p['Empresa'] ?? ''));

                        return $emp === $desc || str_contains($emp, $desc) || str_contains($desc, $emp);
                    })->values();
                }
            }

            return response()->json([
                'success' => true,
                'Personales' => $personales,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en ReporteCapacitacionesController@general', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function formato(Request $request): JsonResponse
    {
        try {
            $courseId = $request->input('courseId');
            $persDnis = array_values(array_filter((array) $request->input('persDnis', [])));

            $curso = DB::connection('mysql_grupoihb')
                ->select('CALL SP_OBTENER_DATOS_CURSO(?)', [$courseId])[0] ?? null;

            $cursoLocal = CursoNew::where('SW_CURS_AV_COD', $courseId)
                ->where('habilitado', 1)
                ->first();

            $nombreCurso = trim((string) ($cursoLocal?->SW_CURS_NOMBRE ?? ''));
            if ($nombreCurso === '') {
                $nombreCurso = $this->obtenerValorColumna($curso, ['course_name']);
            }

            $tipoCurso = '';
            if (!empty($cursoLocal?->SW_CURS_TIPO_COD)) {
                $tipoCurso = trim((string) DB::table('sw_cursos_tipo')
                    ->where('codigo', $cursoLocal->SW_CURS_TIPO_COD)
                    ->value('descripcion'));
            }

            $responsableDni = '';
            if (!empty($cursoLocal?->SW_CURS_RESP_COD)) {
                $responsable = DB::selectOne(
                    'SELECT LTRIM(RTRIM(P.NRO_DOCU_IDEN)) AS NRO_DOCU_IDEN
                     FROM si_solm.dbo.PERSONAL P
                     WHERE TRY_CAST(P.CODI_PERS AS INT) = ?',
                    [(int) $cursoLocal->SW_CURS_RESP_COD]
                );
                $responsableDni = trim((string) ($responsable?->NRO_DOCU_IDEN ?? ''));
            }

            if ($responsableDni === '') {
                $responsableDni = $this->obtenerValorColumna($curso, ['username']);

                if (empty($responsableDni)) {
                    $responsable = DB::selectOne(
                        'SELECT LTRIM(RTRIM(P.NRO_DOCU_IDEN)) AS NRO_DOCU_IDEN
                         FROM AV_WEB_CURSO_RELACION R
                         INNER JOIN si_solm.dbo.AV_PROGRAMA AV ON AV.AVPR_ID = R.AVPR_ID
                         INNER JOIN si_solm.dbo.PERSONAL P ON P.CODI_PERS = AV.CODI_PERS
                         WHERE R.MOODLE_COURSE_ID = ?',
                        [$courseId]
                    );
                    $responsableDni = $this->obtenerValorColumna($responsable, ['NRO_DOCU_IDEN']);
                }
            }

            $dnisCsv = implode(',', $persDnis);
            $aprobados = collect(
                DB::connection('mysql_grupoihb')->select(
                    'CALL SP_OBTENER_APROBADOS_CURSO(?, ?)',
                    [$courseId, $dnisCsv]
                )
            );

            $aprobados = $this->filtrarAprobados($aprobados);
            $dnisPersonal = $aprobados
                ->map(fn($row) => $this->obtenerDniColumna($row))
                ->filter()
                ->unique();

            $dnisFirma = $dnisPersonal
                ->push($responsableDni)
                ->filter()
                ->unique()
                ->values();

            $firmas = collect(
                DB::select('EXEC SP_OBTENER_FIRMA_PERSONAL ?', [implode(',', $dnisFirma->all())])
            )->keyBy(fn($row) => $this->obtenerDniColumna($row));

            $firmaResponsable = $firmas->get($responsableDni);

            $personal = $aprobados
                ->map(function ($row) use ($firmas) {
                    $dni = $this->obtenerDniColumna($row);
                    $firma = $firmas->get($dni);

                    return [
                        'Nombre_Personal' => mb_strtoupper($this->obtenerValorColumna($firma, ['NOMBRES'])),
                        'DNI_Personal' => $dni,
                        'Cargo_Personal' => mb_strtoupper($this->obtenerValorColumna($firma, ['CARGO'])),
                        'Firma_Personal' => FirmaService::toUrl($this->obtenerValorColumna($firma, ['RUTA_FIRMA'])),
                    ];
                })
                ->sortBy('Nombre_Personal', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            $cursoIdFallback = $curso?->course_id ?? $courseId;
            $tipoCursoFinal = $tipoCurso !== ''
                ? mb_strtoupper($tipoCurso)
                : $this->mapearCategoriaCurso(null, $cursoIdFallback);

            return response()->json([
                'success' => true,
                'Moodle_Id' => $cursoLocal?->SW_CURS_AV_COD ?? $cursoIdFallback,
                'Nombre_Curso' => mb_strtoupper($nombreCurso),
                'Nombre_Responsable' => mb_strtoupper($this->obtenerValorColumna($firmaResponsable, ['NOMBRES'])),
                'Cargo_Responsable' => mb_strtoupper($this->obtenerValorColumna($firmaResponsable, ['CARGO'])),
                'Firma_Responsable' => FirmaService::toUrl($this->obtenerValorColumna($firmaResponsable, ['RUTA_FIRMA'])),
                'Personal' => $personal,
                'Tipo_Curso' => $tipoCursoFinal,
                'Total_Aprobados' => count($personal),
                'Fecha_Reporte' => date('t/m/Y'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error en ReporteCapacitacionesController@formato', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los datos para el reporte.',
            ], 500);
        }
    }

    private function cursosLocales(): Collection
    {
        if ($this->cursosLocalesCache === null) {
            $this->cursosLocalesCache = Consulta::cursosLocalesReporte();
        }

        return $this->cursosLocalesCache;
    }

    private function programacionesVigentes(): Collection
    {
        if ($this->programacionesVigentesCache === null) {
            $this->programacionesVigentesCache = Consulta::programacionesVigentesReporte();
        }

        return $this->programacionesVigentesCache;
    }

    private function responsablesMoodle(): Collection
    {
        return collect(
            DB::connection('sqlsrv')->select("
                SELECT
                    R.MOODLE_COURSE_ID,
                    R.AVPR_ID,
                    P.APEL_1 + ' ' + P.APEL_2 + ' ' +
                    P.NOMB_1 + ' ' + ISNULL(P.NOMB_2, '') AS RESPONSABLE
                FROM AV_WEB_CURSO_RELACION R
                INNER JOIN si_solm.dbo.AV_PROGRAMA AV
                    ON AV.AVPR_ID = R.AVPR_ID
                INNER JOIN si_solm.dbo.PERSONAL P
                    ON P.CODI_PERS = AV.CODI_PERS
            ")
        )->keyBy(fn($row) => (string) ($row->MOODLE_COURSE_ID ?? ''));
    }

    private function nombreCursoLocal($courseId, ?string $nombreMoodle = null): string
    {
        if ($courseId !== null && $courseId !== '') {
            $local = $this->cursosLocales()->get((string) $courseId);
            $nombre = trim((string) ($local?->SW_CURS_NOMBRE ?? ''));

            if ($nombre !== '') {
                return $nombre;
            }

            $nombre = trim((string) ($nombreMoodle ?? ''));

            return $nombre !== '' ? $nombre : 'Sin nombre';
        }

        if ($this->cursosPorNombreCache === null) {
            $this->cursosPorNombreCache = $this->cursosLocales()
                ->filter(fn($row) => trim((string) ($row->SW_CURS_NOMBRE ?? '')) !== '')
                ->keyBy(fn($row) => mb_strtoupper(trim((string) $row->SW_CURS_NOMBRE)));
        }

        $local = $this->cursosPorNombreCache->get(mb_strtoupper(trim((string) ($nombreMoodle ?? ''))));
        $nombre = trim((string) ($local?->SW_CURS_NOMBRE ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        $nombre = trim((string) ($nombreMoodle ?? ''));

        return $nombre !== '' ? $nombre : 'Sin nombre';
    }

    private function mapearPersonales(
        Collection $matriculados,
        Collection $personalPorDni,
        bool $desdeJson = false
    ): array {
        return $matriculados
            ->map(function ($moodle) use ($personalPorDni, $desdeJson) {
                $username = trim($moodle->username ?? '');

                if ($username === '') return null;

                $persona = $personalPorDni->get($username);

                if (!$persona) return null;

                return [
                    'CodigoPers' => $persona->CODI_PERS,
                    'SucursalCodigo' => $persona->SUCU_CODIGO,
                    'NombreCompleto' => $persona->NOMBRE_COMPLETO,
                    'DNI' => $persona->NRO_DOC,
                    'Cargo' => $persona->CARGO ?? 'Sin asignar',
                    'TipoTrabajador' => $persona->TIPO_TRABAJADOR,
                    'Nota_Final' => $desdeJson ? null : ($moodle->final_grade ?? null),
                    'Estado' => strtoupper($moodle->estado ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function toTimestamp($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }
}
