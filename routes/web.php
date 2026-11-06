<?php

use App\Http\Controllers\ActualizacionesDjController;
use App\Http\Controllers\Api\NewCapacController;
use App\Http\Controllers\BiometricoController;
use App\Http\Controllers\CapacitacionController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DjController;
use App\Http\Controllers\ExamenesController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ReporteAvancesController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReportePersonalController;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\UbicacionController;
use App\Http\Controllers\UsuarioController;
use App\Mail\AlertaCaducidadMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'index'])->name('login');

Route::middleware(['auth'])->group(function () {

    // ─── RUTAS WEB (vistas y acciones directas) ───────────────────────────────
    Route::get('/dj/periodos/gestionar', [ActualizacionesDjController::class, 'index'])->name('dj.periodos.index');
    Route::get('/api/dj/periodos/listado', [ActualizacionesDjController::class, 'getPeriodos']);
    Route::get('/api/reporte-avances-dj', [App\Http\Controllers\DjController::class, 'reporteAvancesDj']);
    Route::get('/api/reporte-etapa4-dj', [App\Http\Controllers\DjController::class, 'reporteEtapa4Dj']);
    Route::get('/ver-dj/{codPersonal}', [FileController::class, 'verDjPdf'])->name('ver.dj');
    Route::post('/save-dj-folio', [FileController::class, 'saveDjFolio']);
    Route::get('/get-personal-dj', [FileController::class, 'getListaDJXusuario']);
    Route::get('/get-personal-dj-2026', [FileController::class, 'getListaDJ2026']);
    Route::post('/save-dj-folio-2', [FileController::class, 'saveDjFolioAux']);
    Route::get('/get-personal', [FileController::class, 'getPersonal']);
    Route::get('/get-personal-total', [FileController::class, 'getPersonalTotal']);
    Route::get('/carga-escaneo-dj', [FileController::class, 'ViewEscaneoDJ'])->name('carga.escaneo.dj');

    Route::get('/reporte/folios-por-vencer-cliente', [ReporteController::class, 'foliosPorVencerXCliente']);
    Route::post('/capacitacion/save-matricula', [CapacitacionController::class, 'saveMatricula'])->name('capacitacion.save-matricula');
    Route::get('/usuario', [LoginController::class, 'getUsuarioSession']);
    Route::get('/get-documentos/{personalId}', [FileController::class, 'getDocumentosXPersonal']);
    Route::get('/capacitacion/consulta-matriculas', [CapacitacionController::class, 'vistaConsultaMatriculas'])->name('capacitacion.consulta-matriculas');
    Route::get('/reporte/folios-por-vencer', [ReporteController::class, 'foliosPorVencer']);
    Route::post('/pdf_vacio', [FileController::class, 'pdf_vacio']);
    Route::post('/dash-rrhh', [FileController::class, 'dashboard']);
    Route::post('/generar-pdf', [FileController::class, 'generarPDF']);
    Route::post('/generar-pdf2', [FileController::class, 'generarPDF2']);
    Route::post('/save_cargo', [FileController::class, 'saveCargo']);

    // Route::get('/debug-notificaciones', function () {
    //     $usuarioId = \Illuminate\Support\Facades\Auth::id();
    //     $user = \Illuminate\Support\Facades\Auth::user();
    //     $notifs = \Illuminate\Support\Facades\DB::table('sw_notificaciones_matriculas')
    //         ->select('codigo', 'usuario_id', 'nombre_curso', 'tipo', 'leido', 'created_at')
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     return response()->json([
    //         'usuario_autenticado' => ['id' => $usuarioId, 'nombre' => $user->name ?? 'N/A', 'email' => $user->email ?? 'N/A'],
    //         'notificaciones_bd' => $notifs,
    //         'notificaciones_del_usuario' => \App\Models\NotificacionMatricula::where('usuario_id', $usuarioId)->get(),
    //     ]);
    // });

    // ─── RUTAS /api/* (antes en api.php) ─────────────────────────────────────
    Route::prefix('api')->group(function () {

        // Usuarios
        Route::get('/get-usuarios', [UsuarioController::class, 'getUsuarios']);
        Route::post('/save-usuario', [UsuarioController::class, 'store']);
        Route::post('/update-usuario', [UsuarioController::class, 'update']);
        Route::post('/toggle-usuario', [UsuarioController::class, 'toggleHabilitado']);
        Route::get('/get-sucursales-usuario/{codUsuario}', [UsuarioController::class, 'getSucursalesUsuario']);
        Route::post('/save-sucursales-usuario', [UsuarioController::class, 'saveSucursalesUsuario']);
        Route::get('/get-menus-usuario/{codUsuario}', [UsuarioController::class, 'getMenusUsuario']);
        Route::post('/save-menus-usuario', [UsuarioController::class, 'saveMenusUsuario']);

        // Personal
        Route::get('/get-personal', [FileController::class, 'getPersonal']);
        Route::get('/get-personal-total', [FileController::class, 'getPersonalTotal']);
        Route::get('/get-personal-total-reporte', [FileController::class, 'getPersonalTotalReporte']);
        Route::get('/get-personal-total-prueba', [FileController::class, 'getPersonalTotalPrueba']);
        Route::get('/get-personal-reporte-personal', [FileController::class, 'getPersonalReportePersonal']);
        Route::get('/get-personal-reporte-personal/detalle/{codPersonal}', [FileController::class, 'getDetallePersonalHistorial']);
        Route::get('/get-personal-legajos', [FileController::class, 'getPersonalLegajos']);
        Route::get('/get-personal-legajos-pdf', [FileController::class, 'getPersonalLegajosPdf']);

        // File Control
        Route::get('/get-cargos-erp', [FileController::class, 'getCargosErp']);
        Route::get('/get-coincidencias', [FileController::class, 'getCoincidencias']);
        Route::get('/get-documentos/{personalId}', [FileController::class, 'getDocumentosXPersonal']);
        Route::get('/get-legajos', [FileController::class, 'getLegajos']);
        Route::get('/get-clientes', [FileController::class, 'getClientes']);
        Route::get('/get-clientes-legajos', [FileController::class, 'getClientesLegajos']);
        Route::get('/get-cargos', [FileController::class, 'getCargosXCliente']);
        Route::get('/cargo-counters', [FileController::class, 'getCargoCounters']);

        Route::post('/get-folios-personas', [FileController::class, 'getFoliosXPersonas']);
        Route::post('/get-folios-persona_uno', [FileController::class, 'getFoliosXPersona_uno']);
        Route::get('/get-folios/{codCliente}/{codCargo}', [FileController::class, 'getFoliosXLegajo']);
        Route::get('/get-folios-cliente-cargo', [FileController::class, 'getFoliosClienteCargo']);
        Route::get('/get-folios', [FileController::class, 'getFolios']);
        Route::get('/get-folios-vigentes', [ConsultaController::class, 'getFoliosVigentes']);
        Route::get('/get-folios-pendientes', [ConsultaController::class, 'getFoliosPendientes']);
        Route::get('/folios/proximos-vencer', [ConsultaController::class, 'getFoliosProximosVencer']);
        Route::get('/get-folios-comercial/{codCliente}/{codCargo}', [FileController::class, 'getFoliosXLegajo_comercial']);
        Route::post('/save_folio_persona', [FileController::class, 'saveFolioPersona']);
        Route::post('/save_folio', [FileController::class, 'saveFolio']);
        Route::post('/disabled_folio', [FileController::class, 'disabledFolio']);
        Route::post('/activar_folio', [FileController::class, 'activarFolio']);
        Route::get('/check-folio-nombre', [FileController::class, 'checkNombreFolio']);
        Route::post('/save-dj-folio', [FileController::class, 'saveDjFolio']);
        Route::get('/get-view-documents/{codPersonal}/{codFolio}', [FileController::class, 'getViewDocumentsPer']);

        // Cargos
        Route::get('/get-cargo', [FileController::class, 'getCargos']);
        Route::get('/get-cargo/{codigo}', [FileController::class, 'getCargosXCodigo']);
        Route::get('/check-cargo-nombre', [FileController::class, 'checkNombreCargo']);
        Route::post('/update_cargo', [FileController::class, 'updateCargo']);
        Route::post('/delete-cargo', [FileController::class, 'eliminarCargo']);
        Route::post('/activar-cargo', [FileController::class, 'activarCargo']);
        Route::post('save_legajo', [FileController::class, 'saveLegajo']);

        // Notificaciones
        Route::get('/get-notificaciones', [FileController::class, 'getNotificaciones']);
        Route::post('/delete-notif', [NotificacionController::class, 'deleteNotificacion']);
        Route::get('/notificaciones-matriculas', [NotificacionController::class, 'obtenerNotificacionesMatriculas']);
        Route::post('/notificaciones-matriculas/{codigo}/leer', [NotificacionController::class, 'marcarNotificacionLeida']);
        Route::post('/notificaciones-matriculas/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
        Route::get('/notificaciones/folios-por-vencer', [NotificacionController::class, 'foliosPorVencer'])->name('notificaciones.foliosPorVencer');
        Route::get('/notificaciones/pendientes-etapa2', [NotificacionController::class, 'pendientesEtapa2']);
        Route::get('/notificaciones/demandas-admin', [NotificacionController::class, 'getDemandasAdmin']);
        Route::post('/notificaciones/demandas-admin/borrar', [NotificacionController::class, 'deleteDemandaAdmin']);

        // Solicitudes y otros
        Route::post('/save-solicitud', [FileController::class, 'saveSolicitud']);
        Route::get('/get-areas', [FileController::class, 'getAreas']);
        Route::get('/get-posicion', [FileController::class, 'getPosicion']);
        Route::get('/get-grupo', [FileController::class, 'getGrupo']);
        Route::get('/get-grupo/{codigo}', [FileController::class, 'getGrupoId']);

        // Login
        Route::post('/change-password-user', [LoginController::class, 'updatePasswordUser']);

        // Capacitación
        Route::get('/get-cursos/{op?}', [CapacitacionController::class, 'index']);
        Route::get('/cursos/alertas-vencimiento', [CapacitacionController::class, 'getAlertasVencimiento']);
        Route::get('/get-curso-id/{id}', [CapacitacionController::class, 'getCursoExamenXId']);
        Route::get('/get-programacion-id/{id}', [CapacitacionController::class, 'getProgramacionXId']);
        Route::get('/get-curso-programacion/{id}', [CapacitacionController::class, 'getCursoProgramacionXId']);
        Route::post('/save-cursos', [CapacitacionController::class, 'saveCurso']);
        Route::post('/update-curso', [CapacitacionController::class, 'updateCurso']);
        Route::patch('/cursos/{codigo}/habilitado', [CapacitacionController::class, 'updateCursoHab']);
        Route::delete('/cursos/{codigo}', [CapacitacionController::class, 'destroyCurso']);
        Route::post('/save-programacion', [CapacitacionController::class, 'saveProgramacion']);
        Route::post('/update-programacion', [CapacitacionController::class, 'updateProgramacion']);
        Route::patch('/programaciones/{codigo}/habilitado', [CapacitacionController::class, 'updateProgramacionHab']);
        Route::get('/get-capacitacion-tipo-cursos', [CapacitacionController::class, 'getTipoCursos']);
        Route::get('/get-capacitacion-areas', [CapacitacionController::class, 'getAreas']);
        Route::get('/get-matriculas-curso/{cursoId}', [CapacitacionController::class, 'getMatriculasPorCurso']);
        Route::get('/get-matriculas-migra-personal/{cursoId}', [CapacitacionController::class, 'getMatriculasMigraPersonal']);
        Route::get('/get-historial-capacitaciones/{personalId}', [CapacitacionController::class, 'getHistorialCapacitaciones']);
        Route::get('/buscar-personal-capacitacion', [CapacitacionController::class, 'buscarPersonalCapacitacion']);
        Route::get('/get-sucursales', [CapacitacionController::class, 'getSucursales']);
        Route::post('/cursos/analizar-plantilla', [CapacitacionController::class, 'analizarPlantilla']);
        Route::get('/capacitacion/combos-apertura', [CapacitacionController::class, 'getCombosApertura']);
        Route::post('/capacitacion/procesar-examen-ia', [CapacitacionController::class, 'procesarExamenConIA']);
        Route::post('/capacitacion/guardar-examen-ia', [CapacitacionController::class, 'guardarExamenIA']);
        Route::post('/save-matricula', [CapacitacionController::class, 'saveMatricula']);
        Route::get('/matriculas-masivas/{jobId}', [CapacitacionController::class, 'estadoMatriculaMasiva']);
        Route::post('/cursos/programacion-manual', [CapacitacionController::class, 'storeProgramacionManual']);
        Route::get('/get-areas-encargadas', [CapacitacionController::class, 'getAreasEncargadas']);
        Route::get('/get-areas-por-sistema/{sistemaId}', [CapacitacionController::class, 'getAreasPorSistema']);
        Route::get('/get-empresas', [CapacitacionController::class, 'getEmpresasList']);
        Route::get('/get-clientes-pac', [CapacitacionController::class, 'getClientesForPAC']);

        Route::get('/obtener-cursos-av', [ExamenesController::class, 'obtenerCursosAV']);
        Route::post('/obtener-datos-reporte-av', [ExamenesController::class, 'obtenerDatosReporteAV']);

        // ── Nuevas rutas de Capacitación (Rodrigo) ───────────────────────
        Route::post('/obtener-personal-reporte', [CapacitacionController::class, 'obtenerPersonalParaReporte']);
        Route::post('/actualizar-curso/{codigo}', [CapacitacionController::class, 'actualizarCurso']);
        Route::get('/obtener-capacitacion-sistemas', [CapacitacionController::class, 'obtenerSistemas']);
        Route::get('/capacitacion/exportar-excel-matriculas/{cursoId}', [CapacitacionController::class, 'exportMatriculasExcel']);
        Route::get('/listar-jefaturas', [CapacitacionController::class, 'listarJefaturas']);
        Route::get('/obtener-personal-por-sucursal/{sucursalId}', [CapacitacionController::class, 'getPersonalPorSucursal']);
        Route::get('/get-cursos-seguimiento', [CapacitacionController::class, 'getCursosSeguimiento']);
        Route::get('/obtener-detalle-curso/{course_id}', [CapacitacionController::class, 'obtenerDetalleCurso']);
        Route::get('/get-estudiantes-curso', [CapacitacionController::class, 'obtenerEstudiantesCurso']);
        Route::get('/obtener-cursos-alumno', [CapacitacionController::class, 'obtenerEstadoCursosAlumno']);
        Route::post('/comparar-memos', [CapacitacionController::class, 'compararMemos']);
        Route::post('/cursos/aplazar-curso', [CapacitacionController::class, 'aplazarCurso']);
        Route::get('/cursos/obtener-prog-actual/{courseId}', [CapacitacionController::class, 'obtenerProgActual']);
        Route::get('/obtener-programaciones/{courseId}', [CapacitacionController::class, 'obtenerProgramaciones']);
        Route::get('/obtener-matriculados/{courseId}', [CapacitacionController::class, 'obtenerMatriculados']);
        Route::get('/obtener-datos-matricula/{cursoId}', [CapacitacionController::class, 'obtenerDatosMatricula']);
        Route::get('/reporte-cursos', [CapacitacionController::class, 'obtenerCursosParaReportes']);
        Route::get('/obtener-cursos', [CapacitacionController::class, 'obtenerCursos']);
        Route::get('/obtener-cursos-cliente/{cod_legacy}', [CapacitacionController::class, 'obtenerCursosPorCliente']);
        Route::get('/get-cursos-por-area-fechas', [CapacitacionController::class, 'getCursosPorAreaFechas']);
        Route::get('/obtener-areas-por-sistema/{sistemaId}', [CapacitacionController::class, 'getAreasPorSistema']);
        Route::get('/obtener-areas', [CapacitacionController::class, 'obtenerAreas']);
        Route::get('/obtener-plan-pce', [CapacitacionController::class, 'obtenerPlanPCE']);
        Route::get('/obtener-plan-pca/{codCliente}', [CapacitacionController::class, 'obtenerPlanPCA']);
        Route::get('/obtener-cursos-por-plan/{tipo_curso}', [CapacitacionController::class, 'obtenerCursosPorPlan']);
        Route::get('/obtener-cursos-new', [NewCapacController::class, 'obtenerCursos']);
        Route::get('/obtener-curso-new/{codigoCurso}', [NewCapacController::class, 'obtenerCurso']);
        Route::get('/obtener-ultimo-codigo-curso-new', [NewCapacController::class, 'ultimoCodigoCurso']);
        Route::get('/obtener-dirigidos-new', [NewCapacController::class, 'dirigidos']);

        Route::get('/obtener-catalogos/dirigidos-new', [NewCapacController::class, 'obtenerDirigidosNew']);
        Route::get('/obtener-catalogos/areas-new', [NewCapacController::class, 'obtenerAreasNew']);
        Route::get('/obtener-catalogos/sistemas-new', [NewCapacController::class, 'obtenerSistemasNew']);
        Route::get('/obtener-catalogos/planes-new', [NewCapacController::class, 'obtenerPlanesNew']);
        Route::get('/obtener-catalogos/tipos-curso-new', [NewCapacController::class, 'obtenerTiposCursoNew']);

        Route::post('/guardar-curso-new', [NewCapacController::class, 'guardarCursoNew']);
        Route::post('/actualizar-curso-new', [NewCapacController::class, 'actualizarCursoNew']);
        Route::post('/cursos/subir-av', [NewCapacController::class, 'subirCursoAv']);
        Route::get('/obtener-tipos-curso', [CapacitacionController::class, 'obtenerTiposDeCurso']);
        Route::post('/capacitacion/procesar-examen-word', [CapacitacionController::class, 'procesarExamenWord']);
        Route::post('/capacitacion/guardar-examen-word', [CapacitacionController::class, 'guardarExamenWord']);
        Route::post('/capacitacion/desmatricular-usuario', [CapacitacionController::class, 'desmatricularUsuario']);
        Route::post('/capacitacion/suspender-usuario/{cursoId}', [CapacitacionController::class, 'suspenderUsuario']);
        Route::post('/capacitacion/registrar-reporte', [CapacitacionController::class, 'saveReporteCapacitacion']);
        Route::get('/capacitacion/listar-reportes', [CapacitacionController::class, 'listarReportesCapacitaciones']);
        Route::get('/obtener-sucursales', [CapacitacionController::class, 'listarSucursales']);
        Route::get('/obtener-tipos-pers', [CapacitacionController::class, 'listarTiposPers']);
        Route::get('/obtener-certificados', [CapacitacionController::class, 'listarCertificados']);
        Route::get('/obtener-certificados-portuarios', [CapacitacionController::class, 'listarCertificadosPortuarios']);
        Route::post('/cursos-externos/reporte-certificados', [CapacitacionController::class, 'reporteCursosPortuarios']);
        Route::get('/obtener-memos-resumen/{nivelMemo}', [CapacitacionController::class, 'obtenerMemosResumen']);
        Route::get('/obtener-memos-enviados', [CapacitacionController::class, 'obtenerMemosEnviados']);
        Route::get('/obtener-detalle-memo/{memoId}', [CapacitacionController::class, 'obtenerDetalleMemo']);
        Route::post('/obtener-memos-personal', [CapacitacionController::class, 'obtenerMemosPersonal']);
        Route::get('/obtener-info-memo/{nroDoc}', [CapacitacionController::class, 'obtenerInfoMemo']);
        Route::get('/obtener-personal', [CapacitacionController::class, 'obtenerPersonal']);
        Route::post('/resolver-personal-dnis', [CapacitacionController::class, 'resolverPersonalPorDnis']);
        Route::get('/obtener-personal-todas-empresas', [CapacitacionController::class, 'obtenerPersonalTodasEmpresas']);
        Route::post('/obtener-personal-record', [CapacitacionController::class, 'obtenerPersonalParaRecord']);
        Route::post('/obtener-reporte-general', [CapacitacionController::class, 'obtenerReporteGeneral']);
        Route::post('/obtener-reporte-formato', [CapacitacionController::class, 'obtenerReporteFormato']);
        Route::get('/capacitacion/descargar-reporte/{id}/{tipo}', [CapacitacionController::class, 'descargarReporte']);
        Route::put('/capacitacion/actualizar-reporte/{id}', [CapacitacionController::class, 'actualizarReporte']);
        Route::patch('/capacitacion/actualizar-estado-reporte/{id}', [CapacitacionController::class, 'actualizarEstadoReporte']);
        Route::post('/capacitacion/descargar-reportes-zip', [CapacitacionController::class, 'descargarReportesZip']);
        Route::delete('/capacitacion/eliminar-reporte/{id}', [CapacitacionController::class, 'eliminarReporte']);
        Route::post('/enviar-memos-varios', [CapacitacionController::class, 'enviarMemos']);
        Route::post('/enviar-memo-personal', [CapacitacionController::class, 'enviarMemo']);

        // ── Mail (nuevo en Rodrigo) ───────────────────────────────────────
        Route::post('/mail/enviar-recordatorios', [\App\Http\Controllers\Api\MailController::class, 'enviarRecordatorioCursos']);
        Route::post('/mail/enviar-recordatorio', [\App\Http\Controllers\Api\MailController::class, 'enviarRecordatorioCurso']);

        // Ubicación
        Route::get('/ubicacion/departamentos', [UbicacionController::class, 'departamentos']);
        Route::get('/ubicacion/provincias/{departamento_id}', [UbicacionController::class, 'provincias']);
        Route::get('/ubicacion/distritos/{provincia_id}', [UbicacionController::class, 'distritos']);

        // Postulantes / DJ
        Route::get('/get-postulantes', [FileController::class, 'getPostulantes']);
        Route::get('/get-personal-dj', [FileController::class, 'getListaDJ']);
        Route::get('/get-personal-dj-2026', [FileController::class, 'getListaDJ2026']);
        Route::get('/get-personal-dj-migracion', [FileController::class, 'getListaDJMigracion']);
        Route::post('/save-declaracion-jurada', [DjController::class, 'saveDeclaracionJurada']);
        Route::get('/reporte-personal-sin-migracion', [DjController::class, 'reportePersonalSinMigracion']);
        Route::get('/reporte-personal-etapa2', [DjController::class, 'reportePersonalEtapa2PDF']);
        Route::get('/reporte-personal-sin-migracion-v2', [DjController::class, 'reportePersonalSinMigracionV2']);
        Route::post('/save-dj-folio', [DjController::class, 'saveDeclaracionJurada']);
        Route::post('/reporte-avance-dj', [DjController::class, 'saveReporteAvanceDj']);

        Route::get('/rrhh/reporte-avances', [ReporteAvancesController::class, 'index']);

        // Consultas
        Route::get('/sucursales-por-cliente', [ConsultaController::class, 'getSucursalesXCliente']);
        Route::get('/reporte-avances-dj', [App\Http\Controllers\DjController::class, 'reporteAvancesDj']);

        // Reportes
        Route::get('/reporte/folios-pendientes-sucursal', [ReporteController::class, 'foliosPendientesPorSucursal']);
        Route::get('/reporte/folios-por-vencer', [ReporteController::class, 'foliosPorVencer']);
        Route::get('/reporte/folios-pendientes-registro', [ReporteController::class, 'foliosPendientesRegistro']);
        Route::get('/reporte/estado-legajos', [FileController::class, 'getEstadoLegajos']);
        Route::get('/reporte/carnet', [ReporteController::class, 'carnet']);

        Route::get('/reporte/vigencia-dni', [ReporteController::class, 'vigenciaDni']);
        Route::get('/reporte/vigencia-brevete', [ReporteController::class, 'vigenciaBrevete']);
        Route::get('/reporte/categorias-brevete', [ReporteController::class, 'categoriasBrevete']);

        Route::get('/reporte/certificados', [ReporteController::class, 'certificados']);

        // Biométrico
        Route::get('/get-biometrico/{codigo}', [BiometricoController::class, 'show']);

        // Periodos de Actualización DJ
        Route::get('/dj/periodos/personal-corte', [ActualizacionesDjController::class, 'getPersonalPorCorte']);
        Route::post('/dj/periodos/guardar', [ActualizacionesDjController::class, 'storePeriodo']);

        // DJ módulo (antes con prefix 'dj')
        Route::prefix('dj')->middleware('throttle:dj_api')->group(function () {
            Route::get('/get-personal-data', [DjController::class, 'getPersonalData']);
            Route::get('/get-catalogs', [DjController::class, 'getCatalogs']);
            Route::get('/get-ubicacion', [DjController::class, 'getUbicacion']);
            Route::post('/save-dj-completo', [DjController::class, 'saveDjCompleto']);
            Route::get('/get-backup-data', [DjController::class, 'getBackupData']);
            Route::get('/proxy-foto', [DjController::class, 'proxyFoto']);
            Route::post('/update-check-pdf', [DjController::class, 'updateCheckPdf']);
            Route::post('/reset-check-pdf', [DjController::class, 'resetCheckPdf']);
            Route::get('/get-check-pdf', [DjController::class, 'getCheckPdf']);
            Route::post('/reporte-avance-dj', [DjController::class, 'saveReporteAvanceDj']);
            Route::get('/validar-documento', [DjController::class, 'validarDocumentoDj']);
            Route::get('/verificar-vacaciones', [DjController::class, 'verificarVacaciones']);
            Route::get('/get-cargos-dj', [DjController::class, 'getCargosDj']);
            Route::get('/verificar-contrato', [DjController::class, 'verificarContrato']);
            Route::post('/save-nueva-dj', [DjController::class, 'saveNuevaDj']);
            Route::post('/validar-excepcion-edad', [DjController::class, 'validarExcepcionEdad']);
            Route::get('/get-usuarios-excepcion-edad', [DjController::class, 'getUsuariosExcepcionEdad']);
            Route::post('/save-usuarios-excepcion-edad', [DjController::class, 'saveUsuariosExcepcionEdad']);
            Route::get('/buscar-coincidencias', [DjController::class, 'buscarCoincidencias']);
            Route::get('/reporte-personal-datos-generales', [ReportePersonalController::class, 'datosGenerales']);
            Route::get('/reporte/proxy-imagen', [ReportePersonalController::class, 'proxyImagen']);
            Route::get('/reporte/personal-documentos', [ReportePersonalController::class, 'documentosPendientes']);
            Route::get('/reporte/dni-hijos', [ReportePersonalController::class, 'dniHijosPendientes']);
            Route::get('/reporte/descargar/{archivo}', [ReportePersonalController::class, 'descargarReporte'])->where('archivo', '.*')->name('reporte.descargar');
            Route::post('/save-recontratacion', [DjController::class, 'saveRecontratacion']);
            Route::post('/upload-foto-personal', [DjController::class, 'uploadFotoPersonal']);
            Route::post('/upload-dni-personal', [DjController::class, 'uploadDniPersonal']);
            Route::get('/get-dni-personal', [DjController::class, 'getDniPersonal']);
            Route::get('/get-foto-personal', [DjController::class, 'getFotoPersonal']);
            Route::post('/upload-dni-hijos', [DjController::class, 'uploadDniHijos']);
            Route::get('/get-dni-hijos', [DjController::class, 'getDniHijos']);
            Route::get('get-tipo-doc/', [DjController::class, 'getTipoDoc']);
            Route::get('get-tipo-per/', [DjController::class, 'getTipoPer']);
            Route::get('reglas-edad/', [DjController::class, 'reglasEdad']);
            Route::get('get-estado-civil/', [DjController::class, 'getEstadoCivil']);
            Route::get('get-sistema-prev/', [DjController::class, 'getSistemaPrev']);
            Route::get('get-paises/', [DjController::class, 'getPaises']);
        });

    }); // fin prefix('api')

    Route::get('/reportes', [FileController::class, 'ViewReportes'])->name('reportes');
    // ─── RUTAS DE VISTAS (catchall al final) ─────────────────────────────────
    Route::group(['prefix' => '/', 'where' => ['first' => '^(?!api|\.well-known).*']], function () {
        Route::get('', [RoutingController::class, 'index'])->name('root');
        Route::get('/home', fn () => view('index'))->name('home');
        Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])->name('third');
        Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])->name('second');
        Route::get('{any}', [RoutingController::class, 'root'])->name('any');
    });

}); // fin middleware auth

// ─── TEST EMAILS ─────────────────────────────────────────────────────────────
Route::get('/preview-email-caducidad', function () {
    return new AlertaCaducidadMail([
        'nombre_personal' => 'Juan Pérez',
        'nombre_empresa' => 'SISOLMAR',
        'documentos' => [
            ['nombre' => 'Certificado Médico', 'tipo' => 'PRINCIPAL', 'fecha_caducidad' => '05/02/2026', 'dias_restantes' => 5],
            ['nombre' => 'Antecedentes Policiales', 'tipo' => 'ADICIONAL', 'fecha_caducidad' => '10/02/2026', 'dias_restantes' => 10],
        ],
    ]);
});

Route::get('/test-email-caducidad', function () {
    Mail::to('webmaster@gruposolmar.com.pe')->send(new AlertaCaducidadMail(['nombre_empresa' => 'SISOLMAR']));

    return 'Correo enviado (si no hubo error)';
});

Route::get('/debug-permisos', function () {
    return response()->json([
        'tipo_rol' => Auth::user()?->tipo_rol,
        'permisos' => session('permisos'),
    ]);
})->middleware('auth');

require __DIR__.'/auth.php';
