@extends('layouts.vertical', ['title' => 'Consultas de Folios'])

@section('css')
<style>
    /* ===== TABS ===== */
    .consulta-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
        color: #64748b;
        background: transparent;
        white-space: nowrap;
    }

    .consulta-tab:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .consulta-tab.active-vigente {
        background: #dcfce7;
        color: #15803d;
        border-color: #86efac;
    }

    .consulta-tab.active-pendiente {
        background: #fef3c7;
        color: #b45309;
        border-color: #fcd34d;
    }

    .consulta-tab.active-proximos {
        background: #fee2e2;
        color: #b91c1c;
        border-color: #fca5a5;
    }

    /* ===== STAT CARDS ===== */
    .stat-consulta {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: box-shadow 0.2s;
    }

    .stat-consulta:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .stat-consulta .stat-num {
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
        color: #1e293b;
    }

    .stat-consulta .stat-lbl {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .stat-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    /* ===== FILTROS ===== */
    .filtro-panel {
        display: none;
        animation: fadeSlide 0.2s ease;
    }

    .filtro-panel.active {
        display: block;
    }

    @keyframes fadeSlide {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== TABLA ===== */
    .tabla-consultas {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .tabla-consultas thead th {
        padding: 11px 14px;
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
        cursor: pointer;
        user-select: none;
    }

    .tabla-consultas thead th:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .tabla-consultas tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.12s;
    }

    .tabla-consultas tbody tr:hover {
        background: #f8fafc;
    }

    .tabla-consultas tbody td {
        padding: 13px 14px;
        color: #334155;
        vertical-align: middle;
        text-align: center;
    }

    /* ===== BADGES ===== */
    .badge-estado {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .badge-estado::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .badge-vigente    { background: #dcfce7; color: #15803d; }
    .badge-pendiente  { background: #fef3c7; color: #b45309; }
    .badge-por-vencer { background: #ffedd5; color: #c2410c; }
    .badge-vencido    { background: #fee2e2; color: #b91c1c; }
    .badge-falta      { background: #e0e7ff; color: #4338ca; }
    .badge-revision   { background: #e0f2fe; color: #0369a1; }
    .badge-recibido   { background: #dcfce7; color: #15803d; }

    /* ===== EMPTY STATE ===== */
    .empty-state-consulta {
        padding: 60px 20px;
        text-align: center;
        color: #94a3b8;
    }

    .empty-state-consulta i {
        font-size: 48px;
        margin-bottom: 12px;
        display: block;
        opacity: 0.4;
    }

    .empty-state-consulta p {
        font-size: 14px;
        margin: 0;
    }

    /* ===== TABLA SCROLL ===== */
    .tabla-scroll {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 440px;
    }

    /* ===== PAGINACIÓN ===== */
    .pag-info {
        font-size: 12px;
        color: #64748b;
    }

    /* ===== DÍAS RESTANTES ===== */
    .dias-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 6px;
    }

    .dias-ok    { background: #dcfce7; color: #15803d; }
    .dias-warn  { background: #ffedd5; color: #c2410c; }
    .dias-dead  { background: #fee2e2; color: #b91c1c; }

    /* ===== LOADING ===== */
    .loading-row td {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }

    .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #e2e8f0;
        border-top-color: #3b82f6;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
        vertical-align: middle;
        margin-right: 8px;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ===== MODAL DETALLE ===== */
    .modal-detalle {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.5);
        backdrop-filter: blur(2px);
        z-index: 1050;
        align-items: center;
        justify-content: center;
    }

    .modal-detalle.show {
        display: flex;
    }

    .modal-detalle-content {
        background: white;
        border-radius: 12px;
        box-shadow: 0 24px 60px rgba(0,0,0,0.2);
        max-width: 640px;
        width: 95%;
        max-height: 85vh;
        overflow-y: auto;
        padding: 28px;
        animation: modalIn 0.2s ease;
    }

    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.96) translateY(8px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .detalle-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-top: 16px;
    }

    .detalle-field label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #94a3b8;
        display: block;
        margin-bottom: 3px;
    }

    .detalle-field p {
        font-size: 14px;
        font-weight: 500;
        color: #1e293b;
        margin: 0;
    }

    .detalle-field.full {
        grid-column: 1 / -1;
    }

    @media (max-width: 640px) {
        .detalle-grid { grid-template-columns: 1fr; }
        .consulta-tab span { display: none; }
    }

    /* ===== PAGINACIÓN ===== */
    .pag-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: white;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .pag-btn:hover:not(:disabled) {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #1e293b;
    }

    .pag-btn.active {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .pag-btn.disabled,
    .pag-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .pag-ellipsis {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        font-size: 12px;
        color: #94a3b8;
    }
</style>
@endsection

@section('content')

@include("layouts.shared/page-title", ["subtitle" => "Recursos Humanos", "title" => "Consultas"])

<script src="https://kit.fontawesome.com/76256ea07c.js" crossorigin="anonymous"></script>

<div class="mt-8 flex flex-col gap-5">

    {{-- =================== TABS DE TIPO DE CONSULTA =================== --}}
    <div class="card">
        <div class="card-body py-4 px-5">
            <div class="flex flex-wrap items-center justify-between gap-3">

                {{-- Tabs --}}
                <div class="flex flex-wrap gap-2" id="consulta-tabs">
                    <button class="consulta-tab active-vigente" data-tab="vigentes">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Folios Vigentes</span>
                    </button>
                    <button class="consulta-tab" data-tab="pendientes">
                        <i class="fa-solid fa-hourglass-half"></i>
                        <span>Folios Pendientes</span>
                    </button>
                    <button class="consulta-tab" data-tab="proximos">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Próximos a Vencer</span>
                    </button>
                </div>

                {{-- Acciones rápidas --}}
                <div class="flex gap-2">
                    <button id="btn-exportar-csv" class="btn btn-sm rounded-full bg-default-200 text-default-700 hover:bg-default-300">
                        <i class="fa-solid fa-file-csv me-1"></i> Exportar CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- =================== TARJETAS DE RESUMEN =================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="stats-cards">
        <div class="stat-consulta">
            <div>
                <div class="stat-num" id="stat-total">—</div>
                <div class="stat-lbl">Total Folios</div>
            </div>
            <div class="stat-icon-wrap bg-blue-50">
                <i class="fa-solid fa-file-lines text-blue-500"></i>
            </div>
        </div>
        <div class="stat-consulta">
            <div>
                <div class="stat-num" id="stat-personas">—</div>
                <div class="stat-lbl">Total Personas</div>
            </div>
            <div class="stat-icon-wrap bg-indigo-50">
                <i class="fa-solid fa-users text-indigo-500"></i>
            </div>
        </div>
        <div class="stat-consulta">
            <div>
                <div class="stat-num" id="stat-vigente">—</div>
                <div class="stat-lbl">Vigentes</div>
            </div>
            <div class="stat-icon-wrap bg-green-50">
                <i class="fa-solid fa-circle-check text-green-500"></i>
            </div>
        </div>
        <div class="stat-consulta">
            <div>
                <div class="stat-num" id="stat-por-vencer">—</div>
                <div class="stat-lbl">Por Vencer</div>
            </div>
            <div class="stat-icon-wrap bg-orange-50">
                <i class="fa-solid fa-clock text-orange-500"></i>
            </div>
        </div>
        <div class="stat-consulta">
            <div>
                <div class="stat-num" id="stat-vencido">—</div>
                <div class="stat-lbl">Vencidos / Pendientes</div>
            </div>
            <div class="stat-icon-wrap bg-red-50">
                <i class="fa-solid fa-circle-xmark text-red-500"></i>
            </div>
        </div>
    </div>

    {{-- =================== FILTROS + TABLA =================== --}}
    <div class="grid gap-5" style="grid-template-columns: 280px 1fr;">

        {{-- ---- PANEL DE FILTROS ---- --}}
        <div class="card" style="align-self: start;">
            <div class="card-header">
                <h4 class="card-title flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-default-500"></i>
                    Filtros de Búsqueda
                </h4>
            </div>
            <div class="card-body flex flex-col gap-4">

                {{-- == PANEL: VIGENTES == --}}
                <div class="filtro-panel active" id="panel-vigentes">
                    <div class="flex flex-col gap-3">

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Tipo de Folio
                            </label>
                            <select class="form-select text-sm" id="vig-tipo-folio">
                                <option value="">Todos</option>
                                <option value="DOCUMENTO">Documento</option>
                                <option value="FORMATO">Formato</option>
                                <option value="CERTIFICADO">Certificado</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Prioridad
                            </label>
                            <select class="form-select text-sm" id="vig-prioridad">
                                <option value="">Todas</option>
                                <option value="PRINCIPAL">Obligatorio</option>
                                <option value="ADICIONAL">Adicional</option>
                            </select>
                        </div>

                    </div>
                </div>


                {{-- == PANEL: PENDIENTES == --}}
                <div class="filtro-panel" id="panel-pendientes">
                    <div class="flex flex-col gap-3">

                        {{-- BÚSQUEDA DIRECTA --}}
                        <div>
                            <label class="text-xs font-semibold text-primary uppercase tracking-wide mb-1 block">
                                Buscar por DNI
                            </label>
                            <input type="text" 
                                class="form-input text-sm" 
                                id="pen-dni" 
                                placeholder="Ingrese DNI...">
                        </div>

                        <hr class="my-2"> 

                        {{-- FILTROS ESTRUCTURADOS --}}
                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Cliente
                            </label>
                            <select class="form-select text-sm" id="pen-cliente">
                                <option value="">Todos los clientes</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Sucursal
                            </label>
                            <select class="form-select text-sm" id="pen-sucursal">
                                <option value="">Todas las sucursales</option>
                            </select>
                        </div>

                        <!-- <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Persona
                            </label>
                            <input type="text" class="form-input text-sm" id="pen-persona" placeholder="Buscar persona...">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Servicio
                            </label>
                            <select class="form-select text-sm" id="pen-servicio">
                                <option value="">Todos los servicios</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Estado del Documento
                            </label>
                            <select class="form-select text-sm" id="pen-estado-doc">
                                <option value="">Todos los estados</option>
                                <option value="falta">Falta por Entregar</option>
                                <option value="recibido">Recibido</option>
                                <option value="revision">En Revisión</option>
                                <option value="firma">Pendiente de Firma</option>
                            </select>
                        </div> -->

                    </div>
                </div>


                {{-- == PANEL: PRÓXIMOS A VENCER == --}}
                <div class="filtro-panel" id="panel-proximos">
                    <div class="flex flex-col gap-3">

                        {{-- BÚSQUEDA DIRECTA --}}
                        <div>
                            <label class="text-xs font-semibold text-primary uppercase tracking-wide mb-1 block">
                                Buscar por DNI
                            </label>
                            <input type="text"
                                class="form-input text-sm"
                                id="prox-dni"
                                placeholder="Ingrese DNI...">
                        </div>

                        <hr class="my-2">

                        {{-- FILTROS ESTRUCTURADOS --}}
                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Cliente
                            </label>
                            <select class="form-select text-sm" id="prox-cliente">
                                <option value="">Todos los clientes</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Sucursal
                            </label>
                            <select class="form-select text-sm" id="prox-sucursal">
                                <option value="">Todas las sucursales</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Persona
                            </label>
                            <input type="text"
                                class="form-input text-sm"
                                id="prox-persona"
                                placeholder="Buscar persona...">
                        </div>

                        {{-- Ocultado temporalmente --}}
                        <div style="display: none;">
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Código de Servicio
                            </label>
                            <select class="form-select text-sm" id="prox-servicio">
                                <option value="">Todos los servicios</option>
                            </select>
                        </div>

                        {{-- PERÍODO --}}
                        <div>
                            <label class="text-xs font-semibold text-default-500 uppercase tracking-wide mb-1 block">
                                Período a consultar
                            </label>
                            <select class="form-select text-sm"
                                    id="prox-periodo">
                                <option value="7">Próximos 7 días</option>
                                <option value="15">Próximos 15 días</option>
                                <option value="30" selected>Próximos 30 días</option>
                                <option value="60">Próximos 60 días</option>
                                <option value="90">Próximos 90 días</option>
                                <option value="custom">Rango personalizado</option>
                            </select>
                        </div>

                        {{-- FECHAS PERSONALIZADAS --}}
                        <div id="prox-fechas-custom"
                            style="display:none;"
                            class="flex flex-col gap-2">

                            <div>
                                <label class="text-xs text-default-500 mb-1 block">Desde</label>
                                <input type="date"
                                    class="form-input text-sm"
                                    id="prox-fecha-desde">
                            </div>

                            <div>
                                <label class="text-xs text-default-500 mb-1 block">Hasta</label>
                                <input type="date"
                                    class="form-input text-sm"
                                    id="prox-fecha-hasta">
                            </div>

                        </div>

                    </div>
                </div>


                {{-- Botones --}}
                <div class="flex flex-col gap-2 mt-1">
                    <button id="btn-buscar" class="btn rounded-full bg-primary text-white hover:bg-primary/90 w-full">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Buscar
                    </button>
                    <button id="btn-limpiar" class="btn rounded-full bg-default-200 text-default-600 hover:bg-default-300 w-full">
                        <i class="fa-solid fa-rotate-left me-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>

        {{-- ================= RESULTADOS ================= --}}
        <div id="resultados-container">

            {{-- ================= VIGENTES ================= --}}
            <div id="panel-vigentes-resultado" class="panel-resultado">

                <div class="card">
                    <div class="card-header flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <h4 class="card-title">Folios Vigentes</h4>
                            <span class="badge bg-default-200 text-default-600 text-xs" id="badge-count-vigentes">
                                0 registros
                            </span>
                        </div>
                        <div>
                            <input type="text" id="busqueda-rapida-vigentes"
                                class="w-48 px-3 py-1.5 border border-gray-200 rounded-full text-sm"
                                placeholder="Buscar en resultados...">
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="tabla-scroll">
                            <table class="tabla-consultas" id="tabla-vigentes">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Nombre del folio</th>
                                        <th>Tipo</th>
                                        <th>Prioridad</th>
                                        <th>Vencimiento</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-vigentes">
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state-consulta">
                                                Selecciona filtros y haz clic en Buscar
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Footer: info de registros + controles de paginación --}}
                        <div class="flex items-center justify-between flex-wrap gap-2 px-4 py-3 border-t border-default-200 bg-default-50">
                            <p class="pag-info" id="pag-info-vigentes">—</p>
                            <div class="flex gap-1 flex-wrap" id="pag-controles-vigentes"></div>
                        </div>

                    </div>
                </div>

            </div>



            {{-- ================= PENDIENTES ================= --}}
            <div id="panel-pendientes-resultado" class="panel-resultado hidden">

                {{-- TABLA PERSONAS --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">Personas con folios pendientes</h4>
                    </div>

                    <div class="card-body p-0">
                        <div class="tabla-scroll">
                            <table class="tabla-consultas" id="tabla-personas-pendientes">
                                <thead>
                                    <tr>
                                        <th>Persona</th>
                                        <th>DNI</th>
                                        <th>Cliente</th>
                                        <th>Sucursal</th>
                                        <th>Pendientes</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-personas-pendientes">
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state-consulta">
                                                Realiza una búsqueda para ver resultados
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Paginación Pendientes --}}
                    <div class="card-footer bg-default-50 py-3 px-5 flex items-center justify-between border-t border-default-200">
                        <div class="text-sm text-default-500" id="pag-info-pendientes">
                            Sin resultados
                        </div>
                        <div class="flex items-center gap-2" id="pag-controles-pendientes">
                            {{-- Se llena vía JS --}}
                        </div>
                    </div>
                </div>

                {{-- TABLA DOCUMENTOS --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title" id="titulo-detalle-pendientes">
                            Detalle de documentos
                        </h4>
                    </div>

                    <div class="card-body p-0">
                        <div class="tabla-scroll">
                            <table class="tabla-consultas" id="tabla-documentos-pendientes">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Tipo</th>
                                        <th>Prioridad</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-documentos-pendientes">
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state-consulta">
                                                Selecciona una persona para ver el detalle
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>



            {{-- ================= PRÓXIMOS A VENCER ================= --}}
            <div id="panel-proximos-resultado" class="panel-resultado hidden">

                {{-- TABLA PERSONAS --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title">Personas con folios por vencer</h4>
                    </div>

                    <div class="card-body p-0">
                        <div class="tabla-scroll">
                            <table class="tabla-consultas" id="tabla-personas-proximos">
                                <thead>
                                    <tr>
                                        <th>Persona</th>
                                        <th>DNI</th>
                                        <th>Cliente</th>
                                        <th>Sucursal</th>
                                        <th>Por Vencer</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-personas-proximos">
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state-consulta">
                                                Realiza una búsqueda para ver resultados
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Paginación Próximos --}}
                    <div class="card-footer bg-default-50 py-3 px-5 flex items-center justify-between border-t border-default-200">
                        <div class="text-sm text-default-500" id="pag-info-proximos">
                            Sin resultados
                        </div>
                        <div class="flex items-center gap-2" id="pag-controles-proximos">
                            {{-- Se llena vía JS --}}
                        </div>
                    </div>
                </div>

                {{-- TABLA DOCUMENTOS --}}
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title" id="titulo-detalle-proximos">
                            Detalle de documentos
                        </h4>
                    </div>

                    <div class="card-body p-0">
                        <div class="tabla-scroll">
                            <table class="tabla-consultas" id="tabla-documentos-proximos">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Tipo</th>
                                        <th>Prioridad</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-documentos-proximos">
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state-consulta">
                                                Selecciona una persona para ver el detalle
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>


        {{-- ---- RESULTADOS ---- --}}
        <!-- <div class="card">
            <div class="card-header flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h4 class="card-title" id="titulo-tabla">Folios Vigentes</h4>
                    <span class="badge bg-default-200 text-default-600 text-xs" id="badge-count">0 registros</span>
                </div>
                <div>
                    <input type="text" id="busqueda-rapida"
                        class="w-48 px-3 py-1.5 border border-gray-200 rounded-full text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
                        placeholder="Buscar en resultados...">
                </div>
            </div>

            <div class="card-body p-0">
                <div class="tabla-scroll">
                    <table class="tabla-consultas" id="tabla-folios">
                        <thead id="tabla-thead">
                            <tr>
                                <th>N° <i class="fa-solid fa-sort text-default-300 text-xs"></i></th>
                                <th>Nombre del folio <i class="fa-solid fa-sort text-default-300 text-xs"></i></th>
                                <th>Tipo <i class="fa-solid fa-sort text-default-300 text-xs"></i></th>
                                <th>Prioridad <i class="fa-solid fa-sort text-default-300 text-xs"></i></th>
                                <th>Vencimiento <i class="fa-solid fa-sort text-default-300 text-xs"></i></th>
                            </tr>
                        </thead>
                        <tbody id="tabla-body">
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state-consulta">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <p>Selecciona los filtros y haz clic en <strong>"Buscar"</strong> para ver los resultados</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Footer de tabla --}}
                <div class="flex items-center justify-between px-4 py-3 border-t border-default-200 bg-default-50">
                    <p class="pag-info">
                        Mostrando <strong id="pag-desde">0</strong>–<strong id="pag-hasta">0</strong>
                        de <strong id="pag-total">0</strong> registros
                    </p>
                    <div class="flex gap-1" id="paginacion">
                        {{-- Se genera dinámicamente --}}
                    </div>
                </div>
            </div>
        </div> -->

    </div>{{-- end grid --}}

</div>{{-- end main container --}}


{{-- =================== MODAL DETALLE =================== --}}
<div id="modal-detalle" class="modal-detalle">
    <div class="modal-detalle-content">
        <div class="flex items-center justify-between mb-2 pb-4 border-b border-default-200">
            <div>
                <h3 class="text-base font-bold text-default-800">Detalle del Folio</h3>
                <p class="text-xs text-default-400 mt-0.5" id="modal-folio-id"></p>
            </div>
            <button id="btn-cerrar-modal" class="btn btn-sm rounded-full bg-default-200 hover:bg-default-300">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="detalle-grid" id="modal-body">
            {{-- Se llena dinámicamente --}}
        </div>
        <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-default-200">
            <button class="btn rounded-full bg-default-200 text-default-600 hover:bg-default-300">
                Cerrar
            </button>
            <button class="btn rounded-full bg-primary/20 text-primary hover:bg-primary hover:text-white" id="btn-modal-descargar">
                <i class="fa-solid fa-download me-1"></i> Descargar
            </button>
        </div>
    </div>
</div>

@endsection

@vite(['resources/js/functions/consultas.js'])

@section('script')
@endsection
