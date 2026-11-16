@extends('layouts.vertical', ['title' => 'Reportes DJ'])

@section('content')
    <div class="container-fluid">
        @include('layouts.shared/page-title', ['subtitle' => 'DJ', 'title' => 'Reportes'])

        <div class="mt-8 mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-default-900 dark:text-white">Centro de reportes</h1>
                <p class="mt-1 text-sm text-default-600 dark:text-slate-400">Busca y selecciona un reporte para configurar sus filtros y exportarlo.</p>
            </div>
            <span id="reporteDjTotal" class="rounded-full bg-default-100 px-3 py-1 text-xs font-semibold text-default-600 dark:bg-slate-700 dark:text-slate-300">6 reportes</span>
        </div>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="overflow-hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700 dark:bg-white">
                <div class="border-b border-default-200 bg-slate-50 p-4 dark:border-slate-200 dark:bg-slate-50">
                    <h2 class="font-semibold !text-slate-900">Reportes disponibles</h2>
                    <label class="relative mt-3 block">
                        <i class="i-ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-default-400"></i>
                        <input id="buscarReporteDj" type="search" placeholder="Buscar reporte..." class="block w-full rounded-lg border-default-300 py-2 !pl-10 pr-3 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                    </label>
                </div>
                <div id="listaReportesDj" class="max-h-[60vh] space-y-2 overflow-y-auto bg-slate-50 p-3 overscroll-contain">
                    <button type="button" data-reporte="reporte-general" class="reporte-dj-opcion w-full rounded-lg border border-default-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-200 dark:bg-white dark:hover:bg-primary-50" aria-pressed="false">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-default-900 dark:text-white">Reporte General</span>
                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel · PDF</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-default-600 dark:text-slate-400">Consulta y exporta los datos básicos del personal.</span>
                    </button>
                    <button type="button" data-reporte="sin-foto-dni" class="reporte-dj-opcion w-full rounded-lg border border-default-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-200 dark:bg-white dark:hover:bg-primary-50" aria-pressed="false">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-default-900 dark:text-white">Personal Sin Foto o DNI</span>
                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel · PDF</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-default-600 dark:text-slate-400">Personal con foto o documento de identidad pendiente.</span>
                    </button>
                    <button type="button" data-reporte="dni-hijos" class="reporte-dj-opcion w-full rounded-lg border border-default-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-200 dark:bg-white dark:hover:bg-primary-50" aria-pressed="false">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-default-900 dark:text-white">Personal Con Asignación Familiar Sin Sustento</span>
                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel · PDF</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-default-600 dark:text-slate-400">Personal con asignación familiar cuyo DNI de hijo menor (anverso o reverso) está pendiente de cargar.</span>
                    </button>
                    <button type="button" data-reporte="asig-hijos-mayores" class="reporte-dj-opcion w-full rounded-lg border border-default-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-200 dark:bg-white dark:hover:bg-primary-50" aria-pressed="false">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-default-900 dark:text-white">Asignación Familiar Por Hijos Mayores De Edad</span>
                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel · PDF</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-default-600 dark:text-slate-400">Personal con asignación familiar y todos sus hijos registrados mayores de edad.</span>
                    </button>
                    <button type="button" data-reporte="mayoria-edad-proxima" class="reporte-dj-opcion w-full rounded-lg border border-default-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-200 dark:bg-white dark:hover:bg-primary-50" aria-pressed="false">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-default-900 dark:text-white">Asignación Familiar Próxima a Vencer</span>
                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel · PDF</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-default-600 dark:text-slate-400">Hijos de personal activo que cumplen 18 años dentro de los próximos 30 días.</span>
                    </button>
                    <button type="button" data-reporte="sin-dj-escaneada" class="reporte-dj-opcion w-full rounded-lg border border-default-200 bg-white p-3 text-left transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-200 dark:bg-white dark:hover:bg-primary-50" aria-pressed="false">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-semibold text-default-900 dark:text-white">Personal sin DJ escaneada</span>
                            <span class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel · PDF</span>
                        </span>
                        <span class="mt-1 block text-xs leading-5 text-default-600 dark:text-slate-400">Personal sin registro de carga ni PDF de DJ escaneada en el servidor.</span>
                    </button>
                    <p id="sinReportesDj" class="hidden px-2 py-5 text-center text-sm text-default-500">No se encontraron reportes.</p>
                </div>
            </aside>

            <main id="configReporteGeneral" class="hidden min-w-0 !p-0 max-w-none">
                @include('file_control.rrhh.partials_modal_reporte')
            </main>

            <main id="configReporteSinFotoDni" class="hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700 dark:bg-white">
                <div class="flex items-start gap-3 border-b border-default-200 p-5 dark:border-slate-700">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300">
                        <i class="i-ph-file-xls text-2xl"></i>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-default-900 dark:text-white">Personal Sin Foto o DNI</h2>
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Excel</span>
                        </div>
                        <p class="mt-1 text-sm text-default-600 dark:text-slate-300">Configura los filtros para definir qué personal se incluirá en el reporte.</p>
                    </div>
                </div>

                <div class="p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-default-500 dark:text-slate-400">Filtros del reporte</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">
                            Sucursal
                            <select id="reporteSucursal" class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700">
                                <option value="">Todas las sucursales</option>
                                @foreach($sucursales as $sucursal)
                                    @if(($sucursal->codigo ?? '0') !== '0')
                                        <option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura ?? $sucursal->nombre ?? $sucursal->codigo }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">
                            Tipo de personal
                            <select id="reporteTipo" class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700">
                                <option value="">Todos los tipos</option>
                                @foreach($tiposPersonal as $tipo)
                                    <option value="{{ trim($tipo->codigo) }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">
                            Cargo
                            <select id="reporteCargo" disabled class="mt-1 block w-full rounded-lg border-default-300 text-sm disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-slate-600 dark:bg-slate-700">
                                <option value="">Selecciona primero el tipo</option>
                            </select>
                        </label>

                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">
                            Estado
                            <select id="reporteEstado" class="mt-1 block w-full rounded-lg border-default-300 text-sm dark:border-slate-600 dark:bg-slate-700">
                                <option value="">Activos y cesados</option>
                                <option value="SI">Activo</option>
                                <option value="NO">Cesado</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-default-200 pt-5 dark:border-slate-700">
                        <button id="btnLimpiarReportesDj" type="button" class="rounded-lg border border-default-300 px-4 py-2 text-sm font-medium text-default-700 transition hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                            <i class="i-ph-arrow-counter-clockwise mr-1"></i> Limpiar filtros
                        </button>
                        <button id="btnPrevisualizarSinFotoDni" type="button" class="inline-flex items-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5"><i class="i-ph-eye"></i> Vista previa</button>
                        <span id="reporteDjEstadoCarga" class="text-sm text-default-500 dark:text-slate-400" aria-live="polite">El archivo incluirá únicamente los registros con foto o DNI pendientes.</span>
                    </div>
                </div>
            </main>
            <main id="configReporteSinDjEscaneada" class="hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700 dark:bg-white">
                <div class="flex items-start gap-3 border-b border-default-200 p-5 dark:border-slate-700">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><i class="i-ph-file-pdf text-2xl"></i></div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold text-default-900 dark:text-white">Personal sin DJ escaneada</h2><span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Excel · PDF</span></div>
                        <p class="mt-1 text-sm text-default-600 dark:text-slate-300">Personal sin registro de carga ni PDF de DJ escaneada encontrado en las ubicaciones del servidor.</p>
                    </div>
                </div>
                <div class="p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-default-500 dark:text-slate-400">Filtros del reporte</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Sucursal<select id="sinDjSucursal" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todas las sucursales</option>@foreach($sucursales as $sucursal) @if(($sucursal->codigo ?? '0') !== '0')<option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura ?? $sucursal->nombre ?? $sucursal->codigo }}</option>@endif @endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Tipo de personal<select id="sinDjTipo" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todos los tipos</option>@foreach($tiposPersonal as $tipo)<option value="{{ trim($tipo->codigo) }}">{{ $tipo->nombre }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Cargo<select id="sinDjCargo" disabled class="mt-1 block w-full rounded-lg border-default-300 text-sm disabled:cursor-not-allowed disabled:bg-gray-100"><option value="">Selecciona primero el tipo</option></select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Estado<select id="sinDjEstado" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Activos y cesados</option><option value="SI">Activo</option><option value="NO">Cesado</option></select></label>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-default-200 pt-5 dark:border-slate-700">
                        <button id="btnLimpiarSinDj" type="button" class="rounded-lg border border-default-300 px-4 py-2 text-sm font-medium text-default-700"><i class="i-ph-arrow-counter-clockwise mr-1"></i> Limpiar filtros</button>
                        <button id="btnPrevisualizarSinDj" type="button" class="inline-flex items-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5"><i class="i-ph-eye"></i> Vista previa</button>
                        <span id="estadoSinDj" class="text-sm text-default-500" aria-live="polite">Se buscarán DJ escaneadas en las ubicaciones del servidor y en sus registros.</span>
                    </div>
                </div>
            </main>

            <main id="configReporteDniHijos" class="hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700 dark:bg-white">
                <div class="flex items-start gap-3 border-b border-default-200 p-5 dark:border-slate-700">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><i class="i-ph-users-three text-2xl"></i></div>
                    <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold text-default-900">Personal Con Asignación Familiar Sin Sustento</h2><span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Excel</span></div><p class="mt-1 text-sm text-default-600">Incluye personal con asignación familiar cuyo DNI de hijo menor (anverso o reverso) está pendiente de cargar.</p></div>
                </div>
                <div class="p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-default-500">Filtros del reporte</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                        <label class="block text-sm font-medium text-default-700">Sucursal<select id="hijosSucursal" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todas las sucursales</option>@foreach($sucursales as $sucursal) @if(($sucursal->codigo ?? '0') !== '0')<option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura ?? $sucursal->nombre ?? $sucursal->codigo }}</option>@endif @endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700">Tipo de personal<select id="hijosTipo" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todos los tipos</option>@foreach($tiposPersonal as $tipo)<option value="{{ trim($tipo->codigo) }}">{{ $tipo->nombre }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700">Cargo<select id="hijosCargo" disabled class="mt-1 block w-full rounded-lg border-default-300 text-sm disabled:cursor-not-allowed disabled:bg-gray-100"><option value="">Selecciona primero el tipo</option></select></label>
                        <label class="block text-sm font-medium text-default-700">Estado<select id="hijosEstado" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Activos y cesados</option><option value="SI">Activo</option><option value="NO">Cesado</option></select></label>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-default-200 pt-5">
                        <button id="btnLimpiarDniHijos" type="button" class="rounded-lg border border-default-300 px-4 py-2 text-sm font-medium text-default-700"><i class="i-ph-arrow-counter-clockwise mr-1"></i> Limpiar filtros</button>
                        <button id="btnPrevisualizarDniHijos" type="button" class="inline-flex items-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5"><i class="i-ph-eye"></i> Vista previa</button>
                        <span id="estadoDniHijos" class="text-sm text-default-500" aria-live="polite">Se revisará el DNI de hijos menores del personal filtrado.</span>
                    </div>
                </div>
            </main>

            <main id="configReporteAsigHijosMayores" class="hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700 dark:bg-white">
                <div class="flex items-start gap-3 border-b border-default-200 p-5 dark:border-slate-700">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class="i-ph-users-three text-2xl"></i></div>
                    <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold text-default-900 dark:text-white">Asignación Familiar Por Hijos Mayores De Edad</h2><span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Excel · PDF</span></div><p class="mt-1 text-sm text-default-600 dark:text-slate-300">Incluye personal marcado con asignación familiar cuyos hijos registrados tienen todos 18 años o más. Se excluye a quien tenga algún hijo menor o sin fecha de nacimiento.</p></div>
                </div>
                <div class="p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-default-500 dark:text-slate-400">Filtros del reporte</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Sucursal<select id="mayoresSucursal" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todas las sucursales</option>@foreach($sucursales as $sucursal) @if(($sucursal->codigo ?? '0') !== '0')<option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura ?? $sucursal->nombre ?? $sucursal->codigo }}</option>@endif @endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Tipo de personal<select id="mayoresTipo" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todos los tipos</option>@foreach($tiposPersonal as $tipo)<option value="{{ trim($tipo->codigo) }}">{{ $tipo->nombre }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Cargo<select id="mayoresCargo" disabled class="mt-1 block w-full rounded-lg border-default-300 text-sm disabled:cursor-not-allowed disabled:bg-gray-100"><option value="">Selecciona primero el tipo</option></select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Estado<select id="mayoresEstado" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Activos y cesados</option><option value="SI">Activo</option><option value="NO">Cesado</option></select></label>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-default-200 pt-5 dark:border-slate-700">
                        <button id="btnLimpiarMayores" type="button" class="rounded-lg border border-default-300 px-4 py-2 text-sm font-medium text-default-700"><i class="i-ph-arrow-counter-clockwise mr-1"></i> Limpiar filtros</button>
                        <button id="btnPrevisualizarMayores" type="button" class="inline-flex items-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5"><i class="i-ph-eye"></i> Vista previa</button>
                        <span id="estadoMayores" class="text-sm text-default-500" aria-live="polite">Se listará únicamente al personal cuyo indicador de asignación familiar figura como Sí y todos sus hijos son mayores de edad.</span>
                    </div>
                </div>
            </main>
            <main id="configReporteMayoriaEdadProxima" class="hidden rounded-xl border border-default-200 bg-white shadow-sm dark:border-slate-700 dark:bg-white">
                <div class="flex items-start gap-3 border-b border-default-200 p-5 dark:border-slate-700">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600"><i class="i-ph-bell-ringing text-2xl"></i></div>
                    <div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold text-default-900 dark:text-white">Asignación Familiar Próxima a Vencer</h2><span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Excel · PDF</span></div><p class="mt-1 text-sm text-default-600 dark:text-slate-300">Hijos registrados en la DJ que cumplen 18 años dentro de los próximos 30 días; incluye aviso inicial y recordatorio de 15 días.</p></div>
                </div>
                <div class="p-5">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-default-500 dark:text-slate-400">Filtros del reporte</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Sucursal<select id="mayoriaSucursal" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todas las sucursales</option>@foreach($sucursales as $sucursal) @if(($sucursal->codigo ?? '0') !== '0')<option value="{{ $sucursal->codigo }}">{{ $sucursal->abreviatura ?? $sucursal->nombre ?? $sucursal->codigo }}</option>@endif @endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Tipo de personal<select id="mayoriaTipo" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Todos los tipos</option>@foreach($tiposPersonal as $tipo)<option value="{{ trim($tipo->codigo) }}">{{ $tipo->nombre }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Cargo<select id="mayoriaCargo" disabled class="mt-1 block w-full rounded-lg border-default-300 text-sm disabled:cursor-not-allowed disabled:bg-gray-100"><option value="">Selecciona primero el tipo</option></select></label>
                        <label class="block text-sm font-medium text-default-700 dark:text-slate-200">Estado<select id="mayoriaEstado" class="mt-1 block w-full rounded-lg border-default-300 text-sm"><option value="">Activos y cesados</option><option value="SI">Activo</option><option value="NO">Cesado</option></select></label>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-default-200 pt-5 dark:border-slate-700">
                        <button id="btnLimpiarMayoria" type="button" class="rounded-lg border border-default-300 px-4 py-2 text-sm font-medium text-default-700"><i class="i-ph-arrow-counter-clockwise mr-1"></i> Limpiar filtros</button>
                        <button id="btnPrevisualizarMayoria" type="button" class="inline-flex items-center gap-2 rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary/5"><i class="i-ph-eye"></i> Vista previa</button>
                        <span id="estadoMayoria" class="text-sm text-default-500" aria-live="polite">Se incluirán personal activo con asignación familiar y fechas de nacimiento registradas en la DJ.</span>
                    </div>
                </div>
            </main>
        </div>
        <div id="modalVistaPreviaReporte" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="tituloVistaPreviaReporte">
            <section class="flex h-[94vh] max-h-[94vh] w-[96vw] max-w-[1800px] flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <header class="flex flex-wrap items-start gap-4 border-b border-slate-200 px-5 py-4">
                    <div class="min-w-0"><h2 id="tituloVistaPreviaReporte" class="text-lg font-semibold text-slate-900">Vista previa</h2><p id="subtituloVistaPreviaReporte" class="mt-1 text-sm text-slate-500"></p></div>
                    <div id="accionesExportarPreviewDj" class="flex flex-wrap items-center gap-2">
                        <div id="previewExportSinFotoDni" class="hidden flex items-center gap-2">
                            <button id="btnExportarSinFotoDni" type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-xls"></i> Exportar Excel</button>
                            <button id="btnExportarPdfSinFotoDni" type="button" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-pdf"></i> Exportar PDF</button>
                        </div>
                        <div id="previewExportSinDj" class="hidden flex items-center gap-2">
                            <button id="btnExportarSinDj" type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-xls"></i> Exportar Excel</button>
                            <button id="btnExportarPdfSinDj" type="button" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-pdf"></i> Exportar PDF</button>
                        </div>
                        <div id="previewExportDniHijos" class="hidden flex items-center gap-2">
                            <button id="btnExportarDniHijos" type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-xls"></i> Exportar Excel</button>
                            <button id="btnExportarPdfDniHijos" type="button" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-pdf"></i> Exportar PDF</button>
                        </div>
                        <div id="previewExportMayores" class="hidden flex items-center gap-2">
                            <button id="btnExportarMayores" type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-xls"></i> Exportar Excel</button>
                            <button id="btnExportarPdfMayores" type="button" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-pdf"></i> Exportar PDF</button>
                        </div>
                        <div id="previewExportMayoria" class="hidden flex items-center gap-2">
                            <button id="btnExportarMayoria" type="button" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-xls"></i> Exportar Excel</button>
                            <button id="btnExportarPdfMayoria" type="button" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60"><i class="i-ph-file-pdf"></i> Exportar PDF</button>
                        </div>
                    </div>
                    <button id="cerrarVistaPreviaReporte" type="button" class="ml-auto rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Cerrar vista previa"><i class="i-ph-x text-xl"></i></button>
                </header>
                <div id="contenidoVistaPreviaReporte" class="min-h-0 min-w-0 flex-1 overflow-auto p-5" aria-live="polite"><p class="text-sm text-slate-500">Preparando vista previa…</p></div>
                <footer class="flex justify-end border-t border-slate-200 px-5 py-3">
                    <button id="cerrarVistaPreviaReportePie" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cerrar</button>
                </footer>
            </section>
        </div>
    </div>

    <script>
        window.reportesDjCargos = @json($cargosDj);
        window.logoUrl = @json(asset('images/logo_sol.png'));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.1/dist/jspdf.plugin.autotable.min.js"></script>
    @vite(['resources/js/functions/reportes_dj.js', 'resources/js/functions/modal_reporte.js'])
@endsection

