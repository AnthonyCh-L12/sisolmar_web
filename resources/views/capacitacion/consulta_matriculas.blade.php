@extends('layouts.vertical', ['title' => 'Gestión de Matrículas'])
@section('css')
<!-- Estilos -->
<style>
    [x-cloak] {
        display: none !important;
    }

    /* ─────────────────────────────────────────────────────────────
   Layout general
───────────────────────────────────────────────────────────── */

    body {
        height: 100vh !important;
        overflow: hidden !important;
        background: #f8fafc;
    }

    .page-content {
        height: 100vh !important;
        overflow-y: auto !important;
    }

    /* ─────────────────────────────────────────────────────────────
   Hover cards
───────────────────────────────────────────────────────────── */

    .card-hover {
        transition:
            transform .22s ease,
            box-shadow .22s ease,
            border-color .22s ease;
    }

    .card-hover:hover {
        transform: translateY(-3px);
        box-shadow:
            0 10px 30px rgba(15, 23, 42, .08),
            0 2px 6px rgba(15, 23, 42, .04);
    }

    /* ─────────────────────────────────────────────────────────────
   Scrollbar global
───────────────────────────────────────────────────────────── */

    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(100, 116, 139, .35) transparent;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 7px;
        height: 7px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, .35);
        border-radius: 999px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(100, 116, 139, .5);
    }

    /* ─────────────────────────────────────────────────────────────
   TABULATOR
───────────────────────────────────────────────────────────── */

    .tabulator {
        border: 1px solid rgba(226, 232, 240, .8) !important;
        border-radius: 18px !important;
        overflow: hidden !important;
        background: #ffffff !important;

        box-shadow:
            0 1px 2px rgba(15, 23, 42, .03),
            0 10px 30px rgba(15, 23, 42, .04) !important;

        font-size: 13px !important;
    }

    /* ───────────────── Header ───────────────── */

    .tabulator-header {
        background:
            linear-gradient(to bottom,
                #fcfcfd 0%,
                #f8fafc 100%) !important;

        border-top: none !important;
        border-left: none !important;
        border-right: none !important;
        border-bottom: 1px solid #eef2f7 !important;

        padding: 6px 0 4px 0 !important;
    }

    .tabulator-header .tabulator-col {
        background: transparent !important;
        border-right: none !important;
        min-height: 48px !important;
    }

    .tabulator-header .tabulator-col-content {
        padding: 12px 14px !important;
    }

    .tabulator-header .tabulator-col-title {
        width: 100% !important;

        text-align: left !important;

        font-size: 10px !important;
        font-weight: 800 !important;

        letter-spacing: .12em !important;
        text-transform: uppercase !important;

        color: #64748b !important;
    }

    /* Flechas sort */

    .tabulator-col-sorter {
        color: #94a3b8 !important;
    }

    /* ───────────────── Tabla ───────────────── */

    .tabulator-tableholder {
        overflow-x: auto !important;
        overflow-y: hidden !important;

        scrollbar-width: thin;
        scrollbar-color: rgba(100, 116, 139, .35) transparent;
    }

    .tabulator-tableholder::-webkit-scrollbar {
        height: 8px;
    }

    .tabulator-tableholder::-webkit-scrollbar-track {
        background: transparent;
    }

    .tabulator-tableholder::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, .35);
        border-radius: 999px;
    }

    /* ───────────────── Rows ───────────────── */

    .tabulator-row {
        background: #ffffff !important;

        border-left: none !important;
        border-right: none !important;
        border-bottom: 1px solid #f8fafc !important;

        transition:
            background .18s ease,
            transform .18s ease,
            box-shadow .18s ease !important;
    }

    .tabulator-row:last-child {
        border-bottom: none !important;
    }

    .tabulator-row:hover {
        background:
            linear-gradient(to right,
                rgba(59, 130, 246, .04),
                rgba(59, 130, 246, .01)) !important;

        box-shadow:
            inset 3px 0 0 #2563eb !important;
    }

    /* ───────────────── Cells ───────────────── */

    .tabulator-row .tabulator-cell {
        border-right: none !important;

        padding-top: 14px !important;
        padding-bottom: 14px !important;
        padding-left: 14px !important;
        padding-right: 14px !important;

        vertical-align: middle !important;

        color: #1e293b !important;
    }

    /* ───────────────── Footer ───────────────── */

    .tabulator-footer {
        background: #ffffff !important;

        border-top: 1px solid #eef2f7 !important;
        border-left: none !important;
        border-right: none !important;
        border-bottom: none !important;

        padding: 14px 18px !important;
    }

    /* Contenido footer */

    .tabulator-footer-contents {
        display: flex !important;

        align-items: center !important;
        justify-content: space-between !important;

        flex-wrap: wrap !important;

        gap: 12px !important;
    }

    /* Counter */

    .tabulator-footer .tabulator-page-counter {
        color: #475569 !important;

        font-size: 12px !important;
        font-weight: 700 !important;
    }

    /* Paginador */

    .tabulator-footer .tabulator-paginator {
        display: flex !important;

        align-items: center !important;
        gap: 6px !important;
    }

    /* Select */

    .tabulator-footer select.tabulator-page-size {
        height: 38px !important;

        padding: 0 36px 0 14px !important;

        border-radius: 12px !important;
        border: 1px solid #e2e8f0 !important;

        background-color: #ffffff !important;

        font-size: 12px !important;
        font-weight: 700 !important;

        color: #334155 !important;

        appearance: none !important;

        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.7' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;

        background-position: right .7rem center !important;
        background-repeat: no-repeat !important;
        background-size: 1.1em 1.1em !important;

        transition:
            border-color .18s ease,
            box-shadow .18s ease !important;
    }

    .tabulator-footer select.tabulator-page-size:hover {
        border-color: #93c5fd !important;
    }

    .tabulator-footer select.tabulator-page-size:focus {
        outline: none !important;

        border-color: #3b82f6 !important;

        box-shadow:
            0 0 0 3px rgba(59, 130, 246, .12) !important;
    }

    /* Botones páginas */

    .tabulator-footer .tabulator-page {
        min-width: 36px !important;
        height: 36px !important;

        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;

        border-radius: 11px !important;

        border: 1px solid #e2e8f0 !important;

        background: #ffffff !important;

        color: #475569 !important;

        font-size: 12px !important;
        font-weight: 700 !important;

        transition:
            all .18s ease !important;
    }

    .tabulator-footer .tabulator-page:hover:not(.active) {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;

        transform: translateY(-1px);
    }

    .tabulator-footer .tabulator-page.active {
        background:
            linear-gradient(135deg,
                #2563eb 0%,
                #1d4ed8 100%) !important;

        border-color: #1d4ed8 !important;

        color: #ffffff !important;

        box-shadow:
            0 6px 16px rgba(37, 99, 235, .25) !important;
    }

    /* ───────────────── Placeholder ───────────────── */

    /* ───────────────── Styled Checkboxes ───────────────── */

    .checkbox-personal-row:focus-visible,
    .checkbox-select-all:focus-visible {
        outline: 2px solid rgba(59, 130, 246, .4);
        outline-offset: 1px;
    }

    /* ───────────────── Placeholder ───────────────── */

    .tabulator-placeholder {
        min-height: 110px !important;

        display: flex !important;
        align-items: center !important;
        justify-content: center !important;

        background: #ffffff !important;
    }

    .tabulator-placeholder span {
        color: #94a3b8 !important;

        font-size: 13px !important;
        font-weight: 600 !important;
    }

    #btnGuardarMatriculas:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .btn-desmatricular {
        transition: all .2s ease;
    }

    .btn-desmatricular:hover {
        background: #fee2e2 !important;
        color: #991b1b !important;
        transform: scale(1.04);
    }

    .btn-desmatricular:hover .estado-icon,
    .btn-desmatricular:hover .estado-label {
        display: none;
    }

    .btn-desmatricular:hover::after {
        content: "Desmatricular";
        font-size: 11px;
        font-weight: 700;
    }

    .cnt-filter-btn {
        cursor: pointer;
        transition: all .2s ease;
        user-select: none;
    }

    .cnt-filter-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
    }

    .cnt-filter-btn.active {
        box-shadow: 0 0 0 2px #fff, 0 0 0 4px currentColor;
    }

    .cnt-filter-btn[data-filter="matriculados"].active {
        border-color: #3b82f6 !important;
    }

    .cnt-filter-btn[data-filter="sin-matricular"].active {
        border-color: #f59e0b !important;
    }

    .cnt-filter-btn[data-filter="seleccionados"].active {
        border-color: #22c55e !important;
    }
</style>
@endsection

@include('layouts.shared/page-title', ['subtitle' => 'Capacitación', 'title' => 'Gestión de matrículas'])

@section('content')
<div class="px-6 py-6">
    {{-- Header --}}
    <div
        class="relative overflow-hidden rounded-2xl border border-default-200/60 bg-gradient-to-br from-white via-default-50/50 to-primary/5 shadow-sm mb-6">
        <div class="absolute top-0 right-0 w-72 h-72 bg-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 w-48 h-48 bg-amber-500/5 rounded-full blur-2xl"></div>
        <div class="absolute top-1/2 right-1/4 w-32 h-32 bg-green-500/5 rounded-full blur-xl"></div>
        <div class="absolute inset-0 opacity-[0.015]"
            style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 24px 24px;">
        </div>

        <div class="relative p-8">
            <div class="flex items-start justify-between gap-6">
                <div class="flex-1">
                    <div
                        class="inline-flex items-center gap-2 w-fit px-3 py-1.5 rounded-full bg-primary/10 text-primary text-xs font-semibold">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></div>
                        <i class="ti ti-clipboard-list text-sm"></i>
                        Consulta de matrículas
                    </div>

                    <h1 class="text-3xl font-bold tracking-tight text-default-900 mt-4">
                        Gestión de Matrículas
                    </h1>

                    <p class="mt-3 text-sm leading-7 text-default-600 max-w-3xl">
                        Matrícula de participantes en los cursos de los planes de capacitación, gestionando matrículas grupales o individuales.
                    </p>

                    <div class="flex items-center gap-6 mt-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                <i class="ti ti-book-2 text-lg text-primary"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Cursos</p>
                                <p class="text-[10px] text-default-500">disponibles</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                                <i class="ti ti-users text-lg text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Personal</p>
                                <p class="text-[10px] text-default-500">matriculado</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden xl:flex flex-col items-center justify-center shrink-0">
                    <div
                        class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center">
                        <i class="ti ti-clipboard-list text-4xl text-primary/60"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cursos registrados -->
    <div class="rounded-2xl border border-default-200/60 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-default-900">Listado de cursos disponibles</h2>
        </div>

        {{-- Filtros de cursos --}}
        <div class="flex flex-col gap-3 bg-white rounded-xl p-4 border border-default-200/60 shadow-sm mb-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-default-700">Buscar curso</label>
                    <div class="relative">
                        <input type="text" id="txtBuscarCurso" placeholder="Nombre del curso..."
                            class="w-full h-9 pl-3 pr-8 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <button type="button" id="btnLimpiarBusquedaCurso"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-default-400 hover:text-default-700 transition cursor-pointer hidden">
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-default-700">Tipo de curso</label>
                    <select id="filtroTipoCurso" class="w-full h-9 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <option value="">Cargando tipos...</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-default-700">Área</label>
                    <select id="filtroAreaCurso" class="w-full h-9 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <option value="">Cargando áreas...</option>
                    </select>
                </div>
                <div id="filtroSistemaCursoContainer" class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-default-700">Sistema de gestión</label>
                    <select id="filtroSistemaCurso" class="w-full h-9 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <option value="">Cargando sistemas...</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-default-700">Jefatura</label>
                    <select id="filtroJefaturaCurso" class="w-full h-9 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <option value="">Cargando jefaturas...</option>
                    </select>
                </div>
                <div id="filtroClienteCursoContainer" class="flex flex-col gap-1.5" style="display: none;">
                    <label class="text-xs font-medium text-default-700">Cliente</label>
                    <select id="filtroClienteCurso" class="w-full h-9 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <option value="">Cargando clientes...</option>
                    </select>
                </div>

            </div>

            <div class="flex gap-2">
                <button id="btnLimpiarFiltrosCursos" type="button" class="h-9 px-5 inline-flex items-center justify-center gap-1.5 bg-default-100 text-default-700 text-xs font-semibold rounded-lg shadow-sm hover:bg-default-200 transition cursor-pointer">
                    <i class="ti ti-filter-off text-sm"></i>
                    Limpiar filtros
                </button>
            </div>
        </div>

        <div id="tblCursos" class="w-full"></div>
    </div>
</div>

<div id="modal-lista-matriculados" x-data="modalListaMatriculados()" x-show="open" x-cloak
    class="fixed inset-0 z-[120] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="relative flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-7xl border border-default-200 bg-white transition-all duration-300 max-h-[90vh]"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <!-- Loader full-modal -->
        <div x-show="isLoading"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-30 bg-white flex flex-col items-center justify-center text-center px-8">
            <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-slate-700 animate-spin"></div>
            <p class="mt-5 text-base font-bold text-default-900" x-text="loadingTitle">Cargando información...</p>
            <p class="mt-2 text-xs leading-5 text-default-400 max-w-md">Estamos trayendo la programación vigente y el personal del curso. En un momento continuamos.</p>
        </div>


        <!-- Header -->
        <div class="flex justify-between items-start py-5 px-6 border-b border-default-100 shrink-0 bg-gradient-to-r from-white to-default-50/30">
            <div>
                <h3 class="text-xl font-bold text-default-900">Gestionar matriculados</h3>
                <p class="text-sm text-default-500 mt-1">Gestión de personal matriculado en el curso</p>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <!-- Detalles del Curso -->
            <div class="bg-default-50/50 p-5 rounded-xl border border-default-200/60 mb-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-4">
                    <h4 class="text-xs font-bold text-default-500 uppercase tracking-wider flex items-center gap-2">
                        <i class="ti ti-info-circle text-primary"></i>
                        Detalles del Curso
                    </h4>
                </div>
                <div>
                    <span class="block text-[11px] text-default-500 font-medium">Nombre del Curso</span>
                    <span class="font-semibold text-default-900 text-sm" x-text="nombre"></span>
                </div>
                <div>
                    <span class="block text-[11px] text-default-500 font-medium">Responsable del Curso</span>
                    <span class="font-semibold text-default-900 text-sm" x-text="responsable"></span>
                </div>
                <div>
                    <span class="block text-[11px] text-default-500 font-medium">Área Responsable</span>
                    <span class="font-semibold text-default-900 text-sm" x-text="area"></span>
                </div>
                <div>
                    <span class="block text-[11px] text-default-500 font-medium">Sistema de Gestión</span>
                    <span class="font-semibold text-default-900 text-sm" x-text="sistema"></span>
                </div>
                <div class="lg:col-span-4">
                    <span class="block text-[11px] text-default-500 font-medium">Descripción</span>
                    <span class="text-default-700 text-sm" x-text="descripcion"></span>
                </div>
            </div>

            <!-- Programación vigente -->
            <div class="flex flex-col gap-1 bg-default-50 border border-default-200/60 rounded-xl px-4 py-3">
                <p class="text-[11px] font-semibold text-default-400 uppercase tracking-widest">Programación vigente del curso</p>
                <div id="infoProgramacionVigente" class="flex items-center gap-2 text-sm font-semibold text-default-900">
                    <i class="ti ti-calendar-event text-primary"></i>
                    <span id="txtProgramacionVigente">—</span>
                </div>
            </div>

            <!-- Filtros -->
            <div class="space-y-3 mb-3 mt-3">
                <!-- Filtros secundarios -->
                <div class="border border-default-200/60 rounded-xl bg-default-50/50 px-4 pt-2.5 pb-3">
                    <p class="text-[11px] font-semibold text-default-400 uppercase tracking-widest mb-2">Filtros</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <select id="slcFiltroCliente" class="w-full px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                <option value="">Todos los clientes</option>
                            </select>
                        </div>
                        <div>
                            <select id="slcFiltroSucursal" class="w-full px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                <option value="">Todas las sucursales</option>
                            </select>
                        </div>
                        <div>
                            <select id="slcFiltroCargo" class="w-full px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                <option value="">Todos los cargos</option>
                            </select>
                        </div>
                        <div>
                            <select id="slcFiltroTipoTrabajador" class="w-full px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                <option value="">Todos los tipos</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Buscador -->
                <div class="border border-default-200/60 rounded-xl bg-default-50/50 px-4 pt-2.5 pb-3">
                    <p class="text-[11px] font-semibold text-default-400 uppercase tracking-widest mb-2">Búsqueda de personal</p>

                    <div class="relative">
                        <input type="text" id="txtBuscarPersonal"
                            placeholder="Buscar por nombre, código o DNI..."
                            class="w-full pl-8 pr-8 text-[11px] border border-default-200 rounded-xl bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 transition shadow-sm placeholder:text-default-400">
                        <button type="button" id="btnLimpiarBusqueda"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-default-400 hover:text-default-700 transition cursor-pointer hidden">
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Acciones de selección -->
            <div class="flex flex-wrap items-center gap-2 mb-4" id="infoModoMatricula">
                <button type="button" id="btnSeleccionarFiltrados" class="ml-auto flex items-center gap-1.5 px-3 py-2 rounded-lg border border-primary/20 bg-primary/5 text-primary text-xs font-semibold shadow-sm hover:bg-primary/10 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ti ti-checks text-sm"></i>
                    <span>Seleccionar filtrados (0)</span>
                </button>
            </div>

            <!-- Tabla -->
            <div class="border border-default-200/60 rounded-xl overflow-hidden shadow-sm bg-white">
                <div id="tblPersonalMatriculado" class="w-full min-h-[400px]"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end items-center gap-3 px-6 py-4 border-t border-default-100 shrink-0 bg-default-50/50">
            <label id="lblNotificarMatricula" class="mr-auto flex items-center gap-2 text-xs font-medium text-default-500 cursor-pointer select-none" title="Se avisará al coordinador por correo al finalizar (solo si son más de 10 personas)">
                <input type="checkbox" id="chkNotificarMatricula" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                Avisar por correo al finalizar
            </label>
            <button type="button" @click="cerrar()"
                class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition cursor-pointer">
                Cerrar
            </button>
            <button type="button" id="btnGuardarMatriculas"
                class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary/90 focus:ring-2 focus:ring-primary/30 transition cursor-pointer">
                <i class="ti ti-user-plus"></i> Matricular personal (0)
            </button>
        </div>
    </div>
</div>

<script>
    window.modalListaMatriculados = function() {
        return {
            open: false,
            isLoading: false,
            loadingTitle: 'Cargando información...',
            cursoId: null,
            nombre: '',
            area: '',
            sistema: '',
            responsable: '',
            descripcion: '',

            mostrar(data) {
                this.open = true;
                this.isLoading = true;

                this.cursoId = data.LocalId;
                this.nombre = data.Nombre || '';
                this.area = data.Area || '';
                this.sistema = data.Sistema || '';
                this.responsable = data.Responsable || '';
                this.descripcion = data.Descripcion || '';
                this.codResponsable = data.Cod_Responsable || '';

                if (window._iniciarTextoLoader) window._iniciarTextoLoader(this);
            },

            cerrar() {
                this.open = false;
                this.isLoading = false;
                if (window._detenerTextoLoader) window._detenerTextoLoader();
                if (window.limpiarModalMatriculados) window.limpiarModalMatriculados();
            },
        }
    }
</script>
@endsection
@section('script')
@vite(['resources/js/functions/capacitacion/consulta_matriculas.js'])
@endsection