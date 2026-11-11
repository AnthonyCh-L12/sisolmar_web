@extends('layouts.vertical', ['title' => 'Gestión de Folios'])

@section('content')

@include('layouts.shared/page-title', ['subtitle' => 'File Control', 'title' => 'Gestión de Folios'])

<style>
    #folios-page .rep-card {
        position: relative; overflow: hidden; background: #fff; border: 1px solid #e5e7eb;
        border-radius: .75rem; padding: .65rem .85rem; min-width: 118px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
    }
    #folios-page .rep-card .rep-label {
        display: flex; align-items: center; gap: .35rem; font-size: 9px;
        font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
    }
    #folios-page .rep-card .rep-value {
        display: block; font-size: 1.35rem; line-height: 1.15; font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    #folios-page .rep-bar {
        margin-top: .35rem; height: 5px; width: 100%; background: #e5e7eb;
        border-radius: 999px; overflow: hidden;
    }
    #folios-page .rep-bar > i { display: block; height: 100%; width: 0; border-radius: 999px; transition: width .8s cubic-bezier(.4, 0, .2, 1); }
    #folios-page .rep-toolbar {
        background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        border: 1px solid #e2e8f0; border-radius: .75rem; padding: .75rem .85rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    #folios-page .rep-toolbar .form-select,
    #folios-page .rep-toolbar input[type="text"] {
        background: #fff; border: 1px solid #d1d5db; border-radius: .5rem;
        font-size: .8125rem; transition: border-color .15s ease, box-shadow .15s ease;
    }
    #folios-page .rep-toolbar .form-select:focus,
    #folios-page .rep-toolbar input[type="text"]:focus {
        border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); outline: none;
    }
    #folios-page #buscar { padding: .5rem 2.25rem; }
</style>

 <div id="folios-page" class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-8">

    {{-- ── CARD PRINCIPAL: Listado de Folios ───────────────────────────── --}}
     <div class="card overflow-hidden lg:col-span-12">
        <div class="card-header border-b border-gray-100 py-4 px-5">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <h4 class="text-lg font-bold text-primary uppercase flex items-center">
                    <i class='bx bx-folder-open text-2xl mr-2'></i> LISTADO DE FOLIOS
                </h4>

                <div class="flex flex-wrap gap-2.5">
                {{-- Tarjetas de Indicadores --}}
                    <div class="rep-card">
                        <div class="rep-label text-slate-400"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Total</div>
                        <span id="countTotal" class="rep-value text-slate-800">0</span>
                        <div class="rep-bar bg-slate-100"><i id="barTotal" class="bg-slate-500"></i></div>
                    </div>
                    <div class="rep-card" style="border-color:#bbf7d0;">
                        <div class="rep-label text-emerald-600"><i class='bx bx-check-circle'></i> Activos</div>
                        <span id="countActivos" class="rep-value text-emerald-700">0</span>
                        <div class="rep-bar bg-emerald-100"><i id="barActivos" class="bg-emerald-500"></i></div>
                    </div>
                    <div class="rep-card" style="border-color:#fecaca;">
                        <div class="rep-label text-red-500"><i class='bx bx-x-circle'></i> Inactivos</div>
                        <span id="countInactivos" class="rep-value text-red-600">0</span>
                        <div class="rep-bar bg-red-100"><i id="barInactivos" class="bg-red-400"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 space-y-4">

            {{-- Barra de filtros con la misma estructura de Gestión DJ --}}
            <div class="rep-toolbar">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                        <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                        <input type="text" id="buscar" placeholder="Buscar folio..." autocomplete="off"
                            class="w-full text-sm uppercase" />
                    </div>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <button type="button" id="btnNuevoFolio"
                            class="btn rounded-full bg-primary/25 text-primary hover:bg-primary hover:text-white flex items-center gap-1 px-4 py-1">
                            <i class='bx bx-plus text-base'></i><span>Nuevo Folio</span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 pt-3 border-t border-slate-200">

                    {{-- Prioridad --}}
                    <div class="flex items-center gap-2">
                        <label for="filtroTipos" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-category text-gray-400 text-lg'></i>Prioridad</label>
                        <select id="filtroTipos" class="form-select text-sm w-36 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            <option value="TODOS" selected>Todos ({{ $todos }})</option>
                            <option value="PRINCIPAL">Principal ({{ $principal }})</option>
                            <option value="ADICIONAL">Adicional ({{ $adicional }})</option>
                        </select>
                    </div>

                    {{-- Clasificación --}}
                    <div class="flex items-center gap-2">
                        <label for="filtroClasificacion" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                        <select id="filtroClasificacion" class="form-select text-sm w-44 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            <option value="TODOS" selected>Todos</option>
                            <option value="DOCUMENTO">Documento ({{ $documento }})</option>
                            <option value="FORMATO">Formato ({{ $formato }})</option>
                            <option value="CERTIFICADO">Certificado ({{ $certificado }})</option>
                        </select>
                    </div>

                    {{-- Categoría --}}
                    <div class="flex items-center gap-2">
                        <label for="filtroCategoria" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-folder text-gray-400 text-lg'></i>Categoría</label>
                        <select id="filtroCategoria" class="form-select text-sm w-48 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            <option value="TODOS" selected>Todas</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->codigo }}">{{ $categoria->nombre }}</option>
                            @endforeach
                            <option value="SIN_CATEGORIA">Sin categoría</option>
                        </select>
                    </div>

                    {{-- Caduca --}}
                    <div class="flex items-center gap-2">
                        <label for="vencimientoFiltro" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-time text-gray-400 text-lg'></i>Caduca</label>
                        <select id="vencimientoFiltro" class="form-select text-sm w-36 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                            <option value="TODOS" selected>Todos</option>
                            <option value="SI">Sí</option>
                            <option value="NO">No</option>
                        </select>
                    </div>
                    {{-- Estado --}}
                    <div class="flex items-center gap-2 ml-auto">
                    <label for="filtroEstado" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-time text-gray-400 text-lg'></i>Estado</label>
                    <select id="filtroEstado" class="form-select text-sm w-32 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                        <option value="TODOS" selected>Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
                </div>
            </div>

            {{-- Tabla --}}
            <div id="tblFolios" class="w-full"></div>

            {{-- Selector de registros --}}
            <div class="flex items-center gap-2">
                <label for="page-size" class="text-sm text-gray-600">Mostrar</label>
                 <select id="page-size" class="form-select text-sm w-20">
                      <option value="5">5</option>
                      <option value="10">10</option>
                      <option value="20" selected>20</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                  </select>
                <span class="text-sm text-gray-600">registros</span>
            </div>

        </div>
    </div>

    {{-- ── MODAL: Formulario de Gestión ────────────────────────── --}}
    <button type="button" class="hidden" id="btn-modal-gestion" data-hs-overlay="#modal-gestion-folio"></button>

    <div id="modal-gestion-folio"
         class="hs-overlay hidden fixed inset-0 z-[80] overflow-y-auto transition-all duration-500 pointer-events-none">
        <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100
                    translate-y-10 opacity-0 ease-in-out transition-all duration-500
                    sm:max-w-xl w-full my-8 sm:mx-auto flex flex-col bg-white shadow-sm rounded-lg pointer-events-auto
                    border border-gray-200">

            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-slate-50 rounded-t-lg">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-bold text-gray-900">Gestión de Folios</h3>
                    <span id="txtMensajeNuevo"
                          class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                        Nuevo registro
                    </span>
                </div>
                <button type="button" id="btn-modal-gestion-close"
                        class="text-gray-500 hover:text-gray-700 transition-colors"
                        data-hs-overlay="#modal-gestion-folio">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-5">
                <form id="formSaveFolio">
                    <div x-data="{ nameFolio: '', tipoSeleccionado: '' }" class="space-y-4">

                        {{-- Nombre + Categoría --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                  <label for="nombre" class="block text-sm font-bold text-gray-700 mb-1">Nombre</label>
                                  <input type="text" id="nombre" class="form-input w-full px-3 py-2 border-gray-300 rounded-md focus:border-primary focus:ring-primary"
                                         x-model="nameFolio"
                                         @input="nameFolio = nameFolio.toUpperCase()"
                                         placeholder="Nombre del folio" required>
                                  <div id="avisoNombreRepetidoFolio" class="hidden mt-1 flex items-center gap-2 px-3 py-2 rounded-md bg-yellow-50 border border-yellow-300 text-yellow-800 text-xs">
                                      <i class="fa-solid fa-triangle-exclamation"></i>
                                      <span>Ya existe un folio con este nombre.</span>
                                  </div>
                              </div>
                            <div>
                                <label for="categoria" class="block text-sm font-bold text-gray-700 mb-1">Categoría</label>
                                <select id="categoria" class="form-select w-full px-3 py-2 border-gray-300 rounded-md focus:border-primary focus:ring-primary" required>
                                    <option value="" disabled selected>— Seleccionar —</option>
                                    @foreach ($categorias as $cat)
                                        <option value="{{ $cat->codigo }}">{{ $cat->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Tipo + Responsable --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tipo" class="block text-sm font-bold text-gray-700 mb-1">Tipo</label>
                                <select id="tipo" class="form-select w-full px-3 py-2 border-gray-300 rounded-md focus:border-primary focus:ring-primary" x-model="tipoSeleccionado" required>
                                    <option value="" disabled selected>— Seleccionar —</option>
                                    <option value="1">DOCUMENTO</option>
                                    <option value="2">FORMATO</option>
                                    <option value="3">CERTIFICADO</option>
                                </select>
                            </div>
                            <div>
                                <label for="responsable" class="block text-sm font-bold text-gray-700 mb-1">Responsable</label>
                                <select id="responsable" class="form-select w-full px-3 py-2 border-gray-300 rounded-md focus:border-primary focus:ring-primary" required>
                                    <option value="" disabled selected>— Seleccionar —</option>
                                    @foreach ($responsables as $responsable)
                                        <option value="{{ (int) $responsable->codigo }}">{{ $responsable->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Descripción contextual por tipo --}}
                        <div>
                            <span x-show="tipoSeleccionado === '1'"
                                  class="block w-full px-3 py-2 rounded-md text-xs font-medium bg-red-100 text-red-800">
                                <strong>Documento:</strong> Folio que el personal trae a la empresa.
                            </span>
                            <span x-show="tipoSeleccionado === '2'"
                                  class="block w-full px-3 py-2 rounded-md text-xs font-medium bg-yellow-100 text-yellow-800">
                                <strong>Formato:</strong> Folio emitido por la empresa.
                            </span>
                            <span x-show="tipoSeleccionado === '3'"
                                  class="block w-full px-3 py-2 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                                <strong>Certificado:</strong> Folio emitido por una entidad educativa.
                            </span>
                        </div>

                        {{-- Tipo de folio: Principal / Adicional --}}
                        <div class="flex items-center gap-4 mt-2">
                            <span class="text-sm font-bold text-gray-700 whitespace-nowrap">Prioridad:</span>
                            <div class="flex items-center gap-5">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" class="form-radio text-primary" id="radioPrin" name="tipo_folio" value="PRINCIPAL" checked>
                                    <span class="text-sm font-medium">Principal</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" class="form-radio text-primary" id="radioAdi" name="tipo_folio" value="ADICIONAL">
                                    <span class="text-sm font-medium">Adicional</span>
                                </label>
                            </div>
                        </div>
                        {{-- Vencimiento + Periodo --}}
                        <div class="flex items-center gap-4 mt-2">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="switchVencimiento" class="form-switch text-danger cursor-pointer">
                                <label for="switchVencimiento" class="text-sm font-bold cursor-pointer">Caducidad</label>
                            </div>
                            <div id="periodoDiv" class="hidden flex items-center gap-2">
                                <label for="periodo" class="text-sm font-medium text-gray-700 whitespace-nowrap">Periodo:</label>
                                <select id="periodo" class="form-select text-sm w-44 pl-3 pr-8 py-1.5 border-gray-300 rounded-md">
                                    <option disabled selected>— Seleccionar —</option>
                                    @foreach ($periodos as $periodo)
                                        <option value="{{ $periodo->codigo }}">{{ $periodo->descripcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Solo lectura --}}
                        <div class="flex items-center gap-4 mt-2">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="switchLectura" class="form-switch text-danger cursor-pointer">
                                <label for="switchLectura" class="text-sm font-bold cursor-pointer">Sólo lectura</label>
                            </div>
                        </div>
                   

                        {{-- Institución (oculto por defecto) --}}
                        <div id="institucionDiv" class="hidden flex items-center gap-5 mt-2">
                            <span class="text-sm font-bold text-gray-700">Plataforma:</span>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" class="form-radio text-danger" id="radioICMA" name="institucion" value="ICMA">
                                <span class="text-sm font-medium">ICMA</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" class="form-radio text-danger" id="radioAV" name="institucion" value="AV">
                                <span class="text-sm font-medium">AV</span>
                            </label>
                        </div>

                    </div>

                    <input type="hidden" name="codFolio" id="codFolio">

                    {{-- Botones --}}
                    <div class="flex justify-end gap-3 mt-8 border-t border-gray-200 pt-4">
                        <button type="button" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg px-4 py-2" data-hs-overlay="#modal-gestion-folio">
                            Cancelar
                        </button>
                        <button type="submit" id="submitButton"
                                class="btn bg-success text-white hover:bg-green-600 rounded-lg px-4 py-2 flex items-center gap-2">
                            Guardar <i class="fa-solid fa-floppy-disk"></i>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

</div>

@endsection

@vite(['resources/js/functions/folios.js'])
