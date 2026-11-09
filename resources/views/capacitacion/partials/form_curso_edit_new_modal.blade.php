{{-- Modal Editar curso (vista nueva): simple, una sola sección.
      Solo cursos inactivos (sin programación o solo CERRADAS). VIGENTE/PENDIENTE se bloquean en el JS.
      IDs con prefijo nedi para no colisionar con el wizard de registro. --}}
<div id="modalEditarCursoNew" class="contents">
    <div x-data="formCursoEditNew()" id="formCursoEditNewRoot" x-init="init()"
        @close-modal-edicion-new.window="showModal = false">

        <div x-show="showModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="background: rgba(36,39,70,0.45);">

            <div class="relative flex flex-col shadow-2xl rounded-2xl overflow-hidden w-full max-w-3xl border border-default-200 bg-white max-h-[90vh]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                {{-- Loader total mientras se valida la programación y se cargan datos --}}
                <div x-show="cargando" x-transition.opacity
                    class="absolute inset-0 z-30 bg-white flex flex-col items-center justify-center gap-4 p-8 text-center">
                    <span class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></span>
                    <p class="text-base font-bold text-default-900">Verificando programación…</p>
                    <p class="text-xs text-default-500 font-medium max-w-sm">Solo los cursos inactivos (sin programación o solo cerradas) pueden editarse.</p>
                </div>

                {{-- Header --}}
                <div class="px-6 pt-5 pb-4 border-b border-default-100 bg-gradient-to-r from-white to-default-50/40 shrink-0">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-default-900">Editar curso</h3>
                            <p class="text-sm text-default-500 mt-1">
                                Código <span class="font-mono font-bold" x-text="codigo"></span>
                                <span class="text-default-300 mx-1">·</span>
                                Plan <span class="font-semibold" x-text="planNombre"></span>
                            </p>
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

                    <div class="flex flex-col gap-4">
                        <div>
                            <label for="nediNombre" class="text-sm font-medium text-gray-800 inline-block mb-1">Nombre del curso <span class="text-red-500">*</span></label>
                            <input id="nediNombre" type="text" x-model="nombre" placeholder="Nombre del curso"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-800 inline-block mb-1">Descripción</label>
                            <textarea rows="2" x-model="descripcion" placeholder="Contenido, objetivos y temática…"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50"></textarea>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="nediCategoria" class="text-sm font-medium text-gray-800 inline-block mb-1">Tipo del curso <span class="text-red-500">*</span></label>
                                <select id="nediCategoria" x-model="categoria"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione…</option>
                                    <option value="1">Inducción</option>
                                    <option value="2">Charla</option>
                                    <option value="3">Capacitación</option>
                                    <option value="4">Entrenamiento</option>
                                    <option value="5">Simulacros de emergencia</option>
                                </select>
                            </div>
                            <div>
                                <label for="nediResponsable" class="text-sm font-medium text-gray-800 inline-block mb-1">Responsable <span class="text-red-500">*</span></label>
                                <select id="nediResponsable" x-model="codResponsable"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione responsable…</option>
                                    <template x-for="p in personalJefaturas" :key="p.codigo">
                                        <option :value="String(p.codigo)" x-text="`${p.codigo} - ${p.nombre_completo}`"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-blue-50/50 border border-blue-100 rounded-lg cursor-pointer select-none"
                            @click="esPeriodico = !esPeriodico">
                            <div class="pointer-events-none">
                                <p class="text-sm font-bold text-blue-900">Curso periódico</p>
                                <p class="text-[11px] text-blue-600/80 font-medium">Se repetirá automáticamente según la frecuencia</p>
                            </div>
                            <input id="nediPeriodico" type="checkbox" x-model="esPeriodico" @click.stop class="form-switch scale-110 cursor-pointer">
                        </div>
                        <div x-show="esPeriodico" x-transition>
                            <label for="nediFrecuencia" class="text-sm font-medium text-gray-800 inline-block mb-1">Frecuencia <span class="text-red-500">*</span></label>
                            <select id="nediFrecuencia" x-model="frecuencia"
                                class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                <option value="0">Seleccione la frecuencia…</option>
                                <option value="1">Mensual</option>
                                <option value="2">Bimestral</option>
                                <option value="3">Trimestral</option>
                                <option value="4">Cuatrimestral</option>
                                <option value="6">Semestral</option>
                                <option value="12">Anual</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div x-show="!esPCU">
                                <label for="nediSistema" class="text-sm font-medium text-gray-800 inline-block mb-1">Sistema de gestión <span class="text-red-500">*</span></label>
                                <select id="nediSistema" x-model="areaConocimiento"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione sistema…</option>
                                    <template x-for="o in sistemas" :key="o.codigo">
                                        <option :value="String(o.codigo)" x-text="o.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="nediAreaResp" class="text-sm font-medium text-gray-800 inline-block mb-1">Área responsable <span class="text-red-500">*</span></label>
                                <select id="nediAreaResp" x-model="areaResponsable" :disabled="areaBloqueada"
                                    :class="areaBloqueada ? 'bg-gray-100 cursor-not-allowed text-gray-400' : 'bg-white'"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione área…</option>
                                    <template x-for="o in areasResponsables" :key="o.codArea">
                                        <option :value="String(o.codArea)" x-text="o.Area || o.nombre || o.descripcion"></option>
                                    </template>
                                </select>
                                <p x-show="areaBloqueada" class="text-[11px] text-default-400 font-medium mt-1">Primero selecciona un sistema de gestión.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="nediSucursal" class="text-sm font-medium text-gray-800 inline-block mb-1">Sucursal <span class="text-red-500">*</span></label>
                                <select id="nediSucursal" x-model="sucursal"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione sucursal…</option>
                                    <option value="0">Todas las sucursales</option>
                                    <template x-for="s in sucursalesDisponibles" :key="s.codigo">
                                        <option :value="s.codigo" x-text="s.nombre"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="nediDirigido" class="text-sm font-medium text-gray-800 inline-block mb-1">Dirigido a <span class="text-red-500">*</span></label>
                                <select id="nediDirigido" x-model="dirigido"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione a quién va dirigido…</option>
                                    <template x-for="o in opcionesDirigidoEdit" :key="o.codigo">
                                        <option :value="String(o.codigo)" x-text="o.texto"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div x-show="advertenciaAudiencia" x-transition
                            class="flex items-start gap-2.5 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <i class="ti ti-alert-triangle text-amber-500 text-lg shrink-0 mt-0.5"></i>
                            <p class="text-[12px] text-amber-800 font-medium" x-text="advertenciaAudiencia"></p>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end items-center gap-2 px-6 py-4 border-t border-default-100 bg-default-50/50 shrink-0">
                    <button type="button" @click="cerrar()"
                        class="h-9 px-4 inline-flex items-center bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition">
                        Cancelar
                    </button>
                    <span :title="tituloBoton">
                        <button type="button" @click="guardar" :disabled="!formularioCompleto || !hayCambios || guardando"
                            class="h-9 px-5 inline-flex items-center gap-1.5 bg-amber-500 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-amber-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="(!formularioCompleto || !hayCambios) ? 'pointer-events-none' : ''">
                            <i class="ti ti-pencil"></i>
                            <span x-text="guardando ? 'Guardando…' : 'Guardar cambios'"></span>
                        </button>
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>
