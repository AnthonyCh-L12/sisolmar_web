@extends('layouts.vertical', ['title' => 'Actualizar DJ'])

@section('css')
    <style>
        ::placeholder { color: #9ca3af !important; opacity: 1 !important; }
        .tab-content { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tab-circle {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 9999px;
            border: 1.5px solid currentColor;
            font-size: 10px; font-weight: 700; margin-right: 6px; flex-shrink: 0;
        }
        .stage-circle {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 9999px;
            background: #0ea5e9; color: white;
            font-size: 13px; font-weight: 700; margin-right: 10px; flex-shrink: 0;
        }

        /* ════════════════════════════════════════════════════════════
           SISTEMA VISUAL COMPARTIDO (Reporte Personal / Gestión DJ)
           ════════════════════════════════════════════════════════════ */

        /* ── Tablas ─────────────────────────────────────────────────────── */
        .tbl-listado.tabulator .tabulator-cell { font-size: 0.8125rem; }
        .tbl-listado.tabulator .tabulator-row {
            transition: background-color .15s ease, box-shadow .15s ease;
            background-color: #ffffff;               /* base: blanco, sin zebra */
        }
        .tbl-listado.tabulator .tabulator-row.rep-cesado { background-color: #fee2e2; }
        .tbl-listado.tabulator .tabulator-row.rep-alerta { background-color: #fecaca; }

        .tbl-listado.tabulator .tabulator-row:hover {
            background-color: #eef4ff !important;
            box-shadow: inset 3px 0 0 0 #2563eb;
        }
        .tbl-listado.tabulator .tabulator-row.rep-cesado:hover,
        .tbl-listado.tabulator .tabulator-row.rep-alerta:hover {
            background-color: #fecaca !important;
            box-shadow: inset 3px 0 0 0 #dc2626;
        }
        /* la selección por checkbox sigue viéndose */
        .tbl-listado.tabulator .tabulator-row.tabulator-selected { background-color: #9abcea; }
        .tbl-listado.tabulator .tabulator-row.tabulator-selected:hover { background-color: #769bcc; }

        .tbl-listado.tabulator .tabulator-header {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #e2e8f0;
        }
        .tbl-listado.tabulator .tabulator-header .tabulator-col {
            background: transparent;
            border-right-color: #e9eef5;
        }
        .tbl-listado.tabulator .tabulator-header .tabulator-col .tabulator-header-content {
            font-weight: 700;
            color: #475569;
            font-size: .7rem;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .tbl-listado.tabulator .tabulator-placeholder-wrapper {
            display: flex; align-items: center; justify-content: center;
            gap: .5rem; color: #94a3b8; font-size: .875rem;
        }

        /* ── Cards de totales ───────────────────────────────────────────── */
        .rep-card {
            position: relative;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: .75rem;
            padding: .65rem .85rem;
            min-width: 118px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
            transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
        }
        .rep-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(15, 23, 42, .10);
        }
        .rep-card .rep-label {
            display: flex; align-items: center; gap: .35rem;
            font-size: 9px; font-weight: 800; letter-spacing: .07em;
            text-transform: uppercase;
        }
        .rep-card .rep-value {
            display: block;
            font-size: 1.35rem; line-height: 1.15; font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .rep-bar {
            margin-top: .35rem;
            height: 5px; width: 100%;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }
        .rep-bar > i {
            display: block; height: 100%; width: 0;
            border-radius: 999px;
            transition: width .8s cubic-bezier(.4, 0, .2, 1);
        }

        /* ── Barra de filtros ───────────────────────────────────────────── */
        .rep-toolbar {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            border: 1px solid #e2e8f0;
            border-radius: .75rem;
            padding: .75rem .85rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .rep-toolbar .form-select,
        .rep-toolbar .form-input,
        .rep-toolbar input[type="text"] {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: .5rem;
            font-size: .8125rem;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .rep-toolbar .form-select:focus,
        .rep-toolbar .form-input:focus,
        .rep-toolbar input[type="text"]:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
            outline: none;
        }
        /* @tailwindcss/forms pisa las utilidades de padding */
        .rep-toolbar input[type="text"] { padding: .5rem .75rem; }
        /* Los buscadores llevan la lupa a la izquierda: se reserva su ancho */
        #buscarPersonalE1, #buscarPersonalE2, #buscarPersonalE3 { padding: .5rem 2.25rem; }

        /* ── Pies de tabla ──────────────────────────────────────────────── */
        .rep-footline {
            border-top: 1px dashed #e5e7eb;
            margin-top: .75rem;
            padding-top: .65rem;
        }
        .rep-footline .rep-info {
            font-size: .75rem; color: #64748b;
            font-variant-numeric: tabular-nums;
        }
        .rep-footline .rep-info b { color: #0f172a; font-weight: 700; }
    </style>
@endsection

@section('content')

    @include("layouts.shared/page-title", ["subtitle" => "DJ", "title" => "Actualizar DJ"])

    <!-- 🔥 NUEVO CONTROLES MACRO: ANUAL / DEMANDA -->
    <div class="mt-4 mb-8 flex justify-center">
        <div class="inline-flex bg-gray-100 p-1 rounded-full border border-gray-200 shadow-inner">
            <button id="btnModoAnual" class="active bg-white text-blue-900 shadow-md rounded-full px-8 py-2 text-sm font-bold uppercase transition-all">
                Anual
            </button>
            <button id="btnModoDemanda" class="text-gray-500 hover:text-blue-700 rounded-full px-8 py-2 text-sm font-bold uppercase transition-all">
                Demanda
            </button>
        </div>
    </div>


    <div id="tabs-container">

        <div id="etapa1" class="tab-content {{ $esRrhhMigracion ? 'hidden' : 'active' }}">
            <div class="grid lg:grid-cols-1 gap-6">
                <div class="card overflow-hidden border border-gray-100 shadow-sm">
                    <div class="w-full px-5 py-4">

                        <!-- CONTENEDOR UNIFICADO (JS controlará si son 1 o 2 filas) -->
                        <div class="flex flex-wrap justify-between items-center mb-6 gap-4 w-full">
                            <!-- IZQUIERDA: Título y Año -->
                            <div class="flex items-center gap-4 flex-1 min-w-[250px]">
                                <h4 class="text-lg font-bold text-primary uppercase flex items-center"><span class="stage-circle">1</span> Actualización por SIP</h4>
                                <div id="contenedorFiltroAnio" class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-calendar text-gray-400 text-lg'></i>Año</label>
                                    <select id="filtroAnio" class="form-select text-sm w-28 px-3 py-1 border border-gray-300 rounded-lg focus:ring-primary">
                                        <option value="">Todos</option>
                                        <option value="2026" selected>2026</option>
                                        <option value="2025">2025</option>
                                    </select>
                                </div>
                            </div>

                            <!-- CENTRO: Pestañas (Nace en 2 filas) -->
                            <div id="nav-dest-etapa1" class="flex justify-center transition-all w-full order-last mt-4">
                                <nav class="flex flex-nowrap items-center justify-center gap-3 whitespace-nowrap" aria-label="Tabs" id="dj-timeline-tabs">
                                    <button data-target="etapa1" data-active="bg-blue-900 text-white shadow-lg" data-inactive="bg-white text-gray-500 border border-gray-200"
                                        class="tab-btn {{ $esRrhhMigracion ? 'hidden' : 'active bg-blue-900 text-white shadow-lg' }} rounded-full px-5 py-2 text-sm font-medium transition-all whitespace-nowrap">
                                        <span class="tab-circle">1</span> ETAPA: Actualización por SIP
                                    </button>
                                    <button data-target="etapa2" data-active="bg-blue-900 text-white shadow-lg" data-inactive="bg-white text-gray-500 border border-gray-200"
                                        class="tab-btn {{ $esRrhhMigracion ? 'hidden' : 'bg-white text-gray-500 border border-gray-200' }} rounded-full px-5 py-2 text-sm font-medium transition-all whitespace-nowrap hover:border-blue-300 hover:text-blue-700">
                                        <span class="tab-circle">2</span> ETAPA: Verificación
                                    </button>
                                    <button data-target="etapa3" data-active="bg-blue-900 text-white shadow-lg" data-inactive="bg-white text-gray-500 border border-gray-200" class="tab-btn hidden" style="display: none !important;"><span class="tab-circle">3</span> ETAPA: Generación DJ en PDF</button>
                                    <button data-target="etapa_carga" data-active="bg-blue-900 text-white shadow-lg" data-inactive="bg-white text-gray-500 border border-gray-200" class="tab-btn hidden" style="display: none !important;"><span class="tab-circle">4</span> ETAPA: Carga de DJ</button>
                                    <button data-target="etapa4" data-active="bg-blue-900 text-white shadow-lg" data-inactive="bg-white text-gray-500 border border-gray-200" class="tab-btn hidden" style="display: none !important;"><span class="tab-circle">5</span> ETAPA: Validación de Imágenes</button>
                                </nav>
                            </div>

                            <!-- DERECHA: Contadores -->
                            <div class="flex-1 flex flex-wrap justify-end gap-2.5 min-w-[250px]">
                                <div class="rep-card">
                                    <div class="rep-label text-slate-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Total
                                    </div>
                                    <span id="countTotalE1" class="rep-value text-slate-800">0</span>
                                    <div class="rep-bar bg-slate-100"><i id="barTotalE1" class="bg-slate-500"></i></div>
                                </div>
                                <div class="rep-card" style="border-color:#bbf7d0;">
                                    <div class="rep-label text-emerald-600">
                                        <i class='bx bx-check-circle'></i> Actualiz.
                                    </div>
                                    <span id="countActualizadosE1" class="rep-value text-emerald-700">0</span>
                                    <div class="rep-bar bg-emerald-100"><i id="barActualizadosE1" class="bg-emerald-500"></i></div>
                                </div>
                                <div class="rep-card" style="border-color:#fecaca;">
                                    <div class="rep-label text-red-500">
                                        <i class='bx bx-x-circle'></i> Sin Actual.
                                    </div>
                                    <span id="countSinActualizarE1" class="rep-value text-red-600">0</span>
                                    <div class="rep-bar bg-red-100"><i id="barSinActualizarE1" class="bg-red-400"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="rep-toolbar">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                                    <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                                    <input type="text" id="buscarPersonalE1" placeholder="Buscar por nombre o DNI..." class="w-full text-sm uppercase" autocomplete="off" />
                                    <button type="button" id="btnLimpiarBusquedaE1" title="Limpiar búsqueda" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors">
                                        <i class='bx bx-x-circle text-lg'></i>
                                    </button>
                                </div>
                                <div class="ml-auto flex flex-wrap items-center gap-2">
                                    <button id="btnExportExcelE1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700 text-xs font-semibold hover:bg-green-600 hover:text-white hover:border-green-600 transition-colors">
                                        <i class='bx bx-spreadsheet text-base'></i> Excel
                                    </button>
                                    <button id="btnExportPdfE1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-300 bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">
                                        <i class='bx bxs-file-pdf text-base'></i> PDF
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 pt-3 border-t border-slate-200">

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-building text-gray-400 text-lg'></i>Sucursal</label>
                                    @php $sucursalesFiltradasE1 = array_slice($sucursales, 1); @endphp
                                    <select id="filtroSucursalE1" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        @if(count($sucursalesFiltradasE1) > 1) <option value="00">Todas</option> @endif
                                        @foreach ($sucursalesFiltradasE1 as $sucursal) <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option> @endforeach
                                    </select>
                                </div>

                                <div class="flex items-center gap-2">
    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
    <select id="filtroTipoE1" class="form-select text-sm w-44 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
    @if($tipoPerLimitar == 0)
        <option value="00">Todos</option>
        <option value="03" selected>Operativo 5°</option>
        <option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 1)
        <option value="00" selected>Todos</option>
        <option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 2)
        <option value="00">Todos</option>
        <option value="03" selected>Operativo 5°</option>
    @endif
</select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-time text-gray-400 text-lg'></i>Estado</label>
                                    <select id="filtroEstadoE1" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        <option value="null" selected>Todos</option>
                                        <option value="0">Actualizados</option>
                                        <option value="1">Sin Actualizar</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="tblEtapa1" class="w-full mt-6 tbl-listado rounded-lg overflow-hidden border border-gray-200"></div>

                        <div class="rep-footline flex items-center gap-2 mt-3">
                            <label for="page-size-etapa1" class="text-sm text-gray-600">Mostrar</label>
                            <select id="page-size-etapa1" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                <option value="5">5</option><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
                            </select>
                            <span class="text-sm text-gray-600">registros</span>
                            <div class="rep-info ml-auto" id="tblInfoE1"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div id="etapa2" class="tab-content hidden">
            <div class="grid lg:grid-cols-1 gap-6">
                <div class="card overflow-hidden border border-gray-100 shadow-sm">
                    <div class="w-full px-5 py-4">

                        <!-- CONTENEDOR UNIFICADO ETAPA 2 -->
                        <div class="flex flex-wrap justify-between items-center mb-6 gap-4 w-full">
                            <div class="flex items-center gap-4 flex-1 min-w-[250px]">
                                <h4 class="text-lg font-bold text-primary uppercase flex items-center"><span class="stage-circle">2</span> VERIFICACIÓN DE DATOS</h4>
                            </div>

                            <!-- CENTRO: Pestañas (Nace en 2 filas) -->
                            <div id="nav-dest-etapa2" class="flex justify-center transition-all w-full order-last mt-4"></div>

                            <div class="flex-1 flex flex-wrap justify-end gap-2.5 min-w-[250px]">
                                <div class="rep-card">
                                    <div class="rep-label text-slate-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Total
                                    </div>
                                    <span id="contadorTotalE2" class="rep-value text-slate-800">0</span>
                                    <div class="rep-bar bg-slate-100"><i id="barTotalE2" class="bg-slate-500"></i></div>
                                </div>
                                <div class="rep-card" style="border-color:#bbf7d0;">
                                    <div class="rep-label text-emerald-600">
                                        <i class='bx bx-check-double'></i> Verificados
                                    </div>
                                    <span id="contadorFiltradoE2" class="rep-value text-emerald-700">0</span>
                                    <div class="rep-bar bg-emerald-100"><i id="barVerificadosE2" class="bg-emerald-500"></i></div>
                                </div>
                                <div class="rep-card" style="border-color:#fde68a;">
                                    <div class="rep-label text-amber-600">
                                        <i class='bx bx-error-circle'></i> Sin Verificar
                                    </div>
                                    <span id="contadorSinVerificarE2" class="rep-value text-amber-600">0</span>
                                    <div class="rep-bar bg-amber-100"><i id="barSinVerificarE2" class="bg-amber-400"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="rep-toolbar">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                                    <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                                    <input type="text" id="buscarPersonalE2" placeholder="Buscar por nombre o DNI..." class="w-full text-sm uppercase" autocomplete="off" />
                                    <button type="button" id="btnLimpiarBusquedaE2" title="Limpiar búsqueda" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors">
                                        <i class='bx bx-x-circle text-lg'></i>
                                    </button>
                                </div>
                                <div class="ml-auto flex flex-wrap items-center gap-2">
                                    <button type="button" id="btnExportExcelE2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700 text-xs font-semibold hover:bg-green-600 hover:text-white hover:border-green-600 transition-colors">
                                        <i class='bx bx-spreadsheet text-base'></i> Excel
                                    </button>
                                    <button type="button" id="btnReporteVerificados" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-300 bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">
                                        <i class='bx bxs-file-pdf text-base'></i> PDF
                                    </button>
                                    <div class="hidden items-center gap-2">
                                        <button type="button" id="btnDJUnificadoVerificado" class="btn border-primary text-primary hover:bg-primary hover:text-white px-3 py-1 text-sm rounded-lg"><i class='bx bx-file text-base'></i> DJ Unificado</button>
                                        <button type="button" id="btnResetearDJsE2" class="btn bg-danger text-white px-3 py-1 text-sm rounded-lg"><i class='bx bx-reset text-base'></i> Resetear marcas</button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 pt-3 border-t border-slate-200">

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-building text-gray-400 text-lg'></i>Sucursal</label>
                                    @php $sucursalesFiltradasE2 = array_slice($sucursales, 1); @endphp
                                    <select id="filtroSucursalE2" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        @if(count($sucursalesFiltradasE2) > 1) <option value="00">Todas</option> @endif
                                        @foreach ($sucursalesFiltradasE2 as $sucursal) <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option> @endforeach
                                    </select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                                    <select id="filtroTipoPerE2" class="form-select text-sm w-48 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
    @if($tipoPerLimitar == 0)
        <option value="00">Todos</option><option value="03" selected>Operativo 5°</option><option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 1)
        <option value="00" selected>Todos</option><option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 2)
        <option value="00">Todos</option><option value="03" selected>Operativo 5°</option>
    @endif
</select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-time text-gray-400 text-lg'></i>Estado</label>
                                    <select id="filtroEstadoE2" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        <option value="null" selected>Todos</option>
                                        <option value="0">Verificados</option>
                                        <option value="1">Sin Verificar</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="flex items-center gap-3 mb-4 mt-6">
                            <div class="flex-1 border-t border-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider px-2"><i class='bx bx-table mr-1'></i>Resultados Verificados</span>
                            <div class="flex-1 border-t border-gray-200"></div>
                        </div>

                        <div id="tblPersonasVerificado" class="w-full tbl-listado rounded-lg overflow-hidden border border-gray-200"></div>

                        <div class="rep-footline flex items-center gap-2 mt-3">
                            <label for="page-size-verificado" class="text-sm text-gray-600">Mostrar</label>
                            <select id="page-size-verificado" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                <option value="5">5</option><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
                            </select>
                            <span class="text-sm text-gray-600">registros</span>
                            <div class="rep-info ml-auto" id="tblInfoE2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="etapa3" class="tab-content hidden" style="display: none !important;">
            <div class="grid lg:grid-cols-1 gap-6">
                <div class="card overflow-hidden border border-gray-100 shadow-sm">
                    <div class="w-full px-5 py-4">

                        <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                            <div>
                                <h4 class="text-lg font-bold text-primary uppercase flex items-center"><span class="stage-circle">3</span> Generación de DJ Masivo</h4>
                                {{-- <p class="text-sm text-gray-500">Control de impresiones PDF</p> --}}
                            </div>

                            <div class="flex flex-wrap gap-2.5">
                                <div class="rep-card">
                                    <div class="rep-label text-slate-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Total
                                    </div>
                                    <span id="contadorTotalE3" class="rep-value text-slate-800">0</span>
                                    <div class="rep-bar bg-slate-100"><i id="barTotalE3" class="bg-slate-500"></i></div>
                                </div>
                                <div class="rep-card" style="border-color:#bbf7d0;">
                                    <div class="rep-label text-emerald-600">
                                        <i class='bx bx-check-circle'></i> Generados
                                    </div>
                                    <span id="contadorGeneradosE3" class="rep-value text-emerald-700">0</span>
                                    <div class="rep-bar bg-emerald-100"><i id="barGeneradosE3" class="bg-emerald-500"></i></div>
                                </div>
                                <div class="rep-card" style="border-color:#fde68a;">
                                    <div class="rep-label text-amber-600">
                                        <i class='bx bx-time-five'></i> Sin Generar
                                    </div>
                                    <span id="contadorPendientesE3" class="rep-value text-amber-600">0</span>
                                    <div class="rep-bar bg-amber-100"><i id="barPendientesE3" class="bg-amber-400"></i></div>
                                </div>
                            </div>
                        </div>

                        <div class="rep-toolbar">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                                    <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                                    <input type="text" id="buscarPersonalE3" placeholder="Buscar por nombre o DNI..." class="w-full text-sm uppercase" autocomplete="off" />
                                    <button type="button" id="btnLimpiarBusquedaE3" title="Limpiar búsqueda" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors">
                                        <i class='bx bx-x-circle text-lg'></i>
                                    </button>
                                </div>
                                <div class="ml-auto flex flex-wrap items-center gap-2">
                                    <button type="button" id="btnExportExcelE3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-green-300 bg-green-50 text-green-700 text-xs font-semibold hover:bg-green-600 hover:text-white hover:border-green-600 transition-colors">
                                        <i class='bx bx-spreadsheet text-base'></i> Excel
                                    </button>
                                    <button type="button" id="btnExportPdfE3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-300 bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">
                                        <i class='bx bxs-file-pdf text-base'></i> PDF
                                    </button>
                                    <button type="button" id="btnGenerarSeleccionadosE3" disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors bg-indigo-100 text-indigo-400 border border-indigo-200 cursor-not-allowed">
                                        <i class='bx bx-check-square text-base'></i> Generar Reporte
                                    </button>
                                    <button type="button" id="btnReporteAvanceE3" class="hidden btn border-gray-300 text-gray-700 hover:bg-gray-50 px-3 py-1.5 text-sm rounded-lg items-center gap-1.5 font-medium transition-colors">
                                        <i class='bx bx-bar-chart-alt-2 text-base'></i> Rep. Avances
                                    </button>
                                    <button type="button" id="btnDJUnificadoE3" class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-300 bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-600 hover:text-white hover:border-blue-600 transition-colors">
                                        <i class='bx bx-file text-base'></i> DJ Masivo
                                    </button>
                                    <button type="button" id="btnResetearDJsE3" class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-300 bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">
                                        <i class='bx bx-reset text-base'></i> Resetear marcas
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 pt-3 border-t border-slate-200">

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-building text-gray-400 text-lg'></i>Sucursal</label>
                                    @php $sucursalesFiltradasE3 = array_slice($sucursales, 1); @endphp
                                    <select id="filtroSucursalE3" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        @if(count($sucursalesFiltradasE3) > 1) <option value="00">Todas</option> @endif
                                        @foreach ($sucursalesFiltradasE3 as $sucursal) <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option> @endforeach
                                    </select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                                    <select id="filtroTipoPerE3" class="form-select text-sm w-48 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
    @if($tipoPerLimitar == 0)
        <option value="00">Todos</option><option value="03">Operativo 5°</option><option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 1)
        <option value="00">Todos</option><option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 2)
        <option value="00">Todos</option><option value="03">Operativo 5°</option>
    @endif
</select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-time text-gray-400 text-lg'></i>Estado</label>
                                    <select id="filtroEstadoE3" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        <option value="null" selected>Todos</option>
                                        <option value="0">Generados</option>
                                        <option value="1">Sin generar</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="flex items-center gap-3 mb-4 mt-6">
                            <div class="flex-1 border-t border-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider px-2"><i class='bx bx-table mr-1'></i>Lista Control de Impresiones</span>
                            <div class="flex-1 border-t border-gray-200"></div>
                        </div>

                        <div id="tblPersonasEtapa3" class="w-full tbl-listado rounded-lg overflow-hidden border border-gray-200"></div>

                        <div class="rep-footline flex items-center gap-2 mt-3">
                                    <label for="page-size-etapa3" class="text-sm text-gray-600">Mostrar</label>
                                    <select id="page-size-etapa3" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                        <option value="5">5</option><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
                            </select>
                            <span class="text-sm text-gray-600">registros</span>
                            <div class="rep-info ml-auto" id="tblInfoE3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- ============================================================ --}}
        {{-- NUEVA 4° ETAPA: CARGA DE DJ                                  --}}
        {{-- ============================================================ --}}
        <div id="etapa_carga" class="tab-content hidden" style="display: none !important;">
            <div class="grid lg:grid-cols-1 gap-6">
                <div class="card overflow-hidden border border-gray-100 shadow-sm">
                    <div class="w-full px-5 py-4">
                        <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                            <div>
                                <h4 class="text-lg font-bold text-primary uppercase flex items-center"><span class="stage-circle">4</span> Subida de DJ</h4>
                                {{-- <p class="text-sm text-gray-500">Listado de Personal y Subida de PDFs</p> --}}
                            </div>

                            <div class="flex gap-2">
                                <div class="bg-blue-50 px-3 py-2 rounded-lg border border-blue-200 text-center min-w-[90px]">
                                    <span class="block text-[9px] text-blue-600 font-bold uppercase">Total</span>
                                    <span id="countTotalE4C" class="text-lg font-bold text-blue-700">0</span>
                                </div>
                                <div class="bg-green-50 px-3 py-2 rounded-lg border border-green-200 text-center min-w-[90px]">
                                    <span class="block text-[9px] text-green-600 font-bold uppercase">Escaneados</span>
                                    <span id="countEscaneadosE4C" class="text-lg font-bold text-green-700">0</span>
                                </div>
                                <div class="bg-yellow-50 px-3 py-2 rounded-lg border border-yellow-200 text-center min-w-[90px]">
                                    <span class="block text-[9px] text-yellow-600 font-bold uppercase">Sin escanear</span>
                                    <span id="countPendientesE4C" class="text-lg font-bold text-yellow-700">0</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 mb-6 bg-slate-50 p-4 rounded-lg border border-slate-200">
                            <div class="flex flex-wrap items-center gap-5">
                                <input type="text" id="buscarPersonal_E4C" placeholder="Buscar por nombre o DNI..." autocomplete="off"
                                    class="w-48 px-4 py-1.5 text-sm uppercase border border-gray-300 rounded-full focus:outline-none focus:border-primary transition-colors" style="min-width: 220px;" />

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-building text-gray-400 text-lg'></i>Sucursal</label>
                                    <select id="sucursal_E4C" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg">
                                        <option disabled selected>— Seleccionar —</option>
                                        @foreach ($sucursales as $suc)
                                            <option value="{{ $suc->codigo }}">{{ $suc->abreviatura }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                                    <select id="tipo_per_E4C" class="form-select text-sm w-48 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg">
    @if ($tipoPerLimitar == 0)
        <option value="TODOS" selected>Todos</option><option value="ADMIN_5">Administrativo 5°</option><option value="OPER_5">Operativo 5°</option>
    @elseif ($tipoPerLimitar == 1)
        <option value="TODOS" selected>Todos</option><option value="ADMIN_5">Administrativo 5°</option>
    @elseif ($tipoPerLimitar == 2)
        <option value="TODOS" selected>Todos</option><option value="OPER_5">Operativo 5°</option>
    @endif
</select>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-gray-700">DJ:</label>
                                    <select id="filtroDJ_E4C" class="form-select text-sm px-3 py-1.5 border border-gray-300 rounded-lg">
                                        <option value="TODOS">Todos</option>
                                        <option value="SI">Escaneado</option>
                                        <option value="NO">Sin escanear</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-4 border-l-2 border-gray-200 pl-4 hidden">
                                    <span class="text-sm font-medium text-gray-700">Vigencia:</span>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="vigencia_E4C" value="" class="w-4 h-4 text-primary focus:ring-primary">
                                        <span class="text-sm text-gray-700">Todos</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="vigencia_E4C" value="SI" checked class="w-4 h-4 text-primary focus:ring-primary">
                                        <span class="text-sm text-gray-700">Sí</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="vigencia_E4C" value="NO" class="w-4 h-4 text-primary focus:ring-primary">
                                        <span class="text-sm text-gray-700">No</span>
                                    </label>
                                </div>
                            </div>

                            {{-- BOTONES EXCEL Y PDF (ETAPA 4) --}}
                            <div class="flex gap-2 mt-4 lg:mt-0">
                                <button type="button" id="btnExportExcelE4C" class="flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class='bx bx-spreadsheet text-lg'></i> Excel
                                </button>
                                <button type="button" id="btnExportPdfE4C" class="flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                    <i class='bx bxs-file-pdf text-lg'></i> PDF
                                </button>
                            </div>
                        </div>

                        <div class="w-full">
                            <div id="tblPersonas_E4C" class="w-full tbl-listado rounded-lg overflow-hidden border border-gray-200"></div>
                            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                                <div class="rep-footline flex items-center gap-2">
                                    <label for="page-size-personas_E4C" class="text-sm font-medium text-gray-700">Mostrar</label>
                                    <select id="page-size-personas_E4C" class="form-select text-sm w-20 px-3 py-1 border border-gray-300 rounded-lg">
                                        <option value="5">5</option><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option>
                                    </select>
                                    <span class="text-sm text-gray-600">registros</span>
                                    <div class="rep-info ml-auto" id="tblInfoE4C"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MODAL DE CARGA DE DJ (Asociado a Etapa 4) --}}
        <button type="button" class="hidden" id="btn-modal-dj_E4C" data-hs-overlay="#modal-carga-dj_E4C"></button>
        <div id="modal-carga-dj_E4C" class="hs-overlay hidden fixed inset-0 z-[80] overflow-y-auto transition-all duration-500 pointer-events-none">
            <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 translate-y-10 opacity-0 ease-in-out transition-all duration-500 sm:max-w-lg w-full my-8 sm:mx-auto flex flex-col bg-white shadow-sm rounded-lg pointer-events-auto border border-gray-200">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Subir Declaración Jurada</h3>
                        <p class="text-sm text-gray-500 font-medium nombre-personal_E4C mt-0.5"></p>
                    </div>
                    <button type="button" id="btn-modal-dj-close_E4C" class="text-gray-500 hover:text-gray-700 transition-colors" data-hs-overlay="#modal-carga-dj_E4C">
                        <i class="bx bx-x text-2xl"></i>
                    </button>
                </div>
                <form id="formSubirDJ_E4C">
                    @csrf
                    <input type="hidden" id="codPersonalDJ_E4C" value="">
                    <div class="px-5 py-5 space-y-5">
                        <p class="text-sm text-center text-gray-500 bg-blue-50 p-2 rounded-lg border border-blue-100">
                            Solo se acepta archivo <strong>PDF</strong> con un peso máximo de <strong>1 MB</strong>.
                        </p>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Fecha de Emisión</label>
                            <input type="date" id="fecha_emision_dj_E4C" required class="form-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Archivo PDF</label>
                            <div id="zonaDropDJ_E4C" role="button" class="cursor-pointer border-2 border-dashed border-gray-300 rounded-xl p-8 flex flex-col items-center justify-center gap-3 hover:border-primary hover:bg-blue-50 transition-colors">
                                <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white shadow-sm border border-gray-200 text-gray-600">
                                    <i class="bx bxs-file-pdf text-3xl"></i>
                                </span>
                                <div class="text-center text-sm text-gray-600">
                                    <span class="font-medium">Haz click aquí para </span>
                                    <span class="font-bold text-blue-600 hover:underline">SELECCIONAR</span>
                                </div>
                                <p class="text-xs text-gray-400">Máximo 1 MB</p>
                                <input type="file" id="archivoDJ_E4C" accept=".pdf" class="hidden">
                            </div>
                            <ul id="listaArchivosDJ_E4C" class="mt-3 space-y-2"></ul>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 bg-slate-50 rounded-b-lg">
                        <button type="submit" class="btn bg-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1" id="btn-guardar-dj_E4C">
                            <i class="bx bx-upload text-lg"></i> Subir DJ
                        </button>
                        <button type="button" class="btn border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium" data-hs-overlay="#modal-carga-dj_E4C">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="etapa4" class="tab-content hidden" style="display: none !important;">
            <div class="grid lg:grid-cols-1 gap-6">
                <div class="card overflow-hidden border border-gray-100 shadow-sm">
                    <div class="w-full px-5 py-4">

                        <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                            <div>
                                <h4 class="text-lg font-bold text-primary uppercase flex items-center"><span class="stage-circle">5</span>Revision de Validación de Imágenes</h4>
                                {{-- <p class="text-sm text-gray-500">Validación de Huella y Firma</p> --}}
                            </div>

                            {{-- NUEVOS INDICADORES ETAPA 4 --}}
                            <div class="flex gap-2">
                                <div class="bg-blue-50 px-3 py-2 rounded-lg border border-blue-200 text-center min-w-[90px]">
                                    <span class="block text-[9px] text-blue-600 font-bold uppercase">Total</span>
                                    <span id="contadorTotalE4" class="text-lg font-bold text-blue-700">0</span>
                                </div>
                                <div class="bg-green-50 px-3 py-2 rounded-lg border border-green-200 text-center min-w-[90px]">
                                    <span class="block text-[9px] text-green-600 font-bold uppercase">Escaneados</span>
                                    <span id="contadorEscaneadosE4" class="text-lg font-bold text-green-700">0</span>
                                </div>
                                <div class="bg-yellow-50 px-3 py-2 rounded-lg border border-yellow-200 text-center min-w-[90px]">
                                    <span class="block text-[9px] text-yellow-600 font-bold uppercase">Sin escanear</span>
                                    <span id="contadorPendientesE4" class="text-lg font-bold text-yellow-700">0</span>
                                </div>
                            </div>
                        </div>

                        {{-- CAJA GRIS DE FILTROS ESTANDARIZADA --}}
                        <div class="flex flex-wrap items-center justify-between gap-4 mb-6 bg-slate-50 p-4 rounded-lg border border-slate-200">
                            <div class="flex flex-wrap items-center gap-5">
                                
                                {{-- BUSCADOR --}}
                                <div class="flex items-center gap-2">
                                    <input type="text" id="buscarPersonalE4" placeholder="Buscar por nombre o DNI..." class="w-48 px-4 py-1.5 border border-gray-300 rounded-full focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-sm uppercase" style="min-width: 250px;" autocomplete="off" />
                                </div>

                                {{-- SELECT SUCURSAL --}}
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-building text-gray-400 text-lg'></i>Sucursal</label>
                                    @php $sucursalesFiltradasE4 = array_slice($sucursales, 1); @endphp
                                    <select id="filtroSucursalE4" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        @if(count($sucursalesFiltradasE4) > 1) <option value="00">Todas</option> @endif
                                        @foreach ($sucursalesFiltradasE4 as $sucursal) <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option> @endforeach
                                    </select>
                                </div>

                                {{-- SELECT TIPO --}}
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                                    <select id="filtroTipoPerE4" class="w-44 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
    @if($tipoPerLimitar == 0)
        <option value="00">Todos</option><option value="03" selected>Operativo 5°</option><option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 1)
        <option value="00" selected>Todos</option><option value="05">Administrativo 5°</option>
    @elseif($tipoPerLimitar == 2)
        <option value="00">Todos</option><option value="03" selected>Operativo 5°</option>
    @endif
</select>
                                </div>

                                {{-- NUEVO: RADIO BUTTONS ESTADO --}}
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-time text-gray-400 text-lg'></i>Estado</label>
                                    <select id="filtroEstadoE4" class="form-select text-sm w-36 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary">
                                        <option value="null" selected>Todos</option>
                                        <option value="0">Escaneados</option>
                                        <option value="1">Sin escanear</option>
                                    </select>
                                </div>

                            </div>

                            {{-- BOTONES EXCEL Y PDF DIRECTOS --}}
                            <div class="flex gap-2">
                                <button type="button" id="btnExportExcelE4" class="flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                                    <i class='bx bx-spreadsheet text-lg'></i> Excel
                                </button>
                                <button type="button" id="btnExportPdfE4" class="flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors shadow-sm">
                                    <i class='bx bxs-file-pdf text-lg'></i> PDF
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mb-4 mt-6">
                            <div class="flex-1 border-t border-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider px-2"><i class='bx bx-table mr-1'></i>Lista Control Biométrico</span>
                            <div class="flex-1 border-t border-gray-200"></div>
                        </div>

                        <div id="tblPersonasEtapa4" class="w-full tbl-listado rounded-lg overflow-hidden border border-gray-200"></div>

                        <div class="rep-footline flex items-center gap-2 mt-3">
                                    <label for="page-size-etapa4" class="text-sm text-gray-600">Mostrar</label>
                                    <select id="page-size-etapa4" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                        <option value="5">5</option><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
                            </select>
                            <span class="text-sm text-gray-600">registros</span>
                            <div class="rep-info ml-auto" id="tblInfoE4"></div>
                        </div>
                </div>
            </div>
        </div>

        <div id="etapa5" class="tab-content hidden">
            <div class="grid lg:grid-cols-1 gap-6">
                <div class="card overflow-hidden">
                    <div class="card-header border-b border-gray-200 pb-2">
                        <h4 class="card-title text-lg font-medium text-primary">Migración (SIP)</h4>
                    </div>

                    <div class="w-full px-5 py-4">
                        {{-- FILTROS + BOTONES --}}
                        <div class="flex justify-between items-start gap-4 mb-4">
                            <div class="flex flex-col items-start gap-2">
                                <div>
                                    <div class="flex items-baseline gap-1 px-3 py-1 bg-gray-100 rounded-lg border border-gray-200">
                                        <span id="contadorFiltrado" class="text-base font-medium text-gray-800">0</span>
                                        <span class="text-sm text-gray-400">/</span>
                                        <span id="contadorTotal" class="text-sm text-gray-500">0</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" id="btnReporteFaltantes" class="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors"><i class='bx bx-download text-base'></i> Rep. faltantes</button>
                                    <button type="button" id="btnReporteActualizacion" class="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors"><i class='bx bx-download text-base'></i> Rep. Actualización</button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" id="btnDJUnificadoMigrado" class="btn border-primary text-primary hover:bg-primary hover:text-white px-3 py-1 text-sm rounded-lg"><i class='bx bx-file text-base'></i> DJ Unificado (Migrados)</button>
                                    <button type="button" id="btnResetearDJs" class="btn bg-danger text-white px-3 py-1 text-sm rounded-lg"><i class='bx bx-reset text-base'></i> Resetear marcas</button>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-3">
                                <div class="flex items-center gap-2">
                                    <input type="text" id="buscarPersonal" placeholder="Buscar por nombre o DNI..." class="w-48 px-3 py-1 border border-gray-300 rounded-full focus:outline-none focus:border-blue-500 transition-all text-sm uppercase" style="min-width: 250px;" autocomplete="off" />
                                </div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <label class="text-sm text-gray-600">Sucursal:</label>
                                        @php $sucursalesFiltradasLegacy = array_slice($sucursales, 1); @endphp
                                        <select id="filtroSucursal" class="w-24 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                            @if(count($sucursalesFiltradasLegacy) > 1) <option value="">Todas</option> @endif
                                            @foreach ($sucursalesFiltradasLegacy as $sucursal) <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option> @endforeach
                                        </select>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-sm text-gray-600">Tipo:</label>
                                        <select id="filtroTipoPer" class="w-44 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                            @if($tipoPerLimitar == 0)
                                                <option value="">Todos</option><option value="OPERATIVO 4°">Operativo 4°</option><option value="OPERATIVO 5°">Operativo 5°</option><option value="ADMINISTRATIVO 4°">Administrativo 4°</option><option value="ADMINISTRATIVO 5°">Administrativo 5°</option><option value="ESPECIAL">Especial</option>
                                            @elseif($tipoPerLimitar == 1)
                                                <option value="">Todos</option><option value="ADMINISTRATIVO 4°">Administrativo 4°</option><option value="ADMINISTRATIVO 5°">Administrativo 5°</option>
                                            @elseif($tipoPerLimitar == 2)
                                                <option value="">Todos</option><option value="OPERATIVO 4°">Operativo 4°</option><option value="OPERATIVO 5°">Operativo 5°</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mb-4 mt-6">
                            <div class="flex-1 border-t border-gray-200"></div>
                            <span class="text-xs text-gray-400 font-medium uppercase tracking-wider px-2"><i class='bx bx-table mr-1'></i>Resultados</span>
                            <div class="flex-1 border-t border-gray-200"></div>
                        </div>

                        <div id="tblPersonasMigrado" class="w-full tbl-listado rounded-lg overflow-hidden border border-gray-200"></div>

                        <div class="rep-footline flex items-center gap-2 mt-3">
                                    <label for="page-size-migrado" class="text-sm text-gray-600">Mostrar</label>
                                    <select id="page-size-migrado" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                        <option value="5">5</option><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
                            </select>
                            <span class="text-sm text-gray-600">registros</span>
                            <div class="rep-info ml-auto" id="tblInfoMIG"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div id="modalSolicitudSip" class="hidden fixed inset-0 z-[100] items-center justify-center bg-slate-950/60 p-3">
        <section class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
            <header class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Revisión de solicitud de actualización DJ</h2>
                    <p id="solicitudSipPersona" class="mt-1 text-sm text-slate-500"></p>
                </div>
                <button type="button" id="cerrarModalSolicitudSip" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="Cerrar">✕</button>
            </header>
            <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                <div id="solicitudSipEstado" class="mb-3 text-sm text-slate-500"></div>
                <section class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="mb-3 text-sm font-bold text-slate-800">Datos personales</h3>
                    <dl id="solicitudSipDatosPersona" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"></dl>
                </section>
                <table class="min-w-full border-collapse text-sm">
                    <thead class="sticky top-0 bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-600">
                        <tr><th class="border p-3">Cambio solicitado</th><th class="border p-3">Dato registrado</th><th class="border p-3">Dato solicitado</th></tr>
                    </thead>
                    <tbody id="solicitudSipComparacion"></tbody>
                </table>
                <section class="mt-5 rounded-lg border border-slate-200 p-4">
                    <h3 class="mb-3 text-sm font-bold text-slate-800">Datos familiares</h3>
                    <div id="solicitudSipFamiliares" class="grid grid-cols-1 gap-4"></div>
                </section>
            </div>
            <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-6 py-4">
                <button type="button" id="cerrarModalSolicitudSip2" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cerrar</button>
                <button type="button" id="rechazarSolicitudSip" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50">Rechazar</button>
                <button type="button" id="aprobarSolicitudSip" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Aprobar</button>
            </footer>
        </section>
    </div>

    @include('file_control.rrhh.partials_modal_dj')

<button id="btn-modal-biometrico" data-hs-overlay="#modal-biometrico" class="hidden"></button>
    @include('file_control.rrhh.partials_modal_comparacion_huellafirma_dni')

<!-- {{-- MODAL: REPORTE DE AVANCES (ETAPA 4) --}}
    <div id="modal-reporte-avances" class="hs-overlay hidden fixed inset-0 z-[80] overflow-y-auto pointer-events-none transition-all duration-500">
        <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 translate-y-10 opacity-0 ease-in-out transition-all duration-500 sm:max-w-lg sm:w-full m-3 sm:mx-auto">
            <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl pointer-events-auto">
                <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-800">Reporte de Avances</h3>
                    <button type="button" class="flex justify-center items-center w-7 h-7 text-sm font-semibold rounded-full border border-transparent text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none" data-hs-overlay="#modal-reporte-avances">
                        <span class="sr-only">Cerrar</span>
                        <i class='bx bx-x text-xl'></i>
                    </button>
                </div>
                <div class="p-5 overflow-y-auto">
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sucursal</label>
                        @php $sucursalesModal = array_slice($sucursales, 1); @endphp
                        <select id="modalSucursalE4" class="py-2.5 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
                            <option value="00">TODOS</option>
                            @foreach ($sucursalesModal as $sucursal)
                                <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Tipo de Personal</label>
                        <div class="flex items-center gap-6">
                            <select id="modalTipoPerE4" class="py-2.5 px-4 block w-full border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none">
    <option value="00">TODOS</option>
    <option value="03">OPERATIVO 5º</option>
    <option value="05">ADMINISTRATIVO 5º</option>
</select>
                         
                        </div>
                    </div>
                </div>
                <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200">
                    <button type="button" id="btnModalPdfE4" class="py-2 px-4 inline-flex justify-center items-center gap-1.5 rounded-lg border border-transparent font-medium bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all text-sm">
                        <i class='bx bxs-file-pdf text-lg'></i> PDF
                    </button>
                    <button type="button" id="btnModalExcelE4" class="py-2 px-4 inline-flex justify-center items-center gap-1.5 rounded-lg border border-transparent font-medium bg-green-600 text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all text-sm">
                        <i class='bx bx-spreadsheet text-lg'></i> Excel
                    </button>
                    <button type="button" class="py-2 px-4 inline-flex justify-center items-center gap-1.5 rounded-lg border border-gray-200 font-medium bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all text-sm" data-hs-overlay="#modal-reporte-avances">
                        <i class='bx bx-x text-lg'></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div> -->
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.1/dist/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
    <script>
        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
        window.logoUrl = "{{ asset('images/logo_sol.png') }}";
        window.tipoUsuario = {{ $tipoUsuario ?? 'null' }};
        window.puedeResolverSolicitudSip = [11, 17].includes(Number(window.tipoUsuario));
    </script>
@endsection

@vite([
    'resources/js/functions/actualizar_dj.js',
    'resources/js/functions/nueva_dj.js'
])
