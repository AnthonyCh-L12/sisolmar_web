@extends('layouts.vertical', ['title' => 'Carga de Archivos'])

@section('content')

@include('layouts.shared/page-title', ['subtitle' => 'File Control', 'title' => 'Carga de Archivos', 'headingClass' => 'lg:-translate-x-[7px]'])

<style>
    @media (min-width: 1024px) {
        #chargefile-page { position: relative; left: -7px; }
    }
    #chargefile-page .rep-card {
        position: relative; overflow: hidden; background: #fff; border: 1px solid #e5e7eb;
        border-radius: .75rem; padding: .65rem .85rem; min-width: 118px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
    }
    #chargefile-page .rep-card .rep-label {
        display: flex; align-items: center; gap: .35rem; font-size: 9px;
        font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
    }
    #chargefile-page .rep-card .rep-value {
        display: block; font-size: 1.35rem; line-height: 1.15; font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    #chargefile-page .rep-bar {
        margin-top: .35rem; height: 5px; width: 100%; background: #e5e7eb;
        border-radius: 999px; overflow: hidden;
    }
    #chargefile-page .rep-bar > i { display: block; height: 100%; width: 0; border-radius: 999px; transition: width .8s cubic-bezier(.4, 0, .2, 1); }
    #chargefile-page .rep-toolbar {
        background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        border: 1px solid #e2e8f0; border-radius: .75rem; padding: .75rem .85rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    #chargefile-page .rep-toolbar .form-select,
    #chargefile-page .rep-toolbar input[type="text"] {
        background: #fff; border: 1px solid #d1d5db; border-radius: .5rem;
        font-size: .8125rem; transition: border-color .15s ease, box-shadow .15s ease;
    }
    #chargefile-page .rep-toolbar .form-select:focus,
    #chargefile-page .rep-toolbar input[type="text"]:focus {
        border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); outline: none;
    }
    #chargefile-page #buscarPersonal { padding: .5rem 2.25rem; }
    #tblDocs .tabulator-cell[tabulator-field="documento"],
    #tblDocs .tabulator-cell[tabulator-field="tipo_folio"],
    #tblDocs .tabulator-cell[tabulator-field="fecha_emision"],
    #tblDocs .tabulator-cell[tabulator-field="fecha_caducidad"] {
        -webkit-user-select: none;
        user-select: none;
    }
</style>

{{-- =====================================================================
     PANEL PRINCIPAL: Listado de personal + Folios / Legajos
     ===================================================================== --}}
<div id="chargefile-page" class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-8">

    {{-- ── CARD: Listado de Personal ────────────────────────────────────── --}}
    <div class="card overflow-hidden lg:col-span-12">
        <div class="card-header border-b border-gray-100 pb-4">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <h4 class="text-lg font-bold text-primary uppercase">Listado de Personal</h4>
                    @if ($tipoUsuario == 5 || $tipoUsuario == 2)
                        <button type="button"
                                class="btn bg-info text-white btn-sm px-3 py-1.5 rounded-lg text-xs"
                                data-accion="abrir-reporte-avances">
                            Reporte de avances
                        </button>
                    @endif
                </div>

                {{-- Tarjetas de Indicadores --}}
                <div class="flex flex-wrap gap-2.5">
                    <div class="rep-card">
                        <div class="rep-label text-slate-400"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Total</div>
                        <span id="countTotal" class="rep-value text-slate-800">0</span>
                        <div class="rep-bar bg-slate-100"><i id="barTotal" class="bg-slate-500"></i></div>
                    </div>
                    <div class="rep-card" style="border-color:#bbf7d0;">
                        <div class="rep-label text-emerald-600"><i class='bx bx-check-circle'></i> Vigentes</div>
                        <span id="countVigentes" class="rep-value text-emerald-700">0</span>
                        <div class="rep-bar bg-emerald-100"><i id="barVigentes" class="bg-emerald-500"></i></div>
                    </div>
                    <div class="rep-card" style="border-color:#fecaca;">
                        <div class="rep-label text-red-500"><i class='bx bx-x-circle'></i> Cesados</div>
                        <span id="countCesados" class="rep-value text-red-600">0</span>
                        <div class="rep-bar bg-red-100"><i id="barCesados" class="bg-red-400"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 pt-4 pb-2 space-y-3">

            {{-- Filtros con el mismo formato de Gestión de Folios --}}
            <div class="rep-toolbar">
                <div class="relative flex flex-wrap items-center gap-3">
                    {{-- Búsqueda --}}
                    <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                        <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                        <input type="text" id="buscarPersonal" placeholder="Buscar por nombre o DNI..."
                            autocomplete="off" class="w-full text-sm uppercase" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 pt-3 border-t border-slate-200">
                    {{-- Sucursal --}}
                    <div class="flex items-center gap-2">
                        <label for="sucursal" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-map text-gray-400 text-lg'></i>Sucursal</label>
                        <select id="sucursal" class="form-select text-sm w-36 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            @php
                                $haySucursalChimbote = collect($sucursales)->contains(function ($sucursal) {
                                    return str_contains(strtoupper(trim((string) $sucursal->abreviatura)), 'CHIMBOTE');
                                });
                            @endphp
                            <option disabled value="" @if(!$haySucursalChimbote) selected @endif>— Seleccionar —</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->codigo }}" @if(str_contains(strtoupper(trim((string) $sucursal->abreviatura)), 'CHIMBOTE')) selected @endif>{{ $sucursal->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tipo --}}
                    <div class="flex items-center gap-2">
                        <label for="tipo_per" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                        <select id="tipo_per" name="tipo_per" class="form-select text-sm w-48 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            @if ($tipoPerLimitar != 1 && $tipoPerLimitar != 2 && $tipoPerLimitar != 3 && $tipoPerLimitar == 0)
                                <option value="TODOS" selected>Todos</option>
                            @endif

                            @if ($tipoPerLimitar == 0 || $tipoPerLimitar == 2)
                                <option value="OPER_4">Operativo 4°</option>
                                <option value="OPER_5" selected>Operativo 5°</option>
                            @endif

                            @if ($tipoPerLimitar == 0 || $tipoPerLimitar == 1)
                                <option value="ADMIN_4">Administrativo 4°</option>
                                <option value="ADMIN_5">Administrativo 5°</option>
                            @endif
                        </select>
                    </div>

                    {{-- DJ Actualizada (Estado) - OCULTO --}}
                    <div class="hidden items-center gap-2">
                        <label for="filtroDJ" class="text-sm font-medium text-gray-700 whitespace-nowrap">DJ Actualizada:</label>
                        <select id="filtroDJ" name="filtroDJ" class="form-select text-sm w-32 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            <option value="TODOS" selected>Todos</option>
                            <option value="SI">Sí</option>
                            <option value="NO">No</option>
                        </select>
                    </div>

                    {{-- Vigencia --}}
                    <div class="flex items-center gap-2">
                        <label for="filtroVigencia" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-time text-gray-400 text-lg'></i>Vigencia</label>
                        <select id="filtroVigencia" name="vigencia" class="form-select text-sm w-28 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            <option value="">Todos</option>
                            <option value="SI" selected>Sí</option>
                            <option value="NO">No</option>
                        </select>
                    </div>
                    
                </div>
            </div>
        {{-- Tabla de personas --}}
        <div class="px-5 pb-5">
            <div id="tblPersonas" class="w-full"></div>

            {{-- Footer: selector de registros + info + paginación --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
                <div class="flex items-center gap-2">
                    <label for="page-size-personas" class="text-sm text-gray-600">Mostrar</label>
                    <select id="page-size-personas" class="form-select text-sm w-20">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm text-gray-600">registros</span>
                    <div id="tablaInfo" class="text-sm text-gray-500 pl-2 border-l border-gray-200 whitespace-nowrap flex-shrink-0">
                        Cargando...
                    </div>
                </div>
                <div id="tablaPaginacion"></div>
            </div>
        </div>
    </div>

    {{-- ── Trigger oculto: Modal Folios ────────────────────────────── --}}
    <button type="button" class="hidden" id="btn-modal-folios" data-hs-overlay="#modal-folios-personal"></button>

    {{-- ── MODAL: Folios del personal ────────────────────────────────────── --}}
    <div id="modal-folios-personal"
         class="hs-overlay hidden fixed inset-0 z-60 overflow-y-auto transition-all duration-500 pointer-events-none">
        <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100
                    translate-y-10 opacity-0 ease-in-out transition-all duration-500
                    max-w-6xl w-full my-8 mx-auto flex flex-col bg-white shadow-sm rounded-lg pointer-events-auto
                    border border-gray-200">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 nombrePersDocs">Folios de</h3>
                
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-4 border-r pr-4 border-gray-200">
                        <label for="selectTipoFolio" class="text-sm font-medium text-gray-700 whitespace-nowrap">Obligatorio:</label>
                        <select id="selectTipoFolio" class="form-select text-sm py-1.5 w-36 border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500 cursor-pointer">
                            <option value="TODOS">Todos</option>
                            <option value="SI">Sí</option>
                            <option value="NO">No</option>
                        </select>
                    </div>
                    <button type="button" id="btn-modal-folios-close"
                            class="text-gray-500 hover:text-gray-700 transition-colors"
                            data-hs-overlay="#modal-folios-personal">
                        <i class="i-tabler-x text-xl"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 space-y-3">
                <div style="display:grid; grid-template-columns:minmax(10rem, 1fr) auto; align-items:center; gap:0.75rem; width:100%;">
                    <input
                        type="text"
                        id="buscarFolio"
                        placeholder="Buscar..."
                        autocomplete="off"
                        style="width:10rem; min-width:10rem;"
                        class="px-3 py-1.5 text-sm uppercase border border-gray-300 rounded-full
                               focus:outline-none focus:border-blue-500 transition-colors"
                    />
                    <button type="button"
                            id="btn-abrir-agregar-folio"
                            data-hs-overlay="#modal-agregar-folio"
                            class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-200 transition-colors">
                        <i class="i-tabler-plus"></i>
                        Agregar
                    </button>
                </div>
                {{-- Contenedor de la tabla --}}
                <div id="tblDocs" class="w-full"></div>
            </div>
        </div>
    </div>

    {{-- ── MODAL: Crear formulario de folio ────────────────────────────── --}}
    <div id="modal-agregar-folio"
         class="hs-overlay hidden fixed inset-0 z-70 flex items-center justify-center overflow-hidden p-4 transition-all duration-300 pointer-events-none">
        <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100
                    translate-y-4 opacity-0 ease-out transition-all duration-300
                    max-w-5xl w-full h-[88vh] max-h-[88vh] min-h-0 mx-auto flex flex-col bg-white shadow-xl rounded-lg pointer-events-auto
                    border border-gray-200">
            <div class="flex h-[58px] min-h-[58px] shrink-0 items-center justify-between px-5 py-0 border-b border-gray-200">
                <h3 id="titulo-modal-agregar-folio" class="text-base font-semibold text-gray-900">Crear formulario</h3>
                <div class="flex items-center gap-3">
                    <button type="button" id="btn-volver-folio"
                            class="hidden px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        Volver
                    </button>
                    <button type="button" id="btn-guardar-cambios-folio"
                            class="hidden px-3 py-1.5 text-sm font-medium text-white bg-emerald-600 rounded-md hover:bg-emerald-700 transition-colors">
                        Guardar
                    </button>
                    <button type="button" id="btn-modificar-folio"
                            class="hidden px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                        Modificar
                    </button>
                    <button type="button" id="btn-cerrar-modal-agregar-folio" class="text-gray-500 hover:text-gray-700 transition-colors"
                            data-hs-overlay="#modal-agregar-folio" aria-label="Cerrar">
                        <i class="i-tabler-x text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="seleccion-formulario-folio" class="flex h-[82px] min-h-[82px] shrink-0 items-center px-5 py-0">
                <div class="grid w-full grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="filtroTipoFolio" class="block text-sm font-medium text-gray-700 mb-1">Tipo de folio</label>
                    <select id="filtroTipoFolio" class="form-select w-full text-sm py-2 border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Seleccionar tipo</option>
                        @foreach ($tiposFolio as $tipoFolio)
                            <option value="{{ $tipoFolio->codigo }}">{{ $tipoFolio->descripcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filtroFolioTipo" class="block text-sm font-medium text-gray-700 mb-1">Folio</label>
                    <select id="filtroFolioTipo" class="form-select w-full text-sm py-2 border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500" disabled>
                        <option value="">Selecciona un tipo primero</option>
                    </select>
                </div>
                </div>
            </div>
            <div id="formulario-certificado-examen-medico" class="hidden flex-1 min-h-0 px-5 py-4 space-y-4 overflow-y-scroll">
                <fieldset class="border border-gray-300 p-4">
                    <legend id="formulario-folio-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Datos del certificado</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2">
                            <label for="certificado-nombre-personal" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label>
                            <input id="certificado-nombre-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="certificado-documento-personal" class="block text-xs font-medium text-gray-700 mb-1">Documento de identidad</label>
                            <input id="certificado-documento-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="certificado-folio-nombre" class="block text-xs font-medium text-gray-700 mb-1">Certificado</label>
                            <input id="certificado-folio-nombre" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-2">
                            <label for="certificado-entidad" class="block text-xs font-medium text-gray-700 mb-1">Entidad</label>
                            <div class="relative" data-entidad-select>
                                <button type="button" data-entidad-trigger class="form-input w-full text-left flex items-center justify-between">
                                    <span data-entidad-label>Seleccionar entidad</span><i class="i-tabler-chevron-down"></i>
                                </button>
                                <div data-entidad-menu class="hidden absolute left-0 right-0 top-full z-50 mt-1 rounded-md border border-gray-200 bg-white shadow-lg">
                                    <input type="search" data-entidad-busqueda placeholder="Buscar entidad..." class="form-input w-full border-0 border-b border-gray-200 rounded-none text-sm">
                                    <div data-entidad-opciones class="max-h-44 overflow-y-auto p-1"></div>
                                </div>
                                <select id="certificado-entidad" class="hidden" tabindex="-1" aria-hidden="true">
                                    <option value="">Seleccionar entidad</option>
                                    @foreach ($instituciones as $institucion)
                                        <option value="{{ $institucion->codigo }}">{{ $institucion->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="certificado-fecha-emision" class="block text-xs font-medium text-gray-700 mb-1">Fecha de emisión</label>
                            <input id="certificado-fecha-emision" type="date" class="form-input w-full">
                        </div>
                        <div>
                            <label for="certificado-fecha-caducidad" class="block text-xs font-medium text-gray-700 mb-1">Fecha de caducidad</label>
                            <input id="certificado-fecha-caducidad" type="date" class="form-input w-full">
                        </div>
                        <div id="certificado-apto-container" class="md:col-span-2">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Apto</span>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="certificado-apto" value="SI"> Sí</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="certificado-apto" value="NO"> No</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="certificado-apto" value="CON RESTRICCIONES"> Con restricciones</label>
                            </div>
                        </div>
                        <div id="certificado-positivo-container" class="hidden md:col-span-2">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Resultado positivo</span>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="certificado-positivo" value="SI"> Sí</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="certificado-positivo" value="NO"> No</label>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label for="certificado-observacion" class="block text-xs font-medium text-gray-700 mb-1">Observación</label>
                            <textarea id="certificado-observacion" rows="3" class="form-textarea w-full"></textarea>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-4">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="certificado-usuario-registro" class="block text-xs font-medium text-gray-700 mb-1">Usuario registro</label>
                            <input id="certificado-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="certificado-fecha-registro" class="block text-xs font-medium text-gray-700 mb-1">Fecha registro</label>
                            <input id="certificado-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="certificado-usuario-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Usuario modificación</label>
                            <input id="certificado-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="certificado-fecha-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Fecha modificación</label>
                            <input id="certificado-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                    </div>
                </fieldset>
            </div>
            <div id="formulario-vacuna-covid" class="hidden flex-1 min-h-0 px-5 py-4 space-y-4 overflow-y-scroll">
                <fieldset class="border border-gray-300 p-4">
                    <legend id="formulario-vacuna-covid-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Datos del certificado</legend>
                    <input id="covid-folio-nombre" type="hidden" value="CARNET DE VACUNA COVID">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2">
                            <label for="covid-nombre-personal" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label>
                            <input id="covid-nombre-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="covid-documento-personal" class="block text-xs font-medium text-gray-700 mb-1">Documento de identidad</label>
                            <input id="covid-documento-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="covid-certificado" class="block text-xs font-medium text-gray-700 mb-1">Certificado</label>
                            <input id="covid-certificado" type="text" value="CERTIFICADO DE VACUNACION" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-2">
                            <label for="covid-entidad" class="block text-xs font-medium text-gray-700 mb-1">Entidad</label>
                            <div class="relative" data-entidad-select>
                                <button type="button" data-entidad-trigger class="form-input w-full text-left flex items-center justify-between">
                                    <span data-entidad-label>Seleccionar entidad</span><i class="i-tabler-chevron-down"></i>
                                </button>
                                <div data-entidad-menu class="hidden absolute left-0 right-0 top-full z-50 mt-1 rounded-md border border-gray-200 bg-white shadow-lg">
                                    <input type="search" data-entidad-busqueda placeholder="Buscar entidad..." class="form-input w-full border-0 border-b border-gray-200 rounded-none text-sm">
                                    <div data-entidad-opciones class="max-h-44 overflow-y-auto p-1"></div>
                                </div>
                                <select id="covid-entidad" class="hidden" tabindex="-1" aria-hidden="true">
                                    <option value="">Seleccionar entidad</option>
                                    @foreach ($instituciones as $institucion)
                                        <option value="{{ $institucion->codigo }}">{{ $institucion->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div><label for="covid-dosis-1" class="block text-xs font-medium text-gray-700 mb-1">Primera dosis</label><input id="covid-dosis-1" type="date" class="form-input w-full"></div>
                        <div><label for="covid-dosis-3" class="block text-xs font-medium text-gray-700 mb-1">Tercera dosis</label><input id="covid-dosis-3" type="date" class="form-input w-full"></div>
                        <div><label for="covid-dosis-2" class="block text-xs font-medium text-gray-700 mb-1">Segunda dosis</label><input id="covid-dosis-2" type="date" class="form-input w-full"></div>
                        <div><label for="covid-dosis-4" class="block text-xs font-medium text-gray-700 mb-1">Cuarta dosis</label><input id="covid-dosis-4" type="date" class="form-input w-full"></div>
                        <div class="md:col-span-2">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Apto</span>
                            <div class="flex gap-6">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="covid-apto" value="SI"> Sí</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="covid-apto" value="NO"> No</label>
                            </div>
                        </div>
                        <div class="md:col-span-2"><label for="covid-observacion" class="block text-xs font-medium text-gray-700 mb-1">Observación</label><textarea id="covid-observacion" rows="2" class="form-textarea w-full"></textarea></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-4">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="covid-usuario-registro" class="block text-xs font-medium text-gray-700 mb-1">Usuario registro</label><input id="covid-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="covid-fecha-registro" class="block text-xs font-medium text-gray-700 mb-1">Fecha registro</label><input id="covid-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="covid-usuario-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Usuario modificación</label><input id="covid-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="covid-fecha-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Fecha modificación</label><input id="covid-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
            </div>
            <div id="formulario-visita-domiciliaria" class="hidden flex-1 min-h-0 px-5 py-2 space-y-3 overflow-y-scroll">
                <input id="dom-folio-nombre" type="hidden" value="VERIFICACION DOMICILIARIA">
                <fieldset class="border border-gray-300 p-3">
                    <legend id="dom-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Verificación domiciliaria</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div><label for="dom-fecha" class="block text-xs font-medium text-gray-700 mb-1">Fecha</label><input id="dom-fecha" type="date" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos personales</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2"><label for="dom-nombre" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label><input id="dom-nombre" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-documento" class="block text-xs font-medium text-gray-700 mb-1">Documento de identidad</label><input id="dom-documento" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-cargo" class="block text-xs font-medium text-gray-700 mb-1">Cargo</label><input id="dom-cargo" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div class="md:col-span-2"><label for="dom-domicilio" class="block text-xs font-medium text-gray-700 mb-1">Domicilio</label><input id="dom-domicilio" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-ubicacion" class="block text-xs font-medium text-gray-700 mb-1">Departamento / Provincia / Distrito</label><input id="dom-ubicacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-telefono" class="block text-xs font-medium text-gray-700 mb-1">Teléfono</label><input id="dom-telefono" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-estado-civil" class="block text-xs font-medium text-gray-700 mb-1">Estado civil</label><input id="dom-estado-civil" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos de la cónyuge</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="dom-conyuge-nombre" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label><input id="dom-conyuge-nombre" type="text" class="form-input w-full"></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Comparten el domicilio</span><div class="flex gap-5"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-conyuge-comparte" value="SI"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-conyuge-comparte" value="NO"> No</label></div></div>
                        <div><label for="dom-conyuge-profesion" class="block text-xs font-medium text-gray-700 mb-1">Profesión / Ocupación</label><input id="dom-conyuge-profesion" type="text" class="form-input w-full"></div>
                        <div><label for="dom-conyuge-empresa" class="block text-xs font-medium text-gray-700 mb-1">Empresa</label><input id="dom-conyuge-empresa" type="text" class="form-input w-full"></div>
                        <div><label for="dom-conyuge-telefono" class="block text-xs font-medium text-gray-700 mb-1">Teléfono</label><input id="dom-conyuge-telefono" type="text" class="form-input w-full"></div>
                        <div class="md:col-span-2"><label for="dom-conyuge-salud" class="block text-xs font-medium text-gray-700 mb-1">Problemas de salud</label><input id="dom-conyuge-salud" type="text" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Vulnerabilidad en el acceso y entorno a la vivienda</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Vías de acceso</span><div class="flex gap-5"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-vias" value="SI"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-vias" value="NO"> No</label></div></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Iluminación</span><div class="flex flex-wrap gap-4"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-iluminacion" value="1"> Buena</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-iluminacion" value="2"> Mala</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-iluminacion" value="3"> Regular</label></div></div>
                        <div class="md:col-span-2"><label for="dom-entorno" class="block text-xs font-medium text-gray-700 mb-1">Describir el entorno</label><input id="dom-entorno" type="text" class="form-input w-full"></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Puesto de policía o vigilancia cerca</span><div class="flex gap-5"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-vigilancia" value="SI"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-vigilancia" value="NO"> No</label></div></div>
                        <div><label for="dom-otros-acceso" class="block text-xs font-medium text-gray-700 mb-1">Otras observaciones</label><input id="dom-otros-acceso" type="text" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Vulnerabilidad de la vivienda</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Material de construcción</span><div class="flex gap-5"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-material" value="1"> Noble</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-material" value="2"> Rústico</label></div></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Seguridad en puertas, ventanas y techos</span><div class="flex flex-wrap gap-4"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-seguridad" value="1"> Buena</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-seguridad" value="2"> Mala</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-seguridad" value="3"> Regular</label></div></div>
                        <div><label for="dom-casa-telefono" class="block text-xs font-medium text-gray-700 mb-1">Teléfono u otro medio de comunicación</label><input id="dom-casa-telefono" type="text" class="form-input w-full"></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Espacio para quienes viven allí</span><div class="flex flex-wrap gap-3"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-espacio" value="1"> Muy cómodo</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-espacio" value="2"> Cómodo</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-espacio" value="3"> Incómodo</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-espacio" value="4"> Muy incómodo</label></div></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Información del medio familiar</legend>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div><label for="dom-enfermos" class="block text-xs font-medium text-gray-700 mb-1">Enfermos a su cargo</label><input id="dom-enfermos" type="text" class="form-input w-full"></div>
                        <div><label for="dom-discapacitado" class="block text-xs font-medium text-gray-700 mb-1">Discapacitados a su cargo</label><input id="dom-discapacitado" type="text" class="form-input w-full"></div>
                        <div><label for="dom-drogas" class="block text-xs font-medium text-gray-700 mb-1">Drogadicción y alcoholismo</label><input id="dom-drogas" type="text" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Comodidades</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="flex flex-wrap gap-5"><label class="inline-flex items-center gap-2"><input id="dom-nevera" type="checkbox" value="1"> Nevera</label><label class="inline-flex items-center gap-2"><input id="dom-loza" type="checkbox" value="1"> Loza y cubiertos</label></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">Tipo de cocina</span><div class="flex flex-wrap gap-4"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-cocina" value="1"> Gas</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-cocina" value="2"> Eléctrica</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-cocina" value="3"> Otro</label></div></div>
                        <div><label for="dom-televisores" class="block text-xs font-medium text-gray-700 mb-1">Nro. televisores</label><input id="dom-televisores" type="number" min="0" class="form-input w-full"></div>
                        <div><label for="dom-vehiculos" class="block text-xs font-medium text-gray-700 mb-1">Nro. vehículos</label><input id="dom-vehiculos" type="number" min="0" class="form-input w-full"></div>
                        <div class="md:col-span-2"><label for="dom-comodidades" class="block text-xs font-medium text-gray-700 mb-1">Otras comodidades</label><input id="dom-comodidades" type="text" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Información económica y notoriedad social</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="dom-bienes-familia" class="block text-xs font-medium text-gray-700 mb-1">Bienes que aspira tener la familia</label><input id="dom-bienes-familia" type="text" class="form-input w-full"></div>
                        <div><label for="dom-lider-social" class="block text-xs font-medium text-gray-700 mb-1">Participa en grupos políticos, religiosos o deportivos</label><input id="dom-lider-social" type="text" class="form-input w-full"></div>
                        <div><span class="block text-xs font-medium text-gray-700 mb-2">¿Lo conocen los vecinos?</span><div class="flex gap-5"><label class="inline-flex items-center gap-2"><input type="radio" name="dom-conocido" value="SI"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="dom-conocido" value="NO"> No</label></div></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Actividades de bienestar, recreación y deportes</legend>
                    <div class="grid grid-cols-1 gap-3">
                        <div><label for="dom-actividades" class="block text-xs font-medium text-gray-700 mb-1">Tres últimas actividades importantes</label><input id="dom-actividades" type="text" class="form-input w-full"></div>
                        <div><label for="dom-gusto-familia" class="block text-xs font-medium text-gray-700 mb-1">¿Qué es lo que más le gustaría hacer a la familia?</label><input id="dom-gusto-familia" type="text" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Conclusiones</legend>
                    <div class="grid grid-cols-1 gap-3">
                        <div><label for="dom-conclusion-fortalezas" class="block text-xs font-medium text-gray-700 mb-1">Fortalezas para no acceder a la presión o delincuencia</label><textarea id="dom-conclusion-fortalezas" rows="2" class="form-textarea w-full"></textarea></div>
                        <div><label for="dom-conclusion-riesgo" class="block text-xs font-medium text-gray-700 mb-1">¿Ubicaría a esta persona en un puesto de alto riesgo? ¿Por qué?</label><textarea id="dom-conclusion-riesgo" rows="2" class="form-textarea w-full"></textarea></div>
                        <div><label for="dom-verificador" class="block text-xs font-medium text-gray-700 mb-1">Responsable de la visita</label><select id="dom-verificador" class="form-select w-full"><option value="">Seleccionar responsable</option></select></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="dom-usuario-registro" class="block text-xs font-medium text-gray-700 mb-1">Usuario registro</label><input id="dom-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-fecha-registro" class="block text-xs font-medium text-gray-700 mb-1">Fecha registro</label><input id="dom-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-usuario-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Usuario modificación</label><input id="dom-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="dom-fecha-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Fecha modificación</label><input id="dom-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
            </div>
            <div id="formulario-evaluacion-postulante" class="hidden flex-1 min-h-0 px-5 py-3 space-y-3 overflow-y-auto">
                <fieldset class="border border-gray-300 p-3">
                    <legend id="evaluacion-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Evaluación del postulante</legend>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div><label for="evaluacion-fecha" class="mb-1 block text-xs font-medium text-gray-700">Fecha</label><input id="evaluacion-fecha" type="date" class="form-input w-full"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Información del postulante</legend>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div class="md:col-span-3"><label for="evaluacion-personal" class="mb-1 block text-xs font-medium text-gray-700">Personal</label><input id="evaluacion-personal" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-documento" class="mb-1 block text-xs font-medium text-gray-700">Documento de identidad</label><input id="evaluacion-documento" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-estado-civil" class="mb-1 block text-xs font-medium text-gray-700">Estado civil</label><input id="evaluacion-estado-civil" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-edad" class="mb-1 block text-xs font-medium text-gray-700">Edad</label><input id="evaluacion-edad" type="number" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-hijos" class="mb-1 block text-xs font-medium text-gray-700">Nro. hijos</label><input id="evaluacion-hijos" type="number" readonly class="form-input w-full bg-gray-50"></div>
                        <div class="md:col-span-2"><label for="evaluacion-lugar-nacimiento" class="mb-1 block text-xs font-medium text-gray-700">Lugar de nacimiento</label><input id="evaluacion-lugar-nacimiento" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-telefono" class="mb-1 block text-xs font-medium text-gray-700">Teléfono</label><input id="evaluacion-telefono" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Información del puesto</legend>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div><label for="evaluacion-area" class="mb-1 block text-xs font-medium text-gray-700">Unidad orgánica</label><select id="evaluacion-area" class="form-select w-full"><option value="">Seleccionar unidad</option>@foreach ($areasEvaluacion as $area)<option value="{{ $area->codigo }}">{{ $area->nombre }}</option>@endforeach</select></div>
                        <div><label for="evaluacion-cargo" class="mb-1 block text-xs font-medium text-gray-700">Puesto al que postula</label><select id="evaluacion-cargo" class="form-select w-full"><option value="">Seleccionar puesto</option>@foreach ($cargosEvaluacion as $cargo)<option value="{{ $cargo->codigo }}">{{ $cargo->nombre }}</option>@endforeach</select></div>
                        <div class="md:col-span-2"><span class="mb-2 block text-xs font-medium text-gray-700">Cubrir vacante</span><div class="flex flex-wrap gap-x-6 gap-y-2"><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-vacante" value="1"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-vacante" value="0"> No</label><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-vacante" value="2"> Puesto nuevo</label></div></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Evaluación del postulante</legend>
                    <div class="grid grid-cols-1 gap-x-6 gap-y-2 md:grid-cols-[1fr_auto]">
                        <div class="space-y-2">
                            @foreach ([['Educación requerida','educacion'],['Formación','formacion'],['Experiencia','experiencia'],['Habilidades','habilidad']] as [$etiqueta,$campo])
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span class="w-36 text-xs font-medium text-gray-700">{{ $etiqueta }}:</span>
                                    @foreach ([1 => 'Deficiente', 2 => 'Promedio', 3 => 'Superior'] as $valor => $nivel)
                                        <label class="inline-flex items-center gap-1.5 text-xs"><input type="radio" name="evaluacion-{{ $campo }}" value="{{ $valor }}"> {{ $nivel }}</label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-2 self-start md:w-40 md:grid-cols-1">
                            <div><label for="evaluacion-puntaje" class="mb-1 block text-xs font-medium text-gray-700">Puntaje</label><input id="evaluacion-puntaje" type="number" readonly class="form-input w-full bg-gray-50"></div>
                            <div><label for="evaluacion-promedio" class="mb-1 block text-xs font-medium text-gray-700">Promedio</label><input id="evaluacion-promedio" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        </div>
                        <div class="md:col-span-2"><label for="evaluacion-fortalezas" class="mb-1 block text-xs font-medium text-gray-700">Fortalezas</label><textarea id="evaluacion-fortalezas" rows="2" class="form-textarea w-full"></textarea></div>
                        <div class="md:col-span-2"><label for="evaluacion-debilidades" class="mb-1 block text-xs font-medium text-gray-700">Debilidades</label><textarea id="evaluacion-debilidades" rows="2" class="form-textarea w-full"></textarea></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Recomendaciones de contratación</legend>
                    <div class="space-y-3">
                        <div><span class="mb-2 block text-xs font-medium text-gray-700">Apto para el puesto</span><div class="flex gap-6"><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-apto" value="1"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-apto" value="0"> No</label></div></div>
                        <div><span class="mb-2 block text-xs font-medium text-gray-700">¿Consideraría al postulante para otro proceso de selección u otro puesto?</span><div class="flex gap-6"><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-considera" value="1"> Sí</label><label class="inline-flex items-center gap-2"><input type="radio" name="evaluacion-considera" value="0"> No</label></div></div>
                        <div><label for="evaluacion-considera-razon" class="mb-1 block text-xs font-medium text-gray-700">¿Por qué?</label><textarea id="evaluacion-considera-razon" rows="2" class="form-textarea w-full"></textarea></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Evaluadores</legend>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div><label for="evaluacion-evaluador-1" class="mb-1 block text-xs font-medium text-gray-700">Evaluador 1</label><select id="evaluacion-evaluador-1" class="form-select w-full"><option value="">Seleccionar evaluador</option>@foreach ($evaluadoresPostulante as $evaluador)<option value="{{ $evaluador->codigo }}">{{ $evaluador->nombre }}</option>@endforeach</select></div>
                        <div><label for="evaluacion-evaluador-2" class="mb-1 block text-xs font-medium text-gray-700">Evaluador 2</label><select id="evaluacion-evaluador-2" class="form-select w-full"><option value="">Seleccionar evaluador</option>@foreach ($evaluadoresPostulante as $evaluador)<option value="{{ $evaluador->codigo }}">{{ $evaluador->nombre }}</option>@endforeach</select></div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-3">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div><label for="evaluacion-usuario-registro" class="mb-1 block text-xs font-medium text-gray-700">Usuario registro</label><input id="evaluacion-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-fecha-registro" class="mb-1 block text-xs font-medium text-gray-700">Fecha registro</label><input id="evaluacion-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-usuario-modificacion" class="mb-1 block text-xs font-medium text-gray-700">Usuario modificación</label><input id="evaluacion-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="evaluacion-fecha-modificacion" class="mb-1 block text-xs font-medium text-gray-700">Fecha modificación</label><input id="evaluacion-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
            </div>
            <div id="formulario-cul" class="hidden flex-1 min-h-0 px-5 py-4 space-y-4 overflow-y-scroll">
                <fieldset class="border border-gray-300 p-4">
                    <legend id="formulario-cul-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Datos del certificado</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2">
                            <label for="cul-nombre-personal" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label>
                            <input id="cul-nombre-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="cul-documento-personal" class="block text-xs font-medium text-gray-700 mb-1">Documento de identidad</label>
                            <input id="cul-documento-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="cul-folio-nombre" class="block text-xs font-medium text-gray-700 mb-1">Certificado</label>
                            <input id="cul-folio-nombre" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-2">
                            <label for="cul-entidad" class="block text-xs font-medium text-gray-700 mb-1">Entidad</label>
                            <div class="relative" data-entidad-select>
                                <button type="button" data-entidad-trigger class="form-input w-full text-left flex items-center justify-between">
                                    <span data-entidad-label>Seleccionar entidad</span><i class="i-tabler-chevron-down"></i>
                                </button>
                                <div data-entidad-menu class="hidden absolute left-0 right-0 top-full z-50 mt-1 rounded-md border border-gray-200 bg-white shadow-lg">
                                    <input type="search" data-entidad-busqueda placeholder="Buscar entidad..." class="form-input w-full border-0 border-b border-gray-200 rounded-none text-sm">
                                    <div data-entidad-opciones class="max-h-44 overflow-y-auto p-1"></div>
                                </div>
                                <select id="cul-entidad" class="hidden" tabindex="-1" aria-hidden="true">
                                    <option value="">Seleccionar entidad</option>
                                    @foreach ($instituciones as $institucion)
                                        <option value="{{ $institucion->codigo }}">{{ $institucion->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="cul-fecha-emision" class="block text-xs font-medium text-gray-700 mb-1">Fecha de emisión</label>
                            <input id="cul-fecha-emision" type="date" class="form-input w-full">
                        </div>
                        <div>
                            <label for="cul-fecha-caducidad" class="block text-xs font-medium text-gray-700 mb-1">Fecha de caducidad</label>
                            <input id="cul-fecha-caducidad" type="date" class="form-input w-full">
                        </div>
                        <div class="md:col-span-2">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Sin Observaciones</span>
                            <div class="flex gap-6">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cul-sin-observaciones" value="SI"> Sí</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cul-sin-observaciones" value="NO"> No</label>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label for="cul-observacion" class="block text-xs font-medium text-gray-700 mb-1">Observación</label>
                            <textarea id="cul-observacion" rows="2" class="form-textarea w-full"></textarea>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-4">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="cul-usuario-registro" class="block text-xs font-medium text-gray-700 mb-1">Usuario registro</label><input id="cul-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="cul-fecha-registro" class="block text-xs font-medium text-gray-700 mb-1">Fecha registro</label><input id="cul-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="cul-usuario-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Usuario modificación</label><input id="cul-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="cul-fecha-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Fecha modificación</label><input id="cul-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
            </div>
            <div id="formulario-antecedentes-policiales" class="hidden relative -mt-4 flex-1 min-h-0 px-5 py-4 space-y-4 overflow-y-scroll">
                <div id="antecedentes-no-vigente" class="hidden pointer-events-none absolute inset-0 z-10 flex items-center justify-center" aria-hidden="true">
                    <span class="-rotate-12 text-6xl font-semibold tracking-wide text-gray-500/50">NO VIGENTE</span>
                </div>
                <fieldset class="border border-gray-300 p-4">
                    <legend id="formulario-antecedentes-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Datos del certificado</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="md:col-span-2">
                            <label for="antecedentes-nombre-personal" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label>
                            <input id="antecedentes-nombre-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="antecedentes-documento-personal" class="block text-xs font-medium text-gray-700 mb-1">Documento de identidad</label>
                            <input id="antecedentes-documento-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div>
                            <label for="antecedentes-folio-nombre" class="block text-xs font-medium text-gray-700 mb-1">Certificado</label>
                            <input id="antecedentes-folio-nombre" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-2">
                            <label for="antecedentes-entidad" class="block text-xs font-medium text-gray-700 mb-1">Entidad</label>
                            <div class="relative" data-entidad-select>
                                <button type="button" data-entidad-trigger class="form-input w-full text-left flex items-center justify-between">
                                    <span data-entidad-label>Seleccionar entidad</span><i class="i-tabler-chevron-down"></i>
                                </button>
                                <div data-entidad-menu class="hidden absolute left-0 right-0 top-full z-50 mt-1 rounded-md border border-gray-200 bg-white shadow-lg">
                                    <input type="search" data-entidad-busqueda placeholder="Buscar entidad..." class="form-input w-full border-0 border-b border-gray-200 rounded-none text-sm">
                                    <div data-entidad-opciones class="max-h-44 overflow-y-auto p-1"></div>
                                </div>
                                <select id="antecedentes-entidad" class="hidden" tabindex="-1" aria-hidden="true">
                                    <option value="">Seleccionar entidad</option>
                                    @foreach ($instituciones as $institucion)
                                        <option value="{{ $institucion->codigo }}">{{ $institucion->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="antecedentes-fecha-emision" class="block text-xs font-medium text-gray-700 mb-1">Fecha de emisión</label>
                            <input id="antecedentes-fecha-emision" type="date" class="form-input w-full">
                        </div>
                        <div>
                            <label for="antecedentes-fecha-caducidad" class="block text-xs font-medium text-gray-700 mb-1">Fecha de caducidad</label>
                            <input id="antecedentes-fecha-caducidad" type="date" class="form-input w-full">
                        </div>
                        <div class="md:col-span-2">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Registra antecedentes</span>
                            <div class="flex gap-6">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="antecedentes-registra" value="SI"> Sí</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="antecedentes-registra" value="NO"> No</label>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label for="antecedentes-observacion" class="block text-xs font-medium text-gray-700 mb-1">Observación</label>
                            <textarea id="antecedentes-observacion" rows="2" class="form-textarea w-full"></textarea>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-4">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="antecedentes-usuario-registro" class="block text-xs font-medium text-gray-700 mb-1">Usuario registro</label><input id="antecedentes-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="antecedentes-fecha-registro" class="block text-xs font-medium text-gray-700 mb-1">Fecha registro</label><input id="antecedentes-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="antecedentes-usuario-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Usuario modificación</label><input id="antecedentes-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="antecedentes-fecha-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Fecha modificación</label><input id="antecedentes-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
            </div>
            <div id="formulario-cv" class="hidden flex-1 min-h-0 px-5 py-4 space-y-4 overflow-y-scroll">
                <fieldset class="border border-gray-300 p-4">
                    <legend id="formulario-cv-legend" class="px-1 text-xs font-bold uppercase text-gray-700">Datos del currículum vitae</legend>
                    <input id="cv-folio-nombre" type="hidden" value="CURRICULUM VITAE DOCUMENTADO">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        <div class="md:col-span-9">
                            <label for="cv-nombre-personal" class="block text-xs font-medium text-gray-700 mb-1">Nombres y apellidos</label>
                            <input id="cv-nombre-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-2">
                            <label for="cv-documento-personal" class="block text-xs font-medium text-gray-700 mb-1">Documento de identidad</label>
                            <input id="cv-documento-personal" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-1">
                            <label for="cv-edad" class="block text-xs font-medium text-gray-700 mb-1">Edad</label>
                            <input id="cv-edad" type="text" readonly class="form-input w-full bg-gray-50">
                        </div>
                        <div class="md:col-span-12">
                            <label for="cv-residencia" class="block text-xs font-medium text-gray-700 mb-1">Lugar de residencia</label>
                            <input id="cv-residencia" type="text" class="form-input w-full">
                        </div>
                        <div class="md:col-span-12">
                            <label for="cv-direccion" class="block text-xs font-medium text-gray-700 mb-1">Dirección</label>
                            <input id="cv-direccion" type="text" class="form-input w-full">
                        </div>
                        <div class="md:col-span-5">
                            <label for="cv-telefono" class="block text-xs font-medium text-gray-700 mb-1">Teléfono</label>
                            <input id="cv-telefono" type="text" class="form-input w-full">
                        </div>
                        <div class="md:col-span-12">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Nivel en idioma Inglés</span>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-idiomas" value="N"> Ninguno</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-idiomas" value="B"> Básico</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-idiomas" value="I"> Intermedio</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-idiomas" value="A"> Avanzado</label>
                            </div>
                        </div>
                        <div class="md:col-span-12">
                            <span class="block text-xs font-medium text-gray-700 mb-2">Nivel en Computación</span>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-computacion" value="N"> Ninguno</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-computacion" value="B"> Básico</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-computacion" value="I"> Intermedio</label>
                                <label class="inline-flex items-center gap-2"><input type="radio" name="cv-computacion" value="A"> Avanzado</label>
                            </div>
                        </div>
                        <div class="md:col-span-12">
                            <label for="cv-otras-capacidades" class="block text-xs font-medium text-gray-700 mb-1">Otras capacidades</label>
                            <textarea id="cv-otras-capacidades" rows="3" class="form-textarea w-full"></textarea>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border border-gray-300 p-4">
                    <legend class="px-1 text-xs font-bold uppercase text-gray-700">Datos del sistema</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div><label for="cv-usuario-registro" class="block text-xs font-medium text-gray-700 mb-1">Usuario registro</label><input id="cv-usuario-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="cv-usuario-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Usuario modificación</label><input id="cv-usuario-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="cv-fecha-registro" class="block text-xs font-medium text-gray-700 mb-1">Fecha registro</label><input id="cv-fecha-registro" type="text" readonly class="form-input w-full bg-gray-50"></div>
                        <div><label for="cv-fecha-modificacion" class="block text-xs font-medium text-gray-700 mb-1">Fecha modificación</label><input id="cv-fecha-modificacion" type="text" readonly class="form-input w-full bg-gray-50"></div>
                    </div>
                </fieldset>
                <fieldset class="min-w-0 border border-gray-300 px-3 pb-3 pt-1" data-cv-collection="academica">
                    <legend class="w-full px-1"><div class="flex w-full items-center justify-between gap-3">
                        <button type="button" data-cv-toggle="academica" aria-controls="cv-academica-panel" aria-expanded="false" class="flex min-w-0 flex-1 items-center gap-2 text-left text-xs font-bold uppercase text-gray-700 hover:text-blue-700"><span data-cv-chevron class="inline-block w-3 text-center transition-transform">▸</span><span>Formación Académica</span><span data-cv-count class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">0</span></button>
                        <button type="button" data-cv-add-row="academica" class="shrink-0 rounded-md bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">Agregar registro</button>
                    </div></legend>
                    <div id="cv-academica-panel" class="hidden overflow-x-auto pt-2">
                        <table class="min-w-[1050px] w-full border-collapse text-xs">
                            <thead><tr class="bg-gray-100 text-left text-gray-700">
                                <th class="border p-2">N°</th><th class="border p-2">Descripción</th><th class="border p-2">Grado académico</th><th class="border p-2">Fecha inicio</th><th class="border p-2">Fecha fin</th><th class="border p-2">Lugar</th><th class="border p-2">Vigencia</th><th class="border p-2">Acciones</th>
                            </tr></thead><tbody id="cv-academica-rows"></tbody>
                        </table>
                    </div>
                </fieldset>
                <fieldset class="min-w-0 border border-gray-300 px-3 pb-3 pt-1" data-cv-collection="profesional">
                    <legend class="w-full px-1"><div class="flex w-full items-center justify-between gap-3">
                        <button type="button" data-cv-toggle="profesional" aria-controls="cv-profesional-panel" aria-expanded="false" class="flex min-w-0 flex-1 items-center gap-2 text-left text-xs font-bold uppercase text-gray-700 hover:text-blue-700"><span data-cv-chevron class="inline-block w-3 text-center transition-transform">▸</span><span>Formación Profesional</span><span data-cv-count class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">0</span></button>
                        <button type="button" data-cv-add-row="profesional" class="shrink-0 rounded-md bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">Agregar registro</button>
                    </div></legend>
                    <div id="cv-profesional-panel" class="hidden overflow-x-auto pt-2">
                        <table class="min-w-[800px] w-full border-collapse text-xs">
                            <thead><tr class="bg-gray-100 text-left text-gray-700">
                                <th class="border p-2">N°</th><th class="border p-2">Curso / capacitación</th><th class="border p-2">Fecha</th><th class="border p-2">Lugar</th><th class="border p-2">Vigencia</th><th class="border p-2">Acciones</th>
                            </tr></thead><tbody id="cv-profesional-rows"></tbody>
                        </table>
                    </div>
                </fieldset>
                <fieldset class="min-w-0 border border-gray-300 px-3 pb-3 pt-1" data-cv-collection="experiencia">
                    <legend class="w-full px-1"><div class="flex w-full items-center justify-between gap-3">
                        <button type="button" data-cv-toggle="experiencia" aria-controls="cv-experiencia-panel" aria-expanded="false" class="flex min-w-0 flex-1 items-center gap-2 text-left text-xs font-bold uppercase text-gray-700 hover:text-blue-700"><span data-cv-chevron class="inline-block w-3 text-center transition-transform">▸</span><span>Experiencia Laboral</span><span data-cv-count class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">0</span></button>
                        <button type="button" data-cv-add-row="experiencia" class="shrink-0 rounded-md bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700">Agregar registro</button>
                    </div></legend>
                    <div id="cv-experiencia-panel" class="hidden min-w-0 overflow-x-auto pt-2">
                        <table class="min-w-[1500px] w-full border-collapse text-xs">
                            <thead><tr class="bg-gray-100 text-left text-gray-700">
                                <th class="border p-2">N°</th><th class="border p-2">Empresa</th><th class="border p-2">Cargo</th><th class="border p-2">Fecha inicio</th><th class="border p-2">Fecha cese</th><th class="border p-2">Motivo cese</th><th class="border p-2">Referencia</th><th class="border p-2">Referencia cargo</th><th class="border p-2">Referencia correo</th><th class="border p-2">Referencia teléfono</th><th class="border p-2">Vigencia</th><th class="border p-2">Acciones</th>
                            </tr></thead><tbody id="cv-experiencia-rows"></tbody>
                        </table>
                    </div>
                </fieldset>
            </div>
            <div class="flex shrink-0 justify-end gap-2 px-5 py-3 border-t border-gray-200">
                <button type="button" id="btn-cancelar-agregar-folio" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
            </div>
        </div>
    </div>

    {{-- ── Trigger oculto: Modal Legajos ────────────────────────────── --}}
    <button type="button" class="hidden" id="btn-modal-legajos" data-hs-overlay="#modal-legajos-personal"></button>

    {{-- ── MODAL: Legajos del personal ───────────────────────────────────── --}}
    <div id="modal-legajos-personal"
         class="hs-overlay hidden fixed inset-0 z-60 overflow-y-auto transition-all duration-500 pointer-events-none">
        <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100
                    translate-y-10 opacity-0 ease-in-out transition-all duration-500
                    max-w-6xl w-full my-8 mx-auto flex flex-col bg-white shadow-sm rounded-lg pointer-events-auto
                    border border-gray-200">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 nombrePersLeg">Legajos para</h3>
                <button type="button" id="btn-modal-legajos-close"
                        class="text-gray-500 hover:text-gray-700 transition-colors"
                        data-hs-overlay="#modal-legajos-personal">
                    <i class="i-tabler-x text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cliente</label>
                        <select id="clientes" class="form-select w-full">
                            <option disabled selected>— Seleccionar —</option>
                            @foreach ($clientes as $cliente)
                                <option value="{{ $cliente->codigo }}">{{ $cliente->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                        <select id="cargos" class="form-select w-full">
                            <option disabled selected>— Seleccionar —</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="codPersonal" id="codPersonal">
                <div id="tblDocsLegajo" class="w-full hidden"></div>
            </div>
        </div>
    </div>


{{-- =====================================================================
     MODALES
     ===================================================================== --}}

{{-- ── Trigger oculto: Visor de documentos ────────────────────────────── --}}
<button type="button" class="hidden" id="btn-modal-view-docs" data-hs-overlay="#modal-view-docs"></button>

{{-- Modal: Visor de documentos --}}
<div id="modal-view-docs"
     class="hs-overlay hidden fixed inset-0 z-70 overflow-y-auto transition-all duration-500 pointer-events-none">
    <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100
                translate-y-10 opacity-0 ease-in-out transition-all duration-500
                max-w-3xl w-full my-8 mx-auto flex flex-col bg-white shadow-sm rounded-lg pointer-events-auto
                border border-gray-200">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <h3 class="text-base font-medium text-gray-900 modal-title">Visor de documento</h3>
            <button type="button" id="btn-modal-view-docs-close"
                    class="text-gray-500 hover:text-gray-700 transition-colors"
                    onclick="window.HSOverlay.close(document.getElementById('modal-view-docs')); setTimeout(() => { if(window.parentModalToReopen) window.HSOverlay.open(document.getElementById(window.parentModalToReopen)); }, 400);">
                <i class="i-tabler-x text-lg"></i>
            </button>
        </div>

        {{-- Nombre del doc seleccionado --}}
        <p id="txtDocSelec" class="text-center text-sm font-medium text-gray-700 pt-4 px-5"></p>

        {{-- Contenido del visor --}}
        <div id="visorDocs" class="flex flex-col gap-3 px-5 py-4"></div>
    </div>
</div>

{{-- Modal: Previsualización generada del CV --}}
<button type="button" class="hidden" id="btn-modal-cv-preview" data-hs-overlay="#modal-cv-preview"></button>
<div id="modal-cv-preview" class="hs-overlay hidden fixed inset-0 z-80 flex items-center justify-center overflow-y-auto p-4 transition-all duration-300 pointer-events-none" style="z-index: 80">
    <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 translate-y-4 opacity-0 transition-all duration-300 max-w-4xl w-full max-h-[calc(100vh-2rem)] flex flex-col bg-white shadow-2xl rounded-xl pointer-events-auto border border-gray-200">
        <div class="flex shrink-0 items-center justify-between px-5 py-3 border-b border-gray-200">
            <div><h3 class="text-base font-semibold text-gray-900">Previsualización de currículum vitae</h3><p class="mt-0.5 text-xs text-gray-500">Documento generado con los datos actuales del personal</p></div>
            <div class="flex shrink-0 items-center gap-3">
                <button type="button" id="btn-exportar-cv-pdf" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <i class="i-tabler-file-type-pdf text-lg"></i><span>PDF</span>
                </button>
                <button type="button" class="text-gray-500 hover:text-gray-700" onclick="window.HSOverlay.close(document.getElementById('modal-cv-preview')); setTimeout(() => { if(window.parentModalToReopen) window.HSOverlay.open(document.getElementById(window.parentModalToReopen)); }, 400);" aria-label="Cerrar previsualización">
                    <i class="i-tabler-x text-lg"></i>
                </button>
            </div>
        </div>
        <div class="overflow-y-auto bg-gray-100 p-4 sm:p-8">
            <article id="cv-preview-document" class="mx-auto min-h-[420px] max-w-3xl border border-gray-200 bg-white px-8 py-10 shadow-md sm:px-14">
                <h2 class="mb-10 text-center text-xl font-bold uppercase tracking-wide underline underline-offset-4">Curriculum Vitae</h2>
                <h3 class="mb-5 border-b border-gray-200 pb-2 text-sm font-bold uppercase tracking-wide">Datos personales</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-[minmax(0,1fr)_112px]">
                <dl class="grid grid-cols-[minmax(9rem,11rem)_1fr] content-start gap-x-4 gap-y-3 text-sm sm:grid-cols-[11rem_1fr]">
                    <dt class="font-medium">Nombres</dt><dd data-cv-preview="nombres" class="break-words"></dd>
                    <dt class="font-medium">Apellidos</dt><dd data-cv-preview="apellidos" class="break-words"></dd>
                    <dt class="font-medium">Documento de identidad</dt><dd data-cv-preview="documento"></dd>
                    <dt class="font-medium">País</dt><dd data-cv-preview="pais"></dd>
                    <dt class="font-medium">Fecha de nacimiento</dt><dd data-cv-preview="fecha_nacimiento"></dd>
                    <dt class="font-medium">Edad</dt><dd data-cv-preview="edad"></dd>
                    <dt class="font-medium">Domicilio</dt><dd data-cv-preview="direccion" class="break-words"></dd>
                    <dt class="font-medium">Departamento</dt><dd data-cv-preview="departamento"></dd>
                    <dt class="font-medium">Provincia</dt><dd data-cv-preview="provincia"></dd>
                    <dt class="font-medium">Distrito</dt><dd data-cv-preview="distrito"></dd>
                    <dt class="font-medium">Celular</dt><dd data-cv-preview="telefono"></dd>
                    <dt class="font-medium">Correo electrónico</dt><dd data-cv-preview="correo" class="break-words"></dd>
                    <dt class="font-medium">N° de emergencia</dt><dd data-cv-preview="emergencia"></dd>
                </dl>
                <figure class="order-first flex justify-center sm:order-last sm:justify-end">
                    <img id="cv-preview-photo" src="" alt="Foto del personal" class="hidden h-[120px] w-[100px] border border-gray-400 bg-white object-cover p-1 shadow-sm">
                    <div id="cv-preview-photo-placeholder" class="flex h-[120px] w-[100px] items-center justify-center border border-dashed border-gray-300 bg-gray-50 px-3 text-center text-xs text-gray-400">Foto del personal</div>
                </figure>
                </div>
                <section id="cv-preview-academica-section" class="hidden mt-10 border-t border-gray-200 pt-6">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide">Formación académica</h3>
                    <ul id="cv-preview-academica-list" class="space-y-4 pl-2 text-sm text-gray-800"></ul>
                </section>
                <section id="cv-preview-profesional-section" class="hidden mt-10 border-t border-gray-200 pt-6">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide">Formación profesional (cursos / capacitaciones)</h3>
                    <ul id="cv-preview-profesional-list" class="list-disc space-y-3 pl-5 text-sm text-gray-800"></ul>
                </section>
                <section id="cv-preview-experiencia-section" class="hidden mt-10 border-t border-gray-200 pt-6">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide">Experiencia laboral</h3>
                    <ul id="cv-preview-experiencia-list" class="list-disc space-y-4 pl-5 text-sm text-gray-800"></ul>
                </section>
                <section id="cv-preview-referencias-section" class="hidden mt-10 border-t border-gray-200 pt-6">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide">Referencias personales</h3>
                    <div id="cv-preview-referencias-actuales-block" class="hidden mb-6">
                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-700">Jefes actuales</h4>
                        <ul id="cv-preview-referencias-actuales" class="list-disc space-y-4 pl-5 text-sm text-gray-800"></ul>
                    </div>
                    <div id="cv-preview-referencias-anteriores-block" class="hidden">
                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-700">Jefes anteriores</h4>
                        <ul id="cv-preview-referencias-anteriores" class="list-disc space-y-4 pl-5 text-sm text-gray-800"></ul>
                    </div>
                </section>
                <section id="cv-preview-cert-estudios-section" class="hidden mt-10 border-t border-gray-200 pt-6">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide">Certificados de estudios</h3>
                    <div id="cv-preview-cert-estudios" class="space-y-5"></div>
                </section>
                <section id="cv-preview-cert-laborales-section" class="hidden mt-10 border-t border-gray-200 pt-6">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide">Certificados laborales</h3>
                    <div id="cv-preview-cert-laborales" class="space-y-5"></div>
                </section>
            </article>
        </div>
    </div>
</div>

{{-- Modal: Previsualización generada de verificación domiciliaria --}}
<button type="button" class="hidden" id="btn-modal-vdo-preview" data-hs-overlay="#modal-vdo-preview"></button>
<div id="modal-vdo-preview" class="hs-overlay hidden fixed inset-0 z-80 flex items-center justify-center overflow-y-auto p-4 transition-all duration-300 pointer-events-none" style="z-index: 80">
    <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 translate-y-4 opacity-0 transition-all duration-300 max-w-5xl w-full max-h-[calc(100vh-2rem)] flex flex-col bg-white shadow-2xl rounded-xl pointer-events-auto border border-gray-200">
        <div class="flex shrink-0 items-center justify-between px-5 py-3 border-b border-gray-200">
            <div><h3 class="text-base font-semibold text-gray-900">Previsualización de verificación domiciliaria</h3><p class="mt-0.5 text-xs text-gray-500">Documento generado con los datos registrados de la visita</p></div>
            <div class="flex shrink-0 items-center gap-3">
                <button type="button" id="btn-exportar-vdo-pdf" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <i class="i-tabler-file-type-pdf text-lg"></i><span>PDF</span>
                </button>
                <button type="button" class="text-gray-500 hover:text-gray-700" onclick="window.HSOverlay.close(document.getElementById('modal-vdo-preview')); setTimeout(() => { if(window.parentModalToReopen) window.HSOverlay.open(document.getElementById(window.parentModalToReopen)); }, 400);" aria-label="Cerrar previsualización">
                    <i class="i-tabler-x text-lg"></i>
                </button>
            </div>
        </div>
        <div class="overflow-y-auto bg-gray-100 p-4 sm:p-8">
            <article id="vdo-preview-document" style="color:#000" class="mx-auto w-full max-w-[800px] bg-white p-3 text-[11px] font-normal leading-snug text-black shadow-md sm:p-4">
                <table class="mb-3 w-full border-collapse border border-blue-700 text-[11px] font-normal leading-normal text-black">
                    <tbody>
                    <tr>
                        <td rowspan="2" class="w-32 border border-blue-700 p-2 text-center align-middle"><img src="{{ asset('images/logo-grupo-solar.png') }}" alt="Grupo Solar" class="mx-auto max-h-14 w-full object-contain"></td>
                        <td class="border border-blue-700 px-2 py-2 text-center"><strong class="font-semibold">SOL SECURITY S.A.C.</strong></td>
                        <td class="w-48 border border-blue-700 px-2 py-2"><strong class="font-semibold">Código:</strong> <span>FOS-04-002</span></td>
                        <td class="w-28 border border-blue-700 px-2 py-2 text-center"><strong class="font-semibold">PRIMERA EDICIÓN</strong></td>
                    </tr>
                    <tr>
                        <td class="border border-blue-700 px-2 py-2"><strong class="font-semibold">Formato:</strong> Visita Domiciliaria</td>
                        <td class="border border-blue-700 px-2 py-2"><strong class="font-semibold">Fecha de Aprobación:</strong> 06/01/2009</td>
                        <td class="border border-blue-700 px-2 py-2 text-center">Pág. 1 de 2</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="border border-blue-700 px-2 py-2"><strong class="font-semibold">Área de Influencia:</strong> Jefatura de Administración</td>
                        <td colspan="2" class="border border-blue-700 px-2 py-2"><strong class="font-semibold">Fecha de Revisión:</strong> N/A</td>
                    </tr>
                    </tbody>
                </table>
                <h2 style="color:#000" class="mb-2 text-center text-sm font-semibold uppercase underline">Visita domiciliaria</h2>
                <p style="color:#000" class="mb-2 text-black"><strong class="font-semibold text-black">GENERALIDADES:</strong> La siguiente información es necesaria para el estudio de protección para los empleados de la empresa. Los datos suministrados serán clasificados como información confidencial.</p>
                <p style="color:#000" class="mb-2 text-black">Fecha: <span data-vdo-preview="fecha"></span></p>
                <div id="vdo-preview-sections" class="space-y-2"></div>
                <section class="vdo-preview-signature-block mt-4 border-t border-gray-300 pt-2">
                    <p style="color:#000">Certifico que la información aquí suministrada fue verificada físicamente y corresponde a la verdad en lo que respecta al alcance de mi conocimiento.</p>
                    <div class="mt-2 flex flex-col items-center">
                        <div class="flex h-[100px] w-full items-end justify-center">
                            <img id="vdo-preview-signature" src="" alt="Firma del verificador" class="hidden max-h-[96px] max-w-[320px] object-contain">
                            <div id="vdo-preview-signature-placeholder" class="h-[92px] w-[320px]"></div>
                        </div>
                        <div class="w-[245px] border-t border-gray-800 pt-1 text-center">
                            <p id="vdo-preview-responsable" class="font-normal"></p>
                            <p class="text-[10px]">Verificador</p>
                        </div>
                    </div>
                </section>
            </article>
        </div>
    </div>
</div>


{{-- ── Trigger oculto: Formulario de folios ────────────────────────────── --}}
<button type="button" class="hidden" id="btn-modal-docs" data-hs-overlay="#modal-file"></button>

{{-- Modal: Carga de folio PDF --}}
<div id="modal-file"
     class="hs-overlay hidden fixed inset-0 z-70 overflow-y-auto transition-all duration-500 pointer-events-none">
    <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100
                translate-y-10 opacity-0 ease-in-out transition-all duration-500
                sm:max-w-lg w-full my-8 sm:mx-auto flex flex-col bg-white shadow-sm rounded-lg pointer-events-auto
                border border-gray-200">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <h3 class="text-base font-medium text-gray-900 modal-title">Cargar documento</h3>
            <button type="button" id="btn-modal-docs-close"
                    class="text-gray-500 hover:text-gray-700 transition-colors"
                    onclick="window.HSOverlay.close(document.getElementById('modal-file')); setTimeout(() => { if(window.parentModalToReopen) window.HSOverlay.open(document.getElementById(window.parentModalToReopen)); }, 400);">
                <i class="i-tabler-x text-lg"></i>
            </button>
        </div>

        {{-- Formulario --}}
        <form id="formFolioPersonal">
            @csrf
            <input type="hidden" id="codFolioActual" value="">

            <input type="hidden" id="codFolio" value="">
            <input type="hidden" id="meses" value="">
            <input type="hidden" id="cantArchivos" value="">
            <span id="txtPeriodo" class="hidden"></span>
            <span id="txtCantHojas" class="hidden"></span>

            {{-- Caducidad — solo visible para folios normales --}}
            

            <div class="px-5 py-4 space-y-5">

                
                
                <p class="text-sm text-center text-gray-600" id="aviso-tipo-archivo">
                    Solo se aceptan archivos <strong>PDF</strong> con un peso máximo de <strong>1 MB</strong>.
                </p>

                {{-- Fecha de emisión --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Emisión</label>
                    <input type="date" id="fecha_emision" required class="form-input w-full">
                </div>

                <div id="divCaducidad" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Caducidad</label>
                    <input type="date" id="fecha_caducidad" class="form-input w-full">
                </div>

                {{-- Selector de archivo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Archivo PDF</label>

                    <div id="btnSeleccionar" role="button"
                         class="cursor-pointer border-2 border-dashed border-gray-300 rounded-xl p-8
                                flex flex-col items-center justify-center gap-3
                                hover:border-blue-400 hover:bg-blue-50 transition-colors">

                        <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-gray-100 text-gray-700">
                            <i class="i-tabler-file-type-pdf text-2xl"></i>
                        </span>

                        <div class="text-center text-sm text-gray-600">
                            <span class="font-medium text-gray-800">Haz click aqui </span>
                            <span class="font-semibold text-blue-600 hover:underline">SELECCIONAR</span>
                        </div>

                        <p class="text-xs text-gray-400">Máximo 1 MB</p>

                        <input type="file" id="archivoInput" accept=".pdf" class="hidden">
                    </div>

                    <ul id="listaArchivos" class="mt-3 space-y-2"></ul>
                </div>

            </div>

            {{-- Footer del modal --}}
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200">
                <button type="submit" class="btn bg-primary text-white"  id="btn-guardar-folio">
                    <i class="i-tabler-check me-1"></i> Guardar
                </button>
                <button type="button" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200"
                        onclick="window.HSOverlay.close(document.getElementById('modal-file')); setTimeout(() => { if(window.parentModalToReopen) window.HSOverlay.open(document.getElementById(window.parentModalToReopen)); }, 400);">
                    <i class="i-tabler-x me-1"></i> Cerrar
                </button>
            </div>
        </form>
    </div>
</div>


{{-- =====================================================================
     SECCIÓN DE COINCIDENCIAS (oculta por defecto)
     ===================================================================== --}}
<div id="divCoincidencias" class="grid lg:grid-cols-1 gap-6 mt-8 hidden">
    <div class="card overflow-hidden">
        <div class="card-header">
            <h4 class="card-title">Listado de Coincidencias</h4>
        </div>
        <div class="px-5 py-4 space-y-4">
            <input
                type="text"
                id="buscar"
                placeholder="Buscar..."
                class="w-40 px-3 py-1.5 text-sm border border-gray-300 rounded-full
                       focus:outline-none focus:border-blue-500 transition-colors"
            />
            <div id="tblPersonasCN" class="w-full"></div>
        </div>
    </div>
</div>

<button id="btn-modal-biometrico" data-hs-overlay="#modal-biometrico" class="hidden"></button>

@include('file_control.rrhh.partials_modal_comparacion_huellafirma_dni')

@endsection


@section('script')

<script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://unpkg.com/exceljs@4.4.0/dist/exceljs.min.js"></script>

@endsection

@vite(['resources/js/functions/chargeFile.js'])

@vite(['resources/js/functions/chargefile/reporteAvances.js'])
