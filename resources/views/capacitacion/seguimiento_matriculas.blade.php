@extends('layouts.vertical', ['title' => 'Seguimiento de Matrículas'])
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

    .tabulator-row .checkbox-personal-row:checked::after {
        content: "✓";
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
    }

    .checkbox-select-all:checked::after {
        content: "✓";
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
    }

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
</style>
@endsection

@include('layouts.shared/page-title', ['subtitle' => 'Capacitación', 'title' => 'Seguimiento AV'])

@section('content')

<div x-data="{ tabActivo: 'cursos' }">
    <!-- Header de página -->
    <div class="px-6 py-6">
        <div
            class="relative overflow-hidden rounded-2xl border border-default-200/60 bg-gradient-to-br from-white via-default-50/50 to-primary/5 shadow-sm">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-72 h-72 bg-primary/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 w-48 h-48 bg-amber-500/5 rounded-full blur-2xl"></div>
            <div class="absolute top-1/2 right-1/4 w-32 h-32 bg-green-500/5 rounded-full blur-xl"></div>

            <!-- Grid pattern overlay -->
            <div class="absolute inset-0 opacity-[0.015]"
                style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 24px 24px;">
            </div>

            <div class="relative p-8 flex flex-col xl:flex-row gap-6">
                <!-- Columna informativa -->
                <div class="flex flex-col gap-3 xl:w-[70%]">

                    <div
                        class="inline-flex items-center gap-2 w-fit px-3 py-1.5 rounded-full bg-primary/10 text-primary text-xs font-semibold">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></div>
                        <i class="ti ti-chart-bar text-sm"></i>
                        Panel de seguimiento de matrículas
                    </div>

                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-default-900 mt-4">
                            Seguimiento AV
                        </h1>

                        <p class="mt-3 text-sm leading-7 text-default-600 max-w-3xl">
                            Control de los participantes matriculados, aplicando medidas disciplinarias en los casos de incumplimiento de la programación.
                        </p>
                    </div>

                    <!-- Quick stats -->
                    <div class="flex items-center gap-6 mt-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                <i class="ti ti-users text-lg text-primary"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Matriculados</p>
                                <p class="text-[10px] text-default-500">Total inscritos</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                                <i class="ti ti-player-play text-lg text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">En progreso</p>
                                <p class="text-[10px] text-default-500">Activos ahora</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-sky-500/10 flex items-center justify-center">
                                <i class="ti ti-mail text-lg text-sky-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Notificaciones</p>
                                <p class="text-[10px] text-default-500">por correo</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                                <i class="ti ti-notes text-lg text-purple-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">MEMOs</p>
                                <p class="text-[10px] text-default-500">seguimiento por nivel</p>
                            </div>
                        </div>
                    </div>

                    <!-- Info badges -->
                    <div x-data="infoBadges()" class="flex flex-wrap gap-2 pt-2">
                        <div @click="abrirInfo('Participantes matriculados', 'Son todos los colaboradores que han sido registrados en un curso. Este indicador muestra el total de personas inscritas, independientemente de si han comenzado o finalizado el curso.')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-default-200 text-xs text-default-600 cursor-pointer hover:bg-primary/5 transition-colors">
                            <i class="ti ti-users text-xs text-primary"></i>
                            <span>Participantes matriculados</span>
                        </div>

                        <div @click="abrirInfo('Usuarios en progreso', 'Son aquellos colaboradores que ya ingresaron al curso y han avanzado en al menos una actividad o módulo, pero aún no lo completan. Refleja el grupo activo que está actualmente capacitándose.')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-default-200 text-xs text-default-600 cursor-pointer hover:bg-amber-50 transition-colors">
                            <i class="ti ti-player-play text-xs text-amber-600"></i>
                            <span>Usuarios en progreso</span>
                        </div>

                        {{-- Badge Notificaciones por correo (comentado temporalmente)
                        <div @click="abrirInfo('Notificaciones por correo', mensajes.notificaciones)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-default-200 text-xs text-default-600 cursor-pointer hover:bg-sky-50 transition-colors">
                            <i class="ti ti-mail text-xs text-sky-600"></i>
                            <span>Notificaciones por correo</span>
                        </div>
                        --}}

                        <div @click="abrirInfo('MEMOs por nivel', mensajes.memos)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-default-200 text-xs text-default-600 cursor-pointer hover:bg-purple-50 transition-colors">
                            <i class="ti ti-notes text-xs text-purple-600"></i>
                            <span>MEMOs por nivel</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Listado de cursos / Personal / Memos Enviados -->
    <div class="px-6 pb-6">
        <div class="rounded-2xl border border-default-200/60 bg-white shadow-sm p-6">
            <!-- Tab Navigation -->
            <div class="flex flex-wrap items-center gap-1 mb-4">
                <button
                    @click="tabActivo = 'cursos'; setTimeout(() => { if (window.tabulatorCursos) window.tabulatorCursos.redraw(true) }, 100)"
                    :class="tabActivo === 'cursos'
                    ? 'inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold whitespace-nowrap text-primary bg-primary/10 rounded-xl transition-all'
                    : 'inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap text-default-500 hover:text-default-700 hover:bg-default-100 rounded-xl transition-all'">
                    <i class="ti ti-book text-sm"></i>
                    Cursos registrados
                </button>
                <button
                    @click="tabActivo = 'personal'; setTimeout(() => { if (window.tabulatorPersonal) window.tabulatorPersonal.redraw(true) }, 100)"
                    :class="tabActivo === 'personal'
                    ? 'inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold whitespace-nowrap text-primary bg-primary/10 rounded-xl transition-all'
                    : 'inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap text-default-500 hover:text-default-700 hover:bg-default-100 rounded-xl transition-all'">
                    <i class="ti ti-users text-sm"></i>
                    Lista de personal
                </button>
                <button
                    @click="tabActivo = 'memos'; setTimeout(() => { if (window.tabulatorMemos) window.tabulatorMemos.redraw(true) }, 100)"
                    :class="tabActivo === 'memos'
                    ? 'inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold whitespace-nowrap text-primary bg-primary/10 rounded-xl transition-all'
                    : 'inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap text-default-500 hover:text-default-700 hover:bg-default-100 rounded-xl transition-all'">
                    <i class="ti ti-notes text-sm"></i>
                    MEMOs enviados
                </button>
            </div>

            <!-- Tab 1: Cursos registrados -->
            <div x-show="tabActivo === 'cursos'" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
                    <h2 class="text-base font-bold text-default-900">Cursos registrados</h2>
                    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full lg:w-auto">
                        <div class="relative sm:flex-none sm:min-w-[220px]">
                            <i
                                class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                            <input id="buscarCursoSeguimiento" placeholder="Buscar por nombre o código..."
                                class="w-full sm:w-64 pl-8 pr-3 py-2 text-sm border border-default-200 rounded-lg !bg-white !text-default-700 placeholder:text-default-300 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        </div>
                        <select id="filtroResponsableCursos"
                            class="w-full sm:w-56 h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                            <option value="">Todos los responsables</option>
                        </select>
                        <select id="filtroTipoCursos"
                            class="w-full sm:w-56 h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                            <option value="">Todos los tipos</option>
                        </select>
                        <select id="filtroAnioCursos"
                            class="w-full sm:w-40 h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                            <option value="">Todos los años</option>
                        </select>
                    </div>
                </div>
                <div id="tblCursosSeguimiento" class="w-full"></div>
            </div>

            <!-- Tab 2: Lista de personal -->
            <div x-show="tabActivo === 'personal'" class="min-h-[400px]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2">
                <div class="flex flex-col gap-3 mb-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-default-900">Lista de personal</h2>
                        <div id="selectedPersonalInfo" class="flex items-center gap-1.5 text-xs font-medium text-primary bg-primary/5 px-2.5 py-1 rounded-full">
                            <i class="ti ti-checks text-sm"></i>
                            <span><span id="selectedPersonalCount">0</span> seleccionados</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 bg-white rounded-xl p-4 border border-default-200/60 shadow-sm" x-data="filtrosPersonal()">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-medium text-default-700">Sucursal</label>
                                <div class="relative">
                                    <i class="ti ti-building-store absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                                    <select id="filtroSucursalPersonal" :disabled="tabActivo !== 'personal' && tabActivo !== 'memos'"
                                        @change="verificar()"
                                        style="background-image: none !important;" class="w-full pl-8 pr-8 py-1.5 text-sm border border-default-200 rounded-lg
                                   !bg-white !text-default-700 !appearance-none cursor-pointer
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                                        :class="{ '!cursor-not-allowed opacity-60': tabActivo !== 'personal' && tabActivo !== 'memos' }">
                                        <option value="">Todas las sucursales</option>
                                    </select>
                                    <i class="ti ti-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-default-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-medium text-default-700">Tipo de trabajador</label>
                                <div class="relative">
                                    <i class="ti ti-briefcase absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                                    <select id="filtroTipoPersonal" :disabled="tabActivo !== 'personal' && tabActivo !== 'memos'"
                                        @change="verificar()"
                                        style="background-image: none !important;" class="w-full pl-8 pr-8 py-1.5 text-sm border border-default-200 rounded-lg
                                   !bg-white !text-default-700 !appearance-none cursor-pointer
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                                        :class="{ '!cursor-not-allowed opacity-60': tabActivo !== 'personal' && tabActivo !== 'memos' }">
                                        <option value="">Todos los tipos</option>
                                    </select>
                                    <i class="ti ti-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-default-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-medium text-default-700">Cargo</label>
                                <div class="relative">
                                    <i class="ti ti-tag absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                                    <select id="filtroCargoPersonal" :disabled="tabActivo !== 'personal' && tabActivo !== 'memos'"
                                        @change="verificar()"
                                        style="background-image: none !important;" class="w-full pl-8 pr-8 py-1.5 text-sm border border-default-200 rounded-lg
                                   !bg-white !text-default-700 !appearance-none cursor-pointer
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                                        :class="{ '!cursor-not-allowed opacity-60': tabActivo !== 'personal' && tabActivo !== 'memos' }">
                                        <option value="">Todos los cargos</option>
                                    </select>
                                    <i class="ti ti-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-default-400 pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-medium text-default-700">Cliente</label>
                                <div class="relative">
                                    <i class="ti ti-building absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                                    <select id="filtroClientePersonal" :disabled="tabActivo !== 'personal' && tabActivo !== 'memos'"
                                        @change="verificar()"
                                        style="background-image: none !important;" class="w-full pl-8 pr-8 py-1.5 text-sm border border-default-200 rounded-lg
                                   !bg-white !text-default-700 !appearance-none cursor-pointer
                                   focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"
                                        :class="{ '!cursor-not-allowed opacity-60': tabActivo !== 'personal' && tabActivo !== 'memos' }">
                                        <option value="">Todos los clientes</option>
                                        <option value="Sin cliente">Sin cliente</option>
                                    </select>
                                    <i class="ti ti-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-default-400 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5" x-data="searchPersonalSeguimiento()">
                            <label class="text-xs font-medium text-default-700">Buscar personal</label>
                            <div class="relative">
                                <i class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                                <input x-ref="inputBuscar" x-model="query" @input="search(); posicionarDropdown()"
                                    @focus="posicionarDropdown()" @keydown.enter.stop="seleccionarPrimerResultado()"
                                    @click.away="open = false" :disabled="tabActivo === 'memos'"
                                    placeholder="Buscar por DNI o nombres..." class="w-full pl-8 pr-3 py-2 text-sm border border-default-200 rounded-lg
                    !bg-white !text-default-700 placeholder:text-default-300
                    focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50
                    transition-shadow duration-150"
                                    :class="{ '!cursor-not-allowed opacity-60': tabActivo === 'memos' }" />

                                <div x-ref="dropdown" x-show="open && results.length > 0" x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    class="fixed z-[999] bg-white border border-default-200 rounded-xl shadow-xl overflow-hidden">

                                    <div class="px-3 py-2 border-b border-default-100">
                                        <span class="text-[10px] font-semibold tracking-widest uppercase text-default-400">Resultados</span>
                                    </div>

                                    <div class="max-h-56 overflow-y-auto custom-scrollbar">
                                        <template x-for="(p, idx) in results" :key="idx">
                                            <div @click="seleccionarPersonal(p)" class="flex items-center gap-3 px-3 py-2.5 cursor-pointer
                                hover:bg-primary/5 border-b border-default-100 last:border-b-0
                                transition-colors duration-100 group">

                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-default-800 truncate"
                                                        x-text="p.nombre_completo"></p>
                                                    <p class="text-xs mt-0.5">
                                                        <span class="text-default-400">DNI</span>
                                                        <span class="ml-1 bg-default-100 text-default-600 rounded px-1 py-0.5 font-mono text-[11px]"
                                                            x-text="p.dni || '—'"></span>
                                                    </p>
                                                </div>

                                                <i class="ti ti-chevron-right text-xs text-default-300
                                group-hover:text-primary group-hover:translate-x-0.5
                                transition-all duration-150 shrink-0"></i>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-2 w-full">

                            <button
                                id="btnLimpiarFiltroPersonal"
                                type="button"
                                :disabled="!hayFiltros"
                                :class="hayFiltros
                                    ? 'flex-1 h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-default-100 text-default-700 text-xs font-semibold rounded-lg shadow-sm hover:bg-default-200 transition cursor-pointer'
                                    : 'flex-1 h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-default-100 text-default-300 text-xs font-semibold rounded-lg cursor-not-allowed'">
                                <i class="ti ti-filter-off text-sm"></i>
                                Limpiar filtros
                            </button>

                            <button
                                id="btnDeshacerSeleccionPersonal"
                                type="button"
                                disabled
                                class="h-9 px-4 inline-flex items-center justify-center gap-1.5
            bg-white text-default-700 text-xs font-semibold
            rounded-lg shadow-sm border border-default-200
            opacity-50 cursor-not-allowed
            hover:bg-default-50 transition shrink-0">
                                <i class="ti ti-x text-sm"></i>
                                Deshacer selección
                            </button>

                            <button
                                id="btnEnviarMemosPersonal"
                                type="button"
                                disabled
                                class="flex-1 h-9 px-4 inline-flex items-center justify-center gap-1.5
            bg-primary text-white text-xs font-semibold
            rounded-lg shadow-sm opacity-50 cursor-not-allowed
            hover:bg-primary/90 transition">
                                <i class="ti ti-send text-sm"></i>
                                <span id="btnEnviarMemosText">Enviar MEMOs al personal seleccionado</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="tblPersonalSeguimiento" class="w-full"></div>
            </div>

            <!-- Tab 3: Lista de MEMOs enviados (por nivel) -->
            <div x-show="tabActivo === 'memos'" class="min-h-[400px]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2">
                <div class="flex items-center justify-between mb-2.5">
                    <h2 class="text-base font-bold text-default-900">MEMOs enviados a personal</h2>
                </div>

                <div class="flex flex-col gap-3">
                    <div x-data="memosStats()" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <!-- MEMOs de nivel uno enviados -->
                        <div
                            @click="filtrarNivel(1)"
                            :class="nivelActivo === 1
                                ? 'rounded-xl border-2 border-blue-400 bg-blue-50/40 shadow-md ring-2 ring-blue-200/50 cursor-pointer transition-all duration-200 -translate-y-0.5'
                                : 'rounded-xl border border-primary/20 bg-white shadow-sm cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:border-blue-300'">
                            <div class="flex items-center gap-3 px-4 py-3 border-b border-primary/10 bg-gradient-to-r from-blue-50/80 to-transparent">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                    <i class="ti ti-info-circle text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-blue-800">Nivel uno</h3>
                                    <p class="text-[10px] text-blue-500">Informativo</p>
                                </div>
                            </div>
                            <div class="px-4 py-3 flex items-center justify-between">
                                <span class="text-[11px] text-default-500">Total enviados</span>
                                <span class="text-lg font-bold text-blue-600"
                                    x-text="totalNivelUno"></span>
                            </div>
                        </div>

                        <!-- MEMOs de nivel dos enviados -->
                        <div @click="filtrarNivel(2)"
                            :class="nivelActivo === 2
                                ? 'rounded-xl border-2 border-orange-400 bg-orange-50/40 shadow-md ring-2 ring-orange-200/50 cursor-pointer transition-all duration-200 -translate-y-0.5'
                                : 'rounded-xl border border-primary/20 bg-white shadow-sm cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:border-orange-300'">
                            <div class="flex items-center gap-3 px-4 py-3 border-b border-orange-100 bg-gradient-to-r from-orange-50/80 to-transparent">
                                <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                                    <i class="ti ti-alert-triangle text-orange-600 text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-orange-800">Nivel dos</h3>
                                    <p class="text-[10px] text-orange-500">Advertencia</p>
                                </div>
                            </div>
                            <div class="px-4 py-3 flex items-center justify-between">
                                <span class="text-[11px] text-default-500">Total enviados</span>
                                <span class="text-lg font-bold text-orange-600"
                                    x-text="totalNivelDos"></span>
                            </div>
                        </div>

                        <!-- MEMOs de nivel tres enviados -->
                        <div @click="filtrarNivel(3)"
                            :class="nivelActivo === 3
                                ? 'rounded-xl border-2 border-red-400 bg-red-50/40 shadow-md ring-2 ring-red-200/50 cursor-pointer transition-all duration-200 -translate-y-0.5'
                                : 'rounded-xl border border-primary/20 bg-white shadow-sm cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:border-red-300'">
                            <div class="flex items-center gap-3 px-4 py-3 border-b border-red-100 bg-gradient-to-r from-red-50/80 to-transparent">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                                    <i class="ti ti-bell-ringing text-red-600 text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-red-800">Nivel tres</h3>
                                    <p class="text-[10px] text-red-500">Crítico</p>
                                </div>
                            </div>
                            <div class="px-4 py-3 flex items-center justify-between">
                                <span class="text-[11px] text-default-500">Total enviados</span>
                                <span class="text-lg font-bold text-red-600"
                                    x-text="totalNivelTres"></span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="document.getElementById('modal-comparativa')._x_dataStack?.[0]?.abrir()"
                        class="w-full px-4 py-2.5 inline-flex items-center justify-center gap-2
                            rounded-xl border border-purple-200/60
                            bg-white/70 backdrop-blur
                            text-purple-700 text-sm font-semibold
                            shadow-sm
                            hover:bg-purple-50 hover:border-purple-300
                            hover:shadow-md hover:shadow-purple-100
                            active:scale-[0.99]
                            transition-all duration-200 ease-out
                            focus:outline-none focus:ring-2 focus:ring-purple-400/20">
                        <i class="ti ti-chart-comparison text-base text-purple-600"></i>
                        <span>Comparar MEMOs</span>
                    </button>

                    <div class="w-full flex flex-col gap-2">
                        <div class="flex flex-col md:flex-row gap-2">
                            <div class="relative flex-1">
                                <input
                                    id="buscarMemosEnviados"
                                    type="text"
                                    placeholder="Buscar por nombre o DNI..."
                                    class="w-full h-10 pl-9 pr-3 text-sm text-default-700 placeholder:text-default-400
                       bg-white border border-default-200 rounded-lg shadow-sm
                       outline-none transition
                       hover:border-default-300
                       focus:border-primary/50 focus:ring-2 focus:ring-primary/10" />
                            </div>

                            <div class="w-full md:w-64">
                                <select
                                    id="filtroClienteMemos"
                                    class="w-full h-10 px-3 text-sm text-default-700
                       bg-white border border-default-200 rounded-lg shadow-sm
                       outline-none transition
                       hover:border-default-300
                       focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                                    <option value="">Todos los clientes</option>
                                </select>
                            </div>

                            <div class="w-full md:w-64">
                                <select
                                    id="filtroTipoTrabMemos"
                                    class="w-full h-10 px-3 text-sm text-default-700
                       bg-white border border-default-200 rounded-lg shadow-sm
                       outline-none transition
                       hover:border-default-300
                       focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                                    <option value="">Todos los tipos</option>
                                </select>
                            </div>

                            <div class="w-full md:w-64">
                                <select
                                    id="filtroSucursalMemos"
                                    class="w-full h-10 px-3 text-sm text-default-700
                       bg-white border border-default-200 rounded-lg shadow-sm
                       outline-none transition
                       hover:border-default-300
                       focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                                    <option value="">Todas las sucursales</option>
                                </select>
                            </div>

                            <button
                                id="btnLimpiarFiltroMemos"
                                type="button"
                                class="h-10 px-4 inline-flex items-center gap-1.5
                   bg-default-100 text-default-700 text-xs font-semibold
                   rounded-lg shadow-sm
                   hover:bg-default-200 transition">
                                Limpiar filtros
                            </button>
                        </div>
                    </div>

                    <div id="tblMemosEnviados"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal detalle de curso -->
<div id="modal-detalle-curso" x-data="modalCurso()" x-show="open" x-cloak
    class="fixed inset-0 z-[80] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-3xl border border-default-200 bg-white transition-all duration-300"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <!-- Header -->
        <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center text-white shadow-sm shrink-0">
                    <i class="ti ti-book text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-[15px] font-semibold text-default-900 leading-tight truncate" x-text="curso.nombre">Cargando...</h3>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wider"
                            x-text="'#' + curso.codigo"></span>
                        <template x-if="curso.responsable">
                            <div class="flex items-center gap-1.5">
                                <span class="text-default-300">|</span>
                                <i class="ti ti-user text-[10px] text-default-400"></i>
                                <span class="text-[10px] font-semibold text-default-500 truncate" x-text="curso.responsable"></span>
                            </div>
                        </template>
                        <template x-if="curso.fechaCreacion">
                            <div class="flex items-center gap-1.5">
                                <span class="text-default-300">|</span>
                                <i class="ti ti-calendar text-[10px] text-default-400"></i>
                                <span class="text-[10px] text-default-400" x-text="curso.fechaCreacion"></span>
                            </div>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                        <template x-if="curso.programacion_actual">
                            <div class="flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                <span class="text-[11px] text-default-600">
                                    <span class="font-semibold">Progamación actual:</span>
                                    <span x-text="formatearFecha(curso.programacion_actual.fecha_inicio) + ' al ' + formatearFecha(curso.programacion_actual.fecha_final)"></span>
                                </span>
                            </div>
                        </template>
                        <template x-if="!curso.programacion_actual">
                            <div class="flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                                <span class="text-[11px] text-default-600">
                                    <span class="font-semibold">Sin programación aperturada o creada.</span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- Loader -->
        <div x-show="loading" class="flex flex-col items-center justify-center py-16 text-default-400">
            <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
            <p class="text-sm">Cargando detalles del curso...</p>
        </div>

        <!-- Stats Cards -->
        <div x-show="!loading" class="px-6 pt-5 pb-3">
            <p class="text-[10px] font-bold text-default-400 uppercase tracking-widest mb-3">Estadísticas del curso</p>
            <div class="grid grid-cols-5 gap-3">
                <!-- Total Matriculados -->
                <div @click="abrirModalUsuarios(0)"
                    class="text-center p-3 rounded-xl bg-default-50 border border-default-200/60 cursor-pointer hover:shadow-md hover:border-primary/30 transition-all duration-200">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center mx-auto mb-1.5">
                        <i class="ti ti-users text-lg text-primary"></i>
                    </div>
                    <p class="text-[9px] text-default-500 uppercase tracking-widest font-bold mb-0.5">Matriculados</p>
                    <p class="text-xl font-bold text-default-900" x-text="curso.total">0</p>
                </div>

                <!-- Aprobados -->
                <div @click="abrirModalUsuarios(1)"
                    class="text-center p-3 rounded-xl bg-green-50 border border-green-200/60 cursor-pointer hover:shadow-md hover:border-green-400/30 transition-all duration-200">
                    <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-1.5">
                        <i class="ti ti-circle-check text-lg text-green-600"></i>
                    </div>
                    <p class="text-[9px] text-green-700 uppercase tracking-widest font-bold mb-0.5">Aprobados</p>
                    <p class="text-xl font-bold text-green-700" x-text="curso.estadisticas.aprobados">0</p>
                </div>

                <!-- Desaprobados -->
                <div @click="abrirModalUsuarios(2)"
                    class="text-center p-3 rounded-xl bg-red-50 border border-red-200/60 cursor-pointer hover:shadow-md hover:border-red-400/30 transition-all duration-200">
                    <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center mx-auto mb-1.5">
                        <i class="ti ti-circle-x text-lg text-red-600"></i>
                    </div>
                    <p class="text-[9px] text-red-700 uppercase tracking-widest font-bold mb-0.5">Desaprobados</p>
                    <p class="text-xl font-bold text-red-700" x-text="curso.estadisticas.desaprobados">0</p>
                </div>

                <!-- Sin acceder -->
                <div @click="abrirModalUsuarios(3)"
                    class="text-center p-3 rounded-xl bg-gray-50 border border-gray-200/60 cursor-pointer hover:shadow-md hover:border-gray-400/30 transition-all duration-200">
                    <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center mx-auto mb-1.5">
                        <i class="ti ti-eye-off text-lg text-gray-500"></i>
                    </div>
                    <p class="text-[9px] text-gray-600 uppercase tracking-widest font-bold mb-0.5">Sin acceder</p>
                    <p class="text-xl font-bold text-gray-700" x-text="curso.estadisticas.sin_acceder">0</p>
                </div>

                <!-- En curso -->
                <div @click="abrirModalUsuarios(4)"
                    class="text-center p-3 rounded-xl bg-blue-50 border border-blue-200/60 cursor-pointer hover:shadow-md hover:border-blue-400/30 transition-all duration-200">
                    <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-1.5">
                        <i class="ti ti-player-play text-lg text-blue-600"></i>
                    </div>
                    <p class="text-[9px] text-blue-700 uppercase tracking-widest font-bold mb-0.5">En curso</p>
                    <p class="text-xl font-bold text-blue-700" x-text="curso.estadisticas.en_curso">0</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
            <button type="button" @click="cerrar()"
                class="px-5 h-9 inline-flex justify-center items-center rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                Cerrar
            </button>
            <button type="button" @click="cerrar()"
                class="px-5 h-9 inline-flex justify-center items-center rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all cursor-pointer">
                Entendido
            </button>
        </div>
    </div>
</div>

<!-- Modal lista de usuarios del curso -->
<div id="modal-lista-usuarios" x-data="modalListaUsuarios()" x-show="open" x-cloak
    class="fixed inset-0 z-[120] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-7xl border border-default-200 bg-white transition-all duration-300 max-h-[90vh]"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4"
        @click.outside="cerrar()">

        <!-- Header -->
        <div class="flex justify-between items-start py-5 px-6 border-b border-default-100 shrink-0 bg-gradient-to-r from-white to-default-50/30">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="relative shrink-0">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center text-white shadow-sm">
                        <i class="ti text-lg" :class="{
                            'ti-users': statusId === 0,
                            'ti-circle-check': statusId === 1,
                            'ti-circle-x': statusId === 2,
                            'ti-eye-off': statusId === 3,
                            'ti-player-play': statusId === 4
                        }"></i>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-white"
                        :class="{
                            'bg-default-400': statusId === 0,
                            'bg-green-500': statusId === 1,
                            'bg-red-500': statusId === 2,
                            'bg-gray-400': statusId === 3,
                            'bg-blue-500': statusId === 4
                        }"></div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h3 class="text-[15px] font-semibold text-default-900 leading-tight truncate" x-text="cursoNombre">Cargando...</h3>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold shadow-sm"
                            :class="{
                                'bg-default-100 text-default-700': statusId === 0,
                                'bg-green-100 text-green-700': statusId === 1,
                                'bg-red-100 text-red-700': statusId === 2,
                                'bg-gray-100 text-gray-600': statusId === 3,
                                'bg-blue-100 text-blue-700': statusId === 4
                            }">
                            <i class="ti text-[10px]"
                                :class="{
                                    'ti-users': statusId === 0,
                                    'ti-circle-check': statusId === 1,
                                    'ti-circle-x': statusId === 2,
                                    'ti-eye-off': statusId === 3,
                                    'ti-player-play': statusId === 4
                                }"></i>
                            <span x-text="statusLabel"></span>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-[11px] text-default-500">
                        <div class="flex items-center gap-1" x-show="!loading && personales.length > 0">
                            <i class="ti ti-users text-xs"></i>
                            <span>Total: <strong x-text="personales.length" class="text-default-700"></strong></span>
                        </div>
                        <div class="flex items-center gap-1" x-show="!loading && personales.length > 0 && personalesFiltrados.length !== personales.length">
                            <i class="ti ti-filter text-xs"></i>
                            <span>Filtrados: <strong x-text="personalesFiltrados.length" class="text-primary-600"></strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <!-- Filtros -->
        <div x-show="!loading && personales.length > 0" class="px-6 pt-4 pb-3 border-b border-default-100 shrink-0 bg-default-50/30">
            <div class="flex flex-wrap items-end gap-3">
                <!-- Buscador -->
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-[10px] font-bold text-default-400 uppercase tracking-widest mb-1.5">
                        <i class="ti ti-search text-[10px]"></i> Buscar
                    </label>
                    <div class="relative">
                        <input type="text" x-model="busqueda" @input="paginaActual = 1" placeholder="Buscar por nombre o DNI..."
                            class="w-full h-9 pl-9 pr-3 text-xs text-default-700 bg-white border border-default-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder:text-default-300">
                    </div>
                </div>

                <!-- Tipo de Trabajador -->
                <div class="min-w-[160px]">
                    <label class="block text-[10px] font-bold text-default-400 uppercase tracking-widest mb-1.5">
                        <i class="ti ti-briefcase text-[10px]"></i> Tipo Trab.
                    </label>
                    <select x-model="filtroTipoTrabajador" @change="paginaActual = 1"
                        class="w-full h-9 px-3 text-xs text-default-700 bg-white border border-default-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer">
                        <option value="">Todos</option>
                        <template x-for="t in tiposTrabajadorUnicos" :key="t">
                            <option x-text="t" :value="t"></option>
                        </template>
                    </select>
                </div>

                <!-- Cargo -->
                <div class="min-w-[180px]">
                    <label class="block text-[10px] font-bold text-default-400 uppercase tracking-widest mb-1.5">
                        <i class="ti ti-id-badge text-[10px]"></i> Cargo
                    </label>
                    <select x-model="filtroCargo" @change="paginaActual = 1"
                        class="w-full h-9 px-3 text-xs text-default-700 bg-white border border-default-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer">
                        <option value="">Todos</option>
                        <template x-for="c in cargosUnicos" :key="c">
                            <option x-text="c" :value="c"></option>
                        </template>
                    </select>
                </div>

                <!-- Sucursal -->
                <div class="min-w-[160px]">
                    <label class="block text-[10px] font-bold text-default-400 uppercase tracking-widest mb-1.5">
                        <i class="ti ti-building text-[10px]"></i> Sucursal
                    </label>
                    <select x-model="filtroSucursal" @change="paginaActual = 1"
                        class="w-full h-9 px-3 text-xs text-default-700 bg-white border border-default-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer">
                        <option value="">Todas</option>
                        <template x-for="s in sucursalesUnicas" :key="s">
                            <option x-text="s" :value="s"></option>
                        </template>
                    </select>
                </div>

                <!-- Fecha Desde -->
                <div class="min-w-[140px]">
                    <label class="block text-[10px] font-bold text-default-400 uppercase tracking-widest mb-1.5">
                        <i class="ti ti-calendar text-[10px]"></i> Desde
                    </label>
                    <input type="date" x-model="fechaDesde" @change="paginaActual = 1"
                        class="w-full h-9 px-3 text-xs text-default-700 bg-white border border-default-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                </div>

                <!-- Fecha Hasta -->
                <div class="min-w-[140px]">
                    <label class="block text-[10px] font-bold text-default-400 uppercase tracking-widest mb-1.5">
                        <i class="ti ti-calendar text-[10px]"></i> Hasta
                    </label>
                    <input type="date" x-model="fechaHasta" @change="paginaActual = 1"
                        class="w-full h-9 px-3 text-xs text-default-700 bg-white border border-default-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                </div>

                <!-- Limpiar filtros -->
                <button type="button" @click="limpiarFiltros()"
                    x-show="busqueda || fechaDesde || fechaHasta || filtroCargo || filtroSucursal || filtroTipoTrabajador || ordenCampo !== 'default'"
                    class="h-9 px-3 inline-flex items-center gap-1.5 text-[11px] font-medium text-default-500 bg-white border border-default-200 rounded-lg hover:bg-default-50 hover:text-default-700 transition-all cursor-pointer shrink-0">
                    <i class="ti ti-refresh text-sm"></i> Limpiar
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto custom-scrollbar" x-ref="bodyContainer">
            <!-- Loading -->
            <div x-show="loading" class="flex flex-col items-center justify-center py-16 text-default-400">
                <i class="ti ti-loader animate-spin text-3xl mb-3"></i>
                <p class="text-sm font-medium">Cargando usuarios...</p>
            </div>

            <!-- Empty (sin datos) -->
            <div x-show="!loading && personales.length === 0"
                class="flex flex-col items-center justify-center py-16 text-default-400">
                <i class="ti ti-users-off text-5xl opacity-30 mb-3"></i>
                <p class="text-sm font-medium">No se encontraron usuarios</p>
                <p class="text-[11px] text-default-400 mt-1">
                    <span x-text="'No hay personal con el filtro: ' + statusLabel"></span>
                </p>
            </div>

            <!-- Empty (filtrados sin resultados) -->
            <div x-show="!loading && personales.length > 0 && personalesFiltrados.length === 0"
                class="flex flex-col items-center justify-center py-16 text-default-400">
                <i class="ti ti-search-off text-5xl opacity-30 mb-3"></i>
                <p class="text-sm font-medium">No se encontraron resultados</p>
                <p class="text-[11px] text-default-400 mt-1">Intenta ajustar los filtros de búsqueda</p>
            </div>

            <!-- Table -->
            <div x-show="!loading && personalesFiltrados.length > 0" class="w-full overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-default-200 bg-default-50/50">
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest w-12">N°</th>
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest">Nro. Documento</th>
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest cursor-pointer select-none hover:text-primary transition-colors"
                                @click="toggleOrden('nombreCompleto')">
                                <div class="flex items-center gap-1.5">
                                    <span>Nombre Completo</span>
                                    <i class="ti text-[11px]" :class="getIconoOrden('nombreCompleto')"></i>
                                </div>
                            </th>
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest cursor-pointer select-none hover:text-primary transition-colors"
                                @click="toggleOrden('tipo_trabajador')">
                                <div class="flex items-center gap-1.5">
                                    <span>Tipo Trab.</span>
                                    <i class="ti text-[11px]" :class="getIconoOrden('tipo_trabajador')"></i>
                                </div>
                            </th>
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest cursor-pointer select-none hover:text-primary transition-colors"
                                @click="toggleOrden('cargo')">
                                <div class="flex items-center gap-1.5">
                                    <span>Cargo</span>
                                    <i class="ti text-[11px]" :class="getIconoOrden('cargo')"></i>
                                </div>
                            </th>
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest cursor-pointer select-none hover:text-primary transition-colors"
                                @click="toggleOrden('sucursal')">
                                <div class="flex items-center gap-1.5">
                                    <span>Sucursal</span>
                                    <i class="ti text-[11px]" :class="getIconoOrden('sucursal')"></i>
                                </div>
                            </th>
                            <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest">Correo</th>
                            <template x-if="!mostrarAcciones">
                                <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest text-center cursor-pointer select-none hover:text-primary transition-colors"
                                    @click="toggleOrden('nota_final')">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span>Nota Final</span>
                                        <i class="ti text-[11px]" :class="getIconoOrden('nota_final')"></i>
                                    </div>
                                </th>
                            </template>
                            <template x-if="!mostrarAcciones">
                                <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest cursor-pointer select-none hover:text-primary transition-colors"
                                    @click="toggleOrden('ultimo_acceso')">
                                    <div class="flex items-center gap-1.5">
                                        <span>Último Acceso</span>
                                        <i class="ti text-[11px]" :class="getIconoOrden('ultimo_acceso')"></i>
                                    </div>
                                </th>
                            </template>
                            <template x-if="mostrarAcciones">
                                <th class="py-3 px-4 text-[10px] font-bold text-default-400 uppercase tracking-widest text-center">Acción</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(p, idx) in personalesPaginados" :key="idx">
                            <tr class="border-b border-default-100 hover:bg-primary/[0.02] transition-colors">
                                <td class="py-3 px-4 text-xs text-default-400 font-mono" x-text="(paginaActual - 1) * itemsPorPagina + idx + 1"></td>
                                <td class="py-3 px-4">
                                    <span class="text-xs font-mono font-semibold text-default-700 bg-default-100 px-2 py-0.5 rounded" x-text="p.nroDoc"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-xs font-semibold text-default-900" x-text="p.nombreCompleto"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100" x-text="p.tipo_trabajador || '—'"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-xs text-default-700" x-text="p.cargo || '—'"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-xs text-default-700" x-text="p.sucursal || '—'"></span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-xs text-default-500" x-text="p.correo || '—'"></span>
                                </td>
                                <template x-if="!mostrarAcciones">
                                    <td class="py-3 px-4 text-center">
                                        <template x-if="p.nota_final !== null">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                                                :class="p.nota_final >= 11 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                                x-text="p.nota_final.toFixed(2)"></span>
                                        </template>
                                        <template x-if="p.nota_final === null">
                                            <span class="text-xs text-default-300">—</span>
                                        </template>
                                    </td>
                                </template>
                                <template x-if="!mostrarAcciones">
                                    <td class="py-3 px-4">
                                        <span class="text-xs text-default-500" x-text="formatearFechaHora(p.ultimo_acceso)"></span>
                                    </td>
                                </template>
                                <template x-if="mostrarAcciones">
                                    <td class="py-3 px-4 text-center">
                                        <template x-if="!p.correo">
                                            <span class="text-xs text-default-300">—</span>
                                        </template>
                                        <template x-if="p.correo && recordatoriosEnviados[p.nroDoc]">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-green-100 text-green-700">
                                                <i class="ti ti-check text-[11px]"></i> Enviado
                                            </span>
                                        </template>
                                        <template x-if="p.correo && !recordatoriosEnviados[p.nroDoc]">
                                            <button type="button" @click="enviarRecordatorio(p)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all cursor-pointer">
                                                <i class="ti ti-mail text-[12px]"></i>
                                                <span>Enviar recordatorio</span>
                                            </button>
                                        </template>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 py-4 px-6 border-t border-default-100 shrink-0 bg-gradient-to-r from-white to-default-50/30">
            <!-- Info + Paginación -->
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <span class="text-[11px] text-default-400 shrink-0" x-show="!loading && personales.length > 0">
                    Mostrando <strong x-text="(paginaActual - 1) * itemsPorPagina + 1" class="text-default-600"></strong>
                    - <strong x-text="Math.min(paginaActual * itemsPorPagina, personalesFiltrados.length)" class="text-default-600"></strong>
                    de <strong x-text="personalesFiltrados.length" class="text-default-600"></strong>
                </span>

                <!-- Controles de paginación -->
                <nav x-show="!loading && totalPaginas > 1" class="flex items-center gap-1">
                    <button type="button" @click="irPagina(paginaActual - 1)" :disabled="paginaActual === 1"
                        class="w-7 h-7 inline-flex items-center justify-center rounded-md text-xs transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed hover:bg-default-100 text-default-500">
                        <i class="ti ti-chevron-left text-[11px]"></i>
                    </button>

                    <template x-for="(pag, i) in paginasVisibles" :key="i">
                        <button type="button" @click="irPagina(pag)"
                            x-text="pag"
                            :disabled="pag === '...'"
                            class="min-w-[28px] h-7 inline-flex items-center justify-center rounded-md text-[11px] font-semibold transition-all cursor-pointer disabled:cursor-default"
                            :class="pag === paginaActual ? 'bg-primary text-white shadow-sm shadow-primary/20' : pag === '...' ? 'text-default-300 cursor-default' : 'text-default-500 hover:bg-default-100'">
                        </button>
                    </template>

                    <button type="button" @click="irPagina(paginaActual + 1)" :disabled="paginaActual === totalPaginas"
                        class="w-7 h-7 inline-flex items-center justify-center rounded-md text-xs transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed hover:bg-default-100 text-default-500">
                        <i class="ti ti-chevron-right text-[11px]"></i>
                    </button>
                </nav>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="cerrar()"
                    class="px-5 h-9 inline-flex justify-center items-center rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                    Cerrar
                </button>
                <button type="button" @click="cerrar()"
                    class="px-5 h-9 inline-flex justify-center items-center rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all cursor-pointer">
                    Entendido
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal informativo -->
<div id="modal-info" x-data="modalInfo()" x-show="open" x-cloak
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-lg border border-default-200 bg-white transition-all duration-300"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <div class="flex justify-between items-center py-5 px-6 border-b border-default-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <i class="ti ti-info-circle text-primary"></i>
                </div>
                <h3 class="text-[15px] font-semibold text-default-900" x-text="titulo"></h3>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <div class="p-6 text-sm text-default-600 leading-relaxed" x-html="mensaje"></div>

        <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
            <button type="button" @click="cerrar()"
                class="px-4 h-9 inline-flex justify-center items-center rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all cursor-pointer">
                Entendido
            </button>
        </div>
    </div>
</div>

<!-- Modal de usuario encontrado -->
<div id="modal-usuario" x-data="modalUsuario()" x-show="open" x-cloak
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-7xl border border-default-200 bg-white transition-all duration-300 max-h-[90vh]"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <!-- Header con perfil -->
        <div class="flex justify-between items-start py-5 px-6 border-b border-default-100 shrink-0">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <!-- Avatar con iniciales -->
                <div
                    class="w-12 h-12 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-lg shadow-sm shrink-0">
                    <span x-text="getIniciales()"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-[15px] font-semibold text-default-900 leading-tight truncate"
                        x-text="personal.nombre_completo">
                        Cargando...</h3>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5">
                        <p class="text-[10px] font-bold text-default-500 uppercase tracking-widest"
                            x-text="'DNI: ' + personal.dni">DNI: -</p>
                        <!-- <span class="text-default-300">|</span> -->
                        <!-- <p class="text-[10px] font-bold text-primary uppercase tracking-widest"
                            x-text="'Código: ' + personal.codigo">Código: -</p> -->
                        <template x-if="personal.email">
                            <div class="flex items-center gap-x-2">
                                <span class="text-default-300">|</span>
                                <p class="text-[10px] font-bold text-default-400 uppercase tracking-widest truncate"
                                    x-text="personal.email"></p>
                                <button type="button" @click="copiarCorreo(personal.email, $event)"
                                    class="inline-flex items-center justify-center w-5 h-5 rounded text-default-400 hover:text-primary hover:bg-primary/10 transition-colors cursor-pointer shrink-0"
                                    title="Copiar correo electrónico">
                                    <i class="ti ti-copy text-[10px]"></i>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- Info Personal + Stats Resumen -->
        <div class="px-6 pt-4 pb-2 shrink-0">
            <div class="flex items-start gap-4">
                <!-- Datos del personal -->
                <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-default-50 border border-default-200/60">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                            <i class="ti ti-building-store text-sm text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] text-default-400 uppercase tracking-wider font-bold">Sucursal</p>
                            <p class="text-xs font-semibold text-default-700 truncate"
                                x-text="personal.sucursal || 'No registrada'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-default-50 border border-default-200/60">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                            <i class="ti ti-tag text-sm text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] text-default-400 uppercase tracking-wider font-bold">Cargo</p>
                            <p class="text-xs font-semibold text-default-700 truncate"
                                x-text="personal.cargo || 'No registrado'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-default-50 border border-default-200/60">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                            <i class="ti ti-briefcase text-sm text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] text-default-400 uppercase tracking-wider font-bold">Tipo de trabajador
                            </p>
                            <p class="text-xs font-semibold text-default-700 truncate"
                                x-text="personal.tipo_trabajador || 'No registrado'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 p-2.5 rounded-xl bg-default-50 border border-default-200/60">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                            <i class="ti ti-mail text-sm text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[9px] text-default-400 uppercase tracking-wider font-bold">Email</p>
                            <p class="text-xs font-semibold text-default-700 truncate"
                                x-text="personal.email || 'No registrado'"></p>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="w-px h-16 bg-default-200 self-center"></div>

                <!-- Stats de cursos -->
                <div class="flex items-center gap-3">
                    <div class="text-center px-2">
                        <p class="text-lg font-bold text-primary" x-text="cursos.length">0</p>
                        <p class="text-[8px] text-default-400 uppercase tracking-wider font-bold">Total</p>
                    </div>
                    <div class="w-px h-10 bg-default-200"></div>
                    <div class="text-center px-2" x-show="totales">
                        <p class="text-lg font-bold text-amber-500" x-text="totales?.sin_acceder ?? 0">0</p>
                        <p class="text-[8px] text-default-400 uppercase tracking-wider font-bold">Sin acceder</p>
                    </div>
                    <div class="text-center px-2" x-show="totales">
                        <p class="text-lg font-bold text-blue-500" x-text="totales?.en_curso ?? 0">0</p>
                        <p class="text-[8px] text-default-400 uppercase tracking-wider font-bold">En curso</p>
                    </div>
                    <div class="w-px h-10 bg-default-200"></div>
                    <div class="text-center px-2" x-show="totales">
                        <p class="text-lg font-bold text-green-500" x-text="totales?.aprobado ?? 0">0</p>
                        <p class="text-[8px] text-default-400 uppercase tracking-wider font-bold">Aprobados</p>
                    </div>
                    <div class="text-center px-2" x-show="totales">
                        <p class="text-lg font-bold text-red-500" x-text="totales?.desaprobado ?? 0">0</p>
                        <p class="text-[8px] text-default-400 uppercase tracking-wider font-bold">Desaprobados</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cursos del alumno -->
        <div class="flex flex-col flex-1 min-h-0">
            <div class="shrink-0 px-6 pt-4 pb-2">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-bold text-default-600 uppercase tracking-wider flex items-center gap-2">
                        <i class="ti ti-book text-primary text-sm"></i>
                        Cursos matriculados
                    </h4>
                    <span class="text-[10px] text-default-400 font-medium"
                        x-text="cursosFiltrados.length + ' de ' + cursos.length + ' curso(s)'"></span>
                </div>

                <div x-show="cursosCargado" class="flex flex-wrap items-center gap-2 pb-2 border-b border-default-100">
                    <!-- Filtros tipo pills -->
                    <div class="flex items-center gap-1">
                        <button type="button" @click="filtroEstado = 'todos'"
                            :class="filtroEstado === 'todos'
                                ? 'px-3 py-1.5 rounded-lg bg-primary text-white text-[10px] font-semibold transition-all'
                                : 'px-3 py-1.5 rounded-lg bg-default-100 text-default-500 text-[10px] font-semibold hover:bg-default-200 transition-all'">
                            Todos
                        </button>
                        <button type="button" @click="filtroEstado = 'Sin acceder'"
                            :class="filtroEstado === 'Sin acceder'
                                ? 'px-3 py-1.5 rounded-lg bg-amber-500 text-white text-[10px] font-semibold transition-all'
                                : 'px-3 py-1.5 rounded-lg bg-amber-50 text-amber-600 text-[10px] font-semibold hover:bg-amber-100 transition-all'">
                            <i class="ti ti-clock mr-0.5"></i> Sin acceder
                        </button>
                        <button type="button" @click="filtroEstado = 'En curso'"
                            :class="filtroEstado === 'En curso'
                                ? 'px-3 py-1.5 rounded-lg bg-blue-500 text-white text-[10px] font-semibold transition-all'
                                : 'px-3 py-1.5 rounded-lg bg-blue-50 text-blue-600 text-[10px] font-semibold hover:bg-blue-100 transition-all'">
                            <i class="ti ti-player-play mr-0.5"></i> En curso
                        </button>
                        <button type="button" @click="filtroEstado = 'Aprobado'"
                            :class="filtroEstado === 'Aprobado'
                                ? 'px-3 py-1.5 rounded-lg bg-green-500 text-white text-[10px] font-semibold transition-all'
                                : 'px-3 py-1.5 rounded-lg bg-green-50 text-green-600 text-[10px] font-semibold hover:bg-green-100 transition-all'">
                            <i class="ti ti-circle-check mr-0.5"></i> Aprobado
                        </button>
                        <button type="button" @click="filtroEstado = 'Desaprobado'"
                            :class="filtroEstado === 'Desaprobado'
                                ? 'px-3 py-1.5 rounded-lg bg-red-500 text-white text-[10px] font-semibold transition-all'
                                : 'px-3 py-1.5 rounded-lg bg-red-50 text-red-600 text-[10px] font-semibold hover:bg-red-100 transition-all'">
                            <i class="ti ti-x mr-0.5"></i> Desaprobado
                        </button>
                    </div>
                    <div class="flex-1"></div>
                    <select x-model="filtroTipoCurso"
                        class="w-36 py-1.5 text-xs border border-default-200 rounded-lg !bg-white !text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <option value="">Todos los tipos</option>
                        <template x-for="t in tiposCurso" :key="t">
                            <option x-text="t" :value="t"></option>
                        </template>
                    </select>
                    <div class="relative">
                        <i
                            class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-default-400 pointer-events-none"></i>
                        <input x-model="busquedaCurso" placeholder="Buscar curso..."
                            class="w-48 pl-7 pr-3 py-1.5 text-xs border border-default-200 rounded-lg !bg-white !text-default-700 placeholder:text-default-300 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar px-6 pb-4">
                <div x-show="!cursosCargado" class="flex flex-col items-center justify-center py-10 text-default-400">
                    <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                    <p class="text-sm">Cargando cursos...</p>
                </div>

                <div x-show="cursosCargado && cursos.length === 0"
                    class="flex flex-col items-center justify-center py-10 text-default-400">
                    <i class="ti ti-book-off text-3xl opacity-30 mb-2"></i>
                    <p class="text-sm">No se encontraron cursos para este alumno</p>
                </div>

                <div x-show="cursosCargado && cursosFiltrados.length === 0 && cursos.length > 0"
                    class="flex flex-col items-center justify-center py-10 text-default-400">
                    <i class="ti ti-filter-off text-3xl opacity-30 mb-2"></i>
                    <p class="text-sm">Ningún curso coincide con los filtros</p>
                </div>

                <div x-show="cursosCargado && cursosFiltrados.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-default-50 sticky top-0 z-10">
                            <tr
                                class="border-b border-default-200 text-[10px] font-semibold text-default-500 uppercase tracking-widest">
                                <th class="text-left py-2.5 px-3 w-10">#</th>
                                <th class="text-left py-2.5 px-3 w-20">Código</th>
                                <th class="text-left py-2.5 px-3">Nombre del curso</th>
                                <th class="text-center py-2.5 px-3">Tipo de curso</th>
                                <th class="text-center py-2.5 px-3 w-32">Estado</th>
                                <th class="text-center py-2.5 px-3 w-20">Nota final</th>
                                <th class="text-center py-2.5 px-3 w-32">Último acceso</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-default-100">
                            <template x-for="(c, index) in cursosFiltrados" :key="index">
                                <tr class="hover:bg-default-50 transition-colors">
                                    <td class="py-2.5 px-3 text-default-400 text-xs font-mono" x-text="index + 1"></td>
                                    <td class="py-2.5 px-3">
                                        <p class="font-medium text-default-800 text-xs" x-text="c.codigo || ''"></p>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <p class="font-medium text-default-800 text-xs" x-text="c.nombre_curso || c.curso_nombre || c.nombre || ''"></p>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <p class="font-medium text-default-800 text-xs" x-text="c.tipo_curso || ''"></p>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span x-show="c.estado === 'En curso'"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[9px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            <i class="ti ti-player-play text-[8px]"></i>
                                            En curso
                                        </span>
                                        <span x-show="c.estado === 'Sin acceder'"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[9px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="ti ti-clock text-[8px]"></i>
                                            Sin acceder
                                        </span>
                                        <span x-show="c.estado === 'Aprobado'"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[9px] font-semibold bg-green-50 text-green-700 border border-green-200">
                                            <i class="ti ti-circle-check text-[8px]"></i>
                                            Aprobado
                                        </span>
                                        <span x-show="c.estado === 'Desaprobado'"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[9px] font-semibold bg-red-50 text-red-700 border border-red-200">
                                            <i class="ti ti-x text-[8px]"></i>
                                            Desaprobado
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span x-show="c.nota_final !== null && c.nota_final !== undefined"
                                            class="font-semibold"
                                            :class="c.nota_final >= 10 ? 'text-green-600' : 'text-red-500'"
                                            x-text="c.nota_final"></span>
                                        <span x-show="c.nota_final === null || c.nota_final === undefined"
                                            class="text-[10px] text-default-300">—</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <template x-if="formatearFecha(c.fecha_ultimo_acceso)">
                                            <div style="line-height: 1.4;">
                                                <span style="font-size: 11px; font-weight: 600; color: #374151;"
                                                    x-text="formatearFecha(c.fecha_ultimo_acceso).fecha"></span>
                                                <br>
                                                <span style="font-size: 10px; color: #9ca3af;"
                                                    x-text="formatearFecha(c.fecha_ultimo_acceso).hora"></span>
                                            </div>
                                        </template>
                                        <span x-show="!formatearFecha(c.fecha_ultimo_acceso)"
                                            class="text-[10px] text-default-300">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-between items-center gap-2 py-4 px-6 border-t border-default-100 bg-white shrink-0">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-semibold">
                    <i class="ti ti-repeat text-[10px]"></i>
                    Envío cíclico: después del tercer MEMO se reinicia al primero
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                    @click="countByEstado('Sin acceder') > 0 && notificarCursosPendientes()"
                    :disabled="countByEstado('Sin acceder') === 0"
                    :class="countByEstado('Sin acceder') > 0
                        ? 'px-4 h-9 inline-flex justify-center items-center rounded-lg font-semibold bg-sky-500 text-white hover:bg-sky-600 transition-all text-xs shadow-sm'
                        : 'px-4 h-9 inline-flex justify-center items-center rounded-lg font-semibold bg-default-100 text-default-400 cursor-not-allowed text-xs'">
                    <i class="ti ti-mail mr-1.5"></i>
                    <span>Notificar sobre cursos pendientes</span>
                </button>

                <button type="button"
                    @click="countByEstado('Sin acceder') > 0 && enviarMEMO()"
                    :disabled="countByEstado('Sin acceder') === 0"
                    :class="countByEstado('Sin acceder') > 0
                        ? 'px-4 h-9 inline-flex justify-center items-center rounded-lg font-semibold bg-rose-500 text-white hover:bg-rose-600 transition-all text-xs shadow-sm'
                        : 'px-4 h-9 inline-flex justify-center items-center rounded-lg font-semibold bg-default-100 text-default-400 cursor-not-allowed text-xs'">
                    <i class="ti ti-file-alert mr-1.5"></i>
                    <span x-text="'Enviar ' + memoInfo.siguiente_texto + ' MEMO'"></span>
                </button>

                <button type="button" @click="cerrar()"
                    class="px-4 h-9 inline-flex justify-center items-center rounded-lg font-semibold bg-default-100 text-default-600 hover:bg-default-200 transition-all text-xs cursor-pointer">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal detalle de MEMOs por persona -->
<div id="modal-memos-persona" x-data="modalMemosPersona()" x-show="open" x-cloak
    class="fixed inset-0 z-[110] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-4xl border border-default-200 bg-white transition-all duration-300 max-h-[85vh]"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <!-- Header -->
        <div class="flex justify-between items-start py-5 px-6 border-b border-default-100 shrink-0">
            <div class="flex items-start gap-4 flex-1 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                    <i class="ti ti-notes text-primary text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-[15px] font-semibold text-default-900 leading-tight truncate" x-text="personal">Cargando...</h3>
                    <p class="text-[10px] font-bold text-default-400 uppercase tracking-widest mt-0.5">
                        <span x-text="'DNI: ' + nroDoc"></span>
                        <span class="mx-2 text-default-300">|</span>
                        <span class="text-primary" x-text="nivelTexto"></span>
                    </p>
                </div>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- Body: two columns -->
        <div class="flex-1 flex flex-row min-h-0">
            <!-- Left panel: MEMOs list -->
            <div class="w-1/2 border-r border-default-200 flex flex-col min-h-0">
                <div class="flex items-center justify-between px-6 py-3 border-b border-default-100 shrink-0">
                    <span class="text-[10px] font-bold text-default-400 uppercase tracking-widest">MEMOs registrados</span>
                    <span x-show="!cargando" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-bold"
                        x-text="total + ' total'"></span>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar p-4">
                    <div x-show="cargando" class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-3"></i>
                        <p class="text-sm font-medium">Cargando MEMOs...</p>
                    </div>

                    <div x-show="!cargando && memos.length === 0"
                        class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-notes-off text-4xl opacity-30 mb-3"></i>
                        <p class="text-sm font-medium">No se encontraron MEMOs</p>
                    </div>

                    <div x-show="!cargando && memos.length > 0" class="flex flex-col gap-2">
                        <template x-for="(m, idx) in memos" :key="idx">
                            <div @click="seleccionarMemo(m)"
                                :class="selectedMemoId === m.ID
                                    ? 'flex items-center gap-3 px-4 py-3 rounded-xl bg-primary/10 border-2 border-primary/40 shadow-sm cursor-pointer transition-all duration-200'
                                    : 'flex items-center gap-3 px-4 py-3 rounded-xl bg-default-50 border border-default-200/60 cursor-pointer transition-all duration-200 hover:border-primary/30 hover:shadow-sm'">
                                <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                    <i class="ti ti-notes text-primary text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-default-700" x-text="'MEMO #' + m.ID"></p>
                                    <p class="text-[11px] text-default-400 mt-0.5">
                                        <i class="ti ti-calendar text-[9px] mr-1"></i>
                                        <span x-text="m.FECHA_ENVIO"></span>
                                    </p>
                                </div>
                                <div>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold"
                                        :class="{
                                            'bg-blue-50 text-blue-700': nivelTexto === 'NIVEL UNO',
                                            'bg-orange-50 text-orange-700': nivelTexto === 'NIVEL DOS',
                                            'bg-red-50 text-red-700': nivelTexto === 'NIVEL TRES'
                                        }">
                                        <i class="ti"
                                            :class="{
                                                'ti-info-circle': nivelTexto === 'NIVEL UNO',
                                                'ti-alert-triangle': nivelTexto === 'NIVEL DOS',
                                                'ti-bell-ringing': nivelTexto === 'NIVEL TRES'
                                            }"></i>
                                        <span x-text="m.NIVEL_MEMO"></span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Right panel: Courses of selected MEMO -->
            <div class="w-1/2 flex flex-col min-h-0">
                <div class="flex items-center justify-between px-6 py-3 border-b border-default-100 shrink-0">
                    <span class="text-[10px] font-bold text-default-400 uppercase tracking-widest">Cursos del MEMO</span>
                    <span x-show="selectedMemoId && !cargandoCursos && cursos.length > 0"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-sky-100 text-sky-700 text-[10px] font-bold"
                        x-text="cursos.length + ' curso(s)'"></span>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar p-4">
                    <!-- No MEMO selected -->
                    <div x-show="!selectedMemoId"
                        class="flex flex-col items-center justify-center py-12 text-default-400">
                        <i class="ti ti-arrow-left text-4xl opacity-30 mb-3"></i>
                        <p class="text-sm font-medium text-center">Selecciona un MEMO para ver los cursos listados</p>
                    </div>

                    <!-- Loading courses -->
                    <div x-show="selectedMemoId && cargandoCursos"
                        class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-3"></i>
                        <p class="text-sm font-medium">Cargando cursos...</p>
                    </div>

                    <!-- No courses -->
                    <div x-show="selectedMemoId && !cargandoCursos && cursos.length === 0"
                        class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-book-off text-4xl opacity-30 mb-3"></i>
                        <p class="text-sm font-medium">Este MEMO no tiene cursos asignados</p>
                    </div>

                    <!-- Courses list -->
                    <div x-show="selectedMemoId && !cargandoCursos && cursos.length > 0" class="flex flex-col gap-2">
                        <template x-for="(c, idx) in cursos" :key="idx">
                            <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-sky-50/60 border border-sky-200/60">
                                <div class="w-8 h-8 rounded-lg bg-sky-100 flex items-center justify-center shrink-0">
                                    <i class="ti ti-book text-sky-600 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-default-700 leading-tight" x-text="c.NOMBRE_CURSO"></p>
                                    <p class="text-[10px] text-default-400 mt-0.5">
                                        <span class="font-mono font-semibold text-default-500" x-text="'ID: ' + c.ID"></span>
                                        <span class="mx-1.5 text-default-300">|</span>
                                        <span class="font-mono text-primary font-semibold" x-text="'Moodle: ' + c.CODIGO_MOODLE"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100 shrink-0">
            <button type="button" @click="cerrar()"
                class="px-4 h-9 inline-flex justify-center items-center rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 transition-all cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- Modal comparativa entre grupos de MEMOs -->
<div id="modal-comparativa" x-data="modalComparativa()" x-show="open" x-cloak
    class="fixed inset-0 z-[120] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    style="background: rgba(36,39,70,0.45);">

    <div class="flex flex-col w-full max-w-xl bg-white rounded-2xl border border-default-200 shadow-2xl shadow-primary/10 overflow-hidden"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <!-- Header -->
        <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-default-100">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-primary/10 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="ti ti-arrows-diff text-primary text-base"></i>
                </div>
                <div>
                    <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                        Comparativa de MEMOs
                    </h3>
                    <p class="text-[11px] text-default-400 mt-0.5">
                        Selecciona un MEMO base y luego un MEMO superior
                    </p>
                </div>
            </div>
            <button type="button" @click="cerrar()"
                class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 transition-colors shrink-0">
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 flex flex-col gap-4">

            <!-- Selección -->
            <template x-if="!resultado && !cargando">
                <div class="flex flex-col gap-4">

                    <!-- Level cards -->
                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="nivel in [1,2,3]" :key="nivel">
                            <button type="button"
                                @click="toggleNivel(nivel)"
                                :disabled="seleccionados.length === 1 && nivel <= seleccionados[0] && !seleccionados.includes(nivel)"
                                class="relative group flex flex-col items-center gap-2 rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                :class="{
                                    'opacity-40 cursor-not-allowed grayscale': seleccionados.length === 1 && nivel <= seleccionados[0] && !seleccionados.includes(nivel),
                                    'ring-2 ring-offset-1': seleccionados.includes(nivel)
                                }">
                                <template x-if="!seleccionados.includes(nivel)">
                                    <div class="absolute inset-0 rounded-xl border border-default-200 bg-default-50 group-hover:bg-default-100 group-hover:border-default-300 transition-all"></div>
                                </template>
                                <template x-if="seleccionados.includes(nivel)">
                                    <div class="absolute inset-0 rounded-xl border-2 shadow-sm transition-all duration-200"
                                        :class="{
                                            'bg-blue-50 border-blue-400 shadow-blue-200/30': nivel === 1,
                                            'bg-orange-50 border-orange-400 shadow-orange-200/30': nivel === 2,
                                            'bg-red-50 border-red-400 shadow-red-200/30': nivel === 3
                                        }"></div>
                                </template>
                                <template x-if="seleccionados.includes(nivel)">
                                    <div class="absolute top-2 right-2 w-5 h-5 rounded-full flex items-center justify-center z-10"
                                        :class="{
                                            'bg-blue-500': nivel === 1,
                                            'bg-orange-500': nivel === 2,
                                            'bg-red-500': nivel === 3
                                        }">
                                        <i class="ti ti-check text-white text-[10px]"></i>
                                    </div>
                                </template>
                                <div class="relative flex flex-col items-center gap-2 px-4 pt-5 pb-4">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                                        :class="{
                                            'bg-blue-100 text-blue-600': nivel === 1,
                                            'bg-orange-100 text-orange-600': nivel === 2,
                                            'bg-red-100 text-red-600': nivel === 3
                                        }">
                                        <i class="ti text-lg"
                                            :class="{
                                                'ti-info-circle': nivel === 1,
                                                'ti-alert-triangle': nivel === 2,
                                                'ti-bell-ringing': nivel === 3
                                            }"></i>
                                    </div>
                                    <span class="text-xs font-bold"
                                        :class="{
                                            'text-blue-700': nivel === 1,
                                            'text-orange-700': nivel === 2,
                                            'text-red-700': nivel === 3
                                        }"
                                        x-text="'MEMO ' + nivel">
                                    </span>
                                    <span class="text-[10px] text-default-400 font-medium text-center leading-snug"
                                        x-text="nivel === 1
                                            ? 'Primer recordatorio informativo'
                                            : nivel === 2
                                                ? 'Segundo aviso de advertencia'
                                                : 'Aviso urgente y definitivo'">
                                    </span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- Preview -->
                    <div class="rounded-xl border px-4 py-3 transition-all duration-300"
                        :class="seleccionados.length === 2 ? 'border-green-200 bg-green-50/60' : 'border-default-200 bg-default-50'">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[10px] font-bold text-default-400 uppercase tracking-widest">
                                Flujo de comparación
                            </p>
                            <template x-if="seleccionados.length === 2">
                                <span class="text-[10px] font-semibold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">Listo</span>
                            </template>
                            <template x-if="seleccionados.length !== 2">
                                <span class="text-[10px] font-semibold text-default-400 bg-default-200/60 px-2 py-0.5 rounded-full" x-text="seleccionados.length + ' / 2'"></span>
                            </template>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 rounded-lg border px-3 py-2.5 min-h-[56px] bg-white transition-all"
                                :class="{ 'border-green-300 shadow-sm': seleccionados.length >= 1 }">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <i class="ti ti-arrow-back-up text-[9px] text-default-300"></i>
                                    <span class="text-[9px] uppercase tracking-wide font-bold text-default-400">Base</span>
                                </div>
                                <p class="text-sm font-semibold mt-0.5"
                                    :class="seleccionados[0] ? 'text-default-800' : 'text-default-300'"
                                    x-text="seleccionados[0] ? 'MEMO ' + seleccionados[0] : 'Seleccionar...'">
                                </p>
                            </div>
                            <div class="flex flex-col items-center gap-0.5">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center border bg-white"
                                    :class="seleccionados.length === 2 ? 'border-green-300 text-green-600' : 'border-default-200 text-default-300'">
                                    <i class="ti ti-arrow-right text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1 rounded-lg border px-3 py-2.5 min-h-[56px] bg-white transition-all"
                                :class="{ 'border-green-300 shadow-sm': seleccionados.length === 2 }">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <i class="ti ti-corner-right-up text-[9px] text-default-300"></i>
                                    <span class="text-[9px] uppercase tracking-wide font-bold text-default-400">Comparado</span>
                                </div>
                                <p class="text-sm font-semibold mt-0.5"
                                    :class="seleccionados[1] ? 'text-default-800' : 'text-default-300'"
                                    x-text="seleccionados[1] ? 'MEMO ' + seleccionados[1] : 'Seleccionar...'">
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Loading -->
            <template x-if="cargando">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="relative w-16 h-16">
                        <div class="absolute inset-0 rounded-full border-[3px] border-primary/10"></div>
                        <div class="absolute inset-0 rounded-full border-[3px] border-t-primary animate-spin"></div>
                        <div class="absolute inset-2 rounded-full bg-primary/5 flex items-center justify-center">
                            <i class="ti ti-chart-comparison text-primary/40 text-lg"></i>
                        </div>
                    </div>
                    <p class="mt-4 text-sm font-medium text-default-600">
                        Procesando comparativa...
                    </p>
                    <p class="mt-1 text-[11px] text-default-400">
                        Analizando diferencias entre los grupos seleccionados
                    </p>
                </div>
            </template>

            <!-- Resultado -->
            <template x-if="resultado">
                <div class="flex flex-col gap-4">
                    <!-- Resumen -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-green-200 bg-green-50 p-3 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                                <i class="ti ti-users text-green-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-bold text-green-700 leading-tight">Persisten</p>
                                <p class="text-2xl font-bold text-green-800 mt-0.5" x-text="resultado.totales.persisten"></p>
                            </div>
                        </div>
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                                <i class="ti ti-user-x text-red-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-bold text-red-700 leading-tight">Ya no están</p>
                                <p class="text-2xl font-bold text-red-800 mt-0.5" x-text="resultado.totales.ya_no_estan"></p>
                            </div>
                        </div>
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                <i class="ti ti-user-plus text-blue-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-bold text-blue-700 leading-tight">Nuevos</p>
                                <p class="text-2xl font-bold text-blue-800 mt-0.5" x-text="resultado.totales.nuevos"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Detalle resultado -->
                    <div class="flex flex-col gap-3">
                        <!-- Buscador dentro del resultado -->
                        <div class="relative">
                            <i class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                            <input x-model="busquedaResultado" placeholder="Buscar por nombre o DNI..."
                                class="w-full pl-8 pr-3 py-2 text-sm border border-default-200 rounded-lg !bg-white !text-default-700 placeholder:text-default-300 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        </div>
                        <!-- Tab nav -->
                        <div class="flex gap-1 bg-default-100 p-1 rounded-xl">
                            <button type="button" @click="tabResultado = 'persisten'"
                                class="flex-1 px-3 py-1.5 text-[11px] font-semibold rounded-lg transition-all"
                                :class="tabResultado === 'persisten' ? 'bg-white text-green-700 shadow-sm' : 'text-default-500 hover:text-default-700'">
                                <i class="ti ti-users mr-1 text-[10px]"></i>
                                Persisten
                            </button>
                            <button type="button" @click="tabResultado = 'ya_no_estan'"
                                class="flex-1 px-3 py-1.5 text-[11px] font-semibold rounded-lg transition-all"
                                :class="tabResultado === 'ya_no_estan' ? 'bg-white text-red-700 shadow-sm' : 'text-default-500 hover:text-default-700'">
                                <i class="ti ti-user-x mr-1 text-[10px]"></i>
                                Ya no están
                            </button>
                            <button type="button" @click="tabResultado = 'nuevos'"
                                class="flex-1 px-3 py-1.5 text-[11px] font-semibold rounded-lg transition-all"
                                :class="tabResultado === 'nuevos' ? 'bg-white text-blue-700 shadow-sm' : 'text-default-500 hover:text-default-700'">
                                <i class="ti ti-user-plus mr-1 text-[10px]"></i>
                                Nuevos
                            </button>
                        </div>

                        <!-- Persisten -->
                        <div x-show="tabResultado === 'persisten'"
                            class="rounded-xl border border-default-200 overflow-hidden">
                            <div class="max-h-44 overflow-y-auto divide-y divide-default-100 custom-scrollbar">
                                <template x-if="persistenFiltrados.length === 0">
                                    <div class="px-4 py-6 text-center">
                                        <i class="ti ti-users text-default-200 text-2xl mb-1"></i>
                                        <p class="text-xs text-default-400">Sin coincidencias</p>
                                    </div>
                                </template>
                                <template x-for="item in persistenFiltrados">
                                    <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-default-50 transition-colors">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-default-700 truncate" x-text="item.NOMBRE_COMPLETO"></p>
                                            <p class="text-[11px] text-default-400">DNI: <span class="font-mono font-medium text-default-500" x-text="item.NRO_DOCU_IDEN"></span></p>
                                        </div>
                                        <i class="ti ti-check text-green-400 text-sm shrink-0"></i>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Ya no están -->
                        <div x-show="tabResultado === 'ya_no_estan'"
                            class="rounded-xl border border-default-200 overflow-hidden">
                            <div class="max-h-44 overflow-y-auto divide-y divide-default-100 custom-scrollbar">
                                <template x-if="yaNoEstanFiltrados.length === 0">
                                    <div class="px-4 py-6 text-center">
                                        <i class="ti ti-user-x text-default-200 text-2xl mb-1"></i>
                                        <p class="text-xs text-default-400">Sin cambios</p>
                                    </div>
                                </template>
                                <template x-for="item in yaNoEstanFiltrados">
                                    <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-default-50 transition-colors">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-default-700 truncate" x-text="item.NOMBRE_COMPLETO"></p>
                                            <p class="text-[11px] text-default-400">DNI: <span class="font-mono font-medium text-default-500" x-text="item.NRO_DOCU_IDEN"></span></p>
                                        </div>
                                        <i class="ti ti-x text-red-400 text-sm shrink-0"></i>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Nuevos -->
                        <div x-show="tabResultado === 'nuevos'"
                            class="rounded-xl border border-default-200 overflow-hidden">
                            <div class="max-h-44 overflow-y-auto divide-y divide-default-100 custom-scrollbar">
                                <template x-if="nuevosFiltrados.length === 0">
                                    <div class="px-4 py-6 text-center">
                                        <i class="ti ti-user-plus text-default-200 text-2xl mb-1"></i>
                                        <p class="text-xs text-default-400">Sin novedades</p>
                                    </div>
                                </template>
                                <template x-for="item in nuevosFiltrados">
                                    <div class="px-4 py-2.5 flex items-center gap-3 hover:bg-default-50 transition-colors">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-default-700 truncate" x-text="item.NOMBRE_COMPLETO"></p>
                                            <p class="text-[11px] text-default-400">DNI: <span class="font-mono font-medium text-default-500" x-text="item.NRO_DOCU_IDEN"></span></p>
                                        </div>
                                        <i class="ti ti-plus text-blue-400 text-sm shrink-0"></i>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Botón nueva comparativa -->
                    <button type="button" @click="resultado = null; seleccionados = []"
                        class="self-start inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-medium text-default-500 hover:text-default-700 hover:bg-default-100 transition-colors">
                        <i class="ti ti-reload text-xs"></i>
                        Nueva comparativa
                    </button>
                </div>
            </template>
        </div>

        <!-- Footer -->
        <div class="border-t border-default-100 bg-default-50/50">
            <div class="flex justify-end gap-2 px-6 py-4">
                <template x-if="!cargando && !resultado">
                    <button type="button" @click="cerrar()"
                        class="px-4 h-9 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all">
                        Cancelar
                    </button>
                </template>
                <template x-if="!cargando && !resultado">
                    <button type="button"
                        @click="realizarComparativa()"
                        :disabled="seleccionados.length !== 2"
                        :class="seleccionados.length === 2
                            ? 'px-4 h-9 rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all inline-flex items-center gap-1.5 active:scale-[0.97]'
                            : 'px-4 h-9 rounded-lg text-sm font-semibold bg-default-100 text-default-400 cursor-not-allowed inline-flex items-center gap-1.5'">
                        <i class="ti ti-chart-comparison text-sm"></i>
                        Comparar
                    </button>
                </template>
                <template x-if="resultado">
                    <button type="button" @click="cerrar()"
                        class="px-4 h-9 rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm shadow-primary/20 transition-all inline-flex items-center gap-1.5">
                        <i class="ti ti-x text-sm"></i>
                        Cerrar
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>

<!-- Blade <script> -->
<script>
    window.memosStats = function() {
        return {
            totalNivelUno: 0,
            totalNivelDos: 0,
            totalNivelTres: 0,

            nivelActivo: null,

            actualizar(data = []) {

                this.totalNivelUno = data
                    .filter(x => Number(x.NUM_MEMO) === 1)
                    .reduce((a, b) => a + Number(b.TOTAL_MEMOS || 0), 0);

                this.totalNivelDos = data
                    .filter(x => Number(x.NUM_MEMO) === 2)
                    .reduce((a, b) => a + Number(b.TOTAL_MEMOS || 0), 0);

                this.totalNivelTres = data
                    .filter(x => Number(x.NUM_MEMO) === 3)
                    .reduce((a, b) => a + Number(b.TOTAL_MEMOS || 0), 0);
            },

            filtrarNivel(nivel) {
                if (this.nivelActivo === nivel) {
                    this.nivelActivo = null;
                    window.__memoState.memoNivel = null;
                } else {
                    this.nivelActivo = nivel;
                    window.__memoState.memoNivel = nivel;
                }

                window.__aplicarFiltrosMemos();
            }
        };
    };

    window.modalMemosPersona = function() {
        return {
            open: false,
            nroDoc: '',
            personal: '',
            nivelTexto: '',
            memos: [],
            total: 0,
            cargando: false,

            selectedMemoId: null,
            cursos: [],
            cargandoCursos: false,

            _cache: {},
            _textos: {
                1: 'NIVEL UNO',
                2: 'NIVEL DOS',
                3: 'NIVEL TRES'
            },

            mostrar(nroDoc, nivel, nombre) {
                this.nroDoc = nroDoc;
                this.nivelTexto = this._textos[nivel] || 'NIVEL UNO';
                this.personal = nombre || 'Sin nombre';
                this.memos = [];
                this.total = 0;
                this.cargando = true;
                this.selectedMemoId = null;
                this.cursos = [];
                this.cargandoCursos = false;
                this._cache = {};
                this.open = true;

                fetch(`${VITE_URL_APP}/api/obtener-memos-personal`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        },
                        body: JSON.stringify({
                            nroDoc: nroDoc,
                            nivel: nivel,
                        }),
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            this.memos = res.data || [];
                            this.total = res.total || 0;
                        } else {
                            this.memos = [];
                            this.total = 0;
                        }
                        this.cargando = false;
                    })
                    .catch(() => {
                        this.memos = [];
                        this.total = 0;
                        this.cargando = false;
                    });
            },

            seleccionarMemo(memo) {
                if (this.selectedMemoId === memo.ID) return;

                this.selectedMemoId = memo.ID;

                if (this._cache[memo.ID]) {
                    this.cursos = this._cache[memo.ID];
                    this.cargandoCursos = false;
                    return;
                }

                this.cursos = [];
                this.cargandoCursos = true;

                fetch(`${VITE_URL_APP}/api/obtener-detalle-memo/${memo.ID}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            this.cursos = res.data || [];
                            this._cache[memo.ID] = this.cursos;
                        } else {
                            this.cursos = [];
                        }
                        this.cargandoCursos = false;
                    })
                    .catch(() => {
                        this.cursos = [];
                        this.cargandoCursos = false;
                    });
            },

            cerrar() {
                this.open = false;
                this._cache = {};
                this.selectedMemoId = null;
                this.cursos = [];
            },
        };
    };

    window.modalCurso = function() {
        return {
            open: false,
            loading: false,
            curso: {
                nombre: 'Cargando...',
                codigo: '-',
                codigoInterno: 0,
                codigo_moodle: 0,
                responsable: 'Sin responsable',
                fechaCreacion: 'Obteniendo fecha de creación...',
                total: 0,
                programacion_actual: null,
                programacion_pendiente: null,
                estadisticas: {
                    total: 0,
                    aprobados: 0,
                    desaprobados: 0,
                    sin_acceder: 0,
                    en_curso: 0,
                },
            },

            mostrar(data, fetchUsuarios, sendMail) {
                this._fetchUsuarios = fetchUsuarios;
                this._sendMail = sendMail;
                this.loading = true;
                this.curso.nombre = data.nombre || 'Cargando...';
                this.curso.codigo = data.codigo_curso || '00000';
                this.curso.codigo_moodle = data.course_id || 0;
                this.curso.codigoInterno = data.codigo || 0;
                this.curso.responsable = data.responsable || 'Sin responsable';
                this.curso.total = data.total_matriculados || 0;
                this.curso.fechaCreacion = 'Obteniendo fecha de creación...';
                this.curso.programacion_actual = null;
                this.curso.programacion_pendiente = null;
                this.curso.estadisticas = {
                    total: 0,
                    aprobados: 0,
                    desaprobados: 0,
                    sin_acceder: 0,
                    en_curso: 0
                };
                this.open = true;

                const courseId = data.course_id || data.codigo_moodle || data.codigo;
                fetch(`${VITE_URL_APP}/api/obtener-detalle-curso-new/${courseId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(res => {
                        if (res.success) {
                            const d = res;
                            this.curso.nombre = d.nombre;
                            this.curso.codigo = d.codigo;
                            this.curso.codigoInterno = d.codigo_interno;
                            this.curso.codigo_moodle = d.codigo_moodle;
                            this.curso.fechaCreacion = this.formatearFechaCreacion(d.fecha_creacion);
                            this.curso.programacion_actual = d.programacion_actual || null;
                            this.curso.programacion_pendiente = d.programacion_pendiente || null;
                            this.curso.estadisticas = d.estadisticas || {
                                total: 0,
                                aprobados: 0,
                                desaprobados: 0,
                                sin_acceder: 0,
                                en_curso: 0,
                            };
                            const est = this.curso.estadisticas;
                            this.curso.total = Number(est.total)
                                || (est.aprobados + est.desaprobados + est.sin_acceder + est.en_curso);
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener detalle del curso:', error);
                        this.curso.fechaCreacion = '';
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },

            formatearFechaCreacion(fecha) {
                if (!fecha) return '';
                try {
                    const d = new Date(fecha);
                    return d.toLocaleDateString('es-PE', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                } catch {
                    return '';
                }
            },

            formatearFecha(fecha) {
                if (!fecha) return '';
                try {
                    const d = new Date(fecha);
                    const dia = String(d.getDate()).padStart(2, '0');
                    const mes = String(d.getMonth() + 1).padStart(2, '0');
                    const anio = d.getFullYear();
                    return dia + '/' + mes + '/' + anio;
                } catch {
                    return '';
                }
            },

            abrirModalUsuarios(statusId = 0) {
                const modalUsuarios = document.getElementById('modal-lista-usuarios')._x_dataStack?.[0];
                if (modalUsuarios) {
                    modalUsuarios.mostrar(this.curso.nombre, this.curso.codigo_moodle, statusId);
                }
            },

            cerrar() {
                this.open = false;
            },
        };
    };

    window.modalListaUsuarios = function() {
        return {
            open: false,
            loading: false,
            cursoNombre: '',
            cursoId: 0,
            statusId: 0,
            personales: [],
            busqueda: '',
            fechaDesde: '',
            fechaHasta: '',
            filtroCargo: '',
            filtroSucursal: '',
            filtroTipoTrabajador: '',
            ordenCampo: 'default',
            ordenDireccion: 'asc',
            paginaActual: 1,
            itemsPorPagina: 20,
            enviandoRecordatorio: {},
            recordatoriosEnviados: {},

            get mostrarAcciones() {
                return this.statusId === 3 || this.statusId === 4;
            },

            get statusLabel() {
                const labels = {
                    0: 'Todos los matriculados',
                    1: 'Aprobados',
                    2: 'Desaprobados',
                    3: 'Sin acceder',
                    4: 'En curso',
                };
                return labels[this.statusId] || 'Desconocido';
            },

            get personalesFiltrados() {
                let resultado = [...this.personales];

                if (this.busqueda.trim()) {
                    const term = this.busqueda.trim().toLowerCase();
                    resultado = resultado.filter(p =>
                        (p.nombreCompleto && p.nombreCompleto.toLowerCase().includes(term)) ||
                        (p.nroDoc && p.nroDoc.toLowerCase().includes(term))
                    );
                }

                if (this.fechaDesde) {
                    const desde = new Date(this.fechaDesde);
                    desde.setHours(0, 0, 0, 0);
                    resultado = resultado.filter(p => {
                        if (!p.ultimo_acceso) return false;
                        return new Date(p.ultimo_acceso) >= desde;
                    });
                }

                if (this.fechaHasta) {
                    const hasta = new Date(this.fechaHasta);
                    hasta.setHours(23, 59, 59, 999);
                    resultado = resultado.filter(p => {
                        if (!p.ultimo_acceso) return false;
                        return new Date(p.ultimo_acceso) <= hasta;
                    });
                }

                if (this.filtroCargo) {
                    resultado = resultado.filter(p => p.cargo === this.filtroCargo);
                }

                if (this.filtroSucursal) {
                    resultado = resultado.filter(p => p.sucursal === this.filtroSucursal);
                }

                if (this.filtroTipoTrabajador) {
                    resultado = resultado.filter(p => p.tipo_trabajador === this.filtroTipoTrabajador);
                }

                if (this.ordenCampo !== 'default') {
                    resultado.sort((a, b) => {
                        let valA, valB;
                        if (this.ordenCampo === 'nota_final') {
                            valA = a.nota_final ?? -1;
                            valB = b.nota_final ?? -1;
                        } else if (this.ordenCampo === 'nombreCompleto') {
                            valA = (a.nombreCompleto || '').toLowerCase();
                            valB = (b.nombreCompleto || '').toLowerCase();
                        } else if (this.ordenCampo === 'ultimo_acceso') {
                            valA = a.ultimo_acceso ? new Date(a.ultimo_acceso).getTime() : 0;
                            valB = b.ultimo_acceso ? new Date(b.ultimo_acceso).getTime() : 0;
                        } else if (this.ordenCampo === 'cargo') {
                            valA = (a.cargo || '').toLowerCase();
                            valB = (b.cargo || '').toLowerCase();
                        } else if (this.ordenCampo === 'sucursal') {
                            valA = (a.sucursal || '').toLowerCase();
                            valB = (b.sucursal || '').toLowerCase();
                        } else if (this.ordenCampo === 'tipo_trabajador') {
                            valA = (a.tipo_trabajador || '').toLowerCase();
                            valB = (b.tipo_trabajador || '').toLowerCase();
                        }
                        if (valA < valB) return this.ordenDireccion === 'asc' ? -1 : 1;
                        if (valA > valB) return this.ordenDireccion === 'asc' ? 1 : -1;
                        return 0;
                    });
                }

                return resultado;
            },

            get cargosUnicos() {
                return [...new Set(this.personales.map(p => p.cargo).filter(Boolean))].sort();
            },

            get sucursalesUnicas() {
                return [...new Set(this.personales.map(p => p.sucursal).filter(Boolean))].sort();
            },

            get tiposTrabajadorUnicos() {
                return [...new Set(this.personales.map(p => p.tipo_trabajador).filter(Boolean))].sort();
            },

            get totalPaginas() {
                return Math.ceil(this.personalesFiltrados.length / this.itemsPorPagina);
            },

            get personalesPaginados() {
                const inicio = (this.paginaActual - 1) * this.itemsPorPagina;
                return this.personalesFiltrados.slice(inicio, inicio + this.itemsPorPagina);
            },

            get paginasVisibles() {
                const total = this.totalPaginas;
                const actual = this.paginaActual;
                const paginas = [];
                if (total <= 7) {
                    for (let i = 1; i <= total; i++) paginas.push(i);
                } else {
                    paginas.push(1);
                    if (actual > 3) paginas.push('...');
                    const inicio = Math.max(2, actual - 1);
                    const fin = Math.min(total - 1, actual + 1);
                    for (let i = inicio; i <= fin; i++) paginas.push(i);
                    if (actual < total - 2) paginas.push('...');
                    paginas.push(total);
                }
                return paginas;
            },

            irPagina(pagina) {
                if (pagina === '...' || pagina < 1 || pagina > this.totalPaginas) return;
                this.paginaActual = pagina;
                this.$nextTick(() => {
                    if (this.$refs.bodyContainer) this.$refs.bodyContainer.scrollTop = 0;
                });
            },

            formatearFechaHora(fecha) {
                if (!fecha) return '—';
                try {
                    const d = new Date(fecha);
                    const dia = String(d.getDate()).padStart(2, '0');
                    const mes = String(d.getMonth() + 1).padStart(2, '0');
                    const anio = d.getFullYear();
                    let horas = d.getHours();
                    const minutos = String(d.getMinutes()).padStart(2, '0');
                    const ampm = horas >= 12 ? 'PM' : 'AM';
                    horas = horas % 12 || 12;
                    const horasStr = String(horas).padStart(2, '0');
                    return `${dia}/${mes}/${anio} ${horasStr}:${minutos} ${ampm}`;
                } catch {
                    return '—';
                }
            },

            toggleOrden(campo) {
                if (this.ordenCampo === campo) {
                    if (this.ordenDireccion === 'asc') {
                        this.ordenDireccion = 'desc';
                    } else {
                        this.ordenCampo = 'default';
                        this.ordenDireccion = 'asc';
                    }
                } else {
                    this.ordenCampo = campo;
                    this.ordenDireccion = 'asc';
                }
            },

            getIconoOrden(campo) {
                if (this.ordenCampo !== campo) return 'ti-selector';
                return this.ordenDireccion === 'asc' ? 'ti-sort-ascending' : 'ti-sort-descending';
            },

            enviarRecordatorio(p) {
                if (this.enviandoRecordatorio[p.nroDoc] || this.recordatoriosEnviados[p.nroDoc]) return;
                this.enviandoRecordatorio[p.nroDoc] = true;
                this.recordatoriosEnviados[p.nroDoc] = true;
                fetch(`${VITE_URL_APP}/api/mail/enviar-recordatorio?course_id=${this.cursoId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({
                        email: p.correo,
                        full_name: p.nombreCompleto
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'No se pudo enviar el recordatorio');
                })
                .catch(error => {
                    console.error('Error al enviar recordatorio:', error);
                    this.recordatoriosEnviados[p.nroDoc] = false;
                })
                .finally(() => {
                    this.enviandoRecordatorio[p.nroDoc] = false;
                });
            },

            limpiarFiltros() {
                this.busqueda = '';
                this.fechaDesde = '';
                this.fechaHasta = '';
                this.filtroCargo = '';
                this.filtroSucursal = '';
                this.filtroTipoTrabajador = '';
                this.ordenCampo = 'default';
                this.ordenDireccion = 'asc';
                this.paginaActual = 1;
            },

            mostrar(cursoNombre, cursoId, statusId) {
                this.cursoNombre = cursoNombre;
                this.cursoId = cursoId;
                this.statusId = statusId;
                this.personales = [];
                this.loading = true;
                this.open = true;
                this.busqueda = '';
                this.fechaDesde = '';
                this.fechaHasta = '';
                this.filtroCargo = '';
                this.filtroSucursal = '';
                this.filtroTipoTrabajador = '';
                this.ordenCampo = 'default';
                this.ordenDireccion = 'asc';
                this.paginaActual = 1;
                this.enviandoRecordatorio = {};
                this.recordatoriosEnviados = {};

                fetch(`${VITE_URL_APP}/api/get-estudiantes-curso?course_id=${cursoId}&statusId=${statusId}`)
                    .then(response => {
                        if (!response.ok) throw new Error('Error en la respuesta del servidor');
                        return response.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.personales = res.Personales || [];
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener usuarios del curso:', error);
                        this.personales = [];
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },

            cerrar() {
                this.open = false;
            },
        };
    };

    window.infoBadges = function() {
        return {
            mensajes: {
                notificaciones: '<p>Las notificaciones por correo funcionan de dos formas:</p><br><p><b>• Automatizado:</b> Los días 1 y 15 de cada mes, el sistema analiza automáticamente todos los cursos y envía un correo recordatorio a los participantes que aún no han iniciado el curso.</p><br><p><b>• Manual:</b> Ingresando al listado de usuarios que no han iniciado un curso (haciendo clic en la tarjeta "Sin iniciar" del detalle del curso), puedes enviar un recordatorio individual haciendo clic en el botón "Notificar por correo" de cada usuario.</p>',
                memos: '<p>Los <b>MEMOs</b> son comunicaciones internas clasificadas estratégicamente seg\u00fan su nivel de criticidad para garantizar una gesti\u00f3n eficiente de la informaci\u00f3n.</p><br><div style="display:flex;flex-direction:column;gap:10px"><div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#eff6ff;border-radius:10px"><div style="width:28px;height:28px;border-radius:8px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="ti ti-info-circle" style="color:#2563eb;font-size:14px"></i></div><div><b style="color:#1e40af;font-size:13px">Nivel 1 \u2014 Informativo</b><br><span style="color:#64748b;font-size:12px">Comunicados generales y avisos de conocimiento</span></div></div><div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fff7ed;border-radius:10px"><div style="width:28px;height:28px;border-radius:8px;background:#ffedd5;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="ti ti-alert-triangle" style="color:#ea580c;font-size:14px"></i></div><div><b style="color:#9a3412;font-size:13px">Nivel 2 \u2014 Advertencia</b><br><span style="color:#64748b;font-size:12px">Recordatorios y alertas tempranas de cumplimiento</span></div></div><div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:#fef2f2;border-radius:10px"><div style="width:28px;height:28px;border-radius:8px;background:#fee2e2;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="ti ti-bell-ringing" style="color:#dc2626;font-size:14px"></i></div><div><b style="color:#991b1b;font-size:13px">Nivel 3 \u2014 Cr\u00edtico</b><br><span style="color:#64748b;font-size:12px">Notificaciones urgentes con atenci\u00f3n inmediata requerida</span></div></div></div>',
            },
            abrirInfo(titulo, mensaje) {
                const el = document.getElementById('modal-info')._x_dataStack?.[0];
                if (el) {
                    el.mostrar(titulo, mensaje);
                }
            },
        };
    };

    window.modalInfo = function() {
        return {
            open: false,
            titulo: '',
            mensaje: '',
            mostrar(titulo, mensaje) {
                this.titulo = titulo;
                this.mensaje = mensaje;
                this.open = true;
            },
            cerrar() {
                this.open = false;
            },
        };
    };

    window.modalUsuario = function() {
        return {
            open: false,
            personal: {
                nombre_completo: '',
                dni: '',
                codigo: '',
                cargo: '',
                sucursal: '',
                email: ''
            },
            cursos: [],
            totales: null,
            cursosCargado: false,
            filtroEstado: 'todos',
            filtroUltimoAccesoDesde: '',
            filtroUltimoAccesoHasta: '',
            filtroMatriculaDesde: '',
            filtroMatriculaHasta: '',
            filtroAnioCreacion: '',
            busquedaCurso: '',
            filtroTipoCurso: '',
            memoInfo: {
                total: 0,
                siguiente_num_memo: 1,
                siguiente_texto: 'primer',
            },
            memoCargado: false,

            getIniciales() {
                if (!this.personal.nombre_completo) return '?';
                const partes = this.personal.nombre_completo.trim().split(/\s+/).filter(Boolean);
                if (partes.length >= 2) {
                    return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
                }
                return partes[0][0].toUpperCase();
            },

            copiarCorreo(email, event) {
                navigator.clipboard.writeText(email).then(() => {
                    const btn = event.currentTarget;
                    const icon = btn.querySelector('i');
                    icon.className = 'ti ti-check text-[10px]';
                    btn.classList.add('text-green-500', 'bg-green-50');
                    setTimeout(() => {
                        icon.className = 'ti ti-copy text-[10px]';
                        btn.classList.remove('text-green-500', 'bg-green-50');
                    }, 1500);
                });
            },

            countByEstado(estado) {
                return this.cursos.filter(c => c.estado === estado).length;
            },

            get porcentajeAvance() {
                if (this.cursos.length === 0) return 0;
                const completados = this.countByEstado('Aprobado') + this.countByEstado('Desaprobado');
                const enCurso = this.countByEstado('En curso');
                return Math.round(((completados + enCurso) / this.cursos.length) * 100);
            },

            get cursosFiltrados() {
                return this.cursos.filter(c => {
                    if (this.filtroEstado !== 'todos' && c.estado !== this.filtroEstado) return false;
                    const q = this.busquedaCurso.toLowerCase().trim();
                    const nombre = c.nombre_curso || '';
                    if (q && !nombre.toLowerCase().includes(q)) return false;
                    if (this.filtroTipoCurso && c.tipo_curso !== this.filtroTipoCurso) return false;
                    if (this.filtroUltimoAccesoDesde) {
                        const desde = new Date(this.filtroUltimoAccesoDesde);
                        const val = c.fecha_ultimo_acceso ? new Date(c.fecha_ultimo_acceso) : null;
                        if (!val || val < desde) return false;
                    }
                    if (this.filtroUltimoAccesoHasta) {
                        const hasta = new Date(this.filtroUltimoAccesoHasta);
                        hasta.setHours(23, 59, 59, 999);
                        const val = c.fecha_ultimo_acceso ? new Date(c.fecha_ultimo_acceso) : null;
                        if (!val || val > hasta) return false;
                    }
                    if (this.filtroMatriculaDesde) {
                        const desde = new Date(this.filtroMatriculaDesde);
                        const val = c.fecha_inicio_matricula ? new Date(c.fecha_inicio_matricula) : null;
                        if (!val || val < desde) return false;
                    }
                    if (this.filtroMatriculaHasta) {
                        const hasta = new Date(this.filtroMatriculaHasta);
                        hasta.setHours(23, 59, 59, 999);
                        const val = c.fecha_inicio_matricula ? new Date(c.fecha_inicio_matricula) : null;
                        if (!val || val > hasta) return false;
                    }
                    if (this.filtroAnioCreacion) {
                        const anio = parseInt(this.filtroAnioCreacion);
                        const val = c.fecha_creacion_curso ? new Date(c.fecha_creacion_curso).getFullYear() : null;
                        if (val !== anio) return false;
                    }
                    return true;
                });
            },

            get aniosCreacion() {
                const actual = new Date().getFullYear();
                const años = [];
                for (let a = actual; a >= 2010; a--) años.push(a);
                return años;
            },

            get tiposCurso() {
                return [...new Set(this.cursos.map(c => c.tipo_curso).filter(Boolean))].sort();
            },

            formatearFecha(val) {
                if (!val || val === '0' || val === 0) return null;
                const d = new Date(val);
                if (isNaN(d.getTime())) return null;
                const fecha = d.toLocaleDateString('es-PE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                const hora = d.toLocaleTimeString('es-PE', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                return {
                    fecha,
                    hora
                };
            },

            mostrar(data) {
                this.personal = data;
                this.cursos = [];
                this.totales = null;
                this.cursosCargado = false;
                this.filtroEstado = 'todos';
                this.filtroUltimoAccesoDesde = '';
                this.filtroUltimoAccesoHasta = '';
                this.filtroMatriculaDesde = '';
                this.filtroMatriculaHasta = '';
                this.filtroAnioCreacion = '';
                this.busquedaCurso = '';
                this.filtroTipoCurso = '';
                this.memoInfo = {
                    total: 0,
                    siguiente_num_memo: 1,
                    siguiente_texto: 'primer'
                };
                this.memoCargado = false;
                this.open = true;

                fetch(`${VITE_URL_APP}/api/obtener-info-memo/${data.dni}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            this.memoInfo = res.data;
                        }
                        this.memoCargado = true;
                    })
                    .catch(() => {
                        this.memoCargado = true;
                    });

                fetch(`${VITE_URL_APP}/api/obtener-cursos-alumno-new?dni=${data.dni}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            this.cursos = res.Cursos || [];
                            this.totales = res.Totales || null;
                        }
                        this.cursosCargado = true;
                    })
                    .catch(() => {
                        this.cursos = [];
                        this.cursosCargado = true;
                    });
            },

            notificarCursosPendientes() {
                const pendientes = this.cursos.filter(c => c.estado === 'Sin acceder');
                if (pendientes.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin pendientes',
                        text: 'Este usuario inició todos sus cursos'
                    });
                    return;
                }
                if (!this.personal.email) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin correo',
                        text: 'Este colaborador no tiene correo registrado en el sistema'
                    });
                    return;
                }
                Swal.fire({
                    title: 'Enviando recordatorio',
                    text: `Recordatorio de ${pendientes.length} curso(s) pendiente(s)`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                fetch(`${VITE_URL_APP}/api/notificar-cursos-pendientes-new`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            dni: this.personal.dni,
                            nombreCompleto: this.personal.nombre_completo,
                            email: this.personal.email
                        }),
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Recordatorio enviado',
                                timer: 2000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message || 'No se pudo enviar el recordatorio'
                            });
                        }
                    })
                    .catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo enviar el recordatorio'
                        });
                    });
            },

            enviarMEMO() {
                const nroDoc = this.personal.dni;
                if (!nroDoc) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin documento',
                        text: 'No se encontró el DNI del colaborador'
                    });
                    return;
                }
                Swal.fire({
                    title: 'Enviando MEMO...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading(),
                });
                fetch(`${VITE_URL_APP}/api/enviar-memo-personal-new`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        },
                        body: JSON.stringify({
                            nroDoc: this.personal.dni,
                            nombreCompleto: this.personal.nombre_completo,
                            correo: this.personal.email,
                            cargo: this.personal.cargo
                        })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            if (window.tabulatorMemos) {
                                window.tabulatorMemos.setData(`${VITE_URL_APP}/api/obtener-memos-enviados`);
                            }
                            fetch(`${VITE_URL_APP}/api/obtener-info-memo/${this.personal.dni}`)
                                .then(r => r.json())
                                .then(info => {
                                    if (info.success) {
                                        this.memoInfo = info.data;
                                    }
                                });
                            Swal.fire({
                                icon: 'success',
                                title: 'MEMO enviado',
                                timer: 2000,
                                timerProgressBar: true,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message || 'No se pudo enviar el MEMO'
                            });
                        }
                    })
                    .catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo enviar el MEMO'
                        });
                    });
            },

            cerrar() {
                this.open = false;
            },
        };
    };

    window.filtrosPersonal = function() {
        return {
            hayFiltros: false,
            init() {
                this.$nextTick(() => this.verificar());
            },
            verificar() {
                const ids = [
                    'filtroSucursalPersonal',
                    'filtroTipoPersonal',
                    'filtroCargoPersonal',
                    'filtroClientePersonal',
                ];
                this.hayFiltros = ids.some(id => !!document.getElementById(id)?.value);
            },
        };
    };

    window.searchPersonalSeguimiento = function() {
        return {
            open: false,
            query: "",
            results: [],

            search() {
                const q = this.query.toLowerCase().trim();
                if (q.length < 3) {
                    this.results = [];
                    this.open = false;
                    return;
                }
                const personalData = window.tabulatorPersonal?.getData() || [];
                this.results = personalData.filter(p =>
                    (p.nombre_completo || '').toLowerCase().includes(q) ||
                    (p.dni || '').includes(q)
                );
                this.open = this.results.length > 0;
                if (this.open) this.posicionarDropdown();
            },

            seleccionarPrimerResultado() {
                if (this.results.length > 0) {
                    this.seleccionarPersonal(this.results[0]);
                }
            },

            seleccionarPersonal(p) {
                this.open = false;
                this.query = "";
                const modalEl = document.getElementById("modal-usuario");
                const alpineComponent = modalEl?._x_dataStack?.[0];
                if (alpineComponent) {
                    alpineComponent.mostrar(p);
                }
            },

            posicionarDropdown() {
                this.$nextTick(() => {
                    const input = this.$refs.inputBuscar;
                    const dropdown = this.$refs.dropdown;
                    if (!input || !dropdown) return;

                    const rect = input.getBoundingClientRect();
                    dropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                    dropdown.style.left = (rect.left + window.scrollX) + 'px';
                    dropdown.style.width = rect.width + 'px';
                });
            },
        };
    };

    window.modalComparativa = function() {
        return {
            open: false,
            seleccionados: [],
            cargando: false,
            resultado: null,
            error: null,
            tabResultado: 'persisten',
            busquedaResultado: '',

            get persistenFiltrados() {
                return this.filtrarLista(this.resultado?.persisten ?? []);
            },

            get yaNoEstanFiltrados() {
                return this.filtrarLista(this.resultado?.ya_no_estan ?? []);
            },

            get nuevosFiltrados() {
                return this.filtrarLista(this.resultado?.nuevos ?? []);
            },

            filtrarLista(lista) {
                const q = this.busquedaResultado.trim().toLowerCase();
                if (!q) return lista;
                return lista.filter(item =>
                    (item.NOMBRE_COMPLETO || '').toLowerCase().includes(q) ||
                    (item.NRO_DOCU_IDEN || '').includes(q)
                );
            },

            abrir() {
                this.open = true;
                this.seleccionados = [];
                this.resultado = null;
                this.error = null;
            },

            toggleNivel(nivel) {
                if (this.seleccionados.includes(nivel)) {
                    this.seleccionados =
                        this.seleccionados.filter(n => n !== nivel);

                    return;
                }

                if (this.seleccionados.length === 0) {
                    this.seleccionados.push(nivel);

                    return;
                }

                const primero = this.seleccionados[0];

                if (nivel <= primero) {
                    return;
                }

                if (this.seleccionados.length >= 2) {
                    return;
                }

                this.seleccionados.push(nivel);
            },

            async realizarComparativa() {
                if (this.seleccionados.length !== 2) {
                    return;
                }

                this.cargando = true;
                this.resultado = null;
                this.error = null;

                try {

                    const response = await fetch(
                        `${VITE_URL_APP}/api/comparar-memos`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document
                                    .querySelector('meta[name="csrf-token"]')
                                    ?.getAttribute('content')
                            },

                            body: JSON.stringify({
                                contrMEMOs1: this.seleccionados[0],
                                contrMEMOs2: this.seleccionados[1],
                            }),
                        }
                    );

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Error');
                    }

                    this.resultado = data.data;

                } catch (e) {
                    this.error = e.message || 'Ocurrió un error';

                } finally {
                    this.cargando = false;
                }
            },

            estilosNivel(nivel) {
                const map = {
                    1: 'border-color: #93c5fd; background: #eff6ff',
                    2: 'border-color: #fdba74; background: #fff7ed',
                    3: 'border-color: #fca5a5; background: #fef2f2',
                };
                return map[nivel] || '';
            },

            cerrar() {
                this.open = false;
                this.resultado = null;
                this.error = null;
                this.seleccionados = [];
            },
        };
    };
</script>

@endsection
@section('script')
@vite(['resources/js/functions/capacitacion/seguimiento_matriculas_new.js'])
@endsection