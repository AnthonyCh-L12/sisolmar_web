{{-- Reporte General — se muestra dentro del centro de reportes DJ. --}}

<div id="modalReporte" class="min-w-0 overflow-hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700">
    <div class="p-5 md:p-6">
        <div class="flex items-start gap-3 border-b border-default-200 pb-5 dark:border-slate-700">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                <i class="i-ph-identification-card text-2xl"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="modalReporteTitulo" class="text-lg font-semibold text-default-900 dark:text-white">Reporte General</h2>
                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Excel · PDF</span>
                </div>
                <p class="mt-1 text-sm text-default-600 dark:text-slate-300">Consulta los datos básicos del personal, descarga sus fotos o DNI y exporta los resultados.</p>
            </div>
        </div>

        {{-- FILTROS --}}
        <div class="border-b border-default-200 py-5 dark:border-slate-700">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-default-500 dark:text-slate-400">Filtros del reporte</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">

            {{-- Sucursal --}}
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-default-700 dark:text-slate-200">Sucursal</label>
                @php $sucursalesFiltradas = array_slice($sucursales, 1); @endphp
                <select id="rptSucursal"
                        class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    @if(count($sucursalesFiltradas) > 1)
                        <option value="">Todas</option>
                    @endif
                    @foreach ($sucursalesFiltradas as $sucursal)
                        <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tipo --}}
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-default-700 dark:text-slate-200">Tipo de personal</label>
                <select id="rptTipoPer"
                        class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    @if($tipoPerLimitar == 0)
                        <option value="">Todos</option>
                        <option value="01">Operativo 4°</option>
                        <option value="03">Operativo 5°</option>
                        <option value="02">Administrativo 4°</option>
                        <option value="05">Administrativo 5°</option>
                    @elseif($tipoPerLimitar == 1)
                        <option value="02">Administrativo 4°</option>
                        <option value="05">Administrativo 5°</option>
                    @elseif($tipoPerLimitar == 2)
                        <option value="01">Operativo 4°</option>
                        <option value="03">Operativo 5°</option>
                    @endif
                </select>
            </div>

            {{-- Estado --}}
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-default-700 dark:text-slate-200">Estado</label>
                <select id="rptVigente" class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="1">Activos</option>
                    <option value="0">Cesados</option>
                </select>
            </div>

            {{-- Descargar con --}}
            <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-default-700 dark:text-slate-200">Identificar descarga por</label>
                <select id="rptDescCon" class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    <option value="codigo">Código</option>
                    <option value="doc" selected>N.º de documento</option>
                </select>
            </div>

            </div>
        </div>

        {{-- BARRA DE ACCIONES --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-default-200 py-5 dark:border-slate-700">
            <button type="button" id="btnLimpiarFiltrosReporte"
                    class="rounded-lg border border-default-300 px-4 py-2 text-sm font-medium text-default-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                <i class="i-ph-arrow-counter-clockwise mr-1"></i> Limpiar filtros
            </button>
            <button type="button" id="btnBuscarReporte"
                    class="inline-flex items-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5">
                <i class='bx bx-eye text-base'></i> Vista previa
            </button>
        </div>

        {{-- VISTA PREVIA: se muestra después de solicitarla. --}}
        <div id="rptResultados" class="hidden">
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span id="rptSelInfo" class="mr-2 text-sm text-default-500">0 seleccionados</span>
                <span class="text-sm font-semibold text-default-700 dark:text-slate-200">Descargar:</span>
                <button type="button" id="btnRptDescFoto"
                        class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-600 hover:text-white">
                    <i class='bx bx-image-alt text-base'></i> Fotos
                </button>
                <button type="button" id="btnRptDescDNI"
                        class="inline-flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-600 hover:text-white">
                    <i class='bx bx-id-card text-base'></i> DNI
                </button>
                <span class="ml-2 text-sm font-semibold text-default-700 dark:text-slate-200">Exportar:</span>
                <button type="button" id="btnRptExportPDF"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">
                    <i class='bx bxs-file-pdf text-base'></i> Exportar PDF
                </button>
                <button type="button" id="btnRptExportExcel"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    <i class='bx bxs-file text-base'></i> Exportar Excel
                </button>
                <button type="button" id="btnRptDescCUL"
                        class="hidden items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-600 hover:text-white">
                    <i class='bx bx-check-shield text-base'></i> CUL
                </button>
            </div>
            <label class="relative block w-full sm:max-w-sm">
                <i class="i-ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-default-400"></i>
                <input id="rptBuscarPreview" type="search" autocomplete="off" placeholder="Buscar por nombre, apellido o DNI..."
                       style="padding-left: 2.25rem;"
                       class="block w-full rounded-lg border-default-300 py-2 pr-3 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
            </label>
        </div>
        <div class="mt-5 min-h-[300px] overflow-x-auto rounded-lg border border-default-200 dark:border-slate-700">
            <table class="w-full border-collapse text-xs" id="rptTabla" style="table-layout:auto">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="py-1.5 px-1 text-center whitespace-nowrap">
                            <input type="checkbox" id="rptCbAll" class="w-3.5 h-3.5 accent-primary cursor-pointer">
                        </th>
                        <th class="py-1.5 px-1 text-center text-[10px] font-bold text-gray-600 whitespace-nowrap">It.</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Sucur.</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Cód.</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap min-w-[140px]">Apellidos y Nombres</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">País</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Tipo Doc.</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Doc. Iden.</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Caduca Doc.</th>
                        <th class="py-1.5 px-1 text-center text-[10px] font-bold text-gray-600 whitespace-nowrap">Sexo</th>
                        <th class="py-1.5 px-1 text-center text-[10px] font-bold text-gray-600 whitespace-nowrap">Edad</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap min-w-[130px]">Email</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Teléfonos</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap min-w-[150px]">Dirección Actual</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Fecha Ingreso</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap min-w-[100px]">Cargo</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Tipo Pers.</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Caduca EMO</th>
                        <th class="py-1.5 px-1 text-left   text-[10px] font-bold text-gray-600 whitespace-nowrap">Fin Contrato</th>
                    </tr>
                </thead>
                <tbody id="rptTbody">
                    <tr>
                        <td colspan="19" class="text-center py-12 text-gray-300 italic text-xs">
                            Selecciona los filtros y pulsa Vista previa
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- FOOTER --}}
        <div class="flex flex-wrap items-center justify-between gap-3 py-4">
            <span id="rptPagInfo" class="text-sm text-default-500">—</span>
            <div class="flex items-center gap-1.5">
                <button id="rptBtnPrev"
                        class="w-7 h-7 flex items-center justify-center text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed">
                    &#8592;
                </button>
                <span id="rptPageNum" class="text-xs font-medium text-gray-700 min-w-[20px] text-center">1</span>
                <button id="rptBtnNext"
                        class="w-7 h-7 flex items-center justify-center text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed">
                    &#8594;
                </button>
                <span class="text-xs text-gray-400 ml-2">Mostrar</span>
                <select id="rptPageSize"
                        class="h-7 text-xs border border-gray-300 rounded-md px-1.5 bg-white focus:ring-1 focus:ring-primary focus:border-primary">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        </div>

    </div>
</div>
