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

                    {{-- Matrícula --}}
                    <div class="mt-4 rounded-xl border p-4"
                        :class="(!matricularAutomaticamente || esDirigidoOtros) ? 'bg-amber-50/50 border-amber-100' : 'bg-blue-50/50 border-blue-100'">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-bold" :class="(!matricularAutomaticamente || esDirigidoOtros) ? 'text-amber-800' : 'text-blue-800'"
                                x-text="esDirigidoOtros ? 'Matrícula manual' : (matricularAutomaticamente ? 'Matrícula automática' : 'Solo programación')"></p>
                            {{-- Switch: matricular o no automáticamente (solo si aplica) --}}
                            <label x-show="!esDirigidoOtros" class="inline-flex items-center gap-2 cursor-pointer select-none shrink-0">
                                <span class="text-[11px] font-bold uppercase tracking-wider"
                                    :class="matricularAutomaticamente ? 'text-blue-700' : 'text-default-400'"
                                    x-text="matricularAutomaticamente ? 'Matricular: Sí' : 'Matricular: No'"></span>
                                <button type="button" role="switch" :aria-checked="matricularAutomaticamente.toString()"
                                    @click="matricularAutomaticamente = !matricularAutomaticamente"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30"
                                    :class="matricularAutomaticamente ? 'bg-primary' : 'bg-gray-300'">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="matricularAutomaticamente ? 'translate-x-6' : 'translate-x-1'"></span>
                                </button>
                                <input id="napMatricularAuto" type="checkbox" x-model="matricularAutomaticamente" class="sr-only">
                            </label>
                        </div>
                        <p class="text-sm mt-1" :class="(!matricularAutomaticamente || esDirigidoOtros) ? 'text-amber-700' : 'text-blue-700'">
                            <template x-if="!esDirigidoOtros && matricularAutomaticamente">
                                <span>Se matriculará automáticamente a <strong x-text="dirigidoLabel"></strong> en <strong x-text="sucursalLabel"></strong>.</span>
                            </template>
                            <template x-if="!esDirigidoOtros && !matricularAutomaticamente">
                                <span>Solo se creará la programación <strong>sin matrícula automática</strong>. Podrás matricular luego manualmente en <strong>Matrículas</strong> (<span x-text="sucursalLabel"></span>).</span>
                            </template>
                            <template x-if="esDirigidoOtros">
                                <span>Deberás matricular manualmente al personal en <strong>Matrículas</strong> (<span x-text="sucursalLabel"></span>).</span>
                            </template>
                        </p>
                    </div>

                    {{-- Aviso por correo (solo tiene efecto con matrícula automática) --}}
                    <label class="mt-4 flex items-start gap-2.5 p-3 rounded-xl border border-default-100 bg-default-50/60 select-none"
                        :class="(!matricularAutomaticamente || esDirigidoOtros) ? 'opacity-50 pointer-events-none' : 'cursor-pointer'">
                        <input id="napNotificar" type="checkbox" x-model="notificarCorreo" :disabled="!matricularAutomaticamente || esDirigidoOtros" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                        <span>
                            <span class="block text-sm font-semibold text-default-800">Avisar por correo al finalizar</span>
                            <span class="block text-xs text-default-500 mt-0.5">Se enviará el resumen al correo que indiques cuando termine la matrícula masiva (solo si son más de 10 personas).</span>
                        </span>
                    </label>

                    <div x-show="notificarCorreo && !esDirigidoOtros && matricularAutomaticamente" x-transition class="mt-2">
                        <label for="napCorreoNotificacion" class="text-sm font-medium text-gray-800 inline-block mb-1">Correo para el aviso <span class="text-red-500">*</span></label>
                        <input id="napCorreoNotificacion" type="email" x-model.trim="correoNotificacion" placeholder="tu.correo@solsecurity.pe"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <p class="text-xs text-default-500 mt-1">Ingresa tu propio correo para recibir el resumen de la matrícula.</p>
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