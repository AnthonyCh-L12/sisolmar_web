@extends('layouts.vertical', ['title' => 'Procesar Solicitud Vacaciones'])

@section('content')

@include('layouts.shared/page-title', [
    'subtitle' => 'Recursos Humanos',
    'title' => 'Procesar Solicitud Vacaciones'
])

<div class="container-fluid px-4 pt-2 pb-4">

    {{-- PARAMETROS DE BUSQUEDA --}}
        {{-- PARAMETROS DE BUSQUEDA --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-4 mb-4">

        <h4 class="text-base font-semibold mb-3">
            Parámetros de Búsqueda
        </h4>

        <form method="GET"
            action="{{ url('/rrhh/Solicitud') }}"
            id="formFiltros">
            
            <input type="hidden" name="buscar" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">

                {{-- EMPRESA --}}
                <div>
                    <label class="block text-sm font-medium mb-1">
                        Empresa
                    </label>

                    <select name="empresa"
                        id="empresa"
                        class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm dark:bg-slate-700 dark:border-slate-600">

                        <option value="T" {{ $empresa == 'T' ? 'selected' : '' }}>
                            TODAS
                        </option>

                        @foreach($empresas as $item)
                            <option value="{{ $item->EMPR_CODIGO }}"
                                {{ $empresa == $item->EMPR_CODIGO ? 'selected' : '' }}>
                                {{ $item->RAZON_SOCIAL }}
                            </option>
                        @endforeach

                    </select>
                </div>

                {{-- SUCURSAL --}}
                <div>
                    <label class="block text-sm font-medium mb-1">
                        Sucursal
                    </label>

                    <select name="sucursal"
                        id="sucursal"
                        class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm dark:bg-slate-700 dark:border-slate-600">

                        <option value="T" {{ $sucursal == 'T' ? 'selected' : '' }}>
                            TODAS
                        </option>

                        @foreach($sucursales as $item)
                            <option value="{{ $item->SUCU_CODIGO }}"
                                {{ $sucursal == $item->SUCU_CODIGO ? 'selected' : '' }}>
                                {{ $item->SUCU_ABREVIATURA }}
                            </option>
                        @endforeach

                    </select>
                </div>

                {{-- TIPO CARGO --}}
                <div>
                    <label class="block text-sm font-medium mb-1">
                        Tipo Cargo
                    </label>

                    <select name="tipo_cargo"
                        id="tipo_cargo"
                        class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm dark:bg-slate-700 dark:border-slate-600">

                        <option value="T">TODOS</option>

                    </select>
                </div>

                {{-- PERIODO --}}
                <div id="filtro_mes">
                    <label class="block text-sm font-medium mb-1">
                        Periodo
                    </label>

                    <select name="mes"
                        class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm dark:bg-slate-700 dark:border-slate-600">

                        <option value="0">TODOS</option>

                        @foreach(range(1, 12) as $numeroMes)
                            <option value="{{ $numeroMes }}"
                                {{ $mes == $numeroMes ? 'selected' : '' }}>

                                {{ strtoupper(\Carbon\Carbon::create()->month($numeroMes)->locale('es')->monthName) }}

                            </option>
                        @endforeach

                    </select>
                </div>

                {{-- PERSONAL --}}
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium mb-1">
                        Personal
                    </label>

                    <select name="personal"
                        id="personal"
                        class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm dark:bg-slate-700 dark:border-slate-600">

                        <option value="T">TODOS</option>

                    </select>
                </div>

                {{-- AÑO --}}
                <div id="filtro_anio">
                    <label class="block text-sm font-medium mb-1">
                        Año
                    </label>

                    <input type="number"
                        name="anio"
                        value="{{ $anio }}"
                        min="0"
                        max="9999"
                        class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-sm dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- BOTON BUSCAR --}}
                <div class="flex items-end justify-end">

                    <button type="submit"
                        class="w-full lg:w-auto px-5 py-2.5 rounded-md bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition-colors">

                        <i class="ri-search-line mr-1"></i>
                        Buscar

                    </button>

                </div>

            </div>

        </form>

    </div>
    {{-- MODAL DE CARGA --}}
    <div id="modalCargando"
        class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/40">

        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-8 text-center">

            <h2 class="text-2xl font-semibold text-gray-600 mb-6">
                Cargando...
            </h2>

            <div class="flex justify-center mb-4">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <p class="text-sm text-gray-500">
                Preparando información, espere por favor.
            </p>

        </div>

    </div>


    {{-- GRID DE TRABAJADORES --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-4">

        <div class="flex justify-between items-center mb-4">
            <h4 class="text-base font-semibold">
                Trabajadores
            </h4>

            <span class="text-sm text-gray-500">
                Registros: {{ count($trabajadores) }}
            </span>
        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full text-sm border-collapse">

                <thead class="bg-slate-100 dark:bg-slate-700">
                    <tr>
                        <th class="border px-3 py-2">It.</th>
                        <th class="border px-3 py-2">Código</th>
                        <th class="border px-3 py-2">Apellidos y Nombres</th>
                        <th class="border px-3 py-2">Sucursal</th>
                        <th class="border px-3 py-2">Área</th>
                        <th class="border px-3 py-2">Ingreso Planilla</th>
                        <th class="border px-3 py-2">Periodo Laborado</th>
                        <th class="border px-3 py-2">Días</th>
                        <th class="border px-3 py-2">Total Días</th>
                        <th class="border px-3 py-2">Año</th>
                        <th class="border px-3 py-2">Planilla</th>
                    </tr>
                </thead>

                
            <tbody>
                @forelse($trabajadores as $indice => $trabajador)
                    <!-- <tr class="hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer {{ (float)($trabajador->TOTAL_DIAS ?? 0) == 0 ? 'fila-sin-saldo' : '' }}"
                        ondblclick="window.location.href='{{ route('rrhh.solicitud.detalle', $trabajador->VACA_CODIGO) }}'"
                        title="Doble clic para ver el detalle de vacaciones"> -->
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer"
                        style="{{ (float)($trabajador->TOTAL_DIAS ?? 0) == 0 ? 'background-color: #bbbbbb !important;' : '' }}"
                        ondblclick="window.location.href='{{ route('rrhh.solicitud.detalle', $trabajador->VACA_CODIGO) }}'"
                        title="Doble clic para ver el detalle de vacaciones">
                        <td class="border px-3 py-2 text-center">
                            {{ $indice + 1 }}
                        </td>

                        <td class="border px-3 py-2 text-center">
                            {{ $trabajador->CODI_PERS }}
                        </td>  

                        <td class="border px-3 py-2 whitespace-nowrap">
                            {{ $trabajador->PERSONAL }}
                        </td>

                        <td class="border px-3 py-2 text-center">
                            {{ $trabajador->SUCURSALES }}
                        </td>

                        <td class="border px-3 py-2">
                            {{ $trabajador->AREA }}
                        </td>

                        <td class="border px-3 py-2 text-center whitespace-nowrap">
                            {{ $trabajador->FECH_INGRE_PLAN ? \Carbon\Carbon::parse($trabajador->FECH_INGRE_PLAN)->format('d/m/Y') : '' }}
                        </td>

                        <td class="border px-3 py-2 text-center whitespace-nowrap">
                            {{ $trabajador->PER_LAB_INI }} - {{ $trabajador->PER_LAB_FIN }}
                        </td>

                        <td class="border px-3 py-2 text-center">
                            {{ $trabajador->VACA_DIAS }}
                        </td>

                        <td class="border px-3 py-2 text-center">
                            {{ $trabajador->TOTAL_DIAS ?? 0 }}
                        </td>

                        <td class="border px-3 py-2 text-center">
                            {{ $trabajador->VACA_ANIO }}
                        </td>
                        <td class="border px-3 py-2 text-center">
                            {{ $trabajador->PLCA_CODIGO }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="text-center py-6 text-gray-500">
                            No hay registros para mostrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>


            </table>

        </div>
    </div>

</div>
<script>
        document.addEventListener('DOMContentLoaded', function () {

        const empresa = document.querySelector('[name="empresa"]');
        const sucursal = document.querySelector('[name="sucursal"]');
        const tipoCargo = document.getElementById('tipo_cargo');
        const personal = document.getElementById('personal');
        const filtroMes = document.getElementById('filtro_mes');
        const filtroAnio = document.getElementById('filtro_anio');
        const formFiltros = document.getElementById('formFiltros');
        const modalCargando = document.getElementById('modalCargando');
        // Valores enviados por Laravel
        const tipoCargoSeleccionado = @json($tipoCargo);
        const personalSeleccionado = @json($personal);

        let listaPersonal = [];
        let cargaInicial = true;

        // CARGAR TIPOS DE CARGO
        function cargarTiposCargo() {

            const url = "{{ route('rrhh.solicitud.tiposCargo') }}";

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error al consultar tipos de cargo');
                    }

                    return response.json();
                })
                .then(data => {

                    tipoCargo.innerHTML = '<option value="T">TODOS</option>';

                    data.forEach(item => {
                        tipoCargo.innerHTML += `
                            <option value="${item.CODI_TIPO_CARG}">
                                ${item.DESC_TIPO_CARG}
                            </option>
                        `;
                    });

                    // RESTAURAR TIPO CARGO SELECCIONADO
                    tipoCargo.value = tipoCargoSeleccionado;

                    cargarPersonalDesdeServidor();
                })
                .catch(error => {
                    console.error(error);
                    ocultarCargando();
                });
        }

        // CONSULTAR PERSONAL
        function cargarPersonalDesdeServidor() {

            const url = "{{ route('rrhh.solicitud.personal') }}"
                + "?empresa=" + encodeURIComponent(empresa.value)
                + "&sucursal=" + encodeURIComponent(sucursal.value)
                + "&tipo_cargo=" + encodeURIComponent(tipoCargo.value);

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error al consultar personal');
                    }

                    return response.json();
                })
                .then(data => {

                    listaPersonal = data;

                    cargarPersonal();
                })
                .catch(error => {
                    console.error(error);
                    ocultarCargando();
                });
        }

        // MOSTRAR PERSONAL
        function cargarPersonal() {

            personal.innerHTML = '<option value="T">TODOS</option>';

            listaPersonal.forEach(item => {
                personal.innerHTML += `
                    <option value="${item.CODI_PERS}">
                        ${item.CODI_PERS} - ${item.PERSONAL}
                    </option>
                `;
            });

            // CONSERVAR PERSONAL AL CARGAR LA PÁGINA
            if (cargaInicial) {
                personal.value = personalSeleccionado;
                cargaInicial = false;
            }

            mostrarFiltrosPeriodo();
            ocultarCargando();
        }

        // MOSTRAR / OCULTAR MES Y AÑO
        function mostrarFiltrosPeriodo() {

            const mostrar = personal.value === 'T';

            filtroMes.style.visibility = mostrar ? 'visible' : 'hidden';
            filtroAnio.style.visibility = mostrar ? 'visible' : 'hidden';
        }
        function mostrarCargando() {
            modalCargando.classList.remove('hidden');
        }

        function ocultarCargando() {
            modalCargando.classList.add('hidden');
        }
        // EVENTOS
        empresa.addEventListener('change', cargarPersonalDesdeServidor);
        sucursal.addEventListener('change', cargarPersonalDesdeServidor);
        tipoCargo.addEventListener('change', cargarPersonalDesdeServidor);
        personal.addEventListener('change', mostrarFiltrosPeriodo);

        // INICIALIZAR
        mostrarCargando();

        cargarTiposCargo();
        mostrarFiltrosPeriodo();

});
</script>
@endsection
