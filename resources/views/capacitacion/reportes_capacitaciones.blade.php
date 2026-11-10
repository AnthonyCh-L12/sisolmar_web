@extends('layouts.vertical', ['title' => 'Reportes de Capacitaciones'])

@section('css')
<style>
    [x-cloak] {
        display: none !important;
    }

    .card-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
    }

    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 0, 0, 0.1) transparent;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
        height: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.15);
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.25);
    }

    .table-sortable th {
        user-select: none;
    }

    #modal-reporte-por-capacitacion input[x-model="searchCurso"] {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .table-row {
        transition: all 0.15s ease;
    }

    .table-row-even {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .table-row:hover {
        background-color: rgba(59, 130, 246, 0.04) !important;
    }

    .badge-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        margin-right: 5px;
        flex-shrink: 0;
    }
</style>
@endsection

@include('layouts.shared/page-title', ['subtitle' => 'Capacitación', 'title' => 'Reportes'])

@section('content')
<div x-data="reportesApp" class="px-6 py-6">
    {{-- Header --}}
    <div
        class="relative overflow-hidden rounded-2xl border border-default-200/60 bg-gradient-to-br from-white via-default-50/50 to-primary/5 shadow-sm mb-6">
        <!-- Decorative elements -->
        <div class="absolute top-0 right-0 w-72 h-72 bg-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 w-48 h-48 bg-amber-500/5 rounded-full blur-2xl"></div>
        <div class="absolute top-1/2 right-1/4 w-32 h-32 bg-green-500/5 rounded-full blur-xl"></div>

        <!-- Grid pattern overlay -->
        <div class="absolute inset-0 opacity-[0.015]"
            style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 24px 24px;">
        </div>

        <div class="relative p-8">
            <div class="flex items-start justify-between gap-6">
                <div class="flex-1">
                    <!-- Badge -->
                    <div
                        class="inline-flex items-center gap-2 w-fit px-3 py-1.5 rounded-full bg-primary/10 text-primary text-xs font-semibold">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></div>
                        <i class="ti ti-report text-sm"></i>
                        Módulo de reportes de capacitaciones
                    </div>

                    <!-- Title + Description -->
                    <h1 class="text-3xl font-bold tracking-tight text-default-900 mt-4">
                        Centro de Reportes
                    </h1>
                    <p class="mt-3 text-sm leading-7 text-default-600 max-w-3xl">
                        Genere reportes detallados sobre el estado de las capacitaciones del personal.
                        Filtre, ordene y exporte la información a Excel o PDF según sus necesidades.
                    </p>
                    <p class="mt-2 inline-flex items-center gap-2 text-xs text-default-500 leading-6">
                        <i class="ti ti-history text-amber-500"></i>
                        Los reportes consideran tanto cursos históricos (de años anteriores, previos a Sisolmar Web) como los nuevos.
                    </p>

                    <!-- Quick stats -->
                    <div class="flex items-center gap-6 mt-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                <i class="ti ti-file-report text-lg text-primary"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">5 tipos</p>
                                <p class="text-[10px] text-default-500">de reporte</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                                <i class="ti ti-file-spreadsheet text-lg text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Excel</p>
                                <p class="text-[10px] text-default-500">& PDF</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                                <i class="ti ti-filter text-lg text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Filtros</p>
                                <p class="text-[10px] text-default-500">avanzados</p>
                            </div>
                        </div>
                    </div>

                    <!-- Historial button -->
                    <div class="mt-5">
                        <button @click="abrirModalHistorial()"
                            class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-sm font-semibold shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 hover:from-indigo-600 hover:to-purple-600 transition-all cursor-pointer">
                            <i class="ti ti-clock text-base"></i>
                            Ver historial de reportes generados
                        </button>
                    </div>
                </div>

                <!-- Right side: decorative icon -->
                <div class="hidden xl:flex flex-col items-center justify-center shrink-0">
                    <div
                        class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center">
                        <i class="ti ti-chart-bar text-4xl text-primary/60"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        {{-- Card 1: Reporte por capacitación --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-md shadow-primary/20">
                        <i class="ti ti-file-report text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-default-100 text-default-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-users text-[9px]"></i>
                        Personal
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Reporte por capacitación</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Identifique al personal según su estado en una capacitación específica.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Filtre por sistema, área, sucursal y curso</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Estado: Pendiente, Aprobado o Desaprobado</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Exporte a Excel y PDF</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirModalReporte()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors w-full">
                    <i class="ti ti-arrow-right text-sm"></i>
                    Generar reporte
                </button>
            </div>
        </div>

        {{-- Card 2: Reporte de capacitaciones --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-amber-400 flex items-center justify-center shadow-md shadow-amber-500/20">
                        <i class="ti ti-books text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-calendar text-[9px]"></i>
                        Cursos
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Reporte de capacitaciones</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Obtenga el listado completo de cursos con fechas, responsables y descripciones.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-amber-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Filtre por sistema, área y período</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-amber-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Detalle de responsable y descripción</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-amber-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Exporte a Excel y PDF</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirModalCursosArea()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600 transition-colors w-full">
                    <i class="ti ti-arrow-right text-sm"></i>
                    Generar reporte
                </button>
            </div>
        </div>

        {{-- Card 3: Récord por personal --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-400 flex items-center justify-center shadow-md shadow-green-500/20">
                        <i class="ti ti-history text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-50 text-green-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-user text-[9px]"></i>
                        Historial
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Récord Histórico de Capacitación por personal</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Consulte el historial completo de cursos de un colaborador específico.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-green-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Búsqueda por nombre o DNI</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-green-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Filtre por sistema, área y estado</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-green-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Fechas de matrícula y último acceso</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirModalRecordPersonal()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-green-500 text-white text-sm font-semibold hover:bg-green-600 transition-colors w-full">
                    <i class="ti ti-arrow-right text-sm"></i>
                    Generar reporte
                </button>
            </div>
        </div>

        {{-- Card 4: Reporte General de Capacitaciones --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-purple-400 flex items-center justify-center shadow-md shadow-violet-500/20">
                        <i class="ti ti-report-analytics text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-violet-50 text-violet-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-chart-bar text-[9px]"></i>
                        General
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Reporte General de Capacitaciones</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Visualice y exporte datos consolidados con filtros avanzados.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-violet-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Filtre por empresa, sucursal y tipo de trabajo</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-violet-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Personal, estado, capacitación y pool</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-violet-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Exporte a Excel y PDF</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirModalReporteGeneral()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-violet-500 text-white text-sm font-semibold hover:bg-violet-600 transition-colors w-full">
                    <i class="ti ti-arrow-right text-sm"></i>
                    Generar reporte
                </button>
            </div>
        </div>

        {{-- Card 5: Reporte Formato --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-rose-500 to-rose-400 flex items-center justify-center shadow-md shadow-rose-500/20">
                        <i class="ti ti-certificate text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-signature text-[9px]"></i>
                        Constancia
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Reporte Formato</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Constancia de participación del personal seleccionado en una capacitación, con firma del capacitador y personal capacitado.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-rose-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Personal seleccionado por curso</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-rose-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Firma del capacitador y del personal</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-rose-500 shrink-0"></i>
                        <span class="text-xs text-default-600">Exporte a PDF</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirModalReporteFormato()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 transition-colors w-full">
                    <i class="ti ti-arrow-right text-sm"></i>
                    Generar reporte
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Reporte Por Capacitacion --}}
    <div id="modal-reporte-por-capacitacion" x-data="modalReportePorCapacitacion" x-show="open" x-cloak
        @keydown.escape.window="cerrar()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div :class="view === 'personal' ? 'max-w-[90vw] max-h-[100vh]' : 'max-w-xl'"
            class="flex flex-col w-full bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            {{-- Encabezado del modal --}}
            <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-file-report text-lg"></i>
                    </div>
                    <div>
                        {{-- Título dinámico según la vista activa --}}
                        <div x-show="view === 'filters'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Reporte por capacitación
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">Seleccione curso, sucursal (opcional) y estado del personal</p>
                        </div>
                        <div x-show="view === 'personal'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Personal encontrado
                                <span x-show="selectedCurso && selectedEstado === 'PENDIENTE'">
                                    sin iniciar la capacitación de <span x-text="nombreCurso"></span>
                                </span>
                                <span x-show="!selectedCurso && selectedEstado === 'PENDIENTE'">
                                    de <span x-text="nombreCurso"></span> (sin iniciar)
                                </span>
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">Resultado del reporte generado</p>
                        </div>
                    </div>
                </div>
                {{-- Botón cerrar --}}
                <button type="button" @click="cerrar()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            {{-- Vista: Filtros --}}
            <div x-show="view === 'filters'" class="px-6 pt-4 pb-6 space-y-4">
                <!-- <div class="grid grid-cols-2 gap-4">
                    {{-- Selector de personal vigente --}}
                    <div>
                        <label class="text-xs font-medium text-default-700 mb-1.5 block">
                        Personal vigente <span class="text-default-400 font-normal">(opcional, "Si" por defecto)</span>
                        </label>
                        <select x-model="personalVigente"
                            class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                            <option value="2">Todos</option>
                            <option value="1">Si</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div> -->

                {{-- Selector de sucursal --}}
                <div>
                    <label class="text-xs font-medium text-default-700 mb-1.5 block">
                        Sucursal <span class="text-default-400 font-normal">(opcional, "Todas" para general)</span>
                    </label>
                    <select x-model="selectedSucursal"
                        class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        <option value="" disabled
                            x-text="loadingSucursales ? 'Cargando sucursales...' : (sucursales.length === 0 ? 'Sin sucursales disponibles' : 'Seleccione sucursal')">
                        </option>
                        <option value="">Todas las sucursales</option>
                        <template x-for="option in sucursales" :key="option.codigo">
                            <option :value="option.codigo" x-text="option.sucursal"></option>
                        </template>
                    </select>
                </div>

                {{-- Rango de fechas --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-default-700 mb-1.5 block">
                            Fecha de creación (desde) <span class="text-default-400 font-normal">(opcional)</span>
                        </label>
                        <input type="date" x-model="selectedFechaInicio" @input="filtrarCursosPorFecha()"
                            class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" />
                    </div>
                    <div>
                        <label class="text-xs font-medium text-default-700 mb-1.5 block">
                            Fecha de creación (hasta) <span class="text-default-400 font-normal">(opcional)</span>
                        </label>
                        <input type="date" x-model="selectedFechaFin" @input="filtrarCursosPorFecha()"
                            class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" />
                    </div>
                </div>

                {{-- Estado de alumnos --}}
                <div>
                    <label class="text-xs font-medium text-default-700 mb-1.5 block">
                        Estado de alumnos <span class="text-danger">*</span>
                    </label>
                    <select x-model="selectedEstado"
                        class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                        <option value="0">Todos los estados</option>
                        <option value="1">Aprobados</option>
                        <option value="2">Desaprobados</option>
                        <option value="3">Sin acceder</option>
                        <option value="4">En curso</option>
                    </select>
                </div>

                {{-- Selección de cursos --}}
                <div>
                    <label class="text-xs font-medium text-default-700 mb-1.5 block">
                        Cursos de capacitación <span class="text-danger">*</span>
                    </label>

                    <div class="border border-default-200 rounded-lg overflow-hidden">

                        {{-- Barra de búsqueda + toggle seleccionar todos --}}
                        <div class="flex items-center gap-2 border-b border-default-200 bg-white px-3">
                            <i class="ti ti-search text-default-400 text-sm shrink-0"></i>
                            <input
                                type="text"
                                x-model="searchCurso"
                                placeholder="Buscar curso..."
                                class="flex-1 h-9 text-sm bg-transparent text-default-900 placeholder-default-400 border-0 ring-0 focus:ring-0 focus:outline-none shadow-none" />
                            <button
                                type="button"
                                @click="toggleSeleccionarTodos()"
                                :disabled="!searchCurso"
                                class="shrink-0 text-[11px] font-semibold px-2.5 h-6 rounded-md transition-colors whitespace-nowrap"
                                :class="!searchCurso
                ? 'text-default-300 bg-default-50 cursor-not-allowed'
                : (todosCursosSeleccionados
                    ? 'text-danger bg-danger/10 hover:bg-danger/20'
                    : 'text-primary bg-primary/10 hover:bg-primary/20')"
                                x-text="todosCursosSeleccionados ? 'Quitar todos' : 'Todos'">
                            </button>
                        </div>

                        {{-- Lista de cursos --}}
                        <div class="max-h-40 overflow-y-auto custom-scrollbar divide-y divide-default-100">
                            <template x-if="loadingCursos">
                                <div class="flex items-center justify-center py-6 text-default-400 gap-2">
                                    <i class="ti ti-loader animate-spin text-lg"></i>
                                    <span class="text-sm">Cargando cursos...</span>
                                </div>
                            </template>

                            <template x-if="!loadingCursos">
                                <div>
                                    <template
                                        x-for="option in cursos.filter(c => !searchCurso || c.Nombre.toLowerCase().includes(searchCurso.toLowerCase()))"
                                        :key="option.Id">
                                        <label
                                            class="flex items-center gap-2.5 px-3 py-2 cursor-pointer transition-colors select-none"
                                            :class="courseIds.includes(option.Id) ? 'bg-primary/5' : 'hover:bg-default-50'">

                                            <input
                                                type="checkbox"
                                                :value="option.Id"
                                                x-model="courseIds"
                                                class="sr-only"
                                                @change="courseIds = courseIds.map(id => typeof id === 'string' ? parseInt(id) : id)" />

                                            {{-- Checkbox custom --}}
                                            <div
                                                class="w-4 h-4 shrink-0 rounded border flex items-center justify-center transition-all"
                                                :class="courseIds.includes(option.Id)
                                ? 'bg-primary border-primary'
                                : 'bg-white border-default-300'">
                                                <i class="ti ti-check text-white text-[10px]" x-show="courseIds.includes(option.Id)"></i>
                                            </div>

                                            <span
                                                class="text-sm transition-colors"
                                                :class="courseIds.includes(option.Id) ? 'text-primary font-medium' : 'text-default-700'"
                                                x-text="option.Nombre">
                                            </span>
                                        </label>
                                    </template>

                                    <div
                                        x-show="cursos.filter(c => !searchCurso || c.Nombre.toLowerCase().includes(searchCurso.toLowerCase())).length === 0"
                                        class="flex flex-col items-center justify-center py-6 text-default-400 gap-1.5">
                                        <i class="ti ti-search-off text-lg"></i>
                                        <span class="text-xs">Sin resultados para "<span class="font-medium" x-text="searchCurso"></span>"</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Chips de seleccionados --}}
                    <div class="mt-2 min-h-[24px]">
                        <template x-if="courseIds.length === 0">
                            <p class="text-xs text-default-400 italic">Ningún curso seleccionado</p>
                        </template>

                        <template x-if="courseIds.length > 0">
                            <div class="flex flex-wrap gap-1.5">
                                <template
                                    x-for="id in courseIds"
                                    :key="id">
                                    <span class="inline-flex items-center gap-1 pl-2 pr-1 h-6 rounded-full bg-primary/10 border border-primary/25 text-primary text-[11px] font-medium max-w-[200px]">
                                        <span class="truncate" x-text="cursos.find(c => c.Id == id)?.Nombre ?? id"></span>
                                        <button
                                            type="button"
                                            @click="courseIds = courseIds.filter(c => c !== id)"
                                            class="shrink-0 w-4 h-4 rounded-full hover:bg-primary/20 flex items-center justify-center transition-colors">
                                            <i class="ti ti-x text-[9px]"></i>
                                        </button>
                                    </span>
                                </template>

                                <button
                                    type="button"
                                    @click="courseIds = []"
                                    class="inline-flex items-center gap-1 px-2 h-6 rounded-full bg-danger/10 border border-danger/20 text-danger text-[11px] font-medium hover:bg-danger/20 transition-colors">
                                    <i class="ti ti-trash text-[10px]"></i>
                                    Limpiar todo
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Vista: Personal encontrado tras reporte generado --}}
            <div x-show="view === 'personal'" class="px-6 pt-4 pb-6 flex-1 overflow-hidden min-h-0">
                <template x-if="loadingPersonal">
                    <div class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                        <p class="text-sm">Cargando personal...</p>
                    </div>
                </template>

                <template x-if="!loadingPersonal && personal.length === 0">
                    <div class="text-center py-10 text-default-500 text-sm">
                        No se encontró personal disponible.
                    </div>
                </template>

                <template x-if="!loadingPersonal && personal.length > 0">
                    <div class="grid grid-rows-[auto_minmax(0,1fr)] grid-cols-[2fr_8fr] max-h-[600px] min-h-0 border border-default-200 rounded-xl">
                        {{-- Sidebar izquierdo (cursos + sucursales) --}}
                        <div class="w-full shrink-0 border-r border-default-200 overflow-y-auto custom-scrollbar">
                            <div class="px-4 py-3 border-b border-default-100 bg-default-50/50">
                                <span class="text-xs font-semibold text-default-600 uppercase tracking-wider">Cursos seleccionados</span>
                            </div>
                            <template x-for="(grupo, gi) in personal" :key="gi">
                                <div>
                                    <button type="button"
                                        @click="seleccionarCurso(gi)"
                                        :title="grupo.Curso"
                                        class="w-full text-left px-4 py-3 border-b border-default-100 transition-colors cursor-pointer"
                                        :class="selectedCursoIndex === gi ? 'bg-primary/5' : 'hover:bg-default-50'">
                                        <div class="flex items-center gap-2.5">
                                            <i class="ti text-xs shrink-0 transition-transform"
                                                :class="selectedCursoIndex === gi ? 'ti-chevron-down text-primary' : 'ti-chevron-right text-default-400'"></i>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-default-900 leading-tight truncate" x-text="grupo.Curso"></p>
                                                <p class="text-xs text-default-500 mt-0.5">
                                                    <span class="font-semibold" x-text="grupo.Personales.length"></span> personal(es)
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                    <template x-if="selectedCursoIndex === gi">
                                        <div class="border-b border-default-100"
                                            x-transition:enter="transition-all duration-200 ease-out"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition-all duration-150 ease-in"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1">
                                            <template x-for="(suc, si) in sucursalesDelCursoActual" :key="si">
                                                <button type="button"
                                                    @click="seleccionarSucursal(si)"
                                                    class="w-full text-left pl-9 pr-4 py-2.5 transition-colors cursor-pointer border-t border-default-100/50"
                                                    :class="selectedSucursalIdx === si ? 'bg-primary/10 border-l-2 border-l-primary' : 'hover:bg-default-50'"
                                                    x-transition:enter="transition-all duration-300 ease-out"
                                                    x-transition:enter-start="opacity-0 translate-x-[-8px]"
                                                    x-transition:enter-end="opacity-100 translate-x-0"
                                                    :style="`transition-delay: ${si * 60}ms`">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-1 h-4 rounded-full shrink-0"
                                                            :class="selectedSucursalIdx === si ? 'bg-primary' : 'bg-default-300'"></div>
                                                        <span class="text-xs font-medium truncate"
                                                            :class="selectedSucursalIdx === si ? 'text-primary' : 'text-default-600'"
                                                            x-text="suc.SucursalNombre"></span>
                                                        <span class="text-[10px] text-default-400 ml-auto shrink-0" x-text="suc.Personales.length + ' personal(es)'"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Tabla de personal del curso/sucursal seleccionada --}}
                        <div class="flex flex-col min-w-0 overflow-hidden">
                            <template x-if="selectedCursoIndex >= 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-primary/5 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1 h-6 rounded-full bg-primary/60 shrink-0"></div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <h4 class="text-sm font-bold text-default-800 leading-tight" x-text="cursoActual?.Curso"></h4>
                                            <template x-if="sucursalesDelCursoActual[selectedSucursalIdx]">
                                                <span class="text-xs text-default-400">·</span>
                                            </template>
                                            <span class="text-xs font-semibold text-primary" x-text="sucursalesDelCursoActual[selectedSucursalIdx]?.SucursalNombre || ''"></span>
                                            <span class="text-[11px] text-default-500" x-text="'(' + personalSucursalActual.length + ' personal(es))'"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedCursoIndex < 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-default-50 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1 h-6 rounded-full bg-default-300 shrink-0"></div>
                                        <span class="text-sm text-default-400 italic">Expanda un curso para ver su personal</span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedCursoIndex >= 0">
                                <div class="flex flex-col flex-1 min-h-0">
                                    <div class="flex-1 overflow-auto custom-scrollbar">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                                <tr>
                                                    <th class="px-5 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider w-14">#</th>
                                                    <th @click="ordenar('CodigoPers')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Código
                                                            <span :class="sortColumn === 'CodigoPers' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'CodigoPers' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('NombreCompleto')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Nombre completo
                                                            <span :class="sortColumn === 'NombreCompleto' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'NombreCompleto' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('DNI')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            DNI
                                                            <span :class="sortColumn === 'DNI' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'DNI' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('TipoTrabajador')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Tipo
                                                            <span :class="sortColumn === 'TipoTrabajador' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'TipoTrabajador' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('Cargo')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Cargo
                                                            <span :class="sortColumn === 'Cargo' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Cargo' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('Nota_Final')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Nota final
                                                            <span :class="sortColumn === 'Nota_Final' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Nota_Final' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('Estado')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Estado
                                                            <span :class="sortColumn === 'Estado' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Estado' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white">
                                                <template x-for="(persona, pi) in personalSucursalActualPaginado" :key="pi">
                                                    <tr class="table-row border-b border-default-100"
                                                        :class="pi % 2 === 1 ? 'table-row-even' : ''">
                                                        <td class="px-5 py-2.5 text-center text-default-400 text-xs font-mono" x-text="((currentPage - 1) * perPage) + pi + 1"></td>
                                                        <td class="px-5 py-2.5 text-default-800 font-semibold whitespace-nowrap text-sm" x-text="persona.CodigoPers"></td>
                                                        <td class="px-5 py-2.5 text-default-800 whitespace-nowrap text-sm" x-text="persona.NombreCompleto"></td>
                                                        <td class="px-5 py-2.5 text-default-500 font-mono text-sm" x-text="persona.DNI"></td>
                                                        <td class="px-5 py-2.5 text-default-600 whitespace-nowrap text-sm" x-text="persona.TipoTrabajador"></td>
                                                        <td class="px-5 py-2.5 text-default-600 whitespace-nowrap text-sm" x-text="persona.Cargo"></td>
                                                        <td class="px-5 py-2.5 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border-l-4"
                                                                :class="parseFloat(persona.Nota_Final) >= 10 ? 'bg-green-50 text-green-700 border-l-green-500' : (persona.Nota_Final ? 'bg-red-50 text-red-700 border-l-red-500' : 'bg-gray-50 text-gray-500 border-l-gray-400')">
                                                                <span x-text="persona.Nota_Final || '—'"></span>
                                                            </span>
                                                        </td>
                                                        <td class="px-5 py-2.5 text-center">
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border" :class="{
                                                                        'bg-amber-50 text-amber-700 border-amber-200': persona.Estado === 'PENDIENTE',
                                                                        'bg-green-50 text-green-700 border-green-200': persona.Estado === 'APROBADO',
                                                                        'bg-red-50 text-red-700 border-red-200': persona.Estado === 'DESAPROBADO',
                                                                        'bg-gray-50 text-gray-600 border-gray-200': persona.Estado === 'SIN ACCEDER',
                                                                        'bg-blue-50 text-blue-700 border-blue-200': persona.Estado === 'EN CURSO'
                                                                    }">
                                                                <span class="badge-dot" :class="{
                                                                            'bg-amber-500': persona.Estado === 'PENDIENTE',
                                                                            'bg-green-500': persona.Estado === 'APROBADO',
                                                                            'bg-red-500': persona.Estado === 'DESAPROBADO',
                                                                            'bg-gray-400': persona.Estado === 'SIN ACCEDER',
                                                                            'bg-blue-500': persona.Estado === 'EN CURSO'
                                                                        }"></span>
                                                                <i class="ti text-[11px]" :class="{
                                                                            'ti-clock': persona.Estado === 'PENDIENTE',
                                                                            'ti-circle-check': persona.Estado === 'APROBADO',
                                                                            'ti-circle-x': persona.Estado === 'DESAPROBADO',
                                                                            'ti-player-off': persona.Estado === 'SIN ACCEDER',
                                                                            'ti-player-play': persona.Estado === 'EN CURSO'
                                                                        }"></i>
                                                                <span x-text="persona.Estado"></span>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <template x-if="personalSucursalActual.length === 0">
                                            <div class="flex items-center justify-center py-10 text-default-400 text-sm">
                                                Seleccione una sucursal de la lista.
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Paginación --}}
                                    <div x-show="personalSucursalActual.length > perPage"
                                        class="flex items-center justify-between px-5 py-3 border-t border-default-200 bg-default-50/80 shrink-0">
                                        <div class="text-sm text-default-500">
                                            <span class="font-medium text-default-700" x-text="personalSucursalActual.length"></span> registros
                                            <span class="mx-1.5 text-default-300">·</span>
                                            Pág. <span class="font-medium text-default-700" x-text="currentPage"></span>
                                            de <span class="font-medium text-default-700" x-text="totalPagesSucursal"></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="currentPage--" :disabled="currentPage === 1"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                <i class="ti ti-chevron-left text-xs"></i>
                                                Anterior
                                            </button>
                                            <button type="button" @click="currentPage++" :disabled="currentPage >= totalPagesSucursal"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                Siguiente
                                                <i class="ti ti-chevron-right text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedCursoIndex < 0">
                                <div class="flex-1 flex items-center justify-center text-default-400 text-sm">
                                    <div class="text-center py-10">
                                        <i class="ti ti-folder-open text-3xl mb-2 block"></i>
                                        <span>Seleccione un curso para ver su personal</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer / Botones de acción --}}
            <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
                {{-- Botones modo filtros --}}
                <template x-if="view === 'filters'">
                    <div class="flex items-center justify-end gap-2 w-full">
                        <button type="button" @click="cerrar()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button" @click="obtenerPersonal()"
                            :disabled="courseIds.length === 0"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="ti ti-search text-sm"></i>
                            Obtener reporte
                        </button>
                    </div>
                </template>

                {{-- Botones modo personal --}}
                <template x-if="view === 'personal'">
                    <div class="flex items-center justify-between w-full">
                        <button type="button" @click="volverAFiltros()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 transition-all cursor-pointer">
                            <i class="ti ti-arrow-left text-sm"></i>
                            Atrás
                        </button>
                        {{-- Exportar --}}
                        <div x-show="personal.length > 0" class="flex items-center gap-2">
                            <button type="button" @click="exportarExcel()"
                                :disabled="exportandoExcel || exportandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-spreadsheet text-sm" x-show="!exportandoExcel"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="exportandoExcel"></i>
                                <span x-text="exportandoExcel ? 'Generando Excel...' : 'Exportar Excel'"></span>
                            </button>
                            <button type="button" @click="exportarPDF()"
                                :disabled="exportandoExcel || exportandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-type-pdf text-sm" x-show="!exportandoPDF"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="exportandoPDF"></i>
                                <span x-text="exportandoPDF ? 'Generando PDF...' : 'Exportar PDF'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Reporte de Capacitaciones --}}
    <div id="modal-reporte-de-capacitaciones" x-data="modalReporteDeCapacitaciones" x-show="open" x-cloak
        @keydown.escape.window="cerrar()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div :class="view === 'cursos' ? 'max-w-[95vw]' : 'max-w-xl'"
            class="flex flex-col w-full bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-books text-lg"></i>
                    </div>
                    <div>
                        <div x-show="view === 'filters'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Reporte de capacitaciones
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">Filtre por sistema, área y período</p>
                        </div>
                        <div x-show="view === 'cursos'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Cursos encontrados
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">Listado según los filtros aplicados</p>
                        </div>
                    </div>
                </div>
                <button type="button" @click="cerrar()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <div x-show="view === 'filters'" class="px-6 pt-4 pb-6 space-y-4">
                <div>
                    <label class="text-xs font-medium text-default-700 mb-1.5 block">
                        Sistema de gestión
                    </label>
                    <select x-model="selectedSistema"
                        class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        <option value="">Todos los sistemas</option>
                        <template x-for="option in sistemas" :key="option.codigo">
                            <option :value="option.codigo" x-text="option.descripcion"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-medium text-default-700 mb-1.5 block">
                        Área responsable
                    </label>
                    <select x-model="selectedArea"
                        class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        <option value="">Todas las áreas</option>
                        <template x-for="option in areas" :key="option.codModdle">
                            <option :value="option.codModdle" x-text="option.Area"></option>
                        </template>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-default-700 mb-1.5 block">
                            Fecha de creación (desde) <span class="text-default-400 font-normal">(opcional)</span>
                        </label>
                        <input type="date" x-model="selectedFechaInicio"
                            class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-default-700 mb-1.5 block">
                            Fecha de creación (hasta) <span class="text-default-400 font-normal">(opcional)</span>
                        </label>
                        <input type="date" x-model="selectedFechaFin"
                            class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                    </div>
                </div>
            </div>

            <div x-show="view === 'cursos'" class="px-6 pb-6">

                <template x-if="loadingCursos">
                    <div class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                        <p class="text-sm">Cargando cursos...</p>
                    </div>
                </template>

                <template x-if="!loadingCursos && cursosFilas.length === 0">
                    <div class="text-center py-10 text-default-500 text-sm">
                        No se encontraron cursos con los criterios indicados.
                    </div>
                </template>

                <template x-if="!loadingCursos && cursosFilas.length > 0">
                    <div class="grid grid-rows-[auto_minmax(0,1fr)] grid-cols-[2fr_8fr] max-h-[75vh] min-h-0 border border-default-200 rounded-xl">
                        {{-- Sidebar izquierdo (sistemas + áreas) --}}
                        <div class="w-full shrink-0 border-r border-default-200 overflow-y-auto custom-scrollbar">
                            <div class="px-4 py-3 border-b border-default-100 bg-default-50/50">
                                <span class="text-xs font-semibold text-default-600 uppercase tracking-wider">Sistemas de gestión</span>
                            </div>
                            <template x-for="(sistemaNombre, si) in sistemasEnCursos" :key="si">
                                <div>
                                    <button type="button"
                                        @click="seleccionarSistema(si)"
                                        class="w-full text-left px-4 py-3 border-b border-default-100 transition-colors cursor-pointer"
                                        :class="selectedSistemaIndex === si ? 'bg-primary/5' : 'hover:bg-default-50'">
                                        <div class="flex items-center gap-2.5">
                                            <i class="ti text-xs shrink-0 transition-transform"
                                                :class="selectedSistemaIndex === si ? 'ti-chevron-down text-primary' : 'ti-chevron-right text-default-400'"></i>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-default-900 leading-tight truncate" x-text="sistemaNombre"></p>
                                                <p class="text-xs text-default-500 mt-0.5">
                                                    <span class="font-semibold" x-text="cursosFilas.filter(c => c.Sistema === sistemaNombre).length"></span> curso(s)
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                    <template x-if="selectedSistemaIndex === si">
                                        <div class="border-b border-default-100"
                                            x-transition:enter="transition-all duration-200 ease-out"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition-all duration-150 ease-in"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1">
                                            <template x-for="(areaNombre, ai) in areasDelSistemaActual" :key="ai">
                                                <button type="button"
                                                    @click="seleccionarArea(ai + 1)"
                                                    class="w-full text-left pl-9 pr-4 py-2.5 transition-colors cursor-pointer border-t border-default-100/50"
                                                    :class="selectedAreaIdx === ai + 1 ? 'bg-primary/10 border-l-2 border-l-primary' : 'hover:bg-default-50'"
                                                    x-transition:enter="transition-all duration-300 ease-out"
                                                    x-transition:enter-start="opacity-0 translate-x-[-8px]"
                                                    x-transition:enter-end="opacity-100 translate-x-0"
                                                    :style="`transition-delay: ${ai * 60}ms`">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-1 h-4 rounded-full shrink-0"
                                                            :class="selectedAreaIdx === ai + 1 ? 'bg-primary' : 'bg-default-300'"></div>
                                                        <span class="text-xs font-medium truncate"
                                                            :class="selectedAreaIdx === ai + 1 ? 'text-primary' : 'text-default-600'"
                                                            x-text="areaNombre"></span>
                                                        <span class="text-[10px] text-default-400 ml-auto shrink-0" x-text="cursosFilas.filter(c => c.Area === areaNombre && c.Sistema === sistemaNombre).length + ' curso(s)'"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Tabla de cursos del área seleccionada --}}
                        <div class="flex flex-col min-w-0 overflow-hidden">
                            <template x-if="selectedSistemaIndex >= 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-primary/5 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1 h-6 rounded-full bg-primary/60 shrink-0"></div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <h4 class="text-sm font-bold text-default-800 leading-tight" x-text="sistemasEnCursos[selectedSistemaIndex]"></h4>
                                            <template x-if="selectedAreaIdx > 0">
                                                <span class="text-xs font-semibold text-primary" x-text="'· ' + areasDelSistemaActual[selectedAreaIdx - 1]"></span>
                                            </template>
                                            <span class="text-[11px] text-default-500" x-text="'(' + cursosAreaActual.length + ' curso(s))'"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedSistemaIndex < 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-default-50 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1 h-6 rounded-full bg-default-300 shrink-0"></div>
                                        <span class="text-sm text-default-400 italic">Expanda un sistema para ver sus áreas</span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedSistemaIndex >= 0">
                                <div class="flex flex-col flex-1 min-h-0">
                                    <div class="flex-1 overflow-auto custom-scrollbar">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                                <tr>
                                                    <th class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider w-14">#</th>
                                                    <th @click="ordenar('Nombre')"
                                                        class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider min-w-[14rem]">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Curso
                                                            <span :class="sortColumn === 'Nombre' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Nombre' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider min-w-[16rem]">
                                                        Descripción
                                                    </th>
                                                    <th class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider min-w-[10rem]">
                                                        Área
                                                    </th>
                                                    <th class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider whitespace-nowrap">
                                                        Matriculados
                                                    </th>
                                                    <th @click="ordenar('Responsable')"
                                                        class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider min-w-[10rem]">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Responsable
                                                            <span :class="sortColumn === 'Responsable' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Responsable' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('Fecha_Inicio')"
                                                        class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider whitespace-nowrap">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Fecha inicio
                                                            <span :class="sortColumn === 'Fecha_Inicio' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Fecha_Inicio' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('Fecha_Fin')"
                                                        class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider whitespace-nowrap">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Fecha fin
                                                            <span :class="sortColumn === 'Fecha_Fin' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Fecha_Fin' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenar('Fecha_Creacion')"
                                                        class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider whitespace-nowrap">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Fecha creación
                                                            <span :class="sortColumn === 'Fecha_Creacion' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Fecha_Creacion' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white">
                                                <template x-for="(fila, index) in cursosAreaActualPaginado" :key="fila.id">
                                                    <tr class="table-row border-b border-default-100"
                                                        :class="index % 2 === 1 ? 'table-row-even' : ''">
                                                        <td class="px-4 py-2.5 text-center text-default-400 text-xs font-mono" x-text="((currentPage - 1) * perPage) + index + 1"></td>
                                                        <td class="px-4 py-2.5 text-default-800 font-medium whitespace-nowrap text-sm" x-text="fila.Nombre"></td>
                                                        <td class="px-4 py-2.5 text-default-600 text-sm max-w-[16rem]">
                                                            <span class="line-clamp-2" x-text="fila.Descripcion" :title="fila.Descripcion"></span>
                                                        </td>
                                                        <td class="px-4 py-2.5 text-default-600 text-sm" x-text="fila.Area"></td>
                                                        <td class="px-4 py-2.5 text-default-600 text-center font-medium text-sm" x-text="fila.Total_Matriculados"></td>
                                                        <td class="px-4 py-2.5 text-default-800 text-sm max-w-[14rem]">
                                                            <span class="line-clamp-2" x-text="fila.Responsable" :title="fila.Responsable"></span>
                                                        </td>
                                                        <td class="px-4 py-2.5 text-default-600 whitespace-nowrap text-sm" x-text="fila.Fecha_Inicio"></td>
                                                        <td class="px-4 py-2.5 text-default-600 whitespace-nowrap text-sm" x-text="fila.Fecha_Fin"></td>
                                                        <td class="px-4 py-2.5 text-default-600 whitespace-nowrap text-sm" x-text="fila.Fecha_Creacion"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <template x-if="cursosAreaActual.length === 0">
                                            <div class="flex items-center justify-center py-10 text-default-400 text-sm">
                                                Seleccione un área de la lista.
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Paginación --}}
                                    <div x-show="cursosAreaActual.length > perPage"
                                        class="flex items-center justify-between px-4 py-3 border-t border-default-200 bg-default-50/80 shrink-0">
                                        <div class="text-sm text-default-500">
                                            <span class="font-medium text-default-700" x-text="cursosAreaActual.length"></span> registros
                                            <span class="mx-1.5 text-default-300">·</span>
                                            Pág. <span class="font-medium text-default-700" x-text="currentPage"></span>
                                            de <span class="font-medium text-default-700" x-text="totalPagesArea"></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="currentPage--" :disabled="currentPage === 1"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                <i class="ti ti-chevron-left text-xs"></i>
                                                Anterior
                                            </button>
                                            <button type="button" @click="currentPage++" :disabled="currentPage >= totalPagesArea"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                Siguiente
                                                <i class="ti ti-chevron-right text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedSistemaIndex < 0">
                                <div class="flex-1 flex items-center justify-center text-default-400 text-sm">
                                    <div class="text-center py-10">
                                        <i class="ti ti-folder-open text-3xl mb-2 block"></i>
                                        <span>Seleccione un sistema para ver sus cursos</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Total general --}}
                    <div x-show="cursosFilas.length > 0"
                        class="flex items-center justify-between px-5 py-3 border-t border-default-200 bg-default-50/80 rounded-b-xl">
                        <div class="text-sm text-default-500">
                            Total general: <span class="font-semibold text-default-700" x-text="cursosFilas.length"></span> curso(s)
                            en <span class="font-semibold text-default-700" x-text="sistemasEnCursos.length"></span> sistema(s)
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
                <template x-if="view === 'filters'">
                    <div class="flex items-center justify-end gap-2 w-full">
                        <button type="button" @click="cerrar()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button" @click="obtenerCursos()"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm transition-all cursor-pointer">
                            <i class="ti ti-search text-sm"></i>
                            Obtener reporte
                        </button>
                    </div>
                </template>

                <template x-if="view === 'cursos'">
                    <div class="flex items-center justify-between w-full flex-wrap gap-2">
                        <button type="button" @click="volverAFiltros()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 transition-all cursor-pointer">
                            <i class="ti ti-arrow-left text-sm"></i>
                            Atrás
                        </button>
                        <div x-show="cursosFilas.length > 0 && !loadingCursos" class="flex items-center gap-2">
                            <button type="button" @click="exportarExcelHistorialCursos()"
                                :disabled="exportandoExcel || exportandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-spreadsheet text-sm" x-show="!exportandoExcel"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="exportandoExcel"></i>
                                <span x-text="exportandoExcel ? 'Generando Excel...' : 'Exportar Excel'"></span>
                            </button>
                            <button type="button" @click="exportarPDFHistorialCursos()"
                                :disabled="exportandoExcel || exportandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-type-pdf text-sm" x-show="!exportandoPDF"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="exportandoPDF"></i>
                                <span x-text="exportandoPDF ? 'Generando PDF...' : 'Generar PDF'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Record Histórico de Capacitaciones por Personal --}}
    <div id="modal-record-personal" x-data="modalRecordPersonal" x-show="open" x-cloak
        @keydown.escape.window="cerrar()"
        @dnis-importados.window="aplicarDnisImportados($event.detail)"
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div :class="view === 'filters' ? 'max-w-[1200px]' : 'max-w-[90vw] max-h-[100vh]'"
            class="flex flex-col w-full bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-history text-lg"></i>
                    </div>
                    <div>
                        <div x-show="view === 'filters'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Récord Histórico de Capacitación por personal
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">Seleccione cursos, personal y período para consultar</p>
                        </div>
                        <div x-show="view === 'results'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Resultados del récord
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">
                                <span x-text="totalResultados"></span> personal(es) encontrados
                            </p>
                        </div>
                    </div>
                </div>
                <button type="button" @click="cerrar()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            {{-- Initial loader --}}
            <template x-if="loadingInicial">
                <div class="flex flex-col items-center justify-center py-20 text-default-400">
                    <i class="ti ti-loader animate-spin text-3xl mb-3"></i>
                    <p class="text-sm font-medium">Cargando personal y cursos...</p>
                </div>
            </template>

            {{-- Filters view --}}
            <div x-show="!loadingInicial && view === 'filters'" class="px-6 pt-4 pb-6 overflow-y-auto max-h-[72vh] custom-scrollbar">
                <div class="grid grid-cols-2 gap-6">
                    {{-- Left column: Personal --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-default-200 pb-2">
                            <i class="ti ti-users text-green-500 text-base"></i>
                            <h4 class="text-sm font-semibold text-default-800">Personal</h4>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <select x-model="selectedCliente" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="" x-text="loadingClientes ? 'Cargando...' : 'Todos los clientes'"></option>
                                    <template x-for="c in clientes" :key="c.codigo">
                                        <option :value="c.codigo" x-text="c.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedSucursal" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="" x-text="loadingSucursales ? 'Cargando...' : 'Todas las sucursales'"></option>
                                    <template x-for="s in sucursales" :key="s.codigo">
                                        <option :value="s.codigo" x-text="s.sucursal"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedTipoTrabajador" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los tipos</option>
                                    <template x-for="t in tiposTrabajador" :key="t">
                                        <option :value="t" x-text="t"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedCargo" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los cargos</option>
                                    <template x-for="c in cargos" :key="c">
                                        <option :value="c" x-text="c"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <input type="text" x-model="searchPersonal" @input="filtrarPersonales()" placeholder="Buscar por nombre o DNI..."
                                    class="w-full h-9 pl-8 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-default-500" x-text="selectedUsernames.length + ' personal(es) seleccionado(s)'"></span>
                                    <button type="button"
                                        @click="$dispatch('abrir-importar-dnis', { target: 'record', cursoSeleccionado: selectedCourseIds.length > 0 })"
                                        class="inline-flex items-center gap-1 px-2 h-6 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold hover:bg-emerald-100 transition-colors cursor-pointer shrink-0">
                                        <i class="ti ti-file-spreadsheet text-[11px]"></i>
                                        Importar Excel
                                    </button>
                                </div>
                                <button type="button" @click="toggleAllPersonal()"
                                    class="text-xs text-primary hover:text-primary/80 font-medium transition-colors cursor-pointer shrink-0"
                                    x-text="selectAllPersonal ? 'Deseleccionar todos' : 'Seleccionar todos'">
                                </button>
                            </div>

                            {{-- DNIs importados desde Excel --}}
                            <template x-if="importados.length > 0">
                                <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700">
                                            <i class="ti ti-file-spreadsheet text-xs"></i>
                                            <span x-text="importados.length"></span> importado(s) desde Excel
                                        </span>
                                        <button type="button" @click="quitarImportados()"
                                            class="text-[11px] font-medium text-danger hover:text-danger/80 transition-colors cursor-pointer">
                                            Quitar
                                        </button>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap gap-1 max-h-14 overflow-y-auto custom-scrollbar">
                                        <template x-for="imp in importados" :key="'importado-record-' + imp.dni">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white border border-default-200 text-[10px] text-default-600"
                                                :title="(imp.nombre_completo || '') + ' · ' + imp.dni">
                                                <span class="font-mono" x-text="imp.dni"></span>
                                                <span class="text-default-400 truncate max-w-[90px]" x-text="imp.nombre_completo || ''"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="border border-default-200 rounded-lg max-h-64 overflow-y-auto custom-scrollbar">
                                <template x-if="loadingPersonal">
                                    <div class="flex items-center justify-center py-6 text-default-400">
                                        <i class="ti ti-loader animate-spin text-lg mr-2"></i>
                                        <span class="text-sm">Cargando personal...</span>
                                    </div>
                                </template>
                                <template x-if="!loadingPersonal && personalesFiltrados.length === 0">
                                    <div class="text-center py-6 text-default-400 text-sm">
                                        No se encontró personal con los filtros seleccionados.
                                    </div>
                                </template>
                                <template x-for="p in personalesPaginados()" :key="p.dni">
                                    <label class="flex items-center gap-3 px-3 py-2 hover:bg-default-50 cursor-pointer border-b border-default-100 last:border-b-0 transition-colors">
                                        <input type="checkbox" :value="p.dni"
                                            @change="togglePersonal(p.dni)"
                                            :checked="selectedUsernames.includes(p.dni)"
                                            class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer shrink-0">
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium text-default-800 truncate" x-text="p.nombre_completo"></span>
                                        </div>
                                    </label>
                                </template>
                            </div>

                            {{-- Paginación Personal --}}
                            <template x-if="personalesFiltrados.length > personalPerPage">
                                <div class="flex items-center justify-between pt-2.5">
                                    <span class="text-xs text-default-400">
                                        Pág. <span x-text="personalPage"></span> de <span x-text="personalTotalPages"></span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        {{-- Anterior --}}
                                        <button type="button"
                                            @click="personalPage = Math.max(1, personalPage - 1)"
                                            :disabled="personalPage <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-left text-xs"></i>
                                        </button>

                                        {{-- Página 1 siempre visible --}}
                                        <button type="button" @click="personalPage = 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                            :class="personalPage === 1 ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'">
                                            1
                                        </button>

                                        {{-- Elipsis izquierda --}}
                                        <template x-if="personalPage > 3">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Página previa a la actual (si no es 1 ni última) --}}
                                        <template x-if="personalPage > 2 && personalPage < personalTotalPages">
                                            <button type="button" @click="personalPage = personalPage - 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="personalPage - 1">
                                            </button>
                                        </template>

                                        {{-- Página actual (si no es 1 ni última) --}}
                                        <template x-if="personalPage !== 1 && personalPage !== personalTotalPages">
                                            <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border bg-primary text-white border-primary shadow-sm text-xs font-medium cursor-default"
                                                x-text="personalPage">
                                            </button>
                                        </template>

                                        {{-- Página siguiente a la actual (si no es 1 ni última) --}}
                                        <template x-if="personalPage < personalTotalPages - 1 && personalPage !== 1">
                                            <button type="button" @click="personalPage = personalPage + 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="personalPage + 1">
                                            </button>
                                        </template>

                                        {{-- Elipsis derecha --}}
                                        <template x-if="personalPage < personalTotalPages - 2">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Última página siempre visible (si hay más de 1) --}}
                                        <template x-if="personalTotalPages > 1">
                                            <button type="button" @click="personalPage = personalTotalPages"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                                :class="personalPage === personalTotalPages ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'"
                                                x-text="personalTotalPages">
                                            </button>
                                        </template>

                                        {{-- Siguiente --}}
                                        <button type="button"
                                            @click="personalPage = Math.min(personalTotalPages, personalPage + 1)"
                                            :disabled="personalPage >= personalTotalPages"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Right column: Cursos --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-default-200 pb-2">
                            <i class="ti ti-book text-primary text-base"></i>
                            <h4 class="text-sm font-semibold text-default-800">Cursos</h4>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <select x-model="selectedSistema" @change="onSistemaChange()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="" x-text="loadingSistemas ? 'Cargando sistemas...' : 'Todos los sistemas'"></option>
                                    <template x-for="option in sistemas" :key="option.codigo">
                                        <option :value="option.codigo" x-text="option.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedArea" @change="onAreaChange()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="" x-text="loadingAreas ? 'Cargando áreas...' : 'Todas las áreas'"></option>
                                    <template x-for="option in areas" :key="option.codModdle">
                                        <option :value="option.codModdle" x-text="option.Area"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Fecha de creación desde
                                </label>
                                <input type="date" x-model="selectedFechaDesde" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Fecha de creación hasta
                                </label>
                                <input type="date" x-model="selectedFechaHasta" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Año del curso
                                </label>
                                <select x-model="selectedAnio" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los años</option>
                                    <template x-for="anio in aniosDisponibles" :key="anio">
                                        <option :value="anio" x-text="anio"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <select x-model="selectedEstadoId"
                                class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                <option value="0">Todos los estados</option>
                                <option value="1">Aprobados</option>
                                <option value="2">Desaprobados</option>
                                <option value="3">Sin acceder</option>
                                <option value="4">En curso</option>
                            </select>
                        </div>

                        <div>
                            <div class="relative">
                                <input type="text" x-model="searchCurso" @input="filtrarCursos()" placeholder="Buscar curso por nombre..."
                                    class="w-full h-9 pl-8 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-default-500" x-text="selectedCourseIds.length + ' curso(s) seleccionado(s)'"></span>
                                <button type="button" @click="toggleAllCursos()"
                                    class="text-xs text-primary hover:text-primary/80 font-medium transition-colors cursor-pointer"
                                    x-text="selectAllCursos ? 'Deseleccionar todos' : 'Seleccionar todos'">
                                </button>
                            </div>
                            <div class="border border-default-200 rounded-lg max-h-64 overflow-y-auto custom-scrollbar">
                                <template x-if="loadingCursos">
                                    <div class="flex items-center justify-center py-6 text-default-400">
                                        <i class="ti ti-loader animate-spin text-lg mr-2"></i>
                                        <span class="text-sm">Cargando cursos...</span>
                                    </div>
                                </template>
                                <template x-if="!loadingCursos && cursos.length === 0">
                                    <div class="text-center py-6 text-default-400 text-sm">
                                        No hay cursos disponibles.
                                    </div>
                                </template>
                                <template x-for="curso in cursosPaginados()" :key="curso.Id">
                                    <label class="flex items-center gap-3 px-3 py-2 hover:bg-default-50 cursor-pointer border-b border-default-100 last:border-b-0 transition-colors">
                                        <input type="checkbox" :value="curso.Id"
                                            @change="toggleCurso(curso.Id)"
                                            :checked="selectedCourseIds.includes(curso.Id)"
                                            class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer shrink-0">
                                        <span class="text-sm text-default-700 leading-tight" x-text="curso.Nombre"></span>
                                    </label>
                                </template>
                            </div>

                            {{-- Paginación Cursos --}}
                            <template x-if="cursos.length > cursosPerPage">
                                <div class="flex items-center justify-between pt-2.5">
                                    <span class="text-xs text-default-400">
                                        Pág. <span x-text="cursosPage"></span> de <span x-text="cursosTotalPages"></span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        {{-- Anterior --}}
                                        <button type="button"
                                            @click="cursosPage = Math.max(1, cursosPage - 1)"
                                            :disabled="cursosPage <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-left text-xs"></i>
                                        </button>

                                        {{-- Página 1 siempre visible --}}
                                        <button type="button" @click="cursosPage = 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                            :class="cursosPage === 1 ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'">
                                            1
                                        </button>

                                        {{-- Elipsis izquierda --}}
                                        <template x-if="cursosPage > 3">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Página previa a la actual (si no es 1 ni última) --}}
                                        <template x-if="cursosPage > 2 && cursosPage < cursosTotalPages">
                                            <button type="button" @click="cursosPage = cursosPage - 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="cursosPage - 1">
                                            </button>
                                        </template>

                                        {{-- Página actual (si no es 1 ni última) --}}
                                        <template x-if="cursosPage !== 1 && cursosPage !== cursosTotalPages">
                                            <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border bg-primary text-white border-primary shadow-sm text-xs font-medium cursor-default"
                                                x-text="cursosPage">
                                            </button>
                                        </template>

                                        {{-- Página siguiente a la actual (si no es 1 ni última) --}}
                                        <template x-if="cursosPage < cursosTotalPages - 1 && cursosPage !== 1">
                                            <button type="button" @click="cursosPage = cursosPage + 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="cursosPage + 1">
                                            </button>
                                        </template>

                                        {{-- Elipsis derecha --}}
                                        <template x-if="cursosPage < cursosTotalPages - 2">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Última página siempre visible (si hay más de 1) --}}
                                        <template x-if="cursosTotalPages > 1">
                                            <button type="button" @click="cursosPage = cursosTotalPages"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                                :class="cursosPage === cursosTotalPages ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'"
                                                x-text="cursosTotalPages">
                                            </button>
                                        </template>

                                        {{-- Siguiente --}}
                                        <button type="button"
                                            @click="cursosPage = Math.min(cursosTotalPages, cursosPage + 1)"
                                            :disabled="cursosPage >= cursosTotalPages"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Results view --}}
            <div x-show="view === 'results'" class="px-6 pt-4 pb-6 flex-1 overflow-hidden min-h-0">

                <template x-if="buscando">
                    <div class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                        <p class="text-sm">Consultando récord de capacitaciones...</p>
                    </div>
                </template>

                <template x-if="!buscando && resultados.length === 0">
                    <div class="text-center py-10 text-default-500 text-sm">
                        No se encontraron resultados para los criterios seleccionados.
                    </div>
                </template>

                <template x-if="!buscando && resultados.length > 0">
                    <div class="grid grid-rows-[auto_minmax(0,1fr)] grid-cols-[2fr_8fr] max-h-[600px] min-h-0 border border-default-200 rounded-xl">
                        {{-- Sidebar izquierdo (personal) --}}
                        <div class="w-full shrink-0 border-r border-default-200 overflow-y-auto custom-scrollbar">
                            <div class="px-4 py-3 border-b border-default-100 bg-default-50/50">
                                <span class="text-xs font-semibold text-default-600 uppercase tracking-wider">Personal(es) seleccionado(s)</span>
                            </div>
                            <template x-for="(personal, pi) in resultados" :key="pi">
                                <button type="button"
                                    @click="seleccionarPersonal(pi)"
                                    :title="personal.NombreCompleto"
                                    class="w-full text-left px-4 py-3 border-b border-default-100 transition-colors cursor-pointer"
                                    :class="selectedPersonalIdx === pi ? 'bg-primary/5' : 'hover:bg-default-50'">
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-1 h-8 rounded-full shrink-0 mt-0.5"
                                            :class="selectedPersonalIdx === pi ? 'bg-primary' : 'bg-default-300'"></div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-default-900 leading-tight truncate" x-text="personal.NombreCompleto"></p>
                                            <p class="text-[10px] text-default-400 mt-0.5">
                                                <span class="font-medium text-default-500" x-text="personal.NroDoc"></span>
                                                <span> · </span>
                                                <span class="font-medium text-default-500" x-text="personal.CodigoPersonal"></span>
                                            </p>
                                            <p class="text-[10px] text-default-500 mt-0.5 truncate">
                                                <i class="ti ti-briefcase text-[9px] mr-0.5"></i><span x-text="personal.Cargo || '—'"></span>
                                            </p>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-default-100 text-default-600 leading-tight">
                                                    <i class="ti ti-building-community text-[8px]"></i>
                                                    <span x-text="personal.Sucursal || '—'"></span>
                                                </span>
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-default-100 text-default-600 leading-tight">
                                                    <i class="ti ti-tag text-[8px]"></i>
                                                    <span x-text="personal.TipoTrabajador || '—'"></span>
                                                </span>
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-medium bg-default-100 text-default-600 leading-tight">
                                                    <i class="ti ti-user-check text-[8px]"></i>
                                                    <span x-text="(personal.Cursos ? personal.Cursos.length : 0) + ' curso(s)'"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>

                        {{-- Tabla de cursos del personal seleccionado --}}
                        <div class="flex flex-col min-w-0 overflow-hidden">
                            <template x-if="selectedPersonalIdx >= 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-primary/5 to-transparent">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-1 h-6 rounded-full bg-primary/60 shrink-0"></div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <h4 class="text-sm font-bold text-default-800 leading-tight truncate" x-text="resultados[selectedPersonalIdx]?.NombreCompleto"></h4>
                                                    <span class="text-xs text-default-400">·</span>
                                                    <span class="text-xs font-semibold text-primary whitespace-nowrap" x-text="resultados[selectedPersonalIdx]?.NroDoc"></span>
                                                    <span class="text-[11px] text-default-400 whitespace-nowrap" x-text="'(' + cursosPersonalActual.length + ' curso(s))'"></span>
                                                </div>
                                                <div class="flex flex-wrap gap-1.5 mt-1">
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-default-100/80 text-default-600 leading-tight">
                                                        <i class="ti ti-briefcase text-[9px]"></i>
                                                        <span x-text="resultados[selectedPersonalIdx]?.Cargo || '—'"></span>
                                                    </span>
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-default-100/80 text-default-600 leading-tight">
                                                        <i class="ti ti-building-community text-[9px]"></i>
                                                        <span x-text="resultados[selectedPersonalIdx]?.Sucursal || '—'"></span>
                                                    </span>
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-default-100/80 text-default-600 leading-tight">
                                                        <i class="ti ti-tag text-[9px]"></i>
                                                        <span x-text="resultados[selectedPersonalIdx]?.TipoTrabajador || '—'"></span>
                                                    </span>
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-default-100/80 text-default-600 leading-tight">
                                                        <i class="ti ti-mail text-[9px]"></i>
                                                        <span class="truncate max-w-[180px]" x-text="resultados[selectedPersonalIdx]?.Correo || '—'"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold bg-primary/10 text-primary whitespace-nowrap shrink-0">
                                            <span x-text="resultados[selectedPersonalIdx]?.CodigoPersonal"></span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedPersonalIdx < 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-default-50 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1 h-6 rounded-full bg-default-300 shrink-0"></div>
                                        <span class="text-sm text-default-400 italic">Seleccione un personal de la lista para ver sus cursos</span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedPersonalIdx >= 0">
                                <div class="flex flex-col flex-1 min-h-0">
                                    <div class="flex-1 overflow-auto custom-scrollbar">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                                <tr>
                                                    <th class="px-5 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider w-14">#</th>
                                                    <th @click="ordenarCursos('Nombre')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Curso
                                                            <span :class="sortColumn === 'Nombre' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Nombre' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenarCursos('Nota_Final')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Nota final
                                                            <span :class="sortColumn === 'Nota_Final' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Nota_Final' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenarCursos('Estado')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Estado
                                                            <span :class="sortColumn === 'Estado' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Estado' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenarCursos('Fecha_Acceso')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-primary transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Fecha / Últ. Acceso
                                                            <span :class="sortColumn === 'Fecha_Acceso' ? 'text-primary' : 'text-default-300'"
                                                                x-text="sortColumn === 'Fecha_Acceso' ? (sortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white">
                                                <template x-for="(curso, ci) in cursosPersonalActualPaginado" :key="ci">
                                                    <tr class="table-row border-b border-default-100"
                                                        :class="ci % 2 === 1 ? 'table-row-even' : ''">
                                                        <td class="px-5 py-2.5 text-center text-default-400 text-xs font-mono" x-text="((cursosPage - 1) * cursosPerPage) + ci + 1"></td>
                                                        <td class="px-5 py-2.5 text-default-800 font-medium text-sm" x-text="curso.Nombre"></td>
                                                        <td class="px-5 py-2.5 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border-l-4"
                                                                :class="parseFloat(curso.Nota_Final) >= 10 ? 'bg-green-50 text-green-700 border-l-green-500' : (curso.Nota_Final != null && curso.Nota_Final !== 'Sin nota' ? 'bg-red-50 text-red-700 border-l-red-500' : 'bg-gray-50 text-gray-500 border-l-gray-400')">
                                                                <span x-text="curso.Nota_Final || '—'"></span>
                                                            </span>
                                                        </td>
                                                        <td class="px-5 py-2.5 text-center">
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border" :class="{
                                                                    'bg-amber-50 text-amber-700 border-amber-200': curso.Estado === 'PENDIENTE',
                                                                    'bg-green-50 text-green-700 border-green-200': curso.Estado === 'APROBADO',
                                                                    'bg-red-50 text-red-700 border-red-200': curso.Estado === 'DESAPROBADO',
                                                                    'bg-gray-50 text-gray-600 border-gray-200': curso.Estado === 'SIN ACCEDER' || curso.Estado === 'SIN NOTA',
                                                                    'bg-blue-50 text-blue-700 border-blue-200': curso.Estado === 'EN CURSO'
                                                                }">
                                                                <span class="badge-dot" :class="{
                                                                        'bg-amber-500': curso.Estado === 'PENDIENTE',
                                                                        'bg-green-500': curso.Estado === 'APROBADO',
                                                                        'bg-red-500': curso.Estado === 'DESAPROBADO',
                                                                        'bg-gray-400': curso.Estado === 'SIN ACCEDER' || curso.Estado === 'SIN NOTA',
                                                                        'bg-blue-500': curso.Estado === 'EN CURSO'
                                                                    }"></span>
                                                                <i class="ti text-[11px]" :class="{
                                                                        'ti-clock': curso.Estado === 'PENDIENTE',
                                                                        'ti-circle-check': curso.Estado === 'APROBADO',
                                                                        'ti-circle-x': curso.Estado === 'DESAPROBADO',
                                                                        'ti-player-off': curso.Estado === 'SIN ACCEDER' || curso.Estado === 'SIN NOTA',
                                                                        'ti-player-play': curso.Estado === 'EN CURSO'
                                                                    }"></i>
                                                                <span x-text="curso.Estado"></span>
                                                            </span>
                                                        </td>
                                                        <td class="px-5 py-2.5 text-center text-xs text-default-500 font-mono whitespace-nowrap">
                                                            <span x-text="curso.Fecha_Nota ? (curso.Fecha_Nota.includes(' ') ? curso.Fecha_Nota.split(' ')[0].split('-').reverse().join('/') + ' ' + curso.Fecha_Nota.split(' ')[1].slice(0,5) : curso.Fecha_Nota) : (curso.Fecha_Ultimo_Acceso ? (curso.Fecha_Ultimo_Acceso.includes(' ') ? curso.Fecha_Ultimo_Acceso.split(' ')[0].split('-').reverse().join('/') + ' ' + curso.Fecha_Ultimo_Acceso.split(' ')[1].slice(0,5) : curso.Fecha_Ultimo_Acceso) : '—')"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <template x-if="cursosPersonalActual.length === 0">
                                            <div class="flex items-center justify-center py-10 text-default-400 text-sm">
                                                Este personal no tiene cursos registrados.
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Paginación de cursos --}}
                                    <div x-show="cursosPersonalActual.length > cursosPerPage"
                                        class="flex items-center justify-between px-5 py-3 border-t border-default-200 bg-default-50/80 shrink-0">
                                        <div class="text-sm text-default-500">
                                            <span class="font-medium text-default-700" x-text="cursosPersonalActual.length"></span> cursos
                                            <span class="mx-1.5 text-default-300">·</span>
                                            Pág. <span class="font-medium text-default-700" x-text="cursosPage"></span>
                                            de <span class="font-medium text-default-700" x-text="totalPagesCursosPersonal"></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="cursosPage--" :disabled="cursosPage === 1"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                <i class="ti ti-chevron-left text-xs"></i>
                                                Anterior
                                            </button>
                                            <button type="button" @click="cursosPage++" :disabled="cursosPage >= totalPagesCursosPersonal"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                Siguiente
                                                <i class="ti ti-chevron-right text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedPersonalIdx < 0">
                                <div class="flex-1 flex items-center justify-center text-default-400 text-sm">
                                    <div class="text-center py-10">
                                        <i class="ti ti-user-search text-3xl mb-2 block"></i>
                                        <span>Seleccione un personal para ver sus cursos</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
                <template x-if="view === 'filters'">
                    <div class="flex items-center justify-end gap-2 w-full">
                        <button type="button" @click="cerrar()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button" @click="obtenerPersonalRecord()"
                            :disabled="buscando || selectedCourseIds.length === 0 || selectedUsernames.length === 0"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="ti ti-search text-sm" x-show="!buscando"></i>
                            <i class="ti ti-loader animate-spin text-sm" x-show="buscando"></i>
                            <span x-text="buscando ? 'Buscando...' : 'Obtener récord'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="view === 'results'">
                    <div class="flex items-center justify-between w-full">
                        <button type="button" @click="volverAFiltros()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 transition-all cursor-pointer">
                            <i class="ti ti-arrow-left text-sm"></i>
                            Atrás
                        </button>
                        <div x-show="resultados.length > 0 && !buscando" class="flex items-center gap-2">
                            <span class="text-sm text-default-500 mr-2">
                                <span x-text="resultados.length"></span> personal(es) · <span x-text="resultados.reduce((acc, p) => acc + (p.Cursos ? p.Cursos.length : 0), 0)"></span> registro(s) de cursos
                            </span>
                            <button type="button" @click="exportarExcelRecord()"
                                :disabled="generandoExcel"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-spreadsheet text-sm" x-show="!generandoExcel"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="generandoExcel"></i>
                                <span x-text="generandoExcel ? 'Generando Excel...' : 'Exportar Excel'"></span>
                            </button>
                            <button type="button" @click="exportarPDFRecord()"
                                :disabled="generandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-type-pdf text-sm" x-show="!generandoPDF"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="generandoPDF"></i>
                                <span x-text="generandoPDF ? 'Generando PDF...' : 'Exportar PDF'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Reporte General de Capacitaciones --}}
    <div id="modal-reporte-general" x-data="modalReporteGeneral" x-show="open" x-cloak
        @keydown.escape.window="cerrar()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div :class="view === 'results' ? 'max-w-[90vw] max-h-[100vh]' : 'max-w-4xl'" class="flex flex-col w-full bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-10 h-10 rounded-xl bg-violet-500 flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-report-analytics text-lg"></i>
                    </div>
                    <div>
                        <div x-show="view === 'filters'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Reporte General de Capacitaciones
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">Filtros avanzados para generar reportes consolidados</p>
                        </div>
                        <div x-show="view === 'results'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Resultados del reporte general
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">
                                <span x-text="resultados.length"></span> personal(es) encontrados
                            </p>
                        </div>
                    </div>
                </div>
                <button type="button" @click="cerrar()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <template x-if="loadingInicial">
                <div class="flex flex-col items-center justify-center py-20 text-default-400">
                    <i class="ti ti-loader animate-spin text-3xl mb-3"></i>
                    <p class="text-sm font-medium">Cargando personal y cursos...</p>
                </div>
            </template>

            <div x-show="!loadingInicial && view === 'filters'" class="px-6 pt-4 pb-6 overflow-y-auto max-h-[72vh] custom-scrollbar">
                <div class="grid grid-cols-2 gap-6">
                    {{-- Left column: Personal --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-default-200 pb-2">
                            <i class="ti ti-users text-violet-500 text-base"></i>
                            <h4 class="text-sm font-semibold text-default-800">Personal</h4>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <select x-model="selectedCliente" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="" x-text="loadingClientes ? 'Cargando...' : 'Todos los clientes'"></option>
                                    <template x-for="c in clientes" :key="c.codigo">
                                        <option :value="c.codigo" x-text="c.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedEmpresa" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todas las empresas</option>
                                    <option value="__sin_empresa__">Sin empresa</option>
                                    <template x-for="e in empresas" :key="e.codigo">
                                        <option :value="e.codigo" x-text="e.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedSucursal" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todas las sucursales</option>
                                    <template x-for="s in sucursales" :key="s.codigo">
                                        <option :value="s.codigo" x-text="s.sucursal"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedTipoTrabajo" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los tipos</option>
                                    <template x-for="t in tiposTrabajo" :key="t">
                                        <option :value="t" x-text="t"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedCargo" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los cargos</option>
                                    <template x-for="c in cargos" :key="c">
                                        <option :value="c" x-text="c"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <input type="text" x-model="searchPersonal" @input="filtrarPersonales()" placeholder="Buscar por nombre o DNI..."
                                    class="w-full h-9 pl-8 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-default-500" x-text="selectedUsernames.length + ' personal(es) seleccionado(s)'"></span>
                                <button type="button" @click="toggleAllPersonal()"
                                    class="text-xs text-primary hover:text-primary/80 font-medium transition-colors cursor-pointer"
                                    x-text="selectAllPersonal ? 'Deseleccionar todos' : 'Seleccionar todos'">
                                </button>
                            </div>
                            <div class="border border-default-200 rounded-lg max-h-64 overflow-y-auto custom-scrollbar">
                                <template x-if="personalesFiltrados.length === 0">
                                    <div class="text-center py-6 text-default-400 text-sm">
                                        No se encontró personal con los filtros seleccionados.
                                    </div>
                                </template>
                                <template x-for="p in personalesPaginados()" :key="p.dni">
                                    <label class="flex items-center gap-3 px-3 py-2 hover:bg-default-50 cursor-pointer border-b border-default-100 last:border-b-0 transition-colors">
                                        <input type="checkbox" :value="p.dni"
                                            @change="togglePersonal(p.dni)"
                                            :checked="selectedUsernames.includes(p.dni)"
                                            class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer shrink-0">
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium text-default-800 truncate" x-text="p.nombre_completo"></span>
                                            <span class="text-xs text-default-400 truncate" x-text="p.cargo ? p.cargo + (p.empresa ? ' - ' + p.empresa : '') : (p.empresa || '')"></span>
                                        </div>
                                    </label>
                                </template>
                            </div>

                            {{-- Paginación Personal --}}
                            <template x-if="personalesFiltrados.length > personalPerPage">
                                <div class="flex items-center justify-between pt-2.5">
                                    <span class="text-xs text-default-400">
                                        Pág. <span x-text="personalPage"></span> de <span x-text="personalTotalPages"></span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button type="button"
                                            @click="personalPage = Math.max(1, personalPage - 1)"
                                            :disabled="personalPage <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-left text-xs"></i>
                                        </button>
                                        <button type="button" @click="personalPage = 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                            :class="personalPage === 1 ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'">
                                            1
                                        </button>
                                        <template x-if="personalPage > 3">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>
                                        <template x-if="personalPage > 2 && personalPage < personalTotalPages">
                                            <button type="button" @click="personalPage = personalPage - 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="personalPage - 1">
                                            </button>
                                        </template>
                                        <template x-if="personalPage !== 1 && personalPage !== personalTotalPages">
                                            <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border bg-primary text-white border-primary shadow-sm text-xs font-medium cursor-default"
                                                x-text="personalPage">
                                            </button>
                                        </template>
                                        <template x-if="personalPage < personalTotalPages - 1 && personalPage !== 1">
                                            <button type="button" @click="personalPage = personalPage + 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="personalPage + 1">
                                            </button>
                                        </template>
                                        <template x-if="personalPage < personalTotalPages - 2">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>
                                        <template x-if="personalTotalPages > 1">
                                            <button type="button" @click="personalPage = personalTotalPages"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                                :class="personalPage === personalTotalPages ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'"
                                                x-text="personalTotalPages">
                                            </button>
                                        </template>
                                        <button type="button"
                                            @click="personalPage = Math.min(personalTotalPages, personalPage + 1)"
                                            :disabled="personalPage >= personalTotalPages"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Right column: Cursos --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-default-200 pb-2">
                            <i class="ti ti-book text-violet-500 text-base"></i>
                            <h4 class="text-sm font-semibold text-default-800">Cursos</h4>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Fecha de creación desde
                                </label>
                                <input type="date" x-model="selectedFechaDesde" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Fecha de creación hasta
                                </label>
                                <input type="date" x-model="selectedFechaHasta" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <select x-model="selectedEstado" @change="filtrarCursos()"
                                class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                <option value="">Todos los estados</option>
                                <option value="APROBADO">Aprobado</option>
                                <option value="DESAPROBADO">Desaprobado</option>
                                <option value="SIN ACCEDER">Sin acceder</option>
                                <option value="EN CURSO">En curso</option>
                            </select>
                        </div>

                        <div>
                            <div class="relative">
                                <input type="text" x-model="searchCurso" @input="filtrarCursos()" placeholder="Buscar curso por nombre..."
                                    class="w-full h-9 pl-8 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-default-500" x-text="selectedCourseIds.length + ' curso(s) seleccionado(s)'"></span>
                                <button type="button" @click="toggleAllCursos()"
                                    class="text-xs text-primary hover:text-primary/80 font-medium transition-colors cursor-pointer"
                                    x-text="selectAllCursos ? 'Deseleccionar todos' : 'Seleccionar todos'">
                                </button>
                            </div>
                            <div class="border border-default-200 rounded-lg max-h-64 overflow-y-auto custom-scrollbar">
                                <template x-if="cursos.length === 0">
                                    <div class="text-center py-6 text-default-400 text-sm">
                                        No hay cursos disponibles.
                                    </div>
                                </template>
                                <template x-for="curso in cursosPaginados()" :key="curso.Id">
                                    <label class="flex items-center gap-3 px-3 py-2 hover:bg-default-50 cursor-pointer border-b border-default-100 last:border-b-0 transition-colors">
                                        <input type="checkbox" :value="curso.Id"
                                            @change="toggleCurso(curso.Id)"
                                            :checked="selectedCourseIds.includes(curso.Id)"
                                            class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer shrink-0">
                                        <span class="text-sm text-default-700 leading-tight" x-text="curso.Nombre"></span>
                                    </label>
                                </template>
                            </div>

                            {{-- Paginación Cursos --}}
                            <template x-if="cursos.length > cursosPerPage">
                                <div class="flex items-center justify-between pt-2.5">
                                    <span class="text-xs text-default-400">
                                        Pág. <span x-text="cursosPage"></span> de <span x-text="cursosTotalPages"></span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <button type="button"
                                            @click="cursosPage = Math.max(1, cursosPage - 1)"
                                            :disabled="cursosPage <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-left text-xs"></i>
                                        </button>
                                        <button type="button" @click="cursosPage = 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                            :class="cursosPage === 1 ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'">
                                            1
                                        </button>
                                        <template x-if="cursosPage > 3">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>
                                        <template x-if="cursosPage > 2 && cursosPage < cursosTotalPages">
                                            <button type="button" @click="cursosPage = cursosPage - 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="cursosPage - 1">
                                            </button>
                                        </template>
                                        <template x-if="cursosPage !== 1 && cursosPage !== cursosTotalPages">
                                            <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border bg-primary text-white border-primary shadow-sm text-xs font-medium cursor-default"
                                                x-text="cursosPage">
                                            </button>
                                        </template>
                                        <template x-if="cursosPage < cursosTotalPages - 1 && cursosPage !== 1">
                                            <button type="button" @click="cursosPage = cursosPage + 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="cursosPage + 1">
                                            </button>
                                        </template>
                                        <template x-if="cursosPage < cursosTotalPages - 2">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>
                                        <template x-if="cursosTotalPages > 1">
                                            <button type="button" @click="cursosPage = cursosTotalPages"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                                :class="cursosPage === cursosTotalPages ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'"
                                                x-text="cursosTotalPages">
                                            </button>
                                        </template>
                                        <button type="button"
                                            @click="cursosPage = Math.min(cursosTotalPages, cursosPage + 1)"
                                            :disabled="cursosPage >= cursosTotalPages"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Results view --}}
            <div x-show="view === 'results'" class="px-6 pt-4 pb-6 flex-1 overflow-hidden min-h-0">

                <template x-if="buscando">
                    <div class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                        <p class="text-sm">Consultando reporte general...</p>
                    </div>
                </template>

                <template x-if="!buscando && resultados.length === 0">
                    <div class="text-center py-10 text-default-500 text-sm">
                        No se encontraron resultados para los criterios seleccionados.
                    </div>
                </template>

                <template x-if="!buscando && resultados.length > 0">
                    <div class="grid grid-rows-[auto_minmax(0,1fr)] grid-cols-[2fr_8fr] max-h-[600px] min-h-0 border border-default-200 rounded-xl">
                        {{-- Sidebar izquierdo (Sucursales > Personal) --}}
                        <div class="w-full shrink-0 border-r border-default-200 overflow-y-auto custom-scrollbar">
                            <div class="px-4 py-3 border-b border-default-100 bg-default-50/50">
                                <span class="text-xs font-semibold text-default-600 uppercase tracking-wider">Sucursales</span>
                            </div>
                            <template x-for="(sucursalNombre, si) in sucursalesEnResultados" :key="si">
                                <div>
                                    <button type="button"
                                        @click="seleccionarSucursal(si)"
                                        class="w-full text-left px-4 py-3 border-b border-default-100 transition-colors cursor-pointer"
                                        :class="selectedSucursalIdx === si ? 'bg-violet-50' : 'hover:bg-default-50'">
                                        <div class="flex items-center gap-2.5">
                                            <i class="ti text-xs shrink-0 transition-transform"
                                                :class="selectedSucursalIdx === si ? 'ti-chevron-down text-violet-500' : 'ti-chevron-right text-default-400'"></i>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-default-900 leading-tight truncate" x-text="sucursalNombre"></p>
                                                <p class="text-xs text-default-500 mt-0.5">
                                                    <span class="font-semibold" x-text="resultados.filter(p => p.Sucursal === sucursalNombre).length"></span> personal(es)
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                    {{-- Personal list under selected sucursal --}}
                                    <template x-if="selectedSucursalIdx === si">
                                        <div class="border-b border-default-100"
                                            x-transition:enter="transition-all duration-200 ease-out"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="transition-all duration-150 ease-in"
                                            x-transition:leave-start="opacity-100 translate-y-0"
                                            x-transition:leave-end="opacity-0 -translate-y-1">
                                            <template x-for="(personal, pi) in personalesDeSucursalActual" :key="pi">
                                                <button type="button"
                                                    @click="seleccionarPersonal(pi)"
                                                    class="w-full text-left pl-9 pr-4 py-2.5 transition-colors cursor-pointer border-t border-default-100/50"
                                                    :class="selectedPersonalIdx === pi ? 'bg-primary/10 border-l-2 border-l-primary' : 'hover:bg-default-50'"
                                                    x-transition:enter="transition-all duration-300 ease-out"
                                                    x-transition:enter-start="opacity-0 translate-x-[-8px]"
                                                    x-transition:enter-end="opacity-100 translate-x-0"
                                                    :style="`transition-delay: ${pi * 60}ms`">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-1 h-4 rounded-full shrink-0"
                                                            :class="selectedPersonalIdx === pi ? 'bg-primary' : 'bg-default-300'"></div>
                                                        <span class="text-xs font-medium truncate"
                                                            :class="selectedPersonalIdx === pi ? 'text-primary' : 'text-default-600'"
                                                            x-text="personal.NombreCompleto"></span>
                                                        <span class="text-[10px] text-default-400 ml-auto shrink-0" x-text="(personal.Cursos ? personal.Cursos.length : 0) + ' curso(s)'"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Right: Cursos table for selected personal --}}
                        <div class="flex flex-col min-w-0 overflow-hidden">
                            <template x-if="selectedPersonalIdx >= 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-violet-50 to-transparent">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="w-1 h-6 rounded-full bg-violet-500/60 shrink-0"></div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <h4 class="text-sm font-bold text-default-800 leading-tight truncate" x-text="personalesDeSucursalActual[selectedPersonalIdx]?.NombreCompleto"></h4>
                                                    <span class="text-xs text-default-400">·</span>
                                                    <span class="text-xs font-semibold text-violet-600 whitespace-nowrap" x-text="personalesDeSucursalActual[selectedPersonalIdx]?.NroDoc"></span>
                                                    <span class="text-[11px] text-default-400 whitespace-nowrap" x-text="'(' + cursosPersonalActual.length + ' curso(s))'"></span>
                                                </div>
                                                <div class="flex flex-wrap gap-1.5 mt-1">
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-default-100/80 text-default-600 leading-tight">
                                                        <i class="ti ti-briefcase text-[9px]"></i>
                                                        <span x-text="personalesDeSucursalActual[selectedPersonalIdx]?.Cargo || '—'"></span>
                                                    </span>
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-default-100/80 text-default-600 leading-tight">
                                                        <i class="ti ti-tag text-[9px]"></i>
                                                        <span x-text="personalesDeSucursalActual[selectedPersonalIdx]?.TipoTrabajador || '—'"></span>
                                                    </span>
                                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-medium bg-violet-100 text-violet-700 leading-tight">
                                                        <i class="ti ti-building-community text-[9px]"></i>
                                                        <span x-text="sucursalesEnResultados[selectedSucursalIdx] || '—'"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold bg-violet-100 text-violet-700 whitespace-nowrap shrink-0">
                                            <span x-text="personalesDeSucursalActual[selectedPersonalIdx]?.CodigoPersonal"></span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedPersonalIdx < 0">
                                <div class="px-5 py-3 border-b border-default-200 bg-gradient-to-r from-default-50 to-transparent">
                                    <div class="flex items-center gap-2">
                                        <div class="w-1 h-6 rounded-full bg-default-300 shrink-0"></div>
                                        <span class="text-sm text-default-400 italic">Expanda una sucursal y seleccione un personal para ver sus cursos</span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedPersonalIdx >= 0">
                                <div class="flex flex-col flex-1 min-h-0">
                                    <div class="flex-1 overflow-auto custom-scrollbar">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                                <tr>
                                                    <th class="px-5 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider w-14">#</th>
                                                    <th @click="ordenarCursos('Nombre')"
                                                        class="px-5 py-3 text-left font-semibold text-default-700 cursor-pointer hover:text-violet-600 transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Curso
                                                            <span :class="cursosSortColumn === 'Nombre' ? 'text-violet-600' : 'text-default-300'"
                                                                x-text="cursosSortColumn === 'Nombre' ? (cursosSortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenarCursos('Nota_Final')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-violet-600 transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Nota final
                                                            <span :class="cursosSortColumn === 'Nota_Final' ? 'text-violet-600' : 'text-default-300'"
                                                                x-text="cursosSortColumn === 'Nota_Final' ? (cursosSortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenarCursos('Estado')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-violet-600 transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Estado
                                                            <span :class="cursosSortColumn === 'Estado' ? 'text-violet-600' : 'text-default-300'"
                                                                x-text="cursosSortColumn === 'Estado' ? (cursosSortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                    <th @click="ordenarCursos('Fecha_Acceso')"
                                                        class="px-5 py-3 text-center font-semibold text-default-700 cursor-pointer hover:text-violet-600 transition-colors text-xs uppercase tracking-wider table-sortable">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            Fecha / Últ. Acceso
                                                            <span :class="cursosSortColumn === 'Fecha_Acceso' ? 'text-violet-600' : 'text-default-300'"
                                                                x-text="cursosSortColumn === 'Fecha_Acceso' ? (cursosSortDirection === 'asc' ? '▲' : '▼') : '⇅'"
                                                                class="text-[10px] transition-colors"></span>
                                                        </div>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white">
                                                <template x-for="(curso, ci) in cursosPersonalActualPaginado" :key="ci">
                                                    <tr class="table-row border-b border-default-100"
                                                        :class="ci % 2 === 1 ? 'table-row-even' : ''">
                                                        <td class="px-5 py-2.5 text-center text-default-400 text-xs font-mono" x-text="((cursosPage - 1) * cursosPerPage) + ci + 1"></td>
                                                        <td class="px-5 py-2.5 text-default-800 font-medium text-sm" x-text="curso.Nombre"></td>
                                                        <td class="px-5 py-2.5 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border-l-4"
                                                                :class="parseFloat(curso.Nota_Final) >= 10 ? 'bg-green-50 text-green-700 border-l-green-500' : (curso.Nota_Final != null && curso.Nota_Final !== 'Sin nota' ? 'bg-red-50 text-red-700 border-l-red-500' : 'bg-gray-50 text-gray-500 border-l-gray-400')">
                                                                <span x-text="curso.Nota_Final || '—'"></span>
                                                            </span>
                                                        </td>
                                                        <td class="px-5 py-2.5 text-center">
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border" :class="{
                                                                'bg-amber-50 text-amber-700 border-amber-200': curso.Estado === 'PENDIENTE',
                                                                'bg-green-50 text-green-700 border-green-200': curso.Estado === 'APROBADO',
                                                                'bg-red-50 text-red-700 border-red-200': curso.Estado === 'DESAPROBADO',
                                                                'bg-gray-50 text-gray-600 border-gray-200': curso.Estado === 'SIN ACCEDER' || curso.Estado === 'SIN NOTA',
                                                                'bg-blue-50 text-blue-700 border-blue-200': curso.Estado === 'EN CURSO'
                                                            }">
                                                                <span class="badge-dot" :class="{
                                                                    'bg-amber-500': curso.Estado === 'PENDIENTE',
                                                                    'bg-green-500': curso.Estado === 'APROBADO',
                                                                    'bg-red-500': curso.Estado === 'DESAPROBADO',
                                                                    'bg-gray-400': curso.Estado === 'SIN ACCEDER' || curso.Estado === 'SIN NOTA',
                                                                    'bg-blue-500': curso.Estado === 'EN CURSO'
                                                                }"></span>
                                                                <i class="ti text-[11px]" :class="{
                                                                    'ti-clock': curso.Estado === 'PENDIENTE',
                                                                    'ti-circle-check': curso.Estado === 'APROBADO',
                                                                    'ti-circle-x': curso.Estado === 'DESAPROBADO',
                                                                    'ti-player-off': curso.Estado === 'SIN ACCEDER' || curso.Estado === 'SIN NOTA',
                                                                    'ti-player-play': curso.Estado === 'EN CURSO'
                                                                }"></i>
                                                                <span x-text="curso.Estado"></span>
                                                            </span>
                                                        </td>
                                                        <td class="px-5 py-2.5 text-center text-xs text-default-500 font-mono whitespace-nowrap">
                                                            <span x-text="curso.Fecha_Nota ? (curso.Fecha_Nota.includes(' ') ? curso.Fecha_Nota.split(' ')[0].split('-').reverse().join('/') + ' ' + curso.Fecha_Nota.split(' ')[1].slice(0,5) : curso.Fecha_Nota) : (curso.Fecha_Ultimo_Acceso ? (curso.Fecha_Ultimo_Acceso.includes(' ') ? curso.Fecha_Ultimo_Acceso.split(' ')[0].split('-').reverse().join('/') + ' ' + curso.Fecha_Ultimo_Acceso.split(' ')[1].slice(0,5) : curso.Fecha_Ultimo_Acceso) : '—')"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                        <template x-if="cursosPersonalActual.length === 0">
                                            <div class="flex items-center justify-center py-10 text-default-400 text-sm">
                                                Este personal no tiene cursos registrados.
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Paginación --}}
                                    <div x-show="cursosPersonalActual.length > cursosPerPage"
                                        class="flex items-center justify-between px-5 py-3 border-t border-default-200 bg-default-50/80 shrink-0">
                                        <div class="text-sm text-default-500">
                                            <span class="font-medium text-default-700" x-text="cursosPersonalActual.length"></span> cursos
                                            <span class="mx-1.5 text-default-300">·</span>
                                            Pág. <span class="font-medium text-default-700" x-text="cursosPage"></span>
                                            de <span class="font-medium text-default-700" x-text="totalPagesCursosPersonal"></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="cursosPage--" :disabled="cursosPage === 1"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                <i class="ti ti-chevron-left text-xs"></i>
                                                Anterior
                                            </button>
                                            <button type="button" @click="cursosPage++" :disabled="cursosPage >= totalPagesCursosPersonal"
                                                class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-default-200 bg-white text-sm font-medium text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                                                Siguiente
                                                <i class="ti ti-chevron-right text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedPersonalIdx < 0">
                                <div class="flex-1 flex items-center justify-center text-default-400 text-sm">
                                    <div class="text-center py-10">
                                        <i class="ti ti-user-search text-3xl mb-2 block"></i>
                                        <span>Seleccione un personal para ver sus cursos</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
                <template x-if="view === 'filters'">
                    <div class="flex items-center justify-end gap-2 w-full">
                        <button type="button" @click="cerrar()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button" @click="obtenerReporte()"
                            :disabled="buscando || selectedUsernames.length === 0 || selectedCourseIds.length === 0"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-violet-500 hover:bg-violet-600 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="ti ti-search text-sm" x-show="!buscando"></i>
                            <i class="ti ti-loader animate-spin text-sm" x-show="buscando"></i>
                            <span x-text="buscando ? 'Buscando...' : 'Obtener reporte'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="view === 'results'">
                    <div class="flex items-center justify-between w-full">
                        <button type="button" @click="volverAFiltros()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 transition-all cursor-pointer">
                            <i class="ti ti-arrow-left text-sm"></i>
                            Atrás
                        </button>
                        <div x-show="resultados.length > 0 && !buscando" class="flex items-center gap-2">
                            <span class="text-sm text-default-500 mr-2">
                                <span x-text="resultados.length"></span> personal(es) · <span x-text="resultados.reduce((acc, p) => acc + (p.Cursos ? p.Cursos.length : 0), 0)"></span> registro(s) de cursos
                            </span>
                            <button type="button" @click="exportarExcelReporteGeneral()"
                                :disabled="exportandoExcel || exportandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-spreadsheet text-sm" x-show="!exportandoExcel"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="exportandoExcel"></i>
                                <span x-text="exportandoExcel ? 'Generando Excel...' : 'Exportar Excel'"></span>
                            </button>
                            <button type="button" @click="exportarPDFReporteGeneral()"
                                :disabled="exportandoExcel || exportandoPDF"
                                class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="ti ti-file-type-pdf text-sm" x-show="!exportandoPDF"></i>
                                <i class="ti ti-loader animate-spin text-sm" x-show="exportandoPDF"></i>
                                <span x-text="exportandoPDF ? 'Generando PDF...' : 'Exportar PDF'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Historial de Reportes --}}
    <div id="modal-historial-reportes" x-data="modalHistorialReportes" x-show="open" x-cloak
        @keydown.escape.window="cerrar()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div class="flex flex-col w-full max-w-6xl bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-10 h-10 rounded-xl bg-indigo-500 flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-clock text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                            Historial de reportes
                        </h3>
                        <p class="text-xs text-default-500 mt-0.5">Reportes de capacitaciones generados</p>
                    </div>
                </div>
                <button type="button" @click="cerrar()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <div class="px-6 pt-4 pb-6">
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[200px]">
                        <input type="text" x-model="searchQuery" @input="currentPage = 1"
                            placeholder="Buscar por nombre..."
                            class="w-full h-9 pl-10 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                    </div>
                    <div class="relative shrink-0">
                        <input type="date" x-model="filtroFecha" @change="currentPage = 1"
                            class="w-full h-9 pl-10 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                    </div>
                    <label class="inline-flex items-center gap-2 cursor-pointer shrink-0">
                        <input type="checkbox" x-model="showDeletedOnly" @change="currentPage = 1" class="sr-only peer">
                        <div
                            class="w-9 h-5 bg-default-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-default-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-500 relative">
                        </div>
                        <span class="text-xs font-medium text-default-600">Eliminados</span>
                    </label>
                </div>

                <template x-if="loading">
                    <div class="flex flex-col items-center justify-center py-16 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                        <p class="text-sm">Cargando reportes...</p>
                    </div>
                </template>

                <template x-if="!loading && reportes.length === 0">
                    <div class="text-center py-16">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-default-100 flex items-center justify-center mb-4">
                            <i class="ti ti-file-off text-2xl text-default-400"></i>
                        </div>
                        <p class="text-sm text-default-500">No se encontraron reportes generados.</p>
                        <p class="text-xs text-default-400 mt-1">Los reportes aparecerán aquí al exportar a Excel o PDF.
                        </p>
                    </div>
                </template>

                <template x-if="!loading && reportes.length > 0">
                    <div class="flex flex-col max-h-[550px] border border-default-200 rounded-xl">
                        <template x-if="reportesFiltrados.length === 0">
                            <div class="text-center py-10 text-default-500 text-sm">
                                <template x-if="showDeletedOnly">
                                    <span>No se encontraron reportes eliminados.</span>
                                </template>
                                <template x-if="!showDeletedOnly && (searchQuery || filtroFecha)">
                                    <span>No se encontraron reportes que coincidan con los criterios de búsqueda.</span>
                                </template>
                                <template x-if="!showDeletedOnly && !searchQuery && !filtroFecha">
                                    <span>No se encontraron reportes generados.</span>
                                </template>
                            </div>
                        </template>
                        <template x-if="reportesFiltrados.length > 0">
                            <div class="flex-1 overflow-auto custom-scrollbar">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                        <tr>
                                            <th class="px-4 py-3 text-center font-semibold text-default-700 w-12">
                                                <input type="checkbox"
                                                    @change="toggleSeleccionTodos($event.target.checked)"
                                                    :checked="todosSeleccionados"
                                                    class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer">
                                            </th>
                                            <th class="px-4 py-3 text-left font-semibold text-default-700 w-14">#</th>
                                            <th @click="ordenar('nombre_archivo')"
                                                class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer select-none hover:text-primary transition-colors">
                                                Nombre del archivo
                                                <span class="ml-1 text-xs"
                                                    :class="sortColumn === 'nombre_archivo' ? 'text-primary' : 'text-default-300'"
                                                    x-text="sortColumn === 'nombre_archivo' ? (sortDirection === 'asc' ? '↑' : sortDirection === 'desc' ? '↓' : '↕') : '↕'"></span>
                                            </th>
                                            <th @click="ordenar('descripcion')"
                                                class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer select-none hover:text-primary transition-colors">
                                                Descripción
                                                <span class="ml-1 text-xs"
                                                    :class="sortColumn === 'descripcion' ? 'text-primary' : 'text-default-300'"
                                                    x-text="sortColumn === 'descripcion' ? (sortDirection === 'asc' ? '↑' : sortDirection === 'desc' ? '↓' : '↕') : '↕'"></span>
                                            </th>
                                            <th class="px-4 py-3 text-center font-semibold text-default-700 w-32">
                                                Descargar</th>
                                            <th @click="ordenar('fecha_creacion')"
                                                class="px-4 py-3 text-left font-semibold text-default-700 cursor-pointer select-none hover:text-primary transition-colors whitespace-nowrap">
                                                Fecha
                                                <span class="ml-1 text-xs"
                                                    :class="sortColumn === 'fecha_creacion' ? 'text-primary' : 'text-default-300'"
                                                    x-text="sortColumn === 'fecha_creacion' ? (sortDirection === 'asc' ? '↑' : sortDirection === 'desc' ? '↓' : '↕') : '↕'"></span>
                                            </th>
                                            <th class="px-4 py-3 text-center font-semibold text-default-700 w-20"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-default-100 bg-white">
                                        <template x-for="(reporte, index) in reportesPaginados" :key="reporte.id">
                                            <tr class="hover:bg-default-50 transition-colors"
                                                :class="!reporte.habilitado ? 'bg-default-100/50 opacity-60' : ''">
                                                <td class="px-4 py-3 text-center">
                                                    <template x-if="reporte.habilitado">
                                                        <input type="checkbox" :value="reporte.id"
                                                            @change="toggleSeleccion(reporte.id, $event.target.checked)"
                                                            :checked="selectedReportes.includes(reporte.id)"
                                                            class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer">
                                                    </template>
                                                </td>
                                                <td class="px-4 py-3 text-default-500" x-text="((currentPage - 1) * perPage) + index + 1"></td>

                                                <template x-if="editingId !== reporte.id">
                                                    <td class="px-4 py-3 font-medium max-w-[18rem] truncate"
                                                        :class="reporte.habilitado ? 'text-default-800' : 'text-default-500 line-through'"
                                                        x-text="reporte.nombre_archivo" :title="reporte.nombre_archivo">
                                                    </td>
                                                </template>
                                                <template x-if="editingId === reporte.id">
                                                    <td class="px-4 py-3">
                                                        <input type="text" x-model="editForm.nombre_archivo"
                                                            class="w-full h-8 px-2.5 text-sm bg-white border border-primary/30 rounded-lg text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"
                                                            placeholder="Nombre del archivo">
                                                    </td>
                                                </template>

                                                <template x-if="editingId !== reporte.id">
                                                    <td class="px-4 py-3 max-w-[14rem] truncate"
                                                        :class="reporte.habilitado ? 'text-default-600' : 'text-default-400'"
                                                        x-text="reporte.descripcion || '—'"
                                                        :title="reporte.descripcion"></td>
                                                </template>
                                                <template x-if="editingId === reporte.id">
                                                    <td class="px-4 py-3">
                                                        <input type="text" x-model="editForm.descripcion"
                                                            class="w-full h-8 px-2.5 text-sm bg-white border border-primary/30 rounded-lg text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all"
                                                            placeholder="Descripción">
                                                    </td>
                                                </template>

                                                <td class="px-4 py-3">
                                                    <template x-if="reporte.habilitado">
                                                        <div class="flex items-center justify-center gap-2">
                                                            <button x-show="reporte.tipo_archivo === 'pdf'"
                                                                @click="descargarArchivo(reporte.id, 'pdf')"
                                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 transition-colors cursor-pointer"
                                                                title="Descargar PDF">
                                                                <i class="ti ti-download text-sm"></i>
                                                            </button>
                                                            <button x-show="reporte.tipo_archivo === 'xlsx'"
                                                                @click="descargarArchivo(reporte.id, 'excel')"
                                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 hover:text-green-700 transition-colors cursor-pointer"
                                                                title="Descargar Excel">
                                                                <i class="ti ti-download text-sm"></i>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <template x-if="!reporte.habilitado">
                                                        <span class="text-xs text-default-400 italic">Eliminado</span>
                                                    </template>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-xs"
                                                    :class="reporte.habilitado ? 'text-default-600' : 'text-default-400'"
                                                    x-text="formatearFecha(reporte.fecha_creacion)"></td>

                                                <td class="px-4 py-3">
                                                    <div class="flex items-center justify-center gap-1.5">
                                                        <template x-if="reporte.habilitado && editingId !== reporte.id">
                                                            <button @click="iniciarEdicion(reporte)"
                                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-default-100 text-default-500 hover:bg-primary/10 hover:text-primary transition-colors cursor-pointer"
                                                                title="Editar">
                                                                <i class="ti ti-pencil text-sm"></i>
                                                            </button>
                                                        </template>
                                                        <template x-if="reporte.habilitado && editingId === reporte.id">
                                                            <div class="flex items-center gap-1">
                                                                <button @click="guardarEdicion()" :disabled="savingEdit"
                                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 hover:text-green-700 transition-colors cursor-pointer disabled:opacity-50"
                                                                    title="Guardar">
                                                                    <i class="ti ti-check text-sm"></i>
                                                                </button>
                                                                <button @click="cancelarEdicion()"
                                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 transition-colors cursor-pointer"
                                                                    title="Cancelar">
                                                                    <i class="ti ti-x text-sm"></i>
                                                                </button>
                                                            </div>
                                                        </template>

                                                        <template x-if="reporte.habilitado">
                                                            <button @click="cambiarEstado(reporte.id, false)"
                                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-700 transition-colors cursor-pointer"
                                                                title="Eliminar">
                                                                <i class="ti ti-trash text-sm"></i>
                                                            </button>
                                                        </template>
                                                        <template x-if="!reporte.habilitado">
                                                            <div class="flex items-center gap-1">
                                                                <button @click="eliminarDefinitivamente(reporte.id)"
                                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-700 transition-colors cursor-pointer"
                                                                    title="Eliminar permanentemente">
                                                                    <i class="ti ti-trash-off text-sm"></i>
                                                                </button>
                                                                <button @click="cambiarEstado(reporte.id, true)"
                                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-500 hover:bg-green-100 hover:text-green-700 transition-colors cursor-pointer"
                                                                    title="Recuperar">
                                                                    <i class="ti ti-refresh text-sm"></i>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <div
                            class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-default-200 bg-default-50">
                            <div class="text-sm text-default-500">
                                <span>Mostrando <span class="font-medium text-default-700"
                                        x-text="reportesPaginados.length"></span> de <span
                                        class="font-medium text-default-700" x-text="reportesFiltrados.length"></span>
                                    reporte(s)</span>
                            </div>
                            <template x-if="totalPages > 1">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                        @click="currentPage = Math.max(1, currentPage - 1)"
                                        :disabled="currentPage === 1"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-default-200 bg-white text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                                        title="Anterior">
                                        <i class="ti ti-chevron-left text-xs"></i>
                                    </button>
                                    <template x-for="pagina in paginasVisibles" :key="pagina">
                                        <button type="button" @click="currentPage = pagina"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-sm font-medium transition-all cursor-pointer"
                                            :class="currentPage === pagina
                                                ? 'bg-primary text-white shadow-sm shadow-primary/30'
                                                : 'border border-default-200 bg-white text-default-600 hover:bg-default-100 hover:text-default-800'"
                                            x-text="pagina">
                                        </button>
                                    </template>
                                    <button type="button"
                                        @click="currentPage = Math.min(totalPages, currentPage + 1)"
                                        :disabled="currentPage === totalPages"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-default-200 bg-white text-default-600 hover:bg-default-100 hover:text-default-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer"
                                        title="Siguiente">
                                        <i class="ti ti-chevron-right text-xs"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
                <div class="flex items-center gap-3 flex-1">
                    <span x-show="selectedReportes.length > 0" class="text-sm text-default-600">
                        <span x-text="selectedReportes.length"></span>
                        seleccionado(s)
                    </span>
                    <button x-show="selectedReportes.length > 0" @click="descargarSeleccionadosZip()"
                        :disabled="downloadingZip"
                        class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-file-zip text-sm"></i>
                        <span x-text="downloadingZip ? 'Generando ZIP...' : 'Descargar ZIP'"></span>
                    </button>
                </div>
                <button type="button" @click="cerrar()"
                    class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Reporte Formato --}}
    <div id="modal-reporte-formato" x-data="modalReporteFormato" x-show="open" x-cloak
        @keydown.escape.window="cerrar()"
        @dnis-importados.window="aplicarDnisImportados($event.detail)"
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div
            class="flex flex-col w-full max-w-6xl bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-start py-5 px-6 border-b border-default-100">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-10 h-10 rounded-xl bg-rose-500 flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-certificate text-lg"></i>
                    </div>
                    <div>
                        <div x-show="view === 'filtros'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Reporte Formato
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">
                                Seleccione capacitación y personal vigente o no vigente para la constancia de participación
                            </p>
                        </div>
                        <div x-show="view === 'resultado'">
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Constancia de participación
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">
                                Datos obtenidos del reporte de formato
                            </p>
                        </div>
                    </div>
                </div>
                <button type="button" @click="cerrar()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <template x-if="loadingInicial">
                <div class="flex flex-col items-center justify-center py-20 text-default-400">
                    <i class="ti ti-loader animate-spin text-3xl mb-3"></i>
                    <p class="text-sm font-medium">Cargando capacitaciones y personal vigente/no vigente...</p>
                </div>
            </template>

            <div x-show="!loadingInicial && view === 'filtros'" class="px-6 pt-4 pb-6 overflow-y-auto max-h-[72vh] custom-scrollbar">
                <div class="grid grid-cols-2 gap-6">
                    {{-- Left column: Capacitación --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-default-200 pb-2">
                            <i class="ti ti-book text-primary text-base"></i>
                            <h4 class="text-sm font-semibold text-default-800">Capacitación</h4>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Fecha de creación desde
                                </label>
                                <input type="date" x-model="fechaCreacionDesde" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Fecha de creación hasta
                                </label>
                                <input type="date" x-model="fechaCreacionHasta" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-default-700 mb-1 block">
                                    Año del curso
                                </label>
                                <select x-model="selectedAnio" @change="filtrarCursos()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los años</option>
                                    <template x-for="anio in aniosDisponibles" :key="anio">
                                        <option :value="anio" x-text="anio"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <input type="text" x-model="searchCurso" @input="filtrarCursos()"
                                    placeholder="Buscar capacitación por nombre..."
                                    class="w-full h-9 pl-8 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-default-500" x-text="selectedCursoId !== null ? '1 capacitación seleccionada' : 'Ninguna capacitación seleccionada'"></span>
                                <template x-if="selectedCursoId !== null">
                                    <button type="button" @click="selectedCursoId = null"
                                        class="text-xs text-danger hover:text-danger/80 font-medium transition-colors cursor-pointer">
                                        Quitar selección
                                    </button>
                                </template>
                            </div>
                            <div class="border border-default-200 rounded-lg max-h-64 overflow-y-auto custom-scrollbar">
                                <template x-if="loadingCursos">
                                    <div class="flex items-center justify-center py-6 text-default-400">
                                        <i class="ti ti-loader animate-spin text-lg mr-2"></i>
                                        <span class="text-sm">Cargando capacitaciones...</span>
                                    </div>
                                </template>
                                <template x-if="!loadingCursos && cursos.length === 0">
                                    <div class="text-center py-6 text-default-400 text-sm">
                                        No hay capacitaciones disponibles.
                                    </div>
                                </template>
                                <template x-for="curso in cursosPaginados()" :key="curso.Id">
                                    <button type="button"
                                        @click="seleccionarCurso(curso.Id)"
                                        :disabled="selectedCursoId !== null && selectedCursoId !== curso.Id"
                                        class="w-full text-left flex items-center gap-3 px-3 py-2 border-b border-default-100 last:border-b-0 transition-colors select-none"
                                        :class="{
                                            'bg-primary/10': selectedCursoId === curso.Id,
                                            'opacity-40 cursor-not-allowed': selectedCursoId !== null && selectedCursoId !== curso.Id,
                                            'hover:bg-default-50': selectedCursoId === null
                                        }">
                                        <div class="w-4 h-4 shrink-0 rounded-full border flex items-center justify-center transition-all"
                                            :class="selectedCursoId === curso.Id ? 'border-primary' : 'border-default-300'">
                                            <div class="w-2 h-2 rounded-full bg-primary" x-show="selectedCursoId === curso.Id"></div>
                                        </div>
                                        <div class="flex flex-col min-w-0 flex-1">
                                            <span class="text-sm font-medium truncate transition-colors"
                                                :class="selectedCursoId === curso.Id ? 'text-primary' : 'text-default-800'"
                                                x-text="curso.Nombre"></span>
                                            <span class="text-xs text-default-400" x-text="curso.Fecha_Creacion ? formatearFecha(curso.Fecha_Creacion) : 'Sin fecha'"></span>
                                        </div>
                                        <template x-if="selectedCursoId === curso.Id">
                                            <i class="ti ti-circle-check text-primary shrink-0"></i>
                                        </template>
                                        <template x-if="selectedCursoId !== null && selectedCursoId !== curso.Id">
                                            <i class="ti ti-lock text-default-300 shrink-0"></i>
                                        </template>
                                    </button>
                                </template>
                            </div>

                            <template x-if="cursos.length > cursosPerPage">
                                <div class="flex items-center justify-between pt-2.5">
                                    <span class="text-xs text-default-400">
                                        Pág. <span x-text="cursosPage"></span> de <span x-text="cursosTotalPages"></span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        {{-- Anterior --}}
                                        <button type="button"
                                            @click="cursosPage = Math.max(1, cursosPage - 1)"
                                            :disabled="cursosPage <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-left text-xs"></i>
                                        </button>

                                        {{-- Página 1 siempre visible --}}
                                        <button type="button" @click="cursosPage = 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                            :class="cursosPage === 1 ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'">
                                            1
                                        </button>

                                        {{-- Elipsis izquierda --}}
                                        <template x-if="cursosPage > 3">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Página previa a la actual (si no es 1 ni última) --}}
                                        <template x-if="cursosPage > 2 && cursosPage < cursosTotalPages">
                                            <button type="button" @click="cursosPage = cursosPage - 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="cursosPage - 1">
                                            </button>
                                        </template>

                                        {{-- Página actual (si no es 1 ni última) --}}
                                        <template x-if="cursosPage !== 1 && cursosPage !== cursosTotalPages">
                                            <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border bg-primary text-white border-primary shadow-sm text-xs font-medium cursor-default"
                                                x-text="cursosPage">
                                            </button>
                                        </template>

                                        {{-- Página siguiente a la actual (si no es 1 ni última) --}}
                                        <template x-if="cursosPage < cursosTotalPages - 1 && cursosPage !== 1">
                                            <button type="button" @click="cursosPage = cursosPage + 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="cursosPage + 1">
                                            </button>
                                        </template>

                                        {{-- Elipsis derecha --}}
                                        <template x-if="cursosPage < cursosTotalPages - 2">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Última página siempre visible (si hay más de 1) --}}
                                        <template x-if="cursosTotalPages > 1">
                                            <button type="button" @click="cursosPage = cursosTotalPages"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                                :class="cursosPage === cursosTotalPages ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'"
                                                x-text="cursosTotalPages">
                                            </button>
                                        </template>

                                        {{-- Siguiente --}}
                                        <button type="button"
                                            @click="cursosPage = Math.min(cursosTotalPages, cursosPage + 1)"
                                            :disabled="cursosPage >= cursosTotalPages"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Right column: Personal --}}
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 border-b border-default-200 pb-2">
                            <i class="ti ti-users text-rose-500 text-base"></i>
                            <h4 class="text-sm font-semibold text-default-800">Personal</h4>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <select x-model="selectedCliente" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los clientes/empresas</option>
                                    <template x-for="cliente in clientesDisponibles" :key="cliente">
                                        <option :value="cliente" x-text="cliente"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedSucursal" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todas las sucursales</option>
                                    <template x-for="s in sucursales" :key="s.codigo">
                                        <option :value="s.codigo" x-text="s.sucursal"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedVigencia" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="1">Vigente</option>
                                    <option value="0">No vigente</option>
                                    <option value="2">Todas las vigencias</option>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedCargo" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los cargos</option>
                                    <template x-for="cargo in cargosDisponibles" :key="cargo">
                                        <option :value="cargo" x-text="cargo"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <select x-model="selectedTipoTrabajador" @change="filtrarPersonales()"
                                    class="w-full h-9 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                                    <option value="">Todos los tipos de trabajador</option>
                                    <template x-for="tipo in tiposTrabajadorDisponibles" :key="tipo">
                                        <option :value="tipo" x-text="tipo"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="relative">
                                <input type="text" x-model="searchPersonal" @input="filtrarPersonales()"
                                    placeholder="Buscar por nombre o DNI..."
                                    class="w-full h-9 pl-8 pr-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-default-500" x-text="selectedPersonalDnis.length + ' personal(es) seleccionado(s)'"></span>
                                    <button type="button"
                                        @click="$dispatch('abrir-importar-dnis', { target: 'formato', cursoSeleccionado: selectedCursoId !== null })"
                                        class="inline-flex items-center gap-1 px-2 h-6 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold hover:bg-emerald-100 transition-colors cursor-pointer shrink-0">
                                        <i class="ti ti-file-spreadsheet text-[11px]"></i>
                                        Importar Excel
                                    </button>
                                </div>
                                <button type="button" @click="toggleAllPersonal()"
                                    class="text-xs text-primary hover:text-primary/80 font-medium transition-colors cursor-pointer shrink-0"
                                    x-text="selectAllPersonal ? 'Deseleccionar todos' : 'Seleccionar todos'">
                                </button>
                            </div>

                            {{-- DNIs importados desde Excel --}}
                            <template x-if="importados.length > 0">
                                <div class="mb-2 rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700">
                                            <i class="ti ti-file-spreadsheet text-xs"></i>
                                            <span x-text="importados.length"></span> importado(s) desde Excel
                                        </span>
                                        <button type="button" @click="quitarImportados()"
                                            class="text-[11px] font-medium text-danger hover:text-danger/80 transition-colors cursor-pointer">
                                            Quitar
                                        </button>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap gap-1 max-h-14 overflow-y-auto custom-scrollbar">
                                        <template x-for="imp in importados" :key="'importado-formato-' + imp.dni">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white border border-default-200 text-[10px] text-default-600"
                                                :title="(imp.nombre_completo || '') + ' · ' + imp.dni">
                                                <span class="font-mono" x-text="imp.dni"></span>
                                                <span class="text-default-400 truncate max-w-[90px]" x-text="imp.nombre_completo || ''"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="border border-default-200 rounded-lg max-h-64 overflow-y-auto custom-scrollbar">
                                <template x-if="loadingPersonal">
                                    <div class="flex items-center justify-center py-6 text-default-400">
                                        <i class="ti ti-loader animate-spin text-lg mr-2"></i>
                                        <span class="text-sm">Cargando personal...</span>
                                    </div>
                                </template>
                                <template x-if="!loadingPersonal && personales.length === 0">
                                    <div class="text-center py-6 text-default-400 text-sm">
                                        No se encontró personal con los filtros seleccionados.
                                    </div>
                                </template>
                                <template x-for="p in personalesPaginados()" :key="p.codigo">
                                    <label class="flex items-center gap-3 px-3 py-2 hover:bg-default-50 cursor-pointer border-b border-default-100 last:border-b-0 transition-colors">
                                        <input type="checkbox" :value="p.dni"
                                            @change="togglePersonal(p.dni)"
                                            :checked="selectedPersonalDnis.includes(p.dni)"
                                            class="w-4 h-4 rounded border-default-300 text-primary focus:ring-primary cursor-pointer shrink-0">
                                        <div class="flex flex-col min-w-0">
                                            <span class="text-sm font-medium text-default-800 truncate" x-text="p.nombre_completo"></span>
                                            <span class="text-xs text-default-400 truncate" x-text="p.dni + ' · ' + (p.sucursal || '—')"></span>
                                        </div>
                                        <span class="ml-auto shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold"
                                            :class="p.vigente ? 'bg-green-50 text-green-600 border border-green-200' : 'bg-red-50 text-red-500 border border-red-200'"
                                            x-text="p.vigente ? 'VIGENTE' : 'NO VIGENTE'">
                                        </span>
                                    </label>
                                </template>
                            </div>

                            <template x-if="personales.length > personalPerPage">
                                <div class="flex items-center justify-between pt-2.5">
                                    <span class="text-xs text-default-400">
                                        Pág. <span x-text="personalPage"></span> de <span x-text="personalTotalPages"></span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        {{-- Anterior --}}
                                        <button type="button"
                                            @click="personalPage = Math.max(1, personalPage - 1)"
                                            :disabled="personalPage <= 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-left text-xs"></i>
                                        </button>

                                        {{-- Página 1 siempre visible --}}
                                        <button type="button" @click="personalPage = 1"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                            :class="personalPage === 1 ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'">
                                            1
                                        </button>

                                        {{-- Elipsis izquierda --}}
                                        <template x-if="personalPage > 3">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Página previa a la actual (si no es 1 ni última) --}}
                                        <template x-if="personalPage > 2 && personalPage < personalTotalPages">
                                            <button type="button" @click="personalPage = personalPage - 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="personalPage - 1">
                                            </button>
                                        </template>

                                        {{-- Página actual (si no es 1 ni última) --}}
                                        <template x-if="personalPage !== 1 && personalPage !== personalTotalPages">
                                            <button type="button"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border bg-primary text-white border-primary shadow-sm text-xs font-medium cursor-default"
                                                x-text="personalPage">
                                            </button>
                                        </template>

                                        {{-- Página siguiente a la actual (si no es 1 ni última) --}}
                                        <template x-if="personalPage < personalTotalPages - 1 && personalPage !== 1">
                                            <button type="button" @click="personalPage = personalPage + 1"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-600 hover:bg-default-100 text-xs font-medium transition-colors cursor-pointer"
                                                x-text="personalPage + 1">
                                            </button>
                                        </template>

                                        {{-- Elipsis derecha --}}
                                        <template x-if="personalPage < personalTotalPages - 2">
                                            <span class="w-5 h-7 flex items-center justify-center text-default-400 text-xs select-none">…</span>
                                        </template>

                                        {{-- Última página siempre visible (si hay más de 1) --}}
                                        <template x-if="personalTotalPages > 1">
                                            <button type="button" @click="personalPage = personalTotalPages"
                                                class="w-7 h-7 flex items-center justify-center rounded-md border text-xs font-medium transition-colors cursor-pointer"
                                                :class="personalPage === personalTotalPages ? 'bg-primary text-white border-primary shadow-sm' : 'border-default-200 text-default-600 hover:bg-default-100'"
                                                x-text="personalTotalPages">
                                            </button>
                                        </template>

                                        {{-- Siguiente --}}
                                        <button type="button"
                                            @click="personalPage = Math.min(personalTotalPages, personalPage + 1)"
                                            :disabled="personalPage >= personalTotalPages"
                                            class="w-7 h-7 flex items-center justify-center rounded-md border border-default-200 text-default-500 hover:bg-default-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Vista: Resultado del reporte de formato --}}
            <div x-show="!loadingInicial && view === 'resultado'" class="px-6 pt-4 pb-6 overflow-y-auto max-h-[72vh] custom-scrollbar">
                <template x-if="generando">
                    <div class="flex flex-col items-center justify-center py-20 text-default-400">
                        <i class="ti ti-loader animate-spin text-3xl mb-3"></i>
                        <p class="text-sm font-medium">Generando reporte de formato...</p>
                    </div>
                </template>

                <template x-if="!generando && !reporteData">
                    <div class="text-center py-10 text-default-500 text-sm">
                        No se pudo obtener la información del reporte.
                    </div>
                </template>

                <template x-if="!generando && reporteData">
                    <div class="space-y-5">
                        {{-- Encabezado del formato --}}
                        <div class="border border-default-200 rounded-xl overflow-hidden">
                            <div class="px-5 py-4 border-b border-default-100 bg-gradient-to-r from-rose-50 to-transparent flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500 mb-1">
                                        <i class="ti ti-certificate text-xs"></i> Constancia de participación
                                    </p>
                                    <h4 class="text-base font-bold text-default-900 leading-snug" x-text="reporteData.Nombre_Curso"></h4>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold bg-default-100 text-default-600 whitespace-nowrap shrink-0">
                                    <i class="ti ti-calendar text-[10px]"></i>
                                    <span x-text="reporteData.Fecha_Reporte"></span>
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-5 py-4">
                                {{-- Responsable --}}
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 shrink-0 rounded-xl bg-rose-500/10 flex items-center justify-center">
                                        <i class="ti ti-user text-rose-500 text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-default-400">Responsable</p>
                                        <p class="text-sm font-semibold text-default-800 truncate" x-text="reporteData.Nombre_Responsable || '—'"></p>
                                        <p class="text-xs text-default-500 truncate" x-text="reporteData.Cargo_Responsable || ''"></p>
                                    </div>
                                </div>
                                {{-- Firma responsable --}}
                                <div class="flex items-center gap-3">
                                    <div x-show="!reporteData.Firma_Responsable" class="w-10 h-10 shrink-0 rounded-xl bg-default-100 flex items-center justify-center">
                                        <i class="ti ti-signature text-default-500 text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-default-400">Firma responsable</p>
                                        <template x-if="reporteData.Firma_Responsable">
                                            <img :src="reporteData.Firma_Responsable" alt="Firma" class="h-10 object-contain"
                                                x-on:error="$el.style.display = 'none'">
                                        </template>
                                        <p x-show="!reporteData.Firma_Responsable" class="text-xs text-default-400">Sin firma</p>
                                    </div>
                                </div>
                                {{-- Total personal --}}
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 shrink-0 rounded-xl bg-primary/10 flex items-center justify-center">
                                        <i class="ti ti-users text-primary text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-default-400">Personal</p>
                                        <p class="text-sm font-semibold text-default-800">
                                            <span x-text="reporteData.Personal?.length || 0"></span> participante(s)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabla de personal --}}
                        <template x-if="reporteData.Personal?.length">
                            <div class="border border-default-200 rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-default-100 bg-default-50/50 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-default-600 uppercase tracking-wider">Personal participante</span>
                                    <span class="text-xs text-default-400" x-text="'Firmas de ' + (reporteData.Personal?.length || 0) + ' persona(s)'"></span>
                                </div>
                                <div class="overflow-x-auto overflow-y-auto max-h-[340px] custom-scrollbar">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                            <tr>
                                                <th class="px-5 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider w-14">#</th>
                                                <th class="px-5 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">
                                                    <button type="button" @click="ordenarPersonal('Nombre_Personal')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-primary transition-colors cursor-pointer">
                                                        Nombre completo
                                                        <i class="text-[10px]" :class="sortIcon('Nombre_Personal')"></i>
                                                    </button>
                                                </th>
                                                <th class="px-5 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">
                                                    <button type="button" @click="ordenarPersonal('DNI_Personal')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-primary transition-colors cursor-pointer">
                                                        DNI
                                                        <i class="text-[10px]" :class="sortIcon('DNI_Personal')"></i>
                                                    </button>
                                                </th>
                                                <th class="px-5 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">
                                                    <button type="button" @click="ordenarPersonal('Cargo_Personal')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-primary transition-colors cursor-pointer">
                                                        Cargo
                                                        <i class="text-[10px]" :class="sortIcon('Cargo_Personal')"></i>
                                                    </button>
                                                </th>
                                                <th class="px-5 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider">Firma</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            <template x-for="(persona, pi) in personalOrdenado" :key="pi">
                                                <tr class="border-b border-default-100 last:border-b-0" :class="pi % 2 === 1 ? 'bg-default-50/40' : ''">
                                                    <td class="px-5 py-2.5 text-center text-default-400 text-xs font-mono" x-text="pi + 1"></td>
                                                    <td class="px-5 py-2.5 text-default-800 font-medium whitespace-nowrap" x-text="persona.Nombre_Personal"></td>
                                                    <td class="px-5 py-2.5 text-default-500 font-mono whitespace-nowrap" x-text="persona.DNI_Personal"></td>
                                                    <td class="px-5 py-2.5 text-default-600" x-text="persona.Cargo_Personal || '—'"></td>
                                                    <td class="px-5 py-2.5 text-center">
                                                        <template x-if="persona.Firma_Personal">
                                                            <img :src="persona.Firma_Personal" alt="Firma" class="h-8 object-contain mx-auto"
                                                                x-on:error="$el.style.display = 'none'">
                                                        </template>
                                                        <span x-show="!persona.Firma_Personal" class="text-xs text-default-400">—</span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>

                        <template x-if="!reporteData.Personal?.length">
                            <div class="border border-dashed border-default-300 rounded-xl flex flex-col items-center justify-center py-12 text-center bg-default-50/50">
                                <i class="ti ti-users-off text-4xl text-default-300 mb-3"></i>
                                <p class="text-sm font-medium text-default-600">No hay personal participante registrado</p>
                                <p class="text-xs text-default-400 mt-1">El personal seleccionado no participó o no culminó la capacitación.</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex justify-end items-center gap-2 py-4 px-6 border-t border-default-100">
                <template x-if="view === 'filtros'">
                    <div class="flex items-center justify-end gap-2 w-full">
                        <button type="button" @click="cerrar()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer">
                            Cancelar
                        </button>
                        <button type="button" @click="generarReporte()"
                            :disabled="selectedCursoId === null || selectedPersonalDnis.length === 0 || generando"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-rose-500 hover:bg-rose-600 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="ti ti-certificate text-sm" x-show="!generando"></i>
                            <i class="ti ti-loader animate-spin text-sm" x-show="generando"></i>
                            <span x-text="generando ? 'Generando...' : 'Generar reporte'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="view === 'resultado'">
                    <div class="flex items-center justify-between w-full">
                        <button type="button" @click="volverAFiltros()"
                            class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 transition-all cursor-pointer">
                            <i class="ti ti-arrow-left text-sm"></i>
                            Atrás
                        </button>
                        <button type="button" @click="generarPDF()"
                            :disabled="exportandoPDF"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-rose-500 hover:bg-rose-600 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="ti ti-file-type-pdf text-sm" x-show="!exportandoPDF"></i>
                            <i class="ti ti-loader animate-spin text-sm" x-show="exportandoPDF"></i>
                            <span x-text="exportandoPDF ? 'Generando PDF...' : 'Generar PDF'"></span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Importar DNIs desde Excel --}}
    <div x-data="modalImportarDnis" @abrir-importar-dnis.window="abrir($event.detail || {})">
        <input type="file" x-ref="archivo" accept=".xlsx" class="sr-only" tabindex="-1" @change="onFile($event)">

        <div x-show="open" x-cloak @keydown.escape.window="cerrar()"
            class="fixed inset-0 z-[95] flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="background: rgba(36,39,70,0.45);">

            <div class="flex flex-col w-full max-w-xl bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                {{-- Encabezado --}}
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-default-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500 flex items-center justify-center text-white shadow-sm shrink-0">
                            <i class="ti ti-file-spreadsheet text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                                Importar personal desde Excel
                            </h3>
                            <p class="text-xs text-default-500 mt-0.5">
                                Columna A: Nro · Columna B: DNI · Datos desde la fila 2
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="cerrar()" :disabled="procesando"
                        class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                {{-- Loader --}}
                <template x-if="procesando">
                    <div class="flex flex-col items-center justify-center py-14 text-default-400">
                        <i class="ti ti-loader animate-spin text-3xl mb-3 text-emerald-500"></i>
                        <p class="text-sm font-medium text-default-700" x-text="textoEtapa"></p>
                        <p class="text-xs text-default-400 mt-1">No cierres esta ventana.</p>
                    </div>
                </template>

                {{-- Error --}}
                <template x-if="stage === 'error'">
                    <div class="px-6 py-8">
                        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                            <i class="ti ti-alert-triangle text-red-500 text-lg shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-red-700">No se pudo procesar el archivo</p>
                                <p class="text-xs text-red-600 mt-1 break-words" x-text="errorMsg"></p>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Resultado --}}
                <template x-if="stage === 'resultado'">
                    <div class="px-6 py-4 space-y-4 max-h-[65vh] overflow-y-auto custom-scrollbar">
                        {{-- Contadores --}}
                        <div class="grid grid-cols-4 gap-2">
                            <div class="rounded-lg border border-default-200 bg-default-50/60 px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-default-400">Válidos</p>
                                <p class="text-base font-bold text-default-800" x-text="resumen.validos"></p>
                            </div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-600">Encontrados</p>
                                <p class="text-base font-bold text-emerald-700" x-text="resumen.encontrados"></p>
                            </div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-600">No encontrados</p>
                                <p class="text-base font-bold text-amber-700" x-text="resumen.noEncontrados"></p>
                            </div>
                            <div class="rounded-lg border border-default-200 bg-default-50/60 px-2 py-2 text-center">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-default-400">Descartados</p>
                                <p class="text-base font-bold text-default-800" x-text="descartadosTotal"></p>
                            </div>
                        </div>

                        <p class="text-[10px] text-default-400 text-right">
                            Filas de datos leídas:
                            <span class="font-semibold text-default-600" x-text="resumen.totalFilas"></span>
                        </p>

                        {{-- Advertencias --}}
                        <template x-if="advertencias.length > 0">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 space-y-1">
                                <template x-for="(advertencia, ai) in advertencias" :key="'advertencia-' + ai">
                                    <p class="flex items-start gap-1.5 text-[11px] text-amber-700">
                                        <i class="ti ti-info-circle text-xs mt-0.5 shrink-0"></i>
                                        <span x-text="advertencia"></span>
                                    </p>
                                </template>
                            </div>
                        </template>

                        {{-- Encontrados --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-default-500">
                                    Encontrados
                                </span>
                                <span class="text-[11px] text-default-400">
                                    <span x-text="encontrados.length"></span> DNI(s)
                                </span>
                            </div>
                            <div class="border border-default-200 rounded-lg max-h-44 overflow-y-auto custom-scrollbar divide-y divide-default-100">
                                <template x-if="encontrados.length === 0">
                                    <p class="px-3 py-4 text-center text-xs text-default-400">
                                        Ningún DNI fue encontrado en el sistema.
                                    </p>
                                </template>
                                <template x-for="persona in encontrados" :key="'encontrado-' + persona.dni">
                                    <div class="flex items-center gap-2 px-3 py-2">
                                        <i class="ti ti-user-check text-emerald-500 text-sm shrink-0"></i>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-medium text-default-800 truncate"
                                                x-text="persona.nombre_completo || '—'"></p>
                                            <p class="text-[10px] text-default-400 font-mono" x-text="persona.dni"></p>
                                        </div>
                                        <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold"
                                            :class="persona.vigente ? 'bg-green-50 text-green-600 border border-green-200' : 'bg-red-50 text-red-500 border border-red-200'"
                                            x-text="persona.vigente ? 'VIGENTE' : 'NO VIGENTE'">
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- No encontrados --}}
                        <template x-if="noEncontrados.length > 0">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">
                                        No encontrados
                                    </span>
                                    <span class="text-[11px] text-default-400">
                                        <span x-text="noEncontrados.length"></span> DNI(s)
                                    </span>
                                </div>
                                <div class="border border-amber-200 rounded-lg bg-amber-50/40 px-3 py-2 flex flex-wrap gap-1 max-h-24 overflow-y-auto custom-scrollbar">
                                    <template x-for="dni in noEncontrados" :key="'no-encontrado-' + dni">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white border border-amber-200 text-[10px] text-amber-700 font-mono"
                                            x-text="dni">
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Descartados por formato o duplicados --}}
                        <template x-if="descartadosTotal > 0">
                            <div>
                                <button type="button" @click="mostrarDescartados = !mostrarDescartados"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-default-500 hover:text-default-700 transition-colors cursor-pointer">
                                    <i class="ti text-xs" :class="mostrarDescartados ? 'ti-chevron-down' : 'ti-chevron-right'"></i>
                                    Ver descartados (<span x-text="descartadosTotal"></span>)
                                </button>
                                <div x-show="mostrarDescartados" x-cloak
                                    class="mt-1.5 border border-default-200 rounded-lg max-h-28 overflow-y-auto custom-scrollbar divide-y divide-default-100">
                                    <template x-for="(item, ii) in invalidos" :key="'invalido-' + ii">
                                        <div class="flex items-center gap-2 px-3 py-1.5">
                                            <span class="text-[10px] text-default-400 font-mono w-10 shrink-0" x-text="'F' + item.fila"></span>
                                            <span class="text-[11px] text-default-600 font-mono truncate flex-1" x-text="item.valor"></span>
                                            <span class="text-[10px] text-red-500 shrink-0" x-text="item.motivo"></span>
                                        </div>
                                    </template>
                                    <template x-for="(item, di) in duplicados" :key="'duplicado-' + di">
                                        <div class="flex items-center gap-2 px-3 py-1.5">
                                            <span class="text-[10px] text-default-400 font-mono w-10 shrink-0" x-text="'F' + item.fila"></span>
                                            <span class="text-[11px] text-default-600 font-mono truncate flex-1" x-text="item.valor"></span>
                                            <span class="text-[10px] text-default-400 shrink-0">Duplicado</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Aviso de curso pendiente --}}
                        <template x-if="!cursoSeleccionado && encontrados.length > 0">
                            <p class="flex items-start gap-1.5 text-[11px] text-amber-600">
                                <i class="ti ti-info-circle text-xs mt-0.5 shrink-0"></i>
                                <span>Luego de agregarlos, selecciona la capacitación para habilitar el botón de generar.</span>
                            </p>
                        </template>
                    </div>
                </template>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-default-100">
                    <button type="button" @click="cerrar()" :disabled="procesando"
                        class="px-4 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-default-600 bg-default-100 hover:bg-default-200 hover:text-default-800 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        Cancelar
                    </button>
                    <template x-if="stage === 'resultado'">
                        <button type="button" @click="confirmar()" :disabled="encontrados.length === 0"
                            class="px-5 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="ti ti-download text-sm"></i>
                            <span x-text="'Agregar ' + encontrados.length + ' DNI(s)'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
@vite(['resources/js/app.js'])
@endsection