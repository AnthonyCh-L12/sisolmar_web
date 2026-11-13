@extends('layouts.vertical', ['title' => 'Exportación de exámenes'])

@section('css')
<style>
    [x-cloak] {
        display: none !important;
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
</style>
@endsection

@include('layouts.shared/page-title', ['subtitle' => 'Capacitación', 'title' => 'Exámenes'])

@section('content')

<div x-data="exportExamenes" @dnis-importados.window="aplicarDnisImportados($event.detail)" class="px-6 py-6">
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
                        Genere reportes detallados sobre el estado de las capacitaciones del personal,
                        incluyendo la exportación en PDF del desarrollo del examen del último intento
                        rendido por el personal que usted seleccione o busque por nombre o DNI.
                    </p>
                    <p class="mt-2 inline-flex items-center gap-2 text-xs text-default-500 leading-6">
                        <i class="ti ti-history text-amber-500"></i>
                        Los reportes consideran tanto cursos históricos (de años anteriores, previos a Sisolmar Web) como los nuevos.
                    </p>

                    <!-- Quick stats -->
                    <div class="flex items-center gap-6 mt-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center">
                                <i class="ti ti-file-type-pdf text-lg text-red-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Solo PDF</p>
                                <p class="text-[10px] text-default-500">exportación</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                                <i class="ti ti-filter text-lg text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Filtros</p>
                                <p class="text-[10px] text-default-500">empresa, sede, cargo</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                                <i class="ti ti-user-search text-lg text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Búsqueda</p>
                                <p class="text-[10px] text-default-500">por nombre o DNI</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side: decorative icon -->
                <div class="hidden xl:flex flex-col items-center justify-center shrink-0">
                    <div
                        class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center">
                        <i class="ti ti-file-type-pdf text-4xl text-primary/60"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Barra de exportación --}}
    <div class="mb-6">
        <div
            class="flex flex-col lg:flex-row items-stretch lg:items-center gap-4 rounded-2xl border border-default-200/60 bg-white/95 backdrop-blur shadow-sm shadow-default-900/5 px-5 py-4">
            <div class="flex items-center gap-2 shrink-0">
                <div
                    class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-md shadow-primary/20">
                    <i class="ti ti-file-type-pdf text-xl text-white"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-default-900 leading-tight">Exportar exámenes</p>
                    <p class="text-[10px] text-default-500 leading-4">
                        Se requiere 1 examen y al menos 1 persona
                    </p>
                </div>
            </div>

            <div class="flex-1 flex flex-col sm:flex-row gap-3 sm:gap-4 sm:items-center min-w-0">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-default-400 mb-1">Examen</p>
                    <template x-if="examenSeleccionadoInfo">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <i class="ti ti-circle-check text-primary shrink-0"></i>
                            <span class="text-xs font-medium text-default-700 truncate"
                                x-text="examenSeleccionadoInfo.quiz_name"></span>
                        </div>
                    </template>
                    <template x-if="!examenSeleccionadoInfo">
                        <span class="text-xs text-default-400 italic">Ningún examen seleccionado</span>
                    </template>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-default-400 mb-1">Personal</p>
                    <template x-if="seleccionadosPersonal.length > 0">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="ti ti-circle-check text-green-600 shrink-0"></i>
                            <span class="text-xs font-medium text-default-700 truncate">
                                <span x-text="personalCompleto()"></span> persona(s)
                                <span class="text-default-500 font-normal"
                                    x-text="'· ' + personalSeleccionadoDetalle.slice(0, 2).map(p => p.nombre_completo).join(', ') + (personalSeleccionadoDetalle.length > 2 ? '…' : '')"></span>
                            </span>
                        </div>
                    </template>
                    <template x-if="seleccionadosPersonal.length === 0">
                        <span class="text-xs text-default-400 italic">Ninguna persona seleccionada</span>
                    </template>
                </div>
            </div>

            <button type="button" @click="exportar()" :disabled="!puedeExportar || exportandoPDF"
                :class="puedeExportar && !exportandoPDF
                    ? 'bg-primary hover:bg-primary/90 text-white shadow-md shadow-primary/25 cursor-pointer active:scale-[0.98]'
                    : 'bg-default-100 text-default-400 cursor-not-allowed'"
                class="inline-flex items-center justify-center gap-2 px-5 h-11 rounded-xl text-sm font-bold transition-all shrink-0">
                <i class="ti text-lg" :class="exportandoPDF ? 'ti-loader animate-spin' : 'ti-eye'"></i>
                <span x-text="exportandoPDF ? 'Generando...' : 'Previsualizar'"></span>
            </button>
        </div>
    </div>

    {{-- Columnas: Listado de cursos + Listado de personal --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        {{-- Columna: Listado de cursos --}}
        <div class="relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="p-6 flex flex-col min-h-[520px]">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-md shadow-primary/20">
                            <i class="ti ti-books text-xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-default-900 leading-tight">Listado de cursos</h3>
                            <p class="text-xs text-default-500 mt-0.5">Cursos y exámenes disponibles para exportación</p>
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-primary/10 text-primary text-[11px] font-bold whitespace-nowrap">
                        <i class="ti ti-books text-[10px]"></i>
                        <span x-text="cursosFiltrados.length" class="font-extrabold"></span> curso(s)
                    </span>
                </div>

                <div class="flex items-center gap-2 mb-4">
                    <div class="relative flex-1 max-w-xs">
                        <input type="text" x-model="searchCurso" placeholder="Buscar por curso o examen..."
                            @input="page = 1"
                            class="w-full h-10 pl-10 pr-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" />
                    </div>
                    <select x-model="selectedYear" @change="page = 1"
                        class="h-10 px-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer flex-1">
                        <option value="">Todos los años</option>
                        <template x-for="anio in anios" :key="anio">
                            <option :value="anio" x-text="anio"></option>
                        </template>
                    </select>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar min-h-0 space-y-2 border border-default-200/60 rounded-xl p-2">
                    <template x-if="loadingCursos">
                        <div class="flex flex-col items-center justify-center py-12 text-default-400 gap-2">
                            <i class="ti ti-loader animate-spin text-2xl"></i>
                            <span class="text-sm">Cargando cursos...</span>
                        </div>
                    </template>

                    <template x-if="!loadingCursos && errorCursos">
                        <div class="flex flex-col items-center justify-center py-12 text-default-400 gap-2">
                            <i class="ti ti-cloud-off text-2xl"></i>
                            <span class="text-sm">No se pudieron cargar los cursos.</span>
                            <button type="button" @click="cargarCursos()"
                                class="text-xs text-primary font-medium hover:underline mt-1">Reintentar</button>
                        </div>
                    </template>

                    <template x-if="!loadingCursos && !errorCursos && cursosFiltrados.length === 0">
                        <div class="flex flex-col items-center justify-center py-12 text-default-400 gap-2">
                            <i class="ti ti-search-off text-2xl"></i>
                            <span class="text-sm">
                                Sin resultados para los filtros actuales.
                                <span x-show="searchCurso" class="font-medium">"<span x-text="searchCurso"></span>"</span>
                            </span>
                        </div>
                    </template>

                    <template x-for="curso in cursosPaginados" :key="curso.course_id">
                        <div class="border border-default-200/60 rounded-xl overflow-hidden">
                            {{-- Cabecera del curso (expandible) --}}
                            <div class="flex items-center gap-2.5 px-3.5 py-3 transition-colors"
                                :class="expandedCourseId === curso.course_id
                                    ? 'bg-primary/5'
                                    : (cursoBloqueado(curso)
                                        ? 'bg-default-50/50 opacity-60 cursor-not-allowed'
                                        : 'bg-white cursor-pointer hover:bg-default-50')"
                                @click="cursoBloqueado(curso) || toggleExpandirCurso(curso.course_id)">
                                <i class="ti shrink-0 text-sm transition-transform"
                                    :class="expandedCourseId === curso.course_id ? 'ti-chevron-down text-primary' : 'ti-chevron-right text-default-400'"></i>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-default-900 leading-tight truncate"
                                        x-text="curso.course_name"></p>
                                    <p class="text-xs text-default-500 mt-0.5">
                                        <span x-text="curso.quizzes.length + ' examen(es)'"></span>
                                        <span x-show="curso.quizzes.some(q => esSeleccionado(curso, q))"
                                            class="text-primary font-semibold">
                                            · 1 seleccionado
                                        </span>
                                    </p>
                                </div>
                                <i class="ti ti-lock shrink-0 text-sm text-default-300"
                                    x-show="cursoBloqueado(curso)"></i>
                            </div>

                            {{-- Exámenes del curso --}}
                            <template x-if="expandedCourseId === curso.course_id">
                                <div class="border-t border-default-100 divide-y divide-default-100">
                                    <template x-for="quiz in curso.quizzes" :key="quiz.quiz_id">
                                        <label class="flex items-center gap-2.5 px-4 py-2.5 transition-colors select-none"
                                            :class="esSeleccionado(curso, quiz)
                                                ? 'bg-primary/5 cursor-pointer'
                                                : (hayExamenSeleccionado
                                                    ? 'opacity-50 cursor-not-allowed'
                                                    : 'cursor-pointer hover:bg-default-50')">
                                            <input type="radio" name="examen-seleccionado"
                                                :checked="esSeleccionado(curso, quiz)"
                                                :disabled="hayExamenSeleccionado && !esSeleccionado(curso, quiz)"
                                                @change="seleccionarExamen(curso, quiz)" class="sr-only" />
                                            <div class="w-4 h-4 shrink-0 rounded-full border-2 flex items-center justify-center transition-all"
                                                :class="esSeleccionado(curso, quiz) ? 'border-primary' : 'border-default-300 bg-white'">
                                                <div class="w-1.5 h-1.5 rounded-full bg-primary"
                                                    x-show="esSeleccionado(curso, quiz)"></div>
                                            </div>
                                            <span class="text-sm truncate min-w-0"
                                                :class="esSeleccionado(curso, quiz) ? 'text-primary font-medium' : 'text-default-700'"
                                                x-text="quiz.quiz_name"></span>
                                            <i class="ti ti-lock shrink-0 text-xs text-default-300 ml-auto"
                                                x-show="hayExamenSeleccionado && !esSeleccionado(curso, quiz)"></i>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="mt-4 pt-4 border-t border-default-100 space-y-3"
                    x-show="!loadingCursos && !errorCursos">
                    {{-- Paginación client-side (10 por página) --}}
                    <div class="flex items-center justify-between gap-3" x-show="totalPaginas > 1">
                        <p class="text-xs text-default-500">
                            Mostrando <span class="font-semibold text-default-700" x-text="textoMostrando"></span>
                        </p>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="irAPagina(page - 1)" :disabled="page === 1"
                                :class="page === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-default-100'"
                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-default-200 text-default-500 transition-colors">
                                <i class="ti ti-chevron-left text-sm"></i>
                            </button>
                            <template x-for="p in paginas" :key="p">
                                <button type="button" @click="irAPagina(p)"
                                    class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-xs font-semibold transition-colors"
                                    :class="page === p ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-default-600 border border-default-200 hover:bg-default-100'"
                                    x-text="p"></button>
                            </template>
                            <button type="button" @click="irAPagina(page + 1)"
                                :disabled="page === totalPaginas"
                                :class="page === totalPaginas ? 'opacity-40 cursor-not-allowed' : 'hover:bg-default-100'"
                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-default-200 text-default-500 transition-colors">
                                <i class="ti ti-chevron-right text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-default-500">
                            <template x-if="totalSeleccionados === 1">
                                <span><i class="ti ti-circle-check text-primary"></i> 1 examen seleccionado</span>
                            </template>
                            <template x-if="totalSeleccionados === 0">
                                <span>Ningún examen seleccionado</span>
                            </template>
                        </p>
                        <button type="button" @click="seleccionado = ''"
                            :disabled="totalSeleccionados === 0"
                            :class="totalSeleccionados === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-danger/30 hover:bg-danger/5 hover:text-danger'"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-default-200 text-xs font-semibold text-default-500 transition-colors">
                            <i class="ti ti-trash text-sm"></i>
                            Limpiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna: Listado de personal --}}
        <div class="relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="p-6 flex flex-col min-h-[520px]">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-green-400 flex items-center justify-center shadow-md shadow-green-500/20">
                            <i class="ti ti-users text-xl text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-default-900 leading-tight">Listado de personal</h3>
                            <p class="text-xs text-default-500 mt-0.5">Personal vigente y no vigente para exportar sus exámenes</p>
                        </div>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-500/10 text-green-600 text-[11px] font-bold whitespace-nowrap">
                        <i class="ti ti-users text-[10px]"></i>
                        <span x-text="personalFiltrado.length" class="font-extrabold"></span> personales
                    </span>
                </div>

                <div class="mb-3 flex items-center gap-2">
                    <div class="relative flex-1 max-w-xs">
                        <input type="text" x-model.debounce.250ms="busquedaPersonal" placeholder="Buscar por nombre o DNI"
                            @input.debounce.250ms="personalPage = 1"
                            class="w-full h-10 pl-10 pr-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all" />
                    </div>
                    <select x-model="filtroVigencia" @change="personalPage = 1"
                        class="h-10 px-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer flex-1">
                        <option value="">Todos (vigente y no vigente)</option>
                        <option value="1">Solo vigente</option>
                        <option value="0">Solo no vigente</option>
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-2 mb-4">
                    <select x-model="filtroSucursal" @change="personalPage = 1"
                        class="h-10 px-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        <option value="">Todas las sucursales</option>
                        <template x-for="s in sucursales" :key="s">
                            <option :value="s" x-text="s"></option>
                        </template>
                    </select>
                    <select x-model="filtroTipoTrabajador" @change="personalPage = 1"
                        class="h-10 px-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        <option value="">Todos los tipos</option>
                        <template x-for="t in tiposTrabajador" :key="t">
                            <option :value="t" x-text="t"></option>
                        </template>
                    </select>
                    <select x-model="filtroCargo" @change="personalPage = 1"
                        class="h-10 px-3 text-sm bg-default-50 border border-default-200 rounded-xl text-default-900 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                        <option value="">Todos los cargos</option>
                        <template x-for="c in cargos" :key="c">
                            <option :value="c" x-text="c"></option>
                        </template>
                    </select>
                </div>

                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <button type="button"
                            @click="$dispatch('abrir-importar-dnis', { target: 'examenes', cursoSeleccionado: true })"
                            class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold hover:bg-emerald-100 transition-colors cursor-pointer shrink-0">
                            <i class="ti ti-file-spreadsheet text-sm"></i>
                            Importar Excel
                        </button>
                        <button type="button" @click="$dispatch('descargar-formato-dnis')"
                            title="Descargar formato Excel"
                            class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg bg-default-100 text-default-600 border border-default-200 text-[11px] font-semibold hover:bg-default-200 transition-colors cursor-pointer shrink-0">
                            <i class="ti ti-download text-sm"></i>
                            Formato
                        </button>
                    </div>
                    <span class="text-[11px] text-default-500" x-show="importados.length > 0" x-cloak>
                        <i class="ti ti-circle-check text-emerald-600"></i>
                        <span class="font-semibold text-default-700" x-text="importados.length"></span>
                        importado(s)
                    </span>
                </div>

                {{-- DNIs importados desde Excel --}}
                <template x-if="importados.length > 0">
                    <div class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50/60 px-3 py-2">
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
                        <div class="mt-1.5 flex flex-wrap gap-1 max-h-16 overflow-y-auto custom-scrollbar">
                            <template x-for="imp in importados" :key="'importado-examen-' + imp.dni">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white border border-default-200 text-[10px] text-default-600"
                                    :title="(imp.nombre_completo || '') + ' · ' + imp.dni">
                                    <span class="font-mono" x-text="imp.dni"></span>
                                    <span class="text-default-400 truncate max-w-[90px]" x-text="imp.nombre_completo || ''"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="flex-1 overflow-y-auto custom-scrollbar min-h-0 space-y-2 border border-default-200/60 rounded-xl p-2">
                    <template x-if="loadingPersonal">
                        <div class="flex flex-col items-center justify-center py-12 text-default-400 gap-2">
                            <i class="ti ti-loader animate-spin text-2xl"></i>
                            <span class="text-sm">Cargando personal...</span>
                        </div>
                    </template>

                    <template x-if="!loadingPersonal && errorPersonal">
                        <div class="flex flex-col items-center justify-center py-12 text-default-400 gap-2">
                            <i class="ti ti-cloud-off text-2xl"></i>
                            <span class="text-sm">No se pudo cargar el personal.</span>
                            <button type="button" @click="cargarPersonal()"
                                class="text-xs text-primary font-medium hover:underline mt-1">Reintentar</button>
                        </div>
                    </template>

                    <template x-if="!loadingPersonal && !errorPersonal && personalFiltrado.length === 0">
                        <div class="flex flex-col items-center justify-center py-12 text-default-400 gap-2">
                            <i class="ti ti-search-off text-2xl"></i>
                            <span class="text-sm">Sin resultados para los filtros actuales.</span>
                        </div>
                    </template>

                    <template x-for="persona in personalPaginado" :key="clavePersonal(persona)">
                        <div class="border rounded-xl px-3.5 py-3 flex items-center gap-3 transition-colors select-none"
                            :class="estaSeleccionado(persona)
                                ? 'border-primary/40 bg-primary/5'
                                : 'cursor-pointer hover:bg-default-50'"
                            @click="toggleSeleccionarPersonal(persona)">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                                :class="estaSeleccionado(persona) ? 'bg-primary text-white' : 'bg-green-500/10'">
                                <i class="ti text-base"
                                    :class="estaSeleccionado(persona) ? 'ti-circle-check' : 'ti-user'"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-default-900 leading-tight truncate"
                                    x-text="persona.nombre_completo"></p>
                                <p class="text-xs text-default-500 mt-0.5 truncate">
                                    <span x-text="persona.cargo || 'Sin cargo'"></span>
                                    <template x-if="persona.sucursal">
                                        <span><i class="ti ti-point-filled text-[7px] align-middle mx-0.5"></i><span x-text="persona.sucursal"></span></span>
                                    </template>
                                    <template x-if="persona.dni">
                                        <span><i class="ti ti-point-filled text-[7px] align-middle mx-0.5"></i>DNI: <span x-text="persona.dni"></span></span>
                                    </template>
                                </p>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap"
                                :class="persona.vigente ? 'bg-green-500/10 text-green-600' : 'bg-red-500/10 text-red-500'">
                                <span class="w-1.5 h-1.5 rounded-full"
                                    :class="persona.vigente ? 'bg-green-500' : 'bg-red-500'"></span>
                                <span x-text="persona.vigente ? 'Vigente' : 'No vigente'"></span>
                            </span>
                        </div>
                    </template>
                </div>

                <div class="mt-4 pt-4 border-t border-default-100 space-y-3"
                    x-show="!loadingPersonal && !errorPersonal">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-default-500">
                            <template x-if="seleccionadosPersonal.length > 0">
                                <span><i class="ti ti-circle-check text-primary"></i>
                                    <span class="font-semibold text-default-700"
                                        x-text="personalCompleto()"></span>
                                    persona(s) seleccionada(s)
                                </span>
                            </template>
                            <template x-if="seleccionadosPersonal.length === 0">
                                <span>Ninguna persona seleccionada</span>
                            </template>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="seleccionarTodosFiltrados()"
                                :disabled="personalFiltrado.length === 0"
                                :class="personalFiltrado.length === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-primary/30 hover:bg-primary/5 hover:text-primary'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-default-200 text-xs font-semibold text-default-500 transition-colors">
                                <i class="ti text-sm" :class="todosFiltradosSeleccionados ? 'ti-checkbox' : 'ti-square-check'"></i>
                                <span x-text="todosFiltradosSeleccionados ? 'Deseleccionar filtrados' : 'Seleccionar todos (' + personalFiltrado.length + ')'"></span>
                            </button>
                            <button type="button" @click="limpiarSeleccion()"
                                :disabled="seleccionadosPersonal.length === 0"
                                :class="seleccionadosPersonal.length === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-danger/30 hover:bg-danger/5 hover:text-danger'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-default-200 text-xs font-semibold text-default-500 transition-colors">
                                <i class="ti ti-trash text-sm"></i>
                                Limpiar selección
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3" x-show="totalPaginasPersonal > 1">
                        <p class="text-xs text-default-500">
                            Mostrando <span class="font-semibold text-default-700" x-text="textoMostrandoPersonal"></span>
                        </p>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="irAPaginaPersonal(personalPage - 1)"
                                :disabled="personalPage === 1"
                                :class="personalPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-default-100'"
                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-default-200 text-default-500 transition-colors">
                                <i class="ti ti-chevron-left text-sm"></i>
                            </button>
                            <template x-for="p in paginasPersonal" :key="p">
                                <button type="button" @click="irAPaginaPersonal(p)"
                                    class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-xs font-semibold transition-colors"
                                    :class="personalPage === p ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'text-default-600 border border-default-200 hover:bg-default-100'"
                                    x-text="p"></button>
                            </template>
                            <button type="button" @click="irAPaginaPersonal(personalPage + 1)"
                                :disabled="personalPage === totalPaginasPersonal"
                                :class="personalPage === totalPaginasPersonal ? 'opacity-40 cursor-not-allowed' : 'hover:bg-default-100'"
                                class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-default-200 text-default-500 transition-colors">
                                <i class="ti ti-chevron-right text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Aviso previo: personal que no desarrolló el examen --}}
    <div x-show="mostrarModalPendientes" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @keydown.escape.window="mostrarModalPendientes && continuarPreview()">

        <div class="absolute inset-0 bg-default-900/60 backdrop-blur-sm"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden"
            @click.stop>
            {{-- Header --}}
            <div class="flex items-start gap-3 px-6 py-4 border-b border-default-200 shrink-0">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center shrink-0">
                    <i class="ti ti-alert-triangle text-xl text-amber-600"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-bold text-default-900 leading-tight">
                        Personal que no desarrolló el examen
                    </h3>
                    <p class="text-xs text-default-500 leading-5 mt-0.5"
                        x-text="pendientes.length + (pendientes.length === 1
                            ? ' persona no registra intentos en el examen seleccionado.'
                            : ' personas no registran intentos en el examen seleccionado.')">
                    </p>
                </div>
            </div>

            {{-- Curso / examen --}}
            <div class="px-6 pt-4 shrink-0" x-show="examenSeleccionadoInfo">
                <div class="rounded-xl border border-default-200/60 bg-default-50/60 px-3.5 py-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-default-400">Curso</p>
                    <p class="text-xs font-semibold text-default-800 leading-tight truncate"
                        x-text="examenSeleccionadoInfo?.course_name || '—'"></p>
                    <p class="text-[11px] text-default-500 leading-tight truncate mt-0.5"
                        x-text="examenSeleccionadoInfo?.quiz_name || '—'"></p>
                </div>
            </div>

            {{-- Listado --}}
            <div class="flex-1 overflow-y-auto custom-scrollbar min-h-0 px-6 py-4 space-y-2">
                <template x-for="(persona, index) in pendientes" :key="(persona.dni || '') + '-' + index">
                    <div class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl border border-default-200/60 bg-white">
                        <span class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-600 text-[11px] font-bold flex items-center justify-center shrink-0"
                            x-text="index + 1"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-default-900 leading-tight truncate"
                                x-text="persona.nombre_completo || '—'"></p>
                            <p class="text-xs text-default-500 mt-0.5 truncate">
                                DNI: <span x-text="persona.dni || '—'"></span>
                                <template x-if="persona.cargo">
                                    <span><i class="ti ti-point-filled text-[7px] align-middle mx-0.5"></i><span
                                            x-text="persona.cargo"></span></span>
                                </template>
                                <template x-if="persona.sucursal">
                                    <span><i class="ti ti-point-filled text-[7px] align-middle mx-0.5"></i><span
                                            x-text="persona.sucursal"></span></span>
                                </template>
                            </p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Aviso si no hay vista previa --}}
            <div class="px-6 pb-3 shrink-0" x-show="exitosPendientes.length === 0">
                <div class="flex items-start gap-2 rounded-xl bg-amber-500/10 px-3.5 py-2.5">
                    <i class="ti ti-info-circle text-sm text-amber-600 mt-0.5 shrink-0"></i>
                    <p class="text-[11px] text-amber-700 leading-5">
                        Ningún personal seleccionado desarrolló el examen, por lo que no se generará el ZIP con los PDF.
                    </p>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 px-6 py-4 border-t border-default-200 shrink-0">
                <button type="button" @click="exportarPendientesExcel()"
                    :disabled="generandoExcelPendientes"
                    :class="generandoExcelPendientes ? 'opacity-60 cursor-not-allowed' : 'hover:bg-default-100'"
                    class="inline-flex items-center justify-center gap-2 px-4 h-10 rounded-xl border border-default-200 text-sm font-semibold text-default-600 transition-colors">
                    <i class="ti text-base"
                        :class="generandoExcelPendientes ? 'ti-loader animate-spin' : (pendientesExcelListo ? 'ti-check text-green-600' : 'ti-file-spreadsheet')"></i>
                    <span x-text="generandoExcelPendientes
                        ? 'Generando...'
                        : (pendientesExcelListo ? 'Excel descargado' : 'Exportar Excel')"></span>
                </button>
                <button type="button" @click="continuarPreview()"
                    class="inline-flex items-center justify-center gap-2 px-5 h-10 rounded-xl bg-primary hover:bg-primary/90 text-white text-sm font-bold transition-all shadow-md shadow-primary/25 active:scale-[0.98]">
                    <i class="ti ti-check text-base"></i>
                    <span>Entendido</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal de vista previa del PDF --}}
    <div x-show="mostrarPreview" x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @keydown.escape.window="cerrarPreview()">

        <div class="absolute inset-0 bg-default-900/60 backdrop-blur-sm" @click="cerrarPreview()"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[92vh] flex flex-col overflow-hidden"
            @click.stop>
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-default-200 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i class="ti ti-file-zip text-xl text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-default-900 leading-tight">Vista previa del reporte</h3>
                        <p class="text-xs text-default-500 truncate" x-text="pdfNombre"></p>
                        <p class="text-[11px] text-default-400">Vista previa de todos los exámenes · se descargará un ZIP con un PDF por persona</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="descargarZip()"
                        class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-primary hover:bg-primary/90 text-white text-sm font-bold transition-all shadow-md shadow-primary/25 active:scale-[0.98]">
                        <i class="ti ti-file-zip text-base"></i>
                        Descargar ZIP
                    </button>
                    <button type="button" @click="cerrarPreview()"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-default-200 text-default-500 hover:bg-default-100 transition-colors">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
            </div>

            {{-- Visor PDF --}}
            <div class="flex-1 bg-default-100 min-h-0">
                <template x-if="pdfUrl">
                    <iframe :src="pdfUrl" class="w-full h-full border-0" title="Vista previa del PDF"></iframe>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Importar DNIs desde Excel --}}
    <div x-data="modalImportarDnis" @abrir-importar-dnis.window="abrir($event.detail || {})"
        @descargar-formato-dnis.window="descargarFormato()">
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
                <div class="flex items-center justify-between gap-2 px-6 py-4 border-t border-default-100">
                    <button type="button" @click="descargarFormato()" :disabled="procesando"
                        class="px-3 h-9 inline-flex items-center justify-center gap-1.5 rounded-lg text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-download text-sm"></i>
                        Descargar formato
                    </button>
                    <div class="flex items-center gap-2">
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

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

@vite(['resources/js/app.js'])
@endsection