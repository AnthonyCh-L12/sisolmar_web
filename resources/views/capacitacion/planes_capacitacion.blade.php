@extends('layouts.vertical', ['title' => 'Planes de capacitación'])

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
</style>
@endsection

@include('layouts.shared/page-title', ['subtitle' => 'Capacitación', 'title' => 'Planes de capacitación'])

@section('content')
<div x-data="planesCapacAppNew" class="px-6 py-6">
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
                        <i class="ti ti-books text-sm"></i>
                        Planes de capacitación
                    </div>

                    <!-- Title + Description -->
                    <h1 class="text-3xl font-bold tracking-tight text-default-900 mt-4">
                        Planes de Capacitación
                    </h1>
                    <p class="mt-3 text-sm leading-7 text-default-600 max-w-3xl">
                        Visualice los planes de capacitación disponibles en el sistema y descárguelos en formato PDF
                        para su distribución o archivo.
                    </p>

                    <!-- Quick stats -->
                    <div class="flex items-center gap-6 mt-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                <i class="ti ti-books text-lg text-primary"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Planes</p>
                                <p class="text-[10px] text-default-500">disponibles</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                                <i class="ti ti-file-text text-lg text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">PDF</p>
                                <p class="text-[10px] text-default-500">descargable</p>
                            </div>
                        </div>
                        <div class="w-px h-10 bg-default-200"></div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                                <i class="ti ti-search text-lg text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-default-800">Consulta</p>
                                <p class="text-[10px] text-default-500">rápida</p>
                            </div>
                        </div>
                    </div>


                </div>

                <!-- Right side: decorative icon -->
                <div class="hidden xl:flex flex-col items-center justify-center shrink-0">
                    <div
                        class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center">
                        <i class="ti ti-books text-4xl text-primary/60"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        {{-- Card: Consultas rápidas por plan --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-md shadow-primary/20">
                        <i class="ti ti-search text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-default-100 text-default-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-bookmark text-[9px]"></i>
                        Consultas rápidas por plan
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Consultas rápidas por plan</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Genera consultas por plan y otros parámetros para obtener información rápida y breve.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Cursos por plan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Cursos por mes, año y sistema</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Información de cada curso y contadores</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirConsultas()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors w-full cursor-pointer">
                    <i class="ti ti-arrow-right text-sm"></i>
                    Ir a consultas
                </button>
            </div>
        </div>

        {{-- Card: Plan de Capacitación Estandar (PCE) --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-md shadow-primary/20">
                        <i class="ti ti-file-text text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-default-100 text-default-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-bookmark text-[9px]"></i>
                        PCE
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Plan de Capacitación Estandar (PCE)</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Plan general de capacitaciones estandarizadas para el personal de la organización.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Capacitaciones estandarizadas por área</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Cobertura para todo el personal</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Formato PDF descargable e imprimible</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirPDF()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors w-full cursor-pointer">
                    <i class="ti ti-eye text-sm"></i>
                    Ver plan PCE
                </button>
            </div>
        </div>

        {{-- Card: Plan de Capacitación Aliado (PCA) --}}
        <div
            class="card-hover group relative overflow-hidden rounded-2xl border border-default-200/60 bg-white shadow-sm">
            <div class="relative p-6 flex flex-col h-full">
                <!-- Icon + Badge -->
                <div class="flex items-start justify-between mb-5">
                    <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary to-blue-400 flex items-center justify-center shadow-md shadow-primary/20">
                        <i class="ti ti-file-text text-xl text-white"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-default-100 text-default-600 text-[10px] font-bold uppercase tracking-wider">
                        <i class="ti ti-bookmark text-[9px]"></i>
                        PCA
                    </span>
                </div>

                <!-- Title + Description -->
                <h3 class="text-base font-bold text-default-900 mb-2">Plan de Capacitación Aliado (PCA)</h3>
                <p class="text-sm text-default-500 leading-relaxed mb-4">
                    Plan general de capacitaciones para el personal de un cliente.
                </p>

                <!-- Features -->
                <div class="space-y-2 mb-5 flex-grow">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Capacitaciones estandarizadas por área</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Cobertura para todo el personal de un cliente</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-xs text-primary shrink-0"></i>
                        <span class="text-xs text-default-600">Formato PDF descargable e imprimible</span>
                    </div>
                </div>

                <!-- Button -->
                <button @click="abrirSeleccionCliente()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors w-full cursor-pointer">
                    <i class="ti ti-eye text-sm"></i>
                    Ver plan PCA
                </button>
            </div>
        </div>
    </div>

    {{-- Modal: Selección de cliente para PCA --}}
    <div x-show="openSeleccionCliente" x-cloak
        @keydown.escape.window="cerrarSeleccionCliente()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div class="flex flex-col w-full max-w-lg bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-center py-4 px-6 border-b border-default-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-users text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                            Plan de Capacitación Aliado (PCA)
                        </h3>
                        <p class="text-xs text-default-500">Seleccione el cliente para generar el plan</p>
                    </div>
                </div>
                <button type="button" @click="cerrarSeleccionCliente()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <div class="p-6">
                <template x-if="loadingClientesPCA">
                    <div class="flex flex-col items-center justify-center py-10 text-default-400">
                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                        <p class="text-sm">Cargando clientes...</p>
                    </div>
                </template>

                <template x-if="!loadingClientesPCA">
                    <div>
                        <label class="text-xs font-medium text-default-700 mb-1.5 block">
                            Cliente <span class="text-danger">*</span>
                        </label>
                        <select x-model="selectedClientePCA"
                            class="w-full h-10 px-3 text-sm bg-white border border-default-200 rounded-lg text-default-900 placeholder-default-400 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer">
                            <option value="">Seleccione un cliente</option>
                            <template x-for="c in clientesPCA" :key="c.codigo">
                                <option :value="c.cod_legacy" x-text="c.descripcion"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t border-default-100 bg-default-50/50">
                <button type="button" @click="cerrarSeleccionCliente()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-default-200 text-default-700 text-sm font-medium hover:bg-default-100 transition-colors cursor-pointer">
                    Cancelar
                </button>
                <button type="button" @click="obtenerPDF_PCA()" :disabled="!selectedClientePCA"
                    class="inline-flex items-center gap-1.5 px-5 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 shadow-sm transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="ti ti-file-text text-sm"></i>
                    Obtener PDF
                </button>
            </div>
        </div>
    </div>

    {{-- Modal: Consultas rápidas por plan --}}
    <div x-show="openConsultas" x-cloak
        @keydown.escape.window="cerrarConsultas()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div class="flex flex-col w-full max-w-[85vw] bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            {{-- Header --}}
            <div class="flex justify-between items-center py-4 px-6 border-b border-default-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-search text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-default-900 leading-tight">
                            Consultas rápidas por plan
                        </h3>
                        <p class="text-xs text-default-500" x-text="selectedPlan ? 'Plan seleccionado: ' + selectedPlan : 'Seleccione un plan de la lista para empezar'"></p>
                    </div>
                </div>
                <button type="button" @click="cerrarConsultas()"
                    class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            {{-- Body: sidebar + content --}}
            <div class="flex flex-1 min-h-0">
                {{-- Sidebar: Lista de planes --}}
                <div class="w-56 shrink-0 border-r border-default-200 overflow-y-auto custom-scrollbar bg-default-50/30">
                    <div class="px-4 py-3 border-b border-default-100">
                        <span class="text-xs font-semibold text-default-600 uppercase tracking-wider">Planes de capacitación</span>
                    </div>
                    <template x-if="loadingPlanes">
                        <div class="flex items-center justify-center py-8 text-default-400">
                            <i class="ti ti-loader animate-spin text-lg"></i>
                        </div>
                    </template>
                    <template x-if="!loadingPlanes">
                        <div class="py-2">
                            <template x-for="plan in planes" :key="plan.Codigo">
                                <button type="button"
                                    @click="seleccionarPlan(plan)"
                                    class="w-full text-left px-4 py-3 transition-colors cursor-pointer border-b border-default-100 last:border-b-0"
                                    :class="selectedCodigo === plan.Codigo ? 'bg-primary/10 border-l-2 border-l-primary' : 'hover:bg-default-100'">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold shrink-0"
                                            :class="selectedCodigo === plan.Codigo ? 'bg-primary text-white' : 'bg-default-200 text-default-600'"
                                            x-text="plan.Abreviatura"></div>
                                        <div>
                                            <p class="text-sm font-medium text-default-900" x-text="plan.Nombre"></p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Right panel: Cursos del plan seleccionado --}}
                <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                    <template x-if="!selectedCodigo">
                        <div class="flex-1 flex items-center justify-center text-default-400">
                            <div class="text-center py-16">
                                <i class="ti ti-folder-open text-4xl mb-3 block"></i>
                                <p class="text-sm font-medium">Seleccione un plan</p>
                                <p class="text-xs text-default-300 mt-1">Elija un plan de la lista para ver sus cursos</p>
                            </div>
                        </div>
                    </template>

                    <template x-if="selectedCodigo">
                        <div class="flex flex-col flex-1 min-h-0">
                            {{-- Filtros --}}
                            <div class="px-5 py-3 border-b border-default-200 bg-default-50/50 flex items-center gap-3 flex-wrap">
                                <div class="flex items-center gap-2 text-xs text-default-500">
                                    <i class="ti ti-filter text-sm"></i>
                                    <span class="font-medium">Filtrar:</span>
                                </div>
                                <select x-show="selectedAbreviatura !== 'PCA'" x-model="filtroSistema" :disabled="loadingCursos" @change="paginaActual = 1"
                                    class="min-w-[180px] h-8 px-2.5 text-xs bg-white border border-default-200 rounded-lg text-default-700 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                    <option value="" x-text="loadingCursos ? 'Cargando sistemas...' : 'Todos los sistemas'"></option>
                                    <template x-for="s in sistemasUnicos" :key="s">
                                        <option :value="s" x-text="s"></option>
                                    </template>
                                </select>
                                <select x-model="filtroArea" :disabled="loadingCursos" @change="paginaActual = 1"
                                    class="min-w-[230px] h-8 px-2.5 text-xs bg-white border border-default-200 rounded-lg text-default-700 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                    <option value="" x-text="loadingCursos ? 'Cargando áreas...' : 'Todas las áreas'"></option>
                                    <template x-for="a in areasUnicas" :key="a">
                                        <option :value="a" x-text="a"></option>
                                    </template>
                                </select>
                                <select x-show="selectedAbreviatura === 'PCA'" x-model="filtroCliente" :disabled="loadingCursos" @change="paginaActual = 1"
                                    class="min-w-[180px] h-8 px-2.5 text-xs bg-white border border-default-200 rounded-lg text-default-700 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                    <option value="" x-text="loadingCursos ? 'Cargando clientes...' : 'Todos los clientes'"></option>
                                    <template x-for="c in clientesUnicos" :key="c">
                                        <option :value="c" x-text="c"></option>
                                    </template>
                                </select>
                                <select x-model="filtroMes" :disabled="loadingCursos" @change="paginaActual = 1"
                                    class="min-w-[140px] h-8 px-2.5 text-xs bg-white border border-default-200 rounded-lg text-default-700 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                    <option value="" x-text="loadingCursos ? 'Cargando meses...' : 'Todos los meses'"></option>
                                    <template x-for="m in mesesUnicos" :key="m.valor">
                                        <option :value="m.valor" x-text="m.label"></option>
                                    </template>
                                </select>
                                <select x-model="filtroAnio" :disabled="loadingCursos" @change="paginaActual = 1"
                                    class="min-w-[120px] h-8 px-2.5 text-xs bg-white border border-default-200 rounded-lg text-default-700 focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                    <option value="" x-text="loadingCursos ? 'Cargando años...' : 'Todos los años'"></option>
                                    <template x-for="a in aniosUnicos" :key="a">
                                        <option :value="a" x-text="a"></option>
                                    </template>
                                </select>
                                <span class="text-xs text-default-400 ml-auto">
                                    <span class="font-semibold text-default-600" x-text="cursosFiltrados.length"></span> curso(s)
                                </span>
                            </div>

                            {{-- Tabla de cursos --}}
                            <div class="flex-1 overflow-auto custom-scrollbar">
                                <template x-if="loadingCursos">
                                    <div class="flex flex-col items-center justify-center py-16 text-default-400">
                                        <i class="ti ti-loader animate-spin text-2xl mb-2"></i>
                                        <p class="text-sm">Cargando cursos...</p>
                                    </div>
                                </template>

                                <template x-if="!loadingCursos && cursosFiltrados.length === 0">
                                    <div class="flex flex-col items-center justify-center py-16 text-default-400">
                                        <i class="ti ti-search-off text-3xl mb-2"></i>
                                        <p class="text-sm">No se encontraron cursos</p>
                                        <p class="text-xs text-default-300 mt-1">Intente con otros filtros</p>
                                    </div>
                                </template>

                                <template x-if="!loadingCursos && cursosFiltrados.length > 0">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-default-50 border-b border-default-200 sticky top-0 z-10">
                                            <tr>
                                                <th class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider w-12">#</th>
                                                <th class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">Código</th>
                                                <th class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">Curso</th>
                                                <th x-show="selectedAbreviatura === 'PCA'" class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">Cliente</th>
                                                <th x-show="selectedAbreviatura !== 'PCA'" class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">Sistema</th>
                                                <th class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">Área</th>
                                                <th class="px-4 py-3 text-left font-semibold text-default-700 text-xs uppercase tracking-wider">Dirigido a</th>
                                                <th x-show="hayVigentes" class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider">Inicio</th>
                                                <th x-show="hayVigentes" class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider">Cierre</th>
                                                <th class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider">Vigente</th>
                                                <th class="px-4 py-3 text-center font-semibold text-default-700 text-xs uppercase tracking-wider">Creación</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            <template x-for="(curso, i) in cursosPaginados" :key="i">
                                                <tr class="border-b border-default-100 transition-colors"
                                                    :class="i % 2 === 1 ? 'bg-default-50/30' : ''">
                                                    <td class="px-4 py-2.5 text-center text-default-400 text-xs font-mono" x-text="(paginaClamp - 1) * cursosPorPagina + i + 1"></td>
                                                    <td class="px-4 py-2.5 text-default-800 font-medium text-sm" x-text="curso.Codigo"></td>
                                                    <td class="px-4 py-2.5 text-default-800 font-medium text-sm" x-text="curso.Nombre"></td>
                                                    <td x-show="selectedAbreviatura === 'PCA'" class="px-4 py-2.5 text-default-600 text-sm font-semibold" x-text="curso.Cliente"></td>
                                                    <td x-show="selectedAbreviatura !== 'PCA'" class="px-4 py-2.5 text-default-600 text-sm" x-text="curso.Sistema"></td>
                                                    <td class="px-4 py-2.5 text-default-600 text-sm" x-text="curso.Area"></td>
                                                    <td class="px-4 py-2.5 text-default-600 text-sm" x-text="curso.Dirigido"></td>
                                                    <td x-show="curso.Vigente" class="px-4 py-2.5 text-default-600 text-sm text-center whitespace-nowrap" x-text="curso.Fecha_Inicio ?? 'Sin fecha'"></td>
                                                    <td x-show="curso.Vigente" class="px-4 py-2.5 text-default-600 text-sm text-center whitespace-nowrap" x-text="curso.Fecha_Cierre ?? 'Sin fecha'"></td>
                                                    <td class="px-4 py-2.5 text-center">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                                            :class="curso.Vigente ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                                            <span class="w-1.5 h-1.5 rounded-full" :class="curso.Vigente ? 'bg-green-500' : 'bg-red-500'"></span>
                                                            <span x-text="curso.Vigente ? 'Aperturado' : 'Sin aperturar'"></span>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2.5 text-default-600 text-sm text-center whitespace-nowrap" x-text="curso.Fecha_Creacion ?? 'Sin fecha'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </template>
                            </div>

                            {{-- Paginación --}}
                            <div x-show="cursosFiltrados.length > 0"
                                class="px-5 py-3 border-t border-default-200 bg-default-50/50 flex items-center justify-between gap-3 flex-wrap">
                                <div class="flex items-center gap-3 text-xs text-default-500">
                                    <span>
                                        Mostrando <span class="font-semibold text-default-700" x-text="cursosPaginados.length"></span> de
                                        <span class="font-semibold text-default-700" x-text="cursosFiltrados.length"></span> curso(s)
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" @click="irAPagina(paginaActual - 1)" :disabled="paginaClamp <= 1"
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-default-200 bg-white text-default-600 text-xs font-semibold hover:bg-default-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                        <i class="ti ti-chevron-left text-sm"></i>
                                    </button>
                                    <template x-for="n in paginasVisibles" :key="n">
                                        <button type="button" @click="irAPagina(n)"
                                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                                            :class="n === paginaClamp ? 'bg-primary text-white shadow-sm' : 'bg-white border border-default-200 text-default-600 hover:bg-default-100'"
                                            x-text="n"></button>
                                    </template>
                                    <button type="button" @click="irAPagina(paginaActual + 1)" :disabled="paginaClamp >= totalPaginas"
                                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-default-200 bg-white text-default-600 text-xs font-semibold hover:bg-default-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                        <i class="ti ti-chevron-right text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-default-100 bg-default-50/50">
                <button type="button" @click="cerrarConsultas()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-default-200 text-default-700 text-sm font-medium hover:bg-default-100 transition-colors cursor-pointer">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    {{-- Modal previsualización PDF --}}
    <div x-show="open" x-cloak
        @keydown.escape.window="cerrarPCE_PDF()" class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="background: rgba(36,39,70,0.45);">

        <div class="flex flex-col w-full max-w-7xl h-[93vh] bg-white rounded-2xl shadow-2xl shadow-primary/10 border border-default-200 overflow-hidden transition-all duration-300"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex justify-between items-center py-4 px-6 border-b border-default-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center text-white shadow-sm shrink-0">
                        <i class="ti ti-file-text text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-default-900 leading-tight" x-text="pdfTitulo"></h3>
                        <p class="text-xs text-default-500">Previsualización del documento</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="window.open(pdfUrl)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary/90 transition-colors cursor-pointer">
                        <i class="ti ti-external-link text-sm"></i>
                        Abrir en ventana
                    </button>
                    <a :href="pdfUrl" :download="pdfDownloadNombre"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-500 text-white text-xs font-semibold hover:bg-green-600 transition-colors">
                        <i class="ti ti-download text-sm"></i>
                        Descargar
                    </a>
                    <button type="button" @click="cerrar()"
                        class="flex-shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 bg-default-50 flex items-center justify-center">
                <template x-if="pdfUrl">
                    <iframe :src="pdfUrl" class="w-full h-full border-0" :title="pdfTitulo"></iframe>
                </template>
                <template x-if="!pdfUrl">
                    <div class="text-default-500 text-sm">Generando PDF...</div>
                </template>
            </div>
        </div>
    </div>
</div>
@vite('resources/js/app.js')
@endsection