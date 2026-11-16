@extends('layouts.vertical', ['title' => 'Gestión DJ'])

@section('css')
    <style>
        /* Placeholders más opacos globalmente en esta vista */
        ::placeholder {
            color: #9ca3af !important;
            opacity: 1 !important;
        }

        ::-webkit-input-placeholder {
            color: #9ca3af !important;
        }

        ::-moz-placeholder {
            color: #9ca3af !important;
        }
        .tab-btn.border-b-white {
            margin-bottom: -1px;
            border-bottom-color: white !important;
        }

        .hover-select-nativo:hover {
            background-color: #2563eb !important; /* Azul Tailwind */
            color: #ffffff !important;
        }

        /* ════════════════════════════════════════════════════════════
           SISTEMA VISUAL COMPARTIDO (Reporte Personal)
           ════════════════════════════════════════════════════════════ */

        /* ── Tabla ──────────────────────────────────────────────────────── */
        #tblPersonas .tabulator-cell,
        #tblPersonasCN .tabulator-cell,
        #tblPersonasMigrado .tabulator-cell { font-size: 0.8125rem; }

        #tblPersonas .tabulator-row,
        #tblPersonasCN .tabulator-row,
        #tblPersonasMigrado .tabulator-row {
            transition: background-color .15s ease, box-shadow .15s ease;
        }

        /* Base: todas las filas blancas (sin zebra) */
        #tblPersonas .tabulator-row,
        #tblPersonasCN .tabulator-row,
        #tblPersonasMigrado .tabulator-row { background-color: #ffffff; }

        /* Cesados: rojo */
        #tblPersonas .tabulator-row.rep-cesado,
        #tblPersonasCN .tabulator-row.rep-cesado,
        #tblPersonasMigrado .tabulator-row.rep-cesado { background-color: #fee2e2; }

        /* Hover: igual que Reporte Personal (color de fila, un solo acento a la izquierda) */
        #tblPersonas .tabulator-row:hover,
        #tblPersonasCN .tabulator-row:hover,
        #tblPersonasMigrado .tabulator-row:hover {
            background-color: #eef4ff !important;
            box-shadow: inset 3px 0 0 0 #2563eb;
        }
        #tblPersonas .tabulator-row.rep-cesado:hover,
        #tblPersonasCN .tabulator-row.rep-cesado:hover,
        #tblPersonasMigrado .tabulator-row.rep-cesado:hover {
            background-color: #fecaca !important;
            box-shadow: inset 3px 0 0 0 #dc2626;
        }

        /* La selección por checkbox sigue viéndose (gana sobre el hover) */
        #tblPersonas .tabulator-row.tabulator-selected,
        #tblPersonasCN .tabulator-row.tabulator-selected,
        #tblPersonasMigrado .tabulator-row.tabulator-selected { background-color: #9abcea; }
        #tblPersonas .tabulator-row.tabulator-selected:hover,
        #tblPersonasCN .tabulator-row.tabulator-selected:hover,
        #tblPersonasMigrado .tabulator-row.tabulator-selected:hover { background-color: #769bcc; }

        #tblPersonas .tabulator-header,
        #tblPersonasCN .tabulator-header,
        #tblPersonasMigrado .tabulator-header {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 2px solid #e2e8f0;
        }
        #tblPersonas .tabulator-header .tabulator-col,
        #tblPersonasCN .tabulator-header .tabulator-col,
        #tblPersonasMigrado .tabulator-header .tabulator-col {
            background: transparent;
            border-right-color: #e9eef5;
        }
        #tblPersonas .tabulator-header .tabulator-col .tabulator-header-content,
        #tblPersonasCN .tabulator-header .tabulator-col .tabulator-header-content,
        #tblPersonasMigrado .tabulator-header .tabulator-col .tabulator-header-content {
            font-weight: 700;
            color: #475569;
            font-size: .7rem;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        #tblPersonas .tabulator-placeholder-wrapper,
        #tblPersonasCN .tabulator-placeholder-wrapper,
        #tblPersonasMigrado .tabulator-placeholder-wrapper {
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
        /* @tailwindcss/forms pisa las utilidades de padding: se fija a mano */
        #buscarPersonal, #buscar { padding: .5rem 2.25rem; }
        #filtroCargoPEN { padding: .5rem 2.25rem .5rem .75rem; }

        /* ── Fila de resumen bajo la tabla ──────────────────────────────── */
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

    @include("layouts.shared/page-title", ["subtitle" => "DJ", "title" => "Gestion DJ"])

    <div id="divListado" class="grid lg:grid-cols-1 gap-6 mt-8">
        <div class="card overflow-hidden">
            <div class="card-header border-b border-gray-100 py-4 px-5">
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <div>
                        <h4 class="text-lg font-bold text-primary uppercase flex items-center">
                            <i class='bx bx-id-card text-2xl mr-2'></i> REGISTRO DE PERSONAL (DJ)
                        </h4>
                    </div>
                    <div class="flex flex-wrap gap-2.5">
                        {{-- Total --}}
                        <div class="rep-card">
                            <div class="rep-label text-slate-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Total
                            </div>
                            <span id="countTotalPen" class="rep-value text-slate-800">0</span>
                            <div class="rep-bar bg-slate-100"><i id="barTotalPen" class="bg-slate-500"></i></div>
                        </div>

                        {{-- Vigentes --}}
                        <div class="rep-card" style="border-color:#bbf7d0;">
                            <div class="rep-label text-emerald-600">
                                <i class='bx bx-check-circle'></i> Vigentes
                            </div>
                            <span id="countVigentesPen" class="rep-value text-emerald-700">0</span>
                            <div class="rep-bar bg-emerald-100"><i id="barVigentesPen" class="bg-emerald-500"></i></div>
                        </div>

                        {{-- No Vigentes --}}
                        <div class="rep-card" style="border-color:#fecaca;">
                            <div class="rep-label text-red-500">
                                <i class='bx bx-x-circle'></i> No Vigentes
                            </div>
                            <span id="countNoVigentesPen" class="rep-value text-red-600">0</span>
                            <div class="rep-bar bg-red-100"><i id="barNoVigentesPen" class="bg-red-400"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CONTROLES COMUNES --}}
            <div class="w-full px-5 pt-4">
                <div class="rep-toolbar">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                            <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                            <input type="text" id="buscarPersonal" placeholder="Buscar por nombre o DNI..."
                                class="w-full text-sm uppercase" autocomplete="off" />
                            <button type="button" id="btnLimpiarBusquedaPEN" title="Limpiar búsqueda"
                                class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-600 transition-colors">
                                <i class='bx bx-x-circle text-lg'></i>
                            </button>
                        </div>

                <!-- <button type="button" id="btnDescargarDJs"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-primary rounded-lg bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors">
                        <i class='bx bx-archive-in text-base'></i>
                        Descargar DJ's
                    </button>
                    <button type="button" id="btnDJUnificado"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-amber-500 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-500 hover:text-white transition-colors">
                        <i class='bx bx-file text-base'></i>
                        DJ Unificado

                    </button> -->
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <!-- <button type="button" id="btnNuevaDJ"
                        class="btn rounded-full bg-primary/25 text-primary hover:bg-primary hover:text-white flex items-center gap-1 px-4 py-1"
                        data-hs-overlay="#modalDjGestion">
                        <i class='bx bx-plus text-base'></i>
                        <span>Nueva DJ</span>
                    </button> -->

                     @if($tipoUsuario != 9 && $tipoUsuario != 8) 
                     <div class="flex items-center justify-center gap-2">
                            <button type="button" id="btnNuevaDJ"
                            class="btn rounded-full bg-primary/25 text-primary hover:bg-primary hover:text-white flex items-center gap-1 px-4 py-1">
                            <i class='bx bx-plus text-base'></i>
                            <span>Nueva DJ</span>
                        </button>
                     </div>
                        
                    @endif
                    
                    <button type="button" id="btnExtFirmaHuella" hidden disabled
                        class="btn rounded-full bg-warning/25 text-warning hover:bg-warning hover:text-white hidden items-center gap-1 px-4 py-1">
                                            <i class='bx bx-outline'></i>
                        <span>Extraer Firma y Huella</span>
                    </button>
                </div>
                    </div>

                    {{-- Fila 2: filtros (misma tarjeta que la búsqueda) --}}
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3 pt-3 border-t border-slate-200">

                        {{-- Sucursal --}}
                        <div class="flex items-center gap-2">
                            <label for="filtroSucursalPEN" class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-building text-gray-400 text-lg'></i>Sucursal</label>
                            <select id="filtroSucursalPEN"
                                class="form-select text-sm px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                @if(! $restringirSucursalesGestionDj && count($sucursalesGestionDj) > 1)
                                    <option value="">Todas</option>
                                @endif
                                @foreach ($sucursalesGestionDj as $sucursal)
                                    <option value="{{ $sucursal->codigo }}"
                                        @selected($filtrosInicialesGestionDj['sucursal'] === trim((string) $sucursal->codigo))>
                                        {{ $sucursal->abreviatura }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tipo --}}
                        <div class="flex items-center gap-2">
                            <label for="filtroTipoPerPEN" class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-category text-gray-400 text-lg'></i>Tipo</label>
                            <select id="filtroTipoPerPEN" class="form-select text-sm w-44 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                <option value="" @selected($filtrosInicialesGestionDj['tipo'] === '')>Todos</option>
                                <option value="OPERATIVO 4°">Operativo 4°</option>
                                <option value="OPERATIVO 5°" @selected($filtrosInicialesGestionDj['tipo'] === 'OPERATIVO 5°')>Operativo 5°</option>
                                <option value="ADMINISTRATIVO 4°">Administrativo 4°</option>
                                <option value="ADMINISTRATIVO 5°">Administrativo 5°</option>
                                <option value="ESPECIAL">Especial</option>
                            </select>
                        </div>

                        {{-- Cargo --}}
                        <div class="flex items-center gap-2 w-80"> <!-- Ampliado a w-80 -->
                            <label for="filtroCargoPEN" class="flex items-center gap-2 text-sm font-medium text-gray-600 whitespace-nowrap"><i class='bx bx-briefcase text-gray-400 text-lg'></i>Cargo</label>
                            <div class="flex-1 relative" id="custom-select-cargo">
                                <input type="text" id="filtroCargoPEN" placeholder="Todos" autocomplete="off"
                                    class="form-input text-sm w-full pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white cursor-text" />
                                
                                <!-- Icono Flecha (Igual a un select nativo) -->
                                <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>

                                <!-- Lista Desplegable: min-w-full y w-max permiten que crezca sin limitarse al input -->
                                <div id="listaCargosPEN" class="absolute z-50 min-w-full w-max bg-white border border-gray-300 rounded-lg shadow-lg mt-1 hidden max-h-60 overflow-y-auto overflow-x-hidden py-1">
                                    <!-- Las opciones se inyectan con JS -->
                                </div>
                            </div>
                        </div>

                        {{-- Estado (Vigencia) --}}
                        <div class="flex items-center gap-2">
                            <label for="filtroVigenciaPEN" class="flex items-center gap-2 text-sm font-medium text-gray-600"><i class='bx bx-time text-gray-400 text-lg'></i>Estado</label>
                            <select id="filtroVigenciaPEN" class="form-select text-sm w-36 pl-3 pr-8 py-1.5 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                                <option value="" @selected($filtrosInicialesGestionDj['vigencia'] === '')>Todos</option>
                                <option value="SI" @selected($filtrosInicialesGestionDj['vigencia'] === 'SI')>Activos</option>
                                <option value="NO">Cesados</option>
                            </select>
                        </div>

                    {{-- Botón generar seleccionados --}}
                    @if($tipoUsuario != 9 && $tipoUsuario != 8)
                    <button type="button" id="btnGenerarSeleccionadosPEN" disabled
                        class="ml-auto inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors bg-indigo-100 text-indigo-400 border border-indigo-200 cursor-not-allowed">
                        <i class='bx bxs-file-pdf text-base'></i>
                        Generar DJ (<span id="countSelPEN">0</span>)
                    </button>
                    @endif
                    </div>
                </div>
            </div>

            {{-- TABLA PESTAÑA 1: sin columna Migrado --}}
            <div id="panelPendiente" class="w-full px-5 py-2 mt-1">

                <div id="tblPersonas" class="w-full mt-2 rounded-lg overflow-hidden border border-gray-200"></div>

                <div class="rep-footline flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <label for="page-size" class="text-sm text-gray-600">Mostrar</label>
                        <select id="page-size"
                            class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="text-sm text-gray-600">registros</span>
                    </div>
                    <div class="rep-info" id="tblInfoPEN"></div>
                </div>
            </div>

            {{-- TABLA PESTAÑA 2: sin columna Estado, con columna Migrado --}}


<div id="panelMigrado" class="w-full px-5 py-2 mt-1 hidden">

    {{-- FILTROS + BOTONES --}}
    <div class="flex justify-between items-start gap-4 mb-4">

    <div class="flex flex-col items-start gap-2">

            {{-- Contador --}}
            <div>
                <div class="flex items-baseline gap-1 px-3 py-1 bg-gray-100 rounded-lg border border-gray-200">
                <span id="contadorFiltrado" class="text-base font-medium text-gray-800">0</span>
                <span class="text-sm text-gray-400">/</span>
                <span id="contadorTotal" class="text-sm text-gray-500">0</span>
            </div>
            </div>
            

            {{-- Reportes --}}
            <div class="flex items-center gap-2">
                <button type="button" id="btnReporteFaltantes"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class='bx bx-download text-base'></i>
                    Rep. faltantes
                </button>
                <button type="button" id="btnReporteActualizacion"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class='bx bx-download text-base'></i>
                    Rep. Actualización
                </button>
            </div>

            {{-- DJ Unificado --}}
            <div class="flex items-center gap-2">
                <button type="button" id="btnDJUnificado"
                    class="btn border-warning text-warning hover:bg-warning hover:text-white">
                    <i class='bx bx-file text-base'></i>
                    DJ Unificado
                </button>
                <button type="button" id="btnDJUnificadoMigrado"
                    class="btn border-primary text-primary hover:bg-primary hover:text-white">
                    <i class='bx bx-file text-base'></i>
                    DJ Unificado (Migrados)
                </button>
                <button type="button" id="btnResetearDJs"
                    class="btn bg-danger text-white">
                    <i class='bx bx-reset text-base'></i>
                    Resetear marcas
                </button>
            </div>

        </div>

        {{-- IZQUIERDA: Filtros --}}
        <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Sucursal:</label>
                <!-- <select id="filtroSucursal"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                    <option value="">Todas</option>
                </select> -->
                
                <select id="filtroSucursal" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                    
                    @if(! $restringirSucursalesGestionDj && count($sucursalesGestionDj) > 1)
                        <option value="">Todas</option>
                    @endif

                    @foreach ($sucursalesGestionDj as $sucursal)
                        <option value="{{ $sucursal->codigo }}">
                            {{ $sucursal->abreviatura }}
                        </option>
                    @endforeach

 

                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Tipo:</label>
                <!-- <select id="filtroTipoPer"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                    <option value="">Todos</option>
                    <option value="OPERATIVO">Operativo</option>
                    <option value="ADMINISTRATIVO">Administrativo</option>
                    <option value="ESPECIAL">Especial</option>
                </select> -->
                <select id="filtroTipoPer" class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                    @if($tipoPerLimitar == 0)
                        <option value="">Todos</option>
                        <option value="OPERATIVO">Operativo</option>
                        <option value="ADMINISTRATIVO">Administrativo</option>
                        <!-- <option value="ESPECIAL">Especial</option> -->
                    @elseif($tipoPerLimitar == 1)
                        <option value="ADMINISTRATIVO" selected>Administrativo</option>
                    @elseif($tipoPerLimitar == 2)
                        <option value="OPERATIVO" selected>Operativo</option>
                    @endif
                </select>
            </div>
        </div>

        {{-- DERECHA: Contador + Botones --}}
        

    </div>

    {{-- DIVISOR ENTRE FILTROS Y TABLA --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="flex-1 border-t border-gray-200"></div>
        <span class="text-xs text-gray-400 font-medium uppercase tracking-wider px-2">
            <i class='bx bx-table mr-1'></i>Resultados
        </span>
        <div class="flex-1 border-t border-gray-200"></div>
    </div>

    <div id="tblPersonasMigrado" class="w-full rounded-lg overflow-hidden border border-gray-200"></div>

    <div class="rep-footline flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label for="page-size-migrado" class="text-sm text-gray-600">Mostrar</label>
            <select id="page-size-migrado" 
                class="w-20 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-primary focus:border-primary bg-white">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <span class="text-sm text-gray-600">registros</span>
        </div>
        <div class="rep-info" id="tblInfoMIG"></div>
    </div>

</div>


        </div>
    </div>

    <div id="divCoincidencias" class="grid lg:grid-cols-1 gap-6 mt-8 hidden">
        <div class="card overflow-hidden">
            <div class="card-header border-b border-gray-100 py-4 px-5">
                <h4 class="text-lg font-bold text-primary uppercase flex items-center">
                    <i class='bx bx-git-compare text-2xl mr-2'></i> Listado de COINCIDENCIAS
                </h4>
            </div>
            <div class="w-full px-5 pt-4 pb-4">
                <div class="rep-toolbar">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-[220px] max-w-[420px]">
                            <i class='bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg pointer-events-none'></i>
                            <input type="text" id="buscar" placeholder="Buscar..."
                                class="w-full text-sm" autocomplete="off" />
                        </div>
                    </div>
                </div>
                <div id="tblPersonasCN" class="w-full mt-4 rounded-lg overflow-hidden border border-gray-200"></div>
            </div>
        </div>
    </div>
    @include('file_control.rrhh.partials_modal_dj')
    @include('file_control.rrhh.partials_modal_nueva_dj')
    @include('file_control.rrhh.partials_modal_ext_firmahuella')

    {{-- MODAL BIOMÉTRICO (Migrado desde Actualizar DJ) --}}
    <button id="btn-modal-biometrico" data-hs-overlay="#modal-biometrico" class="hidden"></button>
    @include('file_control.rrhh.partials_modal_comparacion_huellafirma_dni')

    {{-- MODAL DE CARGA DE DJ (Migrado desde Actualizar DJ) --}}
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
                <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-gray-200 bg-slate-50 rounded-b-lg">
                    <button type="button" class="btn bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1" id="btn-vista-previa-dj_E4C" disabled>
                        <i class="bx bx-show text-lg"></i> Vista Previa
                    </button>
                    <button type="button" id="btn-cancelar-dj_E4C" class="btn border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium" data-hs-overlay="#modal-carga-dj_E4C">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Vista previa local del PDF: no se envía al servidor hasta una acción posterior. --}}
    <button type="button" class="hidden" id="btn-modal-vista-previa-dj_E4C" data-hs-overlay="#modal-vista-previa-dj_E4C"></button>
    <div id="modal-vista-previa-dj_E4C" class="hs-overlay hidden fixed inset-0 z-[90] overflow-y-auto transition-all duration-300 pointer-events-none">
        <div class="hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 translate-y-4 opacity-0 ease-out transition-all duration-300 w-[calc(100vw-1rem)] max-w-[1600px] my-2 mx-auto sm:my-4 sm:w-[90vw] flex flex-col bg-white shadow-xl rounded-xl pointer-events-auto border border-gray-200 h-[calc(100vh-1rem)] sm:h-[calc(100vh-2rem)]">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
                <div class="min-w-0">
                    <h3 class="text-lg font-bold text-gray-900">Vista Previa de Declaración Jurada</h3>
                    <p id="nombre-archivo-vista-previa-dj_E4C" class="text-sm text-gray-500 truncate"></p>
                </div>
                <button type="button" id="btn-cerrar-vista-previa-dj_E4C" class="text-gray-500 hover:text-gray-700 transition-colors" data-hs-overlay="#modal-vista-previa-dj_E4C" aria-label="Cerrar vista previa">
                    <i class="bx bx-x text-2xl"></i>
                </button>
            </div>
            <div class="flex-1 min-h-0 flex flex-col md:flex-row gap-2 bg-slate-100 p-2">
                <div class="flex-1 min-w-0 min-h-[45vh] md:min-h-0">
                    <iframe id="iframe-vista-previa-dj_E4C" title="Vista previa del PDF de la Declaración Jurada" class="w-full h-full rounded-lg bg-white" src="about:blank"></iframe>
                </div>
                <aside id="panel-resultados-firmahuella-dj_E4C" class="hidden md:w-[320px] shrink-0 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 space-y-3">
                    <div>
                        <h4 class="font-semibold text-gray-800">Firma y huella extraídas</h4>
                        <p class="text-xs text-gray-500">Revisa los recortes antes de continuar.</p>
                    </div>
                    <section class="rounded-lg border border-gray-200 p-2">
                        <h5 class="mb-2 text-sm font-semibold text-gray-700">Firma</h5>
                        <div id="placeholder-firma-extraida-dj_E4C" class="flex h-40 items-center justify-center rounded bg-slate-50 text-xs text-gray-400">Esperando extracción</div>
                        <img id="imagen-firma-extraida-dj_E4C" class="hidden max-h-48 w-full rounded bg-slate-50 object-contain" alt="Firma extraída del PDF">
                        <button type="button" id="btn-vista-previa-firma-extraida-dj_E4C" class="mt-2 w-full rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50" disabled>
                            <i class="bx bx-show mr-1"></i> Vista previa
                        </button>
                    </section>
                    <section class="rounded-lg border border-gray-200 p-2">
                        <h5 class="mb-2 text-sm font-semibold text-gray-700">Huella</h5>
                        <div id="placeholder-huella-extraida-dj_E4C" class="flex h-40 items-center justify-center rounded bg-slate-50 text-xs text-gray-400">Esperando extracción</div>
                        <img id="imagen-huella-extraida-dj_E4C" class="hidden max-h-48 w-full rounded bg-slate-50 object-contain" alt="Huella extraída del PDF">
                        <button type="button" id="btn-vista-previa-huella-extraida-dj_E4C" class="mt-2 w-full rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50" disabled>
                            <i class="bx bx-show mr-1"></i> Vista previa
                        </button>
                    </section>
                </aside>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 border-t border-gray-200 bg-slate-50 rounded-b-xl">
                <button type="button" id="btn-guardar-dj-biometricos_E4C" class="btn bg-emerald-600 text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1" disabled>
                    <i class="bx bx-save text-lg"></i> Guardar DJ y biométricos
                </button>
                <button type="button" id="btn-extraer-firmahuella-dj_E4C" class="btn bg-blue-600 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1" disabled>
                    <i class="bx bx-fingerprint text-lg"></i> Extraer Firma y Huella
                </button>
                <button type="button" class="btn border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 px-4 py-2 rounded-lg text-sm font-medium" data-hs-overlay="#modal-vista-previa-dj_E4C">Cerrar</button>
            </div>
        </div>
    </div>

    <div id="modal-vista-previa-biometrico_E4C" class="hidden fixed inset-0 z-[120] flex items-center justify-center overflow-y-auto bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-vista-previa-biometrico_E4C">
        <div class="my-auto flex w-full max-w-5xl flex-col rounded-xl border border-gray-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                <h3 id="titulo-vista-previa-biometrico_E4C" class="text-lg font-semibold text-gray-900">Vista previa</h3>
                <button type="button" id="btn-cerrar-vista-previa-biometrico-x_E4C" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar vista previa de imagen">
                    <i class="bx bx-x text-2xl"></i>
                </button>
            </div>
            <div class="flex min-h-[50vh] items-center justify-center bg-slate-100 p-4 sm:p-6">
                <img id="imagen-vista-previa-biometrico_E4C" class="max-h-[75vh] max-w-full rounded bg-white object-contain shadow-sm" alt="Imagen biométrica completa">
            </div>
            <div class="flex justify-end border-t border-gray-200 bg-slate-50 px-5 py-3">
                <button type="button" id="btn-cerrar-vista-previa-biometrico_E4C" class="btn rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cerrar</button>
            </div>
        </div>
    </div>

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
    <script>
        if (window.pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>

    <script>
        window.logoUrl = "{{ asset('images/logo_sol.png') }}";
        window.currentUser = "{{ session('usuario') }}";
        window.tipoUsuario = {{ $tipoUsuario ?? 'null' }};
    </script>
@endsection

 @vite([
        'resources/js/functions/gestion_dj.js',
       'resources/js/functions/nueva_dj.js'
    ])
