@extends('layouts.vertical', ['title' => 'Detalle de Solicitud de Vacaciones'])

@section('content')

@include('layouts.shared/page-title', [
    'subtitle' => 'Recursos Humanos',
    'title' => 'Detalle de Solicitud de Vacaciones'
])

<div class="p-4">

    {{-- CABECERA --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4">

        <div class="border-b dark:border-slate-700 px-5 py-4">
            <h4 class="text-base font-semibold">
                Datos del periodo de vacaciones
            </h4>
        </div>

        <div class="p-5">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                {{-- TRABAJADOR --}}
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium mb-1">Trabajador</label>
                    <input type="text" readonly
                        value="{{ $cabecera->PERSONAL }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- INGRESO PLANILLA --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Fecha ingreso planilla</label>
                    <input type="text" readonly
                        value="{{ $cabecera->FECH_INGRE_PLAN ? date('d/m/Y', strtotime($cabecera->FECH_INGRE_PLAN)) : '' }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- DIAS PERIODO --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Días del periodo</label>
                    <input type="text" readonly
                        value="{{ $cabecera->VACA_DIAS }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- TOTAL DIAS --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Total días</label>
                    <input type="text" readonly
                        value="{{ $cabecera->TOTAL_DIAS }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- AÑO --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Año</label>
                    <input type="text" readonly
                        value="{{ $cabecera->VACA_ANIO }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- MES --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Mes</label>
                    <input type="text" readonly
                        value="{{ [
                            1 => 'ENERO',
                            2 => 'FEBRERO',
                            3 => 'MARZO',
                            4 => 'ABRIL',
                            5 => 'MAYO',
                            6 => 'JUNIO',
                            7 => 'JULIO',
                            8 => 'AGOSTO',
                            9 => 'SEPTIEMBRE',
                            10 => 'OCTUBRE',
                            11 => 'NOVIEMBRE',
                            12 => 'DICIEMBRE'
                        ][(int)$cabecera->VACA_MES] ?? '' }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

                {{-- PLANILLA --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Planilla</label>
                    <input type="text" readonly
                        value="{{ $cabecera->PLCA_CODIGO }}"
                        class="w-full rounded border-gray-300 dark:bg-slate-700 dark:border-slate-600">
                </div>

            </div>


        </div>
    </div>


    {{-- DETALLE CRONOGRAMA --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow">

        <div class="border-b dark:border-slate-700 px-5 py-4 flex justify-between items-center">

            <h4 class="text-base font-semibold">
                Cronograma de vacaciones
            </h4>

            <span class="text-sm text-gray-500">
                Registros: {{ count($detalle) }}
            </span>

        </div>

        <div class="px-4 py-4 overflow-x-auto">

            <table class="min-w-full text-sm border-collapse">

                <thead class="bg-slate-100 dark:bg-slate-700">

                    <tr>

                        {{-- SELECCIONAR TODOS --}}
                        <th class="border px-3 py-2 text-center">
                            <input type="checkbox"
                                id="checkTodos"
                                class="rounded border-gray-300">
                        </th>

                        <!--<th class="border px-3 py-2">Código</th>-->
                        <th class="border px-3 py-2">Autorizante</th>
                        <th class="border px-3 py-2">Fecha inicio</th>
                        <th class="border px-3 py-2">Fecha fin</th>
                        <th class="border px-3 py-2">Días</th>
                        <th class="border px-3 py-2">Domingos</th>
                        <th class="border px-3 py-2">Estado</th>
                        <th class="border px-3 py-2">Fecha aprobación</th>
                        <th class="border px-3 py-2">Fecha retorno</th>
                        <th class="px-3 py-2">Acción</th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($detalle as $fila)

                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700">

                            {{-- CHECK INDIVIDUAL --}}
                            <td class="border px-3 py-2 text-center">

                               <input type="checkbox"
                                class="checkCronograma rounded border-gray-300"
                                value="{{ $fila->VACR_CODIGO }}"
                                {{ (int)$fila->VACR_ESTADO === 0 && in_array($fila->VACR_CODIGO, old('cronogramas', [])) ? 'checked' : '' }}
                                {{ (int)$fila->VACR_ESTADO !== 0 ? 'disabled' : '' }}>
                            </td>

                            {{-- CODIGO --}}
                            <!--<td class="border px-3 py-2 text-center">
                                {{ $fila->VACR_CODIGO }}
                            </td>-->
                            
                            {{-- AUTORIZANTE --}}
                            <td class="border px-3 py-2 whitespace-nowrap">
                                {{ $fila->AUTORIZANTE }}
                            </td>

                            {{-- FECHA INICIO --}}
                            <td class="border px-3 py-2 text-center whitespace-nowrap">
                                {{ $fila->VACR_FEC_INI ? date('d/m/Y', strtotime($fila->VACR_FEC_INI)) : '' }}
                            </td>

                            {{-- FECHA FIN --}}
                            <td class="border px-3 py-2 text-center whitespace-nowrap">
                                {{ $fila->VACR_FEC_FIN ? date('d/m/Y', strtotime($fila->VACR_FEC_FIN)) : '' }}
                            </td>

                            {{-- DIAS --}}
                            <td class="border px-3 py-2 text-center">
                                {{ $fila->DIAS }}
                            </td>

                            {{-- DOMINGOS --}}
                            <td class="border px-3 py-2 text-center">
                                {{ $fila->DOMINGOS }}
                            </td>

                            
                            <td class="border px-2 py-2 text-center">

                                @if((int)$fila->VACR_ESTADO === 0)

                                    <span class="inline-flex items-center justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium bg-amber-100 text-amber-700">
                                        En espera
                                    </span>

                                @elseif((int)$fila->VACR_ESTADO === 1)

                                    <span class="inline-flex items-center justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-700">
                                        En actividad
                                    </span>

                                @elseif((int)$fila->VACR_ESTADO === 2)

                                    <span class="inline-flex items-center justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium bg-gray-200 text-gray-700">
                                        Cerrado
                                    </span>

                                @else

                                    <span class="inline-flex items-center justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-700">
                                        Estado desconocido: {{ $fila->VACR_ESTADO }}
                                    </span>

                                @endif

                            </td>

                            {{-- FECHA APROBACION --}}
                            <td class="border px-3 py-2 text-center whitespace-nowrap">
                                {{ $fila->FECHA_APROBADO ? date('d/m/Y', strtotime($fila->FECHA_APROBADO)) : '' }}
                            </td>

                            {{-- FECHA RETORNO --}}
                            <td class="border px-3 py-2 text-center whitespace-nowrap">
                                {{ $fila->VACR_FEC_RETORNO ? date('d/m/Y', strtotime($fila->VACR_FEC_RETORNO)) : '' }}
                            </td>
    
                            {{-- ACCION --}}
                            <td class="border px-3 py-2 text-center">

                                @if((int)$fila->VACR_ESTADO === 1)

                                    <form method="POST"
                                        class="formRevertir"
                                        action="{{ route('rrhh.solicitud.vacaciones.revertir', [
                                            'codigo' => $cabecera->VACA_CODIGO,
                                            'cronograma' => $fila->VACR_CODIGO
                                        ]) }}">

                                        @csrf

                                        <button type="submit"
                                            title="Revertir vacaciones"
                                            class="inline-flex items-center justify-center rounded-md bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700 transition-colors whitespace-nowrap">
                                            ↩ Revertir
                                        </button>

                                    </form>

                                @else
                                    <span class="text-gray-400">—</span>
                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="10" class="text-center py-6 text-gray-500">
                                No existen cronogramas registrados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>
    </div>


    {{-- BOTONES --}}
    {{-- BOTONES --}}
        <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-3">

            {{-- CONTADOR --}}
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <span id="cantidadSeleccionados" class="font-semibold text-gray-700 dark:text-gray-200">0</span>
                cronogramas seleccionados
            </div>

            {{-- ACCIONES --}}
            <div class="flex items-center gap-2">

                <form id="formIniciarVacaciones"
                    method="POST"
                    action="{{ route('rrhh.solicitud.vacaciones.iniciar', $cabecera->VACA_CODIGO) }}">

                    @csrf

                    <div id="cronogramasSeleccionados"></div>

                    <button type="submit"
                        id="btnIniciarVacaciones"
                        disabled
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors">

                        <span>▶</span>
                        Iniciar vacaciones

                    </button>

                </form>

                <button type="button"
                    onclick="window.history.back()"
                    class="px-4 py-2 rounded-md text-sm font-medium text-white bg-slate-500 hover:bg-slate-600 transition-colors">

                    Regresar

                </button>

            </div>

        </div>

</div>


{{-- CONTROL DE CHECKBOX --}}
<script>

    document.addEventListener('DOMContentLoaded', function () {

        const checkTodos = document.getElementById('checkTodos');
        const checks = document.querySelectorAll('.checkCronograma');
        const contador = document.getElementById('cantidadSeleccionados');
        const boton = document.getElementById('btnIniciarVacaciones');
        const formulario = document.getElementById('formIniciarVacaciones');
        const contenedorSeleccionados = document.getElementById('cronogramasSeleccionados');

        // ACTUALIZAR CONTADOR
        function actualizarContador() {

            const seleccionados = document.querySelectorAll('.checkCronograma:checked');

            contador.textContent = seleccionados.length;

            const checksHabilitados = document.querySelectorAll('.checkCronograma:not(:disabled)');

            checkTodos.checked = checksHabilitados.length > 0 &&
                document.querySelectorAll('.checkCronograma:not(:disabled):checked').length === checksHabilitados.length;

            checkTodos.indeterminate =
                seleccionados.length > 0 &&
                seleccionados.length < checksHabilitados.length;

            boton.disabled = seleccionados.length === 0;
        }

        // SELECCIONAR TODOS
        checkTodos.addEventListener('change', function () {

            checks.forEach(function (check) {

                if (!check.disabled) {
                    check.checked = checkTodos.checked;
                }

            });

            actualizarContador();

        });

        // CHECK INDIVIDUAL
        checks.forEach(function (check) {

            check.addEventListener('change', actualizarContador);

        });

        // INICIAR VACACIONES
        formulario.addEventListener('submit', function (event) {

            event.preventDefault();

            const seleccionados = document.querySelectorAll('.checkCronograma:checked');

            if (seleccionados.length === 0) {

                Swal.fire({
                    icon: 'warning',
                    title: 'Sin cronogramas seleccionados',
                    text: 'Seleccione al menos un cronograma para iniciar las vacaciones.',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#6558D9'
                });

                return;
            }

            Swal.fire({

                icon: 'question',

                title: 'Iniciar Vacaciones',

                html: `
                    Se procesarán <b>${seleccionados.length}</b>
                    cronograma(s) seleccionado(s).<br><br>

                    Se descontarán los días correspondientes
                    del saldo vacacional.<br>

                    ¿Desea continuar?
                `,

                showCancelButton: true,

                confirmButtonText: 'Sí, iniciar',

                cancelButtonText: 'Cancelar',

                confirmButtonColor: '#6558D9',

                cancelButtonColor: '#6C757D',

                reverseButtons: true,

                allowOutsideClick: false

            }).then(function (resultado) {

                if (resultado.isConfirmed) {

                    contenedorSeleccionados.innerHTML = '';

                    seleccionados.forEach(function (check) {

                        const input = document.createElement('input');

                        input.type = 'hidden';
                        input.name = 'cronogramas[]';
                        input.value = check.value;

                        contenedorSeleccionados.appendChild(input);

                    });

                    formulario.submit();

                }

            });

        });


        // REVERTIR VACACIONES
        document.querySelectorAll('.formRevertir').forEach(function (form) {

            form.addEventListener('submit', function (event) {

                event.preventDefault();

                Swal.fire({

                    icon: 'question',

                    title: 'Revertir Vacaciones',

                    html: `
                        Se revertirá el cronograma seleccionado.<br><br>

                        Los días descontados serán restituidos
                        al saldo vacacional del trabajador
                        y el registro volverá al estado pendiente.<br><br>

                        ¿Está seguro de continuar?
                    `,

                    showCancelButton: true,

                    confirmButtonText: 'Sí, revertir',

                    cancelButtonText: 'Cancelar',

                    confirmButtonColor: '#DC3545',

                    cancelButtonColor: '#6C757D',

                    reverseButtons: true,

                    allowOutsideClick: false

                }).then(function (resultado) {

                    if (resultado.isConfirmed) {

                        form.submit();

                    }

                });

            });

        });


        actualizarContador();

    });

</script>

@endsection
