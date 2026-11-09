{{-- Modal Actualizar fechas de programación (vista nueva).
      Permite editar inicio y fin de la programación VIGENTE y, opcionalmente,
      sincronizar el curso y las inscripciones existentes en el Aula Virtual.
      IDs con prefijo nfep. --}}
<div id="modalEditarFechasProgramacionNew" class="contents">
    <div x-data="formEditarFechasProgramacionNew()" id="formEditarFechasProgramacionNewRoot" x-init="init()"
        @close-modal-editar-fechas-new.window="showModal = false">

        <div x-show="showModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="background: rgba(36,39,70,0.45);">

            <div class="relative flex flex-col shadow-2xl rounded-2xl overflow-hidden w-full max-w-xl border border-default-200 bg-white max-h-[90vh]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                {{-- Loader total --}}
                <div x-show="cargando" x-transition.opacity
                    class="absolute inset-0 z-30 bg-white flex flex-col items-center justify-center gap-4 p-8 text-center">
                    <span class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></span>
                    <p class="text-base font-bold text-default-900">Cargando programación vigente…</p>
                </div>

                {{-- Header --}}
                <div class="px-6 pt-5 pb-4 border-b border-default-100 bg-gradient-to-r from-white to-default-50/40 shrink-0">
                    <div class="flex justify-between items-start gap-4">
                        <div class="flex items-start gap-3">
                            <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                                <i class="ti ti-calendar-event text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-default-900">Actualizar fechas de programación</h3>
                                <p class="text-sm text-default-500 mt-0.5 font-semibold" x-text="cursoNombre"></p>
                            </div>
                        </div>
                        <button type="button" @click="cerrar()"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 transition-colors">
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white">
                    <div x-show="error" class="mb-4 text-sm font-semibold text-red-600 bg-red-50 border border-red-100 rounded-lg px-4 py-3" x-text="error"></div>

                    <div x-show="programacion" class="flex flex-col gap-4">
                        {{-- Programación actual --}}
                        <div class="bg-default-50/60 border border-default-100 rounded-xl p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-default-500 mb-2">Programación vigente</p>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <p class="text-[11px] text-default-400 font-medium">Inicio actual</p>
                                    <p class="font-bold text-default-800" x-text="fmtFecha(programacion.fecha_inicio)"></p>
                                </div>
                                <div>
                                    <p class="text-[11px] text-default-400 font-medium">Fin actual</p>
                                    <p class="font-bold text-default-800" x-text="fmtFecha(programacion.fecha_final)"></p>
                                </div>
                            </div>
                        </div>

                        {{-- Nuevas fechas --}}
                        <div>
                            <p class="text-sm font-bold text-default-800 mb-2">Nuevas fechas</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="nfepFechaInicio" class="text-sm font-medium text-gray-800 inline-block mb-1">Fecha de inicio <span class="text-red-500">*</span></label>
                                    <input id="nfepFechaInicio" type="date" x-model="fechaInicio"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                                <div>
                                    <label for="nfepFechaFin" class="text-sm font-medium text-gray-800 inline-block mb-1">Fecha de fin <span class="text-red-500">*</span></label>
                                    <input id="nfepFechaFin" type="date" x-model="fechaFin" :min="fechaInicio"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                            </div>
                            <p x-show="errorFecha" class="mt-1 text-sm text-red-600" x-text="errorFecha"></p>
                        </div>

                        {{-- Resumen del cambio --}}
                        <div x-show="fechasValidas" x-transition
                            class="rounded-xl border border-blue-100 bg-blue-50/50 px-4 py-3 flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                                <i class="ti ti-calendar-check text-lg text-blue-600"></i>
                            </div>
                            <div class="text-sm text-blue-800">
                                <p>
                                    La programación quedará vigente del
                                    <strong x-text="fmtFecha(fechaInicio)"></strong> al
                                    <strong x-text="fmtFecha(fechaFin)"></strong>
                                    <span class="font-medium" x-text="`(${diasDuracion} día(s))`"></span>.
                                </p>
                                <p x-show="cambioPeriodo" class="mt-1 text-blue-700">
                                    El periodo cambiará de <strong x-text="programacion.periodo || '—'"></strong>
                                    a <strong x-text="nuevoPeriodo"></strong>.
                                </p>
                            </div>
                        </div>

                        <p x-show="inicioEnPasado && fechasValidas" x-transition
                            class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 leading-5">
                            La fecha de inicio ya pasó. Si actualizas las inscripciones, los participantes verán el cambio de vigencia de inmediato en el Aula Virtual.
                        </p>

                        {{-- Sincronización con el Aula Virtual --}}
                        <div class="rounded-xl border border-default-200/70 bg-default-50/40 p-4 flex flex-col gap-3">
                            <p class="text-sm font-bold text-default-800">Sincronización con el Aula Virtual</p>

                            <div x-show="!tieneAulaVirtual" class="flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 leading-5">
                                <i class="ti ti-alert-triangle text-base shrink-0 mt-0.5"></i>
                                <span>Este curso no tiene un aula virtual asociada. Solo se actualizarán las fechas de la programación en el sistema.</span>
                            </div>

                            <label class="flex items-start gap-3 select-none"
                                :class="tieneAulaVirtual ? 'cursor-pointer' : 'opacity-50 pointer-events-none'">
                                <button type="button" role="switch" :aria-checked="actualizarMatriculas.toString()"
                                    @click="actualizarMatriculas = !actualizarMatriculas" :disabled="!tieneAulaVirtual"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30 shrink-0 mt-0.5"
                                    :class="actualizarMatriculas ? 'bg-primary' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="actualizarMatriculas ? 'translate-x-6' : 'translate-x-1'"></span>
                                </button>
                                <span>
                                    <span class="block text-sm font-semibold text-default-800">Actualizar matrículas de inscritos</span>
                                    <span class="block text-xs text-default-500 mt-0.5">Actualiza la vigencia (inicio y fin) de las inscripciones ya existentes en el Aula Virtual. No matricula ni desmatricula a nadie.</span>
                                </span>
                            </label>

                            <label class="flex items-start gap-3 select-none"
                                :class="tieneAulaVirtual ? 'cursor-pointer' : 'opacity-50 pointer-events-none'">
                                <button type="button" role="switch" :aria-checked="actualizarCursoAv.toString()"
                                    @click="actualizarCursoAv = !actualizarCursoAv" :disabled="!tieneAulaVirtual"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30 shrink-0 mt-0.5"
                                    :class="actualizarCursoAv ? 'bg-primary' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="actualizarCursoAv ? 'translate-x-6' : 'translate-x-1'"></span>
                                </button>
                                <span>
                                    <span class="block text-sm font-semibold text-default-800">Actualizar fechas del curso en AV</span>
                                    <span class="block text-xs text-default-500 mt-0.5">Actualiza las fechas de inicio y fin del aula del curso en Moodle.</span>
                                </span>
                            </label>
                        </div>

                        {{-- Nota --}}
                        <div class="rounded-xl border border-default-200/70 bg-default-50/50 px-4 py-3 flex items-start gap-3">
                            <i class="ti ti-info-circle text-lg text-default-400 shrink-0 mt-0.5"></i>
                            <p class="text-xs text-default-600 leading-5">
                                Las fechas de la programación definen la vigencia del curso. Al matricular nuevo personal en esta programación, su inscripción en el Aula Virtual usará estas fechas de inicio y fin. Los participantes ya inscritos solo se actualizarán si activas <strong>«Actualizar matrículas de inscritos»</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end items-center gap-2 px-6 py-4 border-t border-default-100 bg-default-50/50 shrink-0">
                    <button type="button" @click="cerrar()" :disabled="cargando || guardando"
                        class="h-9 px-4 inline-flex items-center bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition disabled:opacity-50">
                        Cancelar
                    </button>
                    <button type="button" @click="guardarFechas()" :disabled="cargando || guardando || !fechasValidas"
                        class="h-9 px-5 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-device-floppy" x-show="!guardando"></i>
                        <span x-text="guardando ? 'Actualizando…' : 'Actualizar'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
