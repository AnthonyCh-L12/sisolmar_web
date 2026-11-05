@extends('layouts.vertical', ['title' => 'Gestión de cursos (Nuevo)'])

@section('css')
<style>
    [x-cloak] { display: none !important; }

    body { background: #f8fafc; }

    .custom-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(100,116,139,.35) transparent; }
    .custom-scrollbar::-webkit-scrollbar { width: 7px; height: 7px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(100,116,139,.35); border-radius: 999px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(100,116,139,.5); }

    /* ── TABULATOR (mismo estilo que seguimiento_matriculas) ── */
    .tabulator {
        border: 1px solid rgba(226,232,240,.8) !important;
        border-radius: 18px !important;
        overflow: hidden !important;
        background: #ffffff !important;
        box-shadow: 0 1px 2px rgba(15,23,42,.03), 0 10px 30px rgba(15,23,42,.04) !important;
        font-size: 13px !important;
    }
    .tabulator-header {
        background: linear-gradient(to bottom, #fcfcfd 0%, #f8fafc 100%) !important;
        border-top: none !important; border-left: none !important; border-right: none !important;
        border-bottom: 1px solid #eef2f7 !important;
        padding: 6px 0 4px 0 !important;
    }
    .tabulator-header .tabulator-col { background: transparent !important; border-right: none !important; min-height: 48px !important; }
    .tabulator-header .tabulator-col-content { padding: 12px 14px !important; }
    .tabulator-header .tabulator-col-title {
        width: 100% !important; text-align: left !important;
        font-size: 10px !important; font-weight: 800 !important;
        letter-spacing: .12em !important; text-transform: uppercase !important;
        color: #64748b !important;
    }
    .tabulator-col-sorter { color: #94a3b8 !important; }
    /* Encabezados centrados: Tipo / Plan / Creación / Acciones */
    .tabulator-header .tabulator-col[tabulator-field="CURS_TIPO"] .tabulator-col-title,
    .tabulator-header .tabulator-col[tabulator-field="CURS_PLAN_CAPAC_NOMBRE"] .tabulator-col-title,
    .tabulator-header .tabulator-col[tabulator-field="CURS_CREADO_FECHA"] .tabulator-col-title,
    .tabulator-header .tabulator-col[tabulator-field="ACCIONES"] .tabulator-col-title { text-align: center !important; }
    .tabulator-tableholder { overflow-x: auto !important; overflow-y: hidden !important; scrollbar-width: thin; scrollbar-color: rgba(100,116,139,.35) transparent; }
    .tabulator-tableholder::-webkit-scrollbar { height: 8px; }
    .tabulator-tableholder::-webkit-scrollbar-track { background: transparent; }
    .tabulator-tableholder::-webkit-scrollbar-thumb { background: rgba(100,116,139,.35); border-radius: 999px; }
    .tabulator-row {
        background: #ffffff !important;
        border-left: none !important; border-right: none !important;
        border-bottom: 1px solid #f8fafc !important;
    }
    .tabulator-row:last-child { border-bottom: none !important; }
    .tabulator-row:hover {
        background: linear-gradient(to right, rgba(59,130,246,.04), rgba(59,130,246,.01)) !important;
        box-shadow: inset 3px 0 0 #2563eb !important;
    }
    .tabulator-row.tabulator-row-disabled { background: #fff1f1 !important; }
    .tabulator-row .tabulator-cell {
        border-right: none !important;
        padding-top: 14px !important; padding-bottom: 14px !important;
        padding-left: 14px !important; padding-right: 14px !important;
        vertical-align: middle !important; color: #1e293b !important;
    }
    .tabulator-footer {
        background: #ffffff !important;
        border-top: 1px solid #eef2f7 !important;
        border-left: none !important; border-right: none !important; border-bottom: none !important;
        padding: 14px 18px !important;
    }
    .tabulator-footer-contents { display: flex !important; align-items: center !important; justify-content: space-between !important; flex-wrap: wrap !important; gap: 12px !important; }
    .tabulator-footer .tabulator-page-counter { color: #475569 !important; font-size: 12px !important; font-weight: 700 !important; }
    .tabulator-footer .tabulator-paginator { display: flex !important; align-items: center !important; gap: 6px !important; }
    .tabulator-footer select.tabulator-page-size {
        height: 38px !important; padding: 0 36px 0 14px !important;
        border-radius: 12px !important; border: 1px solid #e2e8f0 !important;
        background-color: #ffffff !important;
        font-size: 12px !important; font-weight: 700 !important; color: #334155 !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.7' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") !important;
        background-position: right .7rem center !important; background-repeat: no-repeat !important; background-size: 1.1em 1.1em !important;
        transition: border-color .18s ease, box-shadow .18s ease !important;
    }
    .tabulator-footer select.tabulator-page-size:hover { border-color: #93c5fd !important; }
    .tabulator-footer select.tabulator-page-size:focus { outline: none !important; border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59,130,246,.12) !important; }
    .tabulator-footer .tabulator-page {
        min-width: 36px !important; height: 36px !important;
        display: inline-flex !important; align-items: center !important; justify-content: center !important;
        border-radius: 11px !important; border: 1px solid #e2e8f0 !important;
        background: #ffffff !important; color: #475569 !important;
        font-size: 12px !important; font-weight: 700 !important;
        transition: all .18s ease !important;
    }
    .tabulator-footer .tabulator-page:hover:not(.active) { background: #f8fafc !important; border-color: #cbd5e1 !important; }
    .tabulator-footer .tabulator-page.active {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
        border-color: #1d4ed8 !important; color: #ffffff !important;
        box-shadow: 0 6px 16px rgba(37,99,235,.25) !important;
    }
    .tabulator-placeholder {
        min-height: 110px !important; display: flex !important;
        align-items: center !important; justify-content: center !important;
        background: #ffffff !important;
    }
    .tabulator-placeholder span { color: #94a3b8 !important; font-size: 13px !important; font-weight: 600 !important; }
</style>
@endsection

@include('layouts.shared/page-title', ['subtitle' => 'Capacitación', 'title' => 'Gestión de cursos (Nuevo)'])

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
                        <i class="ti ti-book text-sm"></i>
                        Gestión de cursos
                    </div>

                    <h1 class="text-3xl font-bold tracking-tight text-default-900 mt-4">
                        Gestión de Cursos
                    </h1>

                    <p class="mt-3 text-sm leading-7 text-default-600 max-w-3xl">
                        Creación de cursos especificando sus características y plan de capacitación al que pertenecen.
                    </p>

                    <div class="flex items-center gap-6 mt-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                <i class="ti ti-book-2 text-lg text-primary"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800"><span id="statTotalCursos">—</span> Cursos</p>
                                <p class="text-[10px] text-default-500">disponibles</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                                <i class="ti ti-tag text-lg text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Planes</p>
                                <p class="text-[10px] text-default-500">de capacitación</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden xl:flex flex-col items-center justify-center shrink-0">
                    <div
                        class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center">
                        <i class="ti ti-book text-4xl text-primary/60"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Card tabla --}}
    <div class="rounded-2xl border border-default-200/60 bg-white shadow-sm p-6">
        <div class="flex flex-col gap-4 mb-4">
            {{-- Título + buscador --}}
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-default-900">Cursos registrados</h2>
                    <p class="text-xs text-default-400 mt-0.5">Clic en una fila para editar el curso (solo inactivos).</p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
                    <div class="relative flex-1 sm:flex-none">
                        <i class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-default-400 pointer-events-none"></i>
                        <input id="buscarCursoNew" placeholder="Buscar por nombre..."
                            class="w-full sm:w-64 h-10 pl-8 pr-3 text-sm border border-default-200 rounded-lg !bg-white !text-default-700 placeholder:text-default-300 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                    </div>
                    <button type="button" onclick="window.abrirModalRegistroNew()"
                        class="h-10 px-4 inline-flex items-center justify-center gap-1.5 bg-primary text-white text-xs font-semibold rounded-lg shadow-sm hover:bg-primary-700 transition whitespace-nowrap">
                        <i class="ti ti-plus text-sm"></i>
                        Crear un curso
                    </button>
                    <button id="btnLimpiarFiltrosCursosNew" type="button"
                        class="h-10 px-4 inline-flex items-center justify-center gap-1.5 bg-default-100 text-default-700 text-xs font-semibold rounded-lg shadow-sm hover:bg-default-200 transition whitespace-nowrap">
                        <i class="ti ti-filter-off text-sm"></i>
                        Limpiar filtros
                    </button>
                </div>
            </div>

            {{-- Filtros organizados --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 rounded-xl bg-default-50/60 border border-default-100 p-3">
                <div class="flex flex-col gap-1.5">
                    <label for="filtroPlanCursoNew" class="text-[11px] font-bold uppercase tracking-wider text-default-500">Plan</label>
                    <select id="filtroPlanCursoNew"
                        class="w-full h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                        <option value="">Todos los planes</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="filtroTipoCursoNew" class="text-[11px] font-bold uppercase tracking-wider text-default-500">Tipo</label>
                    <select id="filtroTipoCursoNew"
                        class="w-full h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                        <option value="">Todos los tipos</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="filtroAnioCursoNew" class="text-[11px] font-bold uppercase tracking-wider text-default-500">Año</label>
                    <select id="filtroAnioCursoNew"
                        class="w-full h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                        <option value="">Todos los años</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="filtroDesdeCursoNew" class="text-[11px] font-bold uppercase tracking-wider text-default-500">Desde</label>
                    <input id="filtroDesdeCursoNew" type="date"
                        class="w-full h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="filtroHastaCursoNew" class="text-[11px] font-bold uppercase tracking-wider text-default-500">Hasta</label>
                    <input id="filtroHastaCursoNew" type="date"
                        class="w-full h-10 px-3 text-sm text-default-700 bg-white border border-default-200 rounded-lg shadow-sm outline-none transition hover:border-default-300 focus:border-primary/50 focus:ring-2 focus:ring-primary/10">
                </div>
            </div>
        </div>
        <div id="tblCursosNew" class="w-full"></div>
    </div>

    {{-- Catálogo "Dirigido a" para el wizard (el resto de combos llega por API al abrir el modal) --}}
    @isset($dirigidos)
    <script>
        window.dirigidosNew = {{ Js::from($dirigidos->map(fn($d) => ['codigo' => $d->codigo, 'texto' => $d->texto])->values()->toArray()) }};
    </script>
    @endisset

    {{-- Modal wizard: registrar curso --}}
    @include('capacitacion.partials.form_curso_new_wizard')

    {{-- Modal simple: editar curso inactivo --}}
    @include('capacitacion.partials.form_curso_edit_new_modal')

    {{-- Modal: aperturar curso --}}
    @include('capacitacion.partials.form_curso_apertura_new_modal')

    {{-- Modal: extender plazo --}}
    @include('capacitacion.partials.form_curso_aplazar_new_modal')
</div>
@endsection

@section('script')
@vite(['resources/js/functions/capacitacion/gestion_cursos_new.js', 'resources/js/functions/capacitacion/gestion_cursos_new_modal.js'])
@endsection
