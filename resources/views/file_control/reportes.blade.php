@extends('layouts.vertical', ['title' => 'Reportes'])


@section('css')
    <style>
        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }

        .tabla-reporte th,
        .tabla-reporte td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            white-space: nowrap;
        }

        .tabla-reporte thead th {
            background-color: #1e3a5f;
            color: #fff;
            text-align: center;
        }

        .tabla-reporte tbody tr:nth-child(even) {
            background-color: #f3f4f6;
        }

        .tc {
            text-align: center;
        }

        .celda-p {
            background-color: #d1fae5;
            color: #065f46;
            font-weight: 600;
            text-align: center;
        }

        .celda-a {
            background-color: #dbeafe;
            color: #1e40af;
            font-weight: 600;
            text-align: center;
        }

        .celda-x {
            background-color: #fee2e2;
            color: #991b1b;
            font-weight: 600;
            text-align: center;
        }

        .celda-si {
            background-color: #d1fae5;
            color: #065f46;
            font-weight: 600;
            text-align: center;
        }

        .celda-no {
            background-color: #fee2e2;
            color: #991b1b;
            font-weight: 600;
            text-align: center;
        }

        .reporte-grupo-header td {
            background-color: #1e3a5f;
            color: #fff;
            font-weight: 600;
            padding: 5px 8px;
        }

        .overflow-x-auto::-webkit-scrollbar {
            height: 6px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        /* Sticky header + scrollbar para tablas de reporte */
        .tabla-reporte thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            box-shadow: 0 1px 0 #d1d5db;
            /* Fix borde que desaparece con border-collapse + sticky */
        }

        .tabla-scroll {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
        }

        .tabla-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .tabla-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .tabla-scroll::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        .tabla-scroll::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>
@endsection

@section('content')

    @include("layouts.shared/page-title", ["subtitle" => "File Control", "title" => "Reportes"])

    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx-style@0.8.13/dist/xlsx.full.min.js"></script>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6 pt-8">

        <!-- Folios Vigentes -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bxs-file-find text-primary text-2xl'></i>
                    Folios vigentes
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de folios vigentes en el sistema con
                    su clasificación.</p>
                <button type="button" id="btnReporteFoliosVigentes" class="mt-3 inline-flex items-center
                       gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary
                      hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

        <!-- Folios Pendientes -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bx-hourglass text-warning text-2xl'></i>
                    Folios pendientes
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de folios pendientes por
                    sucursal.</p>
                <button type="button" id="btnReporteFoliosPendientesSucursal" class="mt-3 inline-flex
                      items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary
                      hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

        <!-- Folios Por Vencer -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bxs-bell-ring text-danger text-2xl'></i>
                    Folios Por Vencer
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de folios Por Vencer.</p>
                <button type="button" id="btnReporteFoliosPorVencer" class="mt-3 inline-flex
                      items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary
                      hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

        <!-- Folios Pendientes de Escaneo -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bx-scan text-info text-2xl'></i>
                    Folios Pendientes de Escaneo
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de folios que aún no han sido
                    escaneados.</p>
                <button type="button" id="btnReporteFoliosPendientesEscaneo" class="mt-3 inline-flex
                      items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary
                      hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

        <!-- Folios Pendientes de Registro -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bx-list-ul text-secondary text-2xl'></i>
                    Folios Pendientes de Registro
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de folios que aún no han sido
                    registrados en el sistema.</p>
                <button type="button" id="btnReporteFoliosPendientesRegistro" class="mt-3 inline-flex
                      items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary
                      hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

        <!-- Vigencia de Documentos -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bx-time-five text-success text-2xl'></i>
                    Vigencia de Documentos
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de documentos según su estado de
                    vigencia.</p>
                <button type="button" id="btnReporteVigenciaDocumentos" class="mt-3 inline-flex
                      items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary
                      hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

        <!-- Carnet -->
        <div type="button" id="btnReporteCarnet" class="card custom-card h-full cursor-pointer">
    <div class="p-4 md:p-5 flex flex-col justify-between h-full">
        <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
            <i class='bx bx-id-card text-primary text-2xl'></i>
            Carnet y Certificados
        </h3>
        <p class="mt-2 text-default-500">Genera el reporte de carnet y certificados en el sistema.</p>
        <span class="mt-3 inline-flex items-center gap-x-1 text-sm font-semibold text-primary">
            Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
        </span>
    </div>
</div>

        <!-- Constancias de Entrega -->
        <div class="card custom-card h-full">
            <div class="p-4 md:p-5 flex flex-col justify-between h-full">
                <h3 class="text-lg font-bold text-default-800 flex items-center gap-2">
                    <i class='bx bx-clipboard text-info text-2xl'></i>
                    Constancias de Entrega
                </h3>
                <p class="mt-2 text-default-500">Genera el reporte de constancias de entrega de
                    documentos al personal.</p>
                <button type="button" id="btnReporteConstanciasEntrega"
                    class="mt-3 inline-flex items-center gap-x-1 text-sm font-semibold rounded-lg border border-transparent text-primary hover:text-primary-800">
                    Generar <i class="material-symbols-rounded text-lg flex-shrink-0">chevron_right</i>
                </button>
            </div>
        </div>

    </div>


    <!-- ====== MODAL: Folios Vigentes ====== -->
    <div id="modalFoliosVigentes" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bxs-file-find text-primary text-xl'></i>
                    Folios Vigentes
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 flex-shrink-0 border-b border-default-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="text-default-800 text-sm font-medium inline-block mb-2">Tipo de folio</label>
                        <select id="filtroTipoFolio" class="form-select">
                            <option value="">Todos</option>
                            <option value="DOCUMENTO">Documento</option>
                            <option value="FORMATO">Formato</option>
                            <option value="CERTIFICADO">Certificado</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-default-800 text-sm font-medium inline-block mb-2">Prioridad</label>
                        <select id="filtroPrioridad" class="form-select">
                            <option value="">Todas</option>
                            <option value="PRINCIPAL">Obligatorio</option>
                            <option value="ADICIONAL">Adicional</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                        <button id="btnGenerarFoliosVigentes"
                            class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                            <i class="bx bx-search-alt-2"></i> Generar
                        </button>
                    </div>
                </div>
            </div>
            <!-- Resultados -->
            <div id="resultadosFoliosVigentes" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                <div class="flex items-center justify-between mb-3 flex-shrink-0">
                    <span id="totalFoliosVigentes" class="text-sm text-default-600 font-medium"></span>
                    <div class="flex gap-2">
                        <button id="btnExportExcelVigentes"
                            class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                        <button id="btnExportPdfVigentes"
                            class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-auto flex-1">
                    <div id="tablaFoliosVigentes"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- ====== MODAL: Folios Pendientes ====== -->
    <div id="modalFoliosPendientesSucursal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bx-hourglass text-warning text-xl'></i>
                    Folios Pendientes
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 flex-shrink-0 border-b border-default-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label for="sucursal" class="text-default-800 text-sm font-medium mb-2 block">Sucursal</label>
                        <select id="sucursal" class="form-select">
                            <option disabled selected>-Seleccionar-</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-2">
                        <button
                            class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                        <button id="btnGenerarFoliosPendientes"
                            class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                            <i class="bx bx-search-alt-2"></i> Generar
                        </button>
                    </div>
                </div>
            </div>
            <!-- Resultados -->
            <div id="resultadosFoliosPendientes" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                <div class="flex items-center justify-between mb-3 flex-shrink-0">
                    <span id="totalFoliosPendientes" class="text-sm text-default-600 font-medium"></span>
                    <div class="flex gap-2">
                        <button id="btnExportExcelPendientes"
                            class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                        <button id="btnExportPdfPendientes"
                            class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-auto flex-1">
                    <div id="tablaFoliosPendientes"></div>
                </div>
            </div>
        </div>
    </div>


    <!-- ====== MODAL: Folios Por Vencer ====== -->
    <div id="modalFoliosPorVencer" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bxs-bell-ring text-danger text-xl'></i>
                    Folios Por Vencer
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 flex-shrink-0 border-b border-default-100">
                <div class="flex flex-wrap gap-6 items-end">
                    <div>
                        <label class="text-default-800 text-sm font-medium inline-block mb-3">Filtrar por</label>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="tipoFiltro" value="sucursal" class="form-radio">
                                <span>Sucursal</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="tipoFiltro" value="cliente" class="form-radio">
                                <span>Cliente</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="tipoFiltro" value="servicio" class="form-radio">
                                <span>Código de servicio</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex-1 grid grid-cols-1 gap-4">
                        <div id="filtroSucursalDiv" class="hidden">
                            <label class="text-default-800 text-sm font-medium mb-2 block">Sucursal</label>
                            <select id="filtroSucursalSelect" class="tom-select">
                                <option value="">-Seleccionar-</option>
                                @foreach($sucursales as $sucursal)
                                    <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="filtroClienteDiv" class="hidden">
                            <label class="text-default-800 text-sm font-medium mb-2 block">Cliente</label>
                            <select id="filtroClienteSelect" class="tom-select">
                                <option value="">-Seleccionar-</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->cod_legacy }}">{{ $cliente->abreviatura }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="filtroCodigoDiv" class="hidden">
                            <label class="text-default-800 text-sm font-medium mb-2 block">Código</label>
                            <select id="filtroCodigoSelect" class="form-select">
                                <option value="">-Seleccionar-</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2 self-end">
                        <button
                            class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                        <button id="btnGenerarFoliosPorVencer"
                            class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                            <i class="bx bx-search-alt-2"></i> Generar
                        </button>
                    </div>
                </div>
            </div>
            <!-- Resultados -->
            <div id="resultadosFoliosPorVencer" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                <div class="flex items-center justify-between mb-3 flex-shrink-0">
                    <span id="totalFoliosPorVencer" class="text-sm text-default-600 font-medium"></span>
                    <div class="flex gap-2">
                        <button id="btnExportExcelPorVencer"
                            class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                        <button id="btnExportPdfPorVencer"
                            class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-auto flex-1">
                    <div id="tablaFoliosPorVencer"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- ====== MODAL: Folios Pendientes de Escaneo ====== -->
    <div id="modalFoliosPendientesEscaneo" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bx-scan text-info text-xl'></i>
                    Folios Pendientes de Escaneo
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 flex-shrink-0 border-b border-default-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4">
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">Sucursal</label>
                        <select id="filtroEscaneoSucursal" class="to-amber-800-select">

                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">Cliente</label>
                        <select id="filtroEscaneoCliente" class="tom-select">
                            {{-- <option value="">Todos</option> --}}
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->cod_legacy }}">{{ $cliente->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 items-end">
                        <button
                            class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                        <button id="btnGenerarEscaneo"
                            class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                            <i class="bx bx-search-alt-2"></i> Generar
                        </button>
                    </div>
                </div>
                <!-- Selector de personal -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-default-800 text-sm font-medium">
                            Personal
                            <span class="text-default-400 font-normal ml-1">(opcional — sin selección incluye todos)</span>
                        </label>
                        <span id="contadorSeleccionadosEscaneo" class="text-xs text-primary font-semibold"></span>
                    </div>
                    <input type="text" id="buscarPersonalEscaneo" class="form-control mb-2"
                        placeholder="Buscar por nombre o DNI...">
                    <div class="border border-default-200 rounded-lg overflow-hidden">
                        <div style="max-height:220px; overflow-y:auto;">
                            <table class="tabla-reporte" style="font-size:0.78rem">
                                <thead style="position:sticky;top:0;z-index:1">
                                    <tr>
                                        <th style="width:36px">
                                            <input type="checkbox" id="chkTodosEscaneo"
                                                title="Seleccionar todos los visibles">
                                        </th>
                                        <th style="width:70px">Código</th>
                                        <th style="text-align:left">Apellidos y Nombres</th>
                                        <th style="width:110px">Tipo</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPersonalEscaneo">
                                    <tr>
                                        <td colspan="4" class="tc" style="color:#94a3b8;padding:12px">
                                            Selecciona una sucursal o escribe para buscar
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Resultados -->
            <div id="resultadosEscaneo" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                <div class="flex items-center justify-between mb-3 flex-shrink-0">
                    <span id="totalEscaneo" class="text-sm text-default-600 font-medium"></span>
                    <div class="flex gap-2">
                        <button id="btnExportExcelEscaneo"
                            class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                        <button id="btnExportPdfEscaneo"
                            class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-auto flex-1">
                    <div id="tablaEscaneo"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: Folios Pendientes de Registro ====== -->
    <div id="modalFoliosPendientesRegistro" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bx-list-ul text-secondary text-xl'></i>
                    Folios Pendientes de Registro
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 flex-shrink-0 border-b border-default-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4">
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">Sucursal</label>
                        <select id="filtroRegistroSucursal" class="tom-select">
                            <option value="">Todas</option>
                            @foreach($sucursales as $sucursal)
                                <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">Cliente</label>
                        <select id="filtroRegistroCliente" class="tom-select">
                            <option value="">Todos</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->cod_legacy }}">{{ $cliente->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 items-end">
                        <button
                            class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                        <button id="btnGenerarRegistro"
                            class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                            <i class="bx bx-search-alt-2"></i> Generar
                        </button>
                    </div>
                </div>
                <!-- Selector de personal -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-default-800 text-sm font-medium">
                            Personal
                            <span class="text-default-400 font-normal ml-1">(opcional — sin selección incluye todos)</span>
                        </label>
                        <span id="contadorSeleccionadosRegistro" class="text-xs text-primary font-semibold"></span>
                    </div>
                    <input type="text" id="buscarPersonalRegistro" class="form-control mb-2"
                        placeholder="Buscar por nombre o DNI...">
                    <div class="border border-default-200 rounded-lg overflow-hidden">
                        <div style="max-height:220px; overflow-y:auto;">
                            <table class="tabla-reporte" style="font-size:0.78rem">
                                <thead style="position:sticky;top:0;z-index:1">
                                    <tr>
                                        <th style="width:36px">
                                            <input type="checkbox" id="chkTodosRegistro"
                                                title="Seleccionar todos los visibles">
                                        </th>
                                        <th style="width:70px">Código</th>
                                        <th style="text-align:left">Apellidos y Nombres</th>
                                        <th style="width:110px">Tipo</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPersonalRegistro">
                                    <tr>
                                        <td colspan="4" class="tc" style="color:#94a3b8;padding:12px">
                                            Selecciona una sucursal o escribe para buscar
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Resultados -->
            <div id="resultadosRegistro" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                <div class="flex items-center justify-between mb-3 flex-shrink-0">
                    <span id="totalRegistro" class="text-sm text-default-600 font-medium"></span>
                    <div class="flex gap-2">
                        <button id="btnExportExcelRegistro"
                            class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                        <button id="btnExportPdfRegistro"
                            class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-auto flex-1">
                    <div id="tablaRegistro"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: Visor DNI ====== -->
    <div id="modalVisorDNI" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/70 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-4 border-b border-default-200 flex-shrink-0">
                <div>
                    <h5 class="text-sm font-semibold text-default-800 flex items-center gap-2">
                        <i class='bx bx-id-card text-primary text-lg'></i>
                        <span id="visorDniNombre">—</span>
                    </h5>
                    <p class="text-xs text-default-500 mt-0.5">Código: <span id="visorDniCodigo">—</span></p>
                </div>
                <button id="btnCerrarVisorDNI" class="text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 overflow-auto flex-1">
                <div id="visorDniCargando" class="flex flex-col items-center justify-center py-10 gap-3">
                   
                    <span class="text-sm text-default-500">Cargando imágenes...</span>
                </div>
                <div id="visorDniImagen" class="hidden">
                    <div class="mb-2 flex items-center gap-2">
                        <span id="visorDniBadge"
                            class="text-xs font-semibold bg-primary/10 text-primary px-2 py-0.5 rounded">ANVERSO</span>
                    </div>
                    <img id="visorDniImg" class="w-full rounded-lg shadow border border-default-200 cursor-zoom-in"
                        style="max-height:360px;object-fit:contain" alt="DNI">
                    <div class="mt-3">
                        <button id="btnVisorToggle"
                            class="text-xs px-3 py-1.5 rounded-lg border border-primary text-primary hover:bg-primary/10 disabled:opacity-40 disabled:cursor-not-allowed">
                            Ver reverso
                        </button>
                    </div>
                </div>
                <div id="visorDniError" class="hidden text-center py-10">
                    <i class='bx bx-error-circle text-3xl text-warning mb-2 block'></i>
                    <span class="text-sm text-default-500">No se encontró imagen para este personal.</span>
                </div>
            </div>
            <div class="p-4 border-t border-default-100 flex-shrink-0">
                <button id="btnVolverVisor"
                    class="w-full px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">
                    ← Volver al reporte
                </button>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: Vigencia de Documentos ====== -->
    <div id="modalVigenciaDocumentos" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-4 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bx-time-five text-success text-xl'></i>
                    Vigencia de Documentos
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-5 flex-shrink-0 border-b border-default-100">
                <!-- Fila 1: Documento, Sucursal, Tipo Personal -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4">
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">
                            Documento <span class="text-danger">*</span>
                        </label>
                        <select id="filtroVigDocumento" class="form-select">
                            <option value="">— Seleccionar —</option>
                            <option value="DNI">DNI</option>
                            <option value="BREVETE">BREVETE</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">
                            Sucursal <span class="text-danger">*</span>
                        </label>
                        <select id="filtroVigSucursal" class="form-select">
                            <option value="">— Seleccionar —</option>
                            @foreach($sucursales as $sucursal)
                                @if($loop->first)
                                    @continue
                                @endif
                                <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">
                            Tipo de Personal <span class="text-danger">*</span>
                        </label>
                        <select id="filtroVigTipoPers" class="form-select">
                            <option value="">— Seleccionar —</option>
                            <option value="A">Administrativo</option>
                            <option value="O">Operativo</option>
                        </select>
                    </div>
                </div>
                <!-- Fila 2: Radio estado + botón Memorandum -->
                <div class="flex flex-wrap items-end gap-6 mb-4">
                    <div>
                        <label class="text-default-800 text-sm font-medium mb-2 block">Estado</label>
                        <div class="flex gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="filtroVigEstado" value="SI" checked class="form-radio">
                                <span class="text-sm">Vigentes</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="filtroVigEstado" value="NO" class="form-radio">
                                <span class="text-sm">Vencidos</span>
                            </label>

                        </div>
                    </div>
                    <div class="ml-auto flex flex-col items-end gap-1">
                        <button id="btnMemorandum" type="button" disabled
                            class="px-4 py-2 rounded-lg border border-default-200 text-default-400 bg-default-50 text-sm cursor-not-allowed flex items-center gap-1">
                            <i class='bx bx-file'></i> Memorandum
                        </button>
                        <span id="msgMemorandum" class="hidden text-xs text-warning flex items-center gap-1">
                            <i class='bx bx-info-circle'></i> Formato no disponible
                        </span>
                    </div>
                </div>
                <!-- Fila 3: Filtros de Brevete (ocultos por defecto) -->
                <div id="filtrosBreveteDiv" class="hidden mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-default-800 text-sm font-medium mb-2 block">Clase de Brevete</label>
                            <select id="filtroVigClase" class="form-select">
                                <option value="T">Todos</option>
                                @foreach($clasesBrevete as $clase)
                                    <option value="{{ $clase->nombre }}" data-cod="{{ $clase->codigo }}">{{ $clase->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-default-800 text-sm font-medium mb-2 block">Categoría de Brevete</label>
                            <select id="filtroVigCategoria" class="form-select">
                                <option value="T">Todas</option>
                                @foreach($categoriasBrevete as $cat)
                                    <option value="{{ $cat->nombre }}">{{ $cat->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Botones -->
                <div class="flex justify-end gap-2">
                    <button
                        class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                    <button id="btnGenerarVigencia"
                        class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                        <i class="bx bx-search-alt-2"></i> Generar
                    </button>
                </div>
            </div>
            <!-- Resultados -->
            <div id="resultadosVigencia" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                <div class="flex items-center justify-between mb-3 flex-shrink-0">
                    <span id="totalVigencia" class="text-sm text-default-600 font-medium"></span>
                    <div class="flex gap-2">
                        <button id="btnExportExcelVigencia"
                            class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                            <i class="bx bx-spreadsheet"></i> Excel
                        </button>
                        <button id="btnExportPdfVigencia"
                            class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                            <i class="bx bxs-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="overflow-auto flex-1">
                    <div id="tablaVigencia"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalCarnet" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl mx-auto flex flex-col max-h-[85vh]">
            
            <div class="flex items-center justify-between p-5 border-b border-default-200 flex-shrink-0">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bx-file text-primary text-xl'></i>
                    Reporte de Carnet y Certificados
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>

            <div class="flex border-b border-default-200 bg-default-50 flex-shrink-0">
                <button type="button" id="tabCarnet" class="flex-1 py-3 px-4 text-sm font-bold border-b-2 border-primary text-primary transition-all text-center flex items-center justify-center gap-2 bg-white">
                    <i class='bx bx-id-card text-lg'></i> Carnet
                </button>
                <button type="button" id="tabCertificados" class="flex-1 py-3 px-4 text-sm font-semibold border-b-2 border-transparent text-default-500 hover:text-primary hover:border-default-300 transition-all text-center flex items-center justify-center gap-2">
                    <i class='bx bxs-award text-lg'></i> Certificados
                </button>
            </div>

            <div class="flex-1 flex flex-col min-h-0 overflow-visible rounded-b-xl">
            
                <!-- ================= PESTAÑA CARNET ================= -->
                <div id="contenidoCarnet" class="flex flex-col flex-1 min-h-0 rounded-b-xl">
                    <div class="p-5 flex-shrink-0 border-b border-default-100 rounded-b-xl relative z-10">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">
                                    Categoría <span class="text-danger">*</span>
                                </label>
                                <select id="filtroCarnetCategoria" class="form-select">
                                    <option value="">— Seleccionar —</option>
                                    @foreach($categoriasCarnet as $cat)
                                        <option value="{{ $cat->CATE_CODIGO }}">{{ $cat->CATE_DESCRIPCION }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Sucursal</label>
                                <select id="filtroCarnetSucursal" class="form-select">
                                    <option value="T">Todas</option>
                                    @foreach($sucursales as $sucursal)
                                        @if(!$loop->first)
                                            <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Tipo de Personal</label>
                                <select id="filtroCarnetTipoPers" class="form-select">
                                    <option value="T">Todos</option>
                                    @foreach($tiposPersonal as $tipo)
                                        <option value="{{ $tipo->TIPE_CODIGO }}">{{ $tipo->TIPE_DESCRIPCION }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Vigencia del personal</label>
                                <select id="filtroCarnetVigencia" class="form-select">
                                    <option value="T">Todos</option>
                                    <option value="SI">Vigente</option>
                                    <option value="NO">No vigente</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Estado del carnet</label>
                                <select id="filtroCarnetEstado" class="form-select">
                                    <option value="T">Todos</option>
                                    <option value="1">Activo</option>
                                    <option value="2">Inactivo</option>
                                </select>
                            </div>
                            <div class="flex justify-end gap-2 items-end">
                                <button class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                                <button id="btnGenerarCarnet" class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                                    <i class="bx bx-search-alt-2"></i> Generar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="resultadosCarnet" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                        <div class="flex items-center justify-between mb-3 flex-shrink-0">
                            <span id="totalCarnet" class="text-sm text-default-600 font-medium"></span>
                            <div class="flex gap-2">
                                <button id="btnExportExcelCarnet" class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                                    <i class="bx bx-spreadsheet"></i> Excel
                                </button>
                                <button id="btnExportPdfCarnet" class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                                    <i class="bx bxs-file-pdf"></i> PDF
                                </button>
                            </div>
                        </div>
                        <div class="overflow-auto flex-1">
                            <div id="tablaCarnet"></div>
                        </div>
                    </div>
                </div>

                <!-- ================= PESTAÑA CERTIFICADOS ================= -->
                <div id="contenidoCertificados" class="hidden flex-col flex-1 min-h-0 rounded-b-xl">
                    <div class="p-5 flex-shrink-0 border-b border-default-100 rounded-b-xl relative z-10">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4">
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">
                                    Sucursal <span class="text-danger">*</span>
                                </label>
                                <select id="filtroCertSucursal" class="tom-select">
                                    <option value="">— Seleccionar —</option>
                                    @foreach($sucursales as $sucursal)
                                        @if($loop->first) @continue @endif
                                        <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">
                                    Tipo de Personal <span class="text-danger">*</span>
                                </label>
                                <select id="filtroCertTipoPers" class="tom-select">
                                    <option value="T">Todos</option>
                                    @foreach($tiposPersonal as $tipo)
                                        <option value="{{ $tipo->TIPE_CODIGO }}">{{ $tipo->TIPE_DESCRIPCION }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">
                                    Certificado <span class="text-danger">*</span>
                                </label>
                                <select id="filtroCertCertificado" class="tom-select">
    <option value="">— Seleccionar —</option>
    @foreach($certificados as $cert)
        <option value="{{ $cert->REQU_CODIGO }}">{{ $cert->REQU_DESCRIPCION }}</option>
    @endforeach
</select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end mb-4">
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Vigencia</label>
                                <select id="filtroCertVigencia" class="tom-select">
                                    <option value="T">Todos</option>
                                    <option value="SI">Sí</option>
                                    <option value="NO">No</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Estado</label>
                                <select id="filtroCertEstado" class="tom-select">
                                    <option value="T">Todos</option>
                                    <option value="SI">Activo</option>
                                    <option value="NO">Inactivo</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-default-800 text-sm font-medium mb-2 block">Vencimiento al</label>
                                <input type="date" id="filtroCertFechaVenc" class="form-input" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cancelar</button>
                            <button id="btnGenerarCertificados" class="bg-primary text-white px-5 py-2 rounded-lg shadow hover:bg-primary/90 text-sm">
                                <i class="bx bx-search-alt-2"></i> Generar
                            </button>
                        </div>
                    </div> <div id="resultadosCertificados" class="hidden flex-1 flex flex-col overflow-hidden p-5">
                        <div class="flex items-center justify-between mb-3 flex-shrink-0">
                            <span id="totalCertificados" class="text-sm text-default-600 font-medium"></span>
                            <div class="flex gap-2">
                                <button id="btnExportExcelCertificados" class="bg-success text-white px-4 py-2 rounded-lg shadow hover:bg-success/90 text-sm flex items-center gap-1">
                                    <i class="bx bx-spreadsheet"></i> Excel
                                </button>
                                <button id="btnExportPdfCertificados" class="bg-danger text-white px-4 py-2 rounded-lg shadow hover:bg-danger/90 text-sm flex items-center gap-1">
                                    <i class="bx bxs-file-pdf"></i> PDF
                                </button>
                            </div>
                        </div>
                        <div class="overflow-auto flex-1">
                            <div id="tablaCertificados"></div>
                        </div>
                    </div>
                    </div>
                        <div class="overflow-auto flex-1">
                            <div id="tablaCertificados"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ====== MODAL: Constancias de Entrega ====== -->
    <div id="modalConstanciasEntrega" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4">
            <div class="flex items-center justify-between p-5 border-b border-default-200">
                <h5 class="text-base font-semibold text-default-800 flex items-center gap-2">
                    <i class='bx bx-clipboard text-info text-xl'></i>
                    Filtros – Constancias de Entrega
                </h5>
                <button class="btnCerrarModal text-default-400 hover:text-default-600">
                    <i class='bx bx-x text-2xl'></i>
                </button>
            </div>
            <div class="p-6">
                <p class="text-default-500 text-sm">Próximamente...</p>
                <div class="mt-6 flex justify-end">
                    <button
                        class="btnCerrarModal px-4 py-2 rounded-lg border border-default-300 text-default-600 hover:bg-default-100 text-sm">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@vite(['resources/js/functions/reportes.js'])
@section('script')
@endsection
