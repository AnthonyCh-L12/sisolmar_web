{{-- Modal Apertura de curso (vista nueva): misma lógica que el modal original,
      estilo moderno + feedback de vigencia. IDs con prefijo nap. --}}
<div id="modalAperturaCursoNew" class="contents">
    <div x-data="formAperturaNew()" id="formAperturaNewRoot" x-init="init()"
        @close-modal-apertura-new.window="showModal = false">

        <div x-show="showModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="background: rgba(36,39,70,0.45);">

            <div class="relative flex flex-col shadow-2xl rounded-2xl overflow-hidden w-full max-w-2xl border border-default-200 bg-white max-h-[90vh]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                {{-- Loader total --}}
                <div x-show="cargando" x-transition.opacity
                    class="absolute inset-0 z-30 bg-white flex flex-col items-center justify-center gap-4 p-8 text-center">
                    <span class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></span>
                    <p class="text-base font-bold text-default-900">Cargando datos del curso…</p>
                </div>

                {{-- Header --}}
                <div class="px-6 pt-5 pb-4 border-b border-default-100 bg-gradient-to-r from-white to-default-50/40 shrink-0">
                    <div class="flex justify-between items-start gap-4">
                        <div class="flex items-start gap-3">
                            <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                                <i class="ti ti-calendar-star text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-default-900">Aperturar curso</h3>
                                <p class="text-sm text-default-500 mt-0.5">
                                    <span class="font-semibold text-default-800" x-text="cursoNombre"></span>
                                    <span class="text-default-300 mx-1">·</span>
                                    <span x-text="planNombre"></span>
                                    <span class="text-default-300 mx-1">·</span>
                                    Frecuencia <span class="font-semibold" x-text="frecuencia || '—'"></span>
                                </p>
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="napInicio" class="text-sm font-medium text-gray-800 inline-block mb-1">Fecha de inicio <span class="text-red-500">*</span></label>
                            <input id="napInicio" type="date" x-model="fechaInicio" :min="fechaMinima"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        </div>
                        <div x-show="requiereFechaFin" x-transition>
                            <label for="napFin" class="text-sm font-medium text-gray-800 inline-block mb-1">Fecha de fin <span class="text-red-500">*</span></label>
                            <input id="napFin" type="date" x-model="fechaFin" :min="fechaInicio || fechaMinima"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        </div>
                    </div>

                    {{-- Feedback de vigencia --}}
                    <div x-show="fechasValidas" x-transition class="mt-4 rounded-xl border overflow-hidden"
                        :class="renovacionAutomatica ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/60'">
                        <div class="px-4 py-3 flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                :class="renovacionAutomatica ? 'bg-emerald-500/10' : 'bg-amber-500/10'">
                                <i class="ti text-lg" :class="renovacionAutomatica ? 'ti-refresh text-emerald-600' : 'ti-alert-triangle text-amber-600'"></i>
                            </div>
                            <div class="text-sm">
                                <p class="font-bold" :class="renovacionAutomatica ? 'text-emerald-800' : 'text-amber-800'">
                                    Disponible del <span x-text="fmtFecha(fechaInicio)"></span> al <span x-text="fmtFecha(fechaFinEstimada())"></span>
                                    <span class="font-medium" x-text="`(${diasDuracion} días)`"></span>
                                </p>
                                <p x-show="renovacionAutomatica" class="text-emerald-700 mt-1">
                                    Curso periódico: se renovará automáticamente el <strong x-text="fmtFecha(fechaRenovacion)"></strong> con un nuevo ciclo.
                                </p>
                                <p x-show="!renovacionAutomatica" class="text-amber-700 mt-1">
                                    Al finalizar el <strong x-text="fmtFecha(fechaFinEstimada())"></strong> deberás re-aperturarlo manualmente.
                                </p>
                            </div>
                        </div>
                    </div>
                    <p x-show="!fechasValidas" class="mt-3 text-xs text-default-400 font-medium">Selecciona las fechas para ver la vigencia estimada del curso.</p>

                    {{-- Matrícula: se gestiona desde el submódulo de Matrículas --}}
                    <div class="mt-4 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
                                <i class="ti ti-users text-lg text-blue-600"></i>
                            </div>
                            <div class="text-sm">
                                <p class="font-bold text-blue-800">Matrícula en Gestión de Matrículas</p>
                                <p class="text-blue-700 mt-1">
                                    Al aperturar el curso solo se crea la <strong>programación</strong>. La matrícula
                                    del personal se realiza después en el submódulo <strong>Matrículas</strong>,
                                    donde podrás seleccionar y matricular de forma masiva o individual.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="flex justify-end items-center gap-2 px-6 py-4 border-t border-default-100 bg-default-50/50 shrink-0">
                    <button type="button" @click="cerrar()" :disabled="guardando"
                        class="h-9 px-4 inline-flex items-center bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition disabled:opacity-50">
                        Cancelar
                    </button>
                    <button type="button" @click="guardarApertura()" :disabled="guardando || !fechasValidas"
                        class="h-9 px-5 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-calendar-star" x-show="!guardando"></i>
                        <span x-text="guardando ? 'Procesando…' : 'Aperturar el curso'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>