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
                            <option disabled selected>— Seleccionar —</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
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

                            @if ($tipoPerLimitar == 0 || $tipoPerLimitar == 1)
                                <option value="ADMIN_4">Administrativo 4°</option>
                                <option value="ADMIN_5">Administrativo 5°</option>
                            @endif

                            @if ($tipoPerLimitar == 0 || $tipoPerLimitar == 2)
                                <option value="OPER_4">Operativo 4°</option>
                                <option value="OPER_5">Operativo 5°</option>
                            @endif

                            @if ($tipoPerLimitar == 0 || $tipoPerLimitar == 3)
                                <option value="ESPECIAL">Especiales</option>
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
                            <option value="" selected>Todos</option>
                            <option value="SI">Sí</option>
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
                        <label for="selectTipoFolio" class="text-sm font-medium text-gray-700 whitespace-nowrap">Prioridad:</label>
                        <select id="selectTipoFolio" class="form-select text-sm py-1.5 w-36 border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500 cursor-pointer">
                            <option value="TODOS">Todos</option>
                            <option value="PRINCIPAL" selected>Obligatorio</option>
                            <option value="ADICIONAL">Adicional</option>
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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <input
                        type="text"
                        id="buscarFolio"
                        placeholder="Buscar..."
                        autocomplete="off"
                        class="w-40 px-3 py-1.5 text-sm uppercase border border-gray-300 rounded-full
                               focus:outline-none focus:border-blue-500 transition-colors"
                    />
                    <div class="flex flex-wrap items-center justify-end gap-3 ml-auto">
                        <div class="flex items-center gap-2">
                            <label for="filtroCategoriaFolio" class="text-sm font-medium text-gray-700 whitespace-nowrap">Categoría:</label>
                            <select id="filtroCategoriaFolio" class="form-select text-sm w-48 py-1.5 border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Seleccionar categoría</option>
                                @foreach ($categoriasFolio as $categoria)
                                    <option value="{{ $categoria->codigo }}">{{ $categoria->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="filtroFolioCategoria" class="text-sm font-medium text-gray-700 whitespace-nowrap">Folio:</label>
                            <select id="filtroFolioCategoria" class="form-select text-sm w-64 py-1.5 border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500" disabled>
                                <option value="">Selecciona una categoría primero</option>
                            </select>
                        </div>
                    </div>
                </div>
                {{-- Contenedor de la tabla --}}
                <div id="tblDocs" class="w-full"></div>
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
