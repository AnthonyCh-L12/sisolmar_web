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

<div x-data="exportExamenes" class="px-6 py-6">
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
                        <input type="text" x-model="busquedaPersonal" placeholder="Buscar por nombre o DNI"
                            @input="personalPage = 1"
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
                            <button type="button" @click="seleccionadosPersonal = []"
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
                        Ningún personal seleccionado desarrolló el examen, por lo que no se generará la vista previa del PDF.
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
                        <i class="ti ti-file-type-pdf text-xl text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-default-900 leading-tight">Vista previa del reporte</h3>
                        <p class="text-xs text-default-500 truncate" x-text="pdfNombre"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="descargarPdf()"
                        class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-primary hover:bg-primary/90 text-white text-sm font-bold transition-all shadow-md shadow-primary/25 active:scale-[0.98]">
                        <i class="ti ti-download text-base"></i>
                        Descargar PDF
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

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

@vite(['resources/js/app.js'])
@endsection