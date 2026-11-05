{{-- Modal Nuevo: Registrar curso con wizard (Paso 1 Datos / Paso 2 Planificación).
      Usa x-data="formCursoNew()" de gestion_cursos_new_modal.js. IDs con prefijo nreg para no colisionar. --}}
<div id="modalRegistroCursoNew" class="contents">
    <div x-data="formCursoNew()" id="formCursoNewRoot" x-init="init()"
        @open-modal-registro-new.window="abrir()" @close-modal-registro-new.window="showModal = false">

        <div x-show="showModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="background: rgba(36,39,70,0.45);">

            <div class="relative flex flex-col shadow-2xl rounded-2xl overflow-hidden w-full max-w-5xl border border-default-200 bg-white max-h-[90vh]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                {{-- Loader total: ocupa todo el modal hasta terminar la precarga --}}
                <div x-show="cargandoCombos" x-transition.opacity
                    class="absolute inset-0 z-30 bg-white flex flex-col items-center justify-center gap-4 p-8 text-center">
                    <span class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></span>
                    <p class="text-base font-bold text-default-900 min-w-[240px]" x-text="textoCarga">Cargando información...</p>
                    <p class="text-xs text-default-500 font-medium max-w-sm">Estamos trayendo sucursales, áreas responsables, planes de capacitación y responsables. En un momento continuamos.</p>
                </div>

                {{-- Header + stepper --}}
                <div class="px-6 pt-5 pb-4 border-b border-default-100 bg-gradient-to-r from-white to-default-50/40 shrink-0">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-default-900">Registrar curso</h3>
                            <p class="text-sm text-default-500 mt-1">Completa la información en 2 pasos. Los campos con <span class="text-red-500 font-bold">*</span> son obligatorios.</p>
                        </div>
                        <button type="button" @click="cerrar()"
                            class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 transition-colors">
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 mt-4">
                        <template x-for="n in [1,2]" :key="n">
                            <div class="flex items-center gap-2 flex-1 last:flex-none">
                                <button type="button" @click="irPaso(n)"
                                    class="flex items-center gap-2 shrink-0"
                                    :class="paso === n ? 'text-primary' : (n < paso ? 'text-emerald-600' : 'text-default-400')">
                                    <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-xs font-bold border"
                                        :class="paso === n ? 'bg-primary text-white border-primary' : (n < paso ? 'bg-emerald-500 text-white border-emerald-500' : 'bg-white text-default-400 border-default-200')"
                                        x-text="n < paso ? '✓' : n"></span>
                                    <span class="text-xs font-bold hidden sm:inline"
                                        x-text="n === 1 ? 'Datos' : 'Planificación'"></span>
                                </button>
                                <div x-show="n < 2" class="h-0.5 flex-1 rounded-full" :class="n < paso ? 'bg-emerald-400' : 'bg-default-100'"></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="combosError" class="mt-3 text-xs font-semibold text-red-600" x-text="combosError"></div>
                </div>

                {{-- Body --}}
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white">

                    {{-- PASO 1 --}}
                    <div x-show="paso === 1" class="flex flex-col gap-4">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold inline-flex items-center justify-center">1</span>
                            <h4 class="text-sm font-bold text-default-900">Información del curso</h4>
                        </div>
                        <div>
                            <label for="nregNombre" class="text-sm font-medium text-gray-800 inline-block mb-1">Nombre del curso <span class="text-red-500">*</span></label>
                            <input id="nregNombre" type="text" x-model="nombre" placeholder="Ej. Inducción en seguridad y salud en el trabajo"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/50 outline-none">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-800 inline-block mb-1">Descripción</label>
                            <textarea rows="2" x-model="descripcion" placeholder="Contenido, objetivos y temática del curso…"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50 outline-none"></textarea>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="nregCategoria" class="text-sm font-medium text-gray-800 inline-block mb-1">Tipo de curso <span class="text-red-500">*</span></label>
                                <select id="nregCategoria" x-model="categoria"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/50 outline-none">
                                    <option value="">Seleccione el tipo…</option>
                                    <option value="1">Inducción</option>
                                    <option value="2">Charla</option>
                                    <option value="3">Capacitación</option>
                                    <option value="4">Entrenamiento</option>
                                    <option value="5">Simulacros de emergencia</option>
                                </select>
                            </div>
                            <div>
                                <label for="nregResponsable" class="text-sm font-medium text-gray-800 inline-block mb-1">Responsable <span class="text-red-500">*</span></label>
                                <select id="nregResponsable" x-model="codResponsable"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/50 outline-none">
                                    <option value="">Seleccione responsable…</option>
                                    <template x-for="p in personalJefaturas" :key="p.codigo">
                                        <option :value="p.codigo" x-text="`${p.codigo} - ${p.nombre_completo}`"></option>
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
                            <input id="nregPeriodico" type="checkbox" x-model="esPeriodico" @click.stop class="form-switch scale-110 cursor-pointer">
                        </div>
                        <div x-show="esPeriodico" x-transition>
                            <label for="nregFrecuencia" class="text-sm font-medium text-gray-800 inline-block mb-1">Frecuencia <span class="text-red-500">*</span></label>
                            <select id="nregFrecuencia" x-model="frecuencia"
                                class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary/50 outline-none">
                                <option value="0">Seleccione la frecuencia…</option>
                                <option value="1">Mensual</option>
                                <option value="2">Bimestral</option>
                                <option value="3">Trimestral</option>
                                <option value="4">Cuatrimestral</option>
                                <option value="6">Semestral</option>
                                <option value="12">Anual</option>
                            </select>
                            <p x-show="!frecuencia" class="text-[11px] text-amber-600 font-medium mt-1">Elige una frecuencia para continuar al siguiente paso.</p>
                        </div>
                    </div>

                    {{-- PASO 2 --}}
                    <div x-show="paso === 2" class="flex flex-col gap-4" x-cloak>
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-bold inline-flex items-center justify-center">2</span>
                            <h4 class="text-sm font-bold text-default-900">Planificación: a quién y dónde aplica</h4>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Plan de capacitación <span class="text-red-500">*</span></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="t in planes" :key="t.codigo">
                                    <label class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm cursor-pointer transition-all"
                                        :class="String(tipoCurso) === String(t.codigo) ? 'border-primary ring-1 ring-primary/30 bg-primary/5' : 'hover:bg-slate-50'">
                                        <input type="radio" :value="t.codigo" x-model="tipoCurso"
                                            @change="checkEsPACByText(t.descripcion)" class="w-4 h-4 text-primary focus:ring-primary border-gray-300">
                                        <span class="text-sm font-medium text-gray-700" x-text="t.descripcion"></span>
                                    </label>
                                </template>
                            </div>
                            <p x-show="!planes.length && !cargandoCombos" class="text-xs text-default-400 mt-2">Sin planes cargados. Cierra y reabre el modal.</p>
                        </div>
                        {{-- Clientes PCA --}}
                        <div x-show="tipoCurso == '6'" x-transition class="bg-blue-50/50 border border-blue-100 rounded-lg p-4">
                            <p class="text-sm font-bold text-blue-800 mb-1">Clientes <span class="text-red-500">*</span></p>
                            <p class="text-xs text-blue-500 mb-3">Se matriculará al personal asignado a este cliente.</p>
                            <input type="text" x-model="busquedaCliente" placeholder="Buscar cliente…" class="w-full border border-blue-200 rounded-md px-3 py-2 text-sm mb-2 outline-none focus:ring-2 focus:ring-blue-200">
                            <div class="border border-blue-100 rounded-md p-2 overflow-y-auto bg-white custom-scrollbar" style="max-height: 150px;">
                                <template x-for="c in clientesFiltrados" :key="c.codigo">
                                    <label class="flex items-center gap-2 p-2 rounded-md hover:bg-slate-50 cursor-pointer">
                                        <input type="radio" :value="c.codigo" x-model="clienteSeleccionado" class="w-4 h-4 text-blue-600">
                                        <span class="text-sm text-gray-700"><span class="text-xs bg-gray-200 px-1.5 py-0.5 rounded mr-1" x-text="c.codigo"></span><span x-text="c.descripcion"></span></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div x-show="tipoCurso != '6'">
                                <label for="nregSistema" class="text-sm font-medium text-gray-800 inline-block mb-1">Sistema de gestión <span class="text-red-500">*</span></label>
                                <select id="nregSistema" x-model="areaConocimiento" @change="area = areaConocimiento; cargarAreasResponsables(areaConocimiento)"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione sistema…</option>
                                    <template x-for="o in sistemas" :key="o.codigo">
                                        <option :value="o.codigo" x-text="o.descripcion"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="nregAreaResp" class="text-sm font-medium text-gray-800 inline-block mb-1">Área responsable <span class="text-red-500">*</span></label>
                                <select id="nregAreaResp" x-model="areaResponsable"
                                    :disabled="tipoCurso != '6' && !areaConocimiento"
                                    :class="(tipoCurso != '6' && !areaConocimiento) ? 'bg-gray-100 cursor-not-allowed text-gray-400' : 'bg-white'"
                                    @change="codMoodleArea = (areasResponsables.find(a => String(a.codArea) === String(areaResponsable)) || {}).codModdle || ''"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione área…</option>
                                    <template x-for="o in areasResponsables" :key="o.codArea">
                                        <option :value="o.codArea" x-text="o.Area || o.nombre || o.descripcion"></option>
                                    </template>
                                </select>
                                <p x-show="tipoCurso != '6' && !areaConocimiento" class="text-[11px] text-default-400 font-medium mt-1">Primero selecciona un sistema de gestión para desbloquear este campo.</p>
                            </div>
                            <div>
                                <label for="nregSucursal" class="text-sm font-medium text-gray-800 inline-block mb-1">Sucursal <span class="text-red-500">*</span></label>
                                <select id="nregSucursal" x-model="sucursal"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione sucursal…</option>
                                    <option value="TODAS">Todas las sucursales</option>
                                    <template x-for="o in sucursalesOpciones" :key="o.Codigo">
                                        <option :value="o.Codigo" x-text="o.Sucursal"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label for="nregDirigido" class="text-sm font-medium text-gray-800 inline-block mb-1">Dirigido a <span class="text-red-500">*</span></label>
                                <select id="nregDirigido" x-model="dirigido"
                                    class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                    <option value="">Seleccione…</option>
                                    <template x-for="o in opcionesDirigido" :key="o.codigo">
                                        <option :value="o.codigo" x-text="o.texto"></option>
                                    </template>
                                </select>
                                <p class="text-[11px] text-default-400 mt-1">Las opciones cambian según el plan elegido.</p>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="flex justify-between items-center gap-3 px-6 py-4 border-t border-default-100 bg-default-50/50 shrink-0">
                    <button type="button" @click="paso > 1 ? anterior() : cerrar()"
                        class="h-9 px-4 inline-flex items-center gap-1.5 bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition">
                        <span x-text="paso > 1 ? '← Atrás' : 'Cancelar'"></span>
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-default-400 font-semibold hidden sm:inline" x-text="`Paso ${paso} de 2`"></span>
                        <button x-show="paso < 2" type="button" @click="siguiente()"
                            :disabled="paso === 1 && !puedeAvanzarPaso1"
                            class="h-9 px-5 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Siguiente →
                        </button>
                        <span x-show="paso === 2" :title="!formularioCompleto ? tituloCamposFaltantes : ''">
                            <button type="button" @click="registrar" :disabled="!formularioCompleto"
                                class="h-9 px-5 inline-flex items-center gap-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="!formularioCompleto ? 'pointer-events-none' : ''">
                                <i class="ti ti-device-floppy"></i> Registrar curso
                            </button>
                        </span>
                    </div>
                </div>

                {{-- Preview imagen --}}
                <div x-show="modalPreviewAbierto" x-cloak class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-900/70 p-4" @click.self="modalPreviewAbierto = false">
                    <div class="bg-white rounded-2xl overflow-hidden max-w-3xl w-full">
                        <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-200">
                            <span class="text-sm font-bold text-slate-700" x-text="modalPreviewTitulo"></span>
                            <button type="button" @click="modalPreviewAbierto = false" class="w-8 h-8 rounded-full bg-slate-200 hover:bg-slate-300 inline-flex items-center justify-center"><i class="bx bx-x text-lg"></i></button>
                        </div>
                        <div class="p-2 flex items-center justify-center bg-slate-900/5" style="max-height: 70vh;">
                            <img :src="modalPreviewSrc" class="max-w-full max-h-[65vh] object-contain rounded-lg">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal revisión Word (nuevo, independiente del antiguo) --}}
        <div x-data="modalExamenWordNew()" x-init="init()" @abrir-modal-word-new.window="abrirModalWord($event.detail.preguntas, $event.detail.nombreArc)" style="display:contents">
            <div x-show="mostrarModal" x-cloak class="fixed inset-0 z-[140] flex items-center justify-center p-4" style="background:rgba(15,23,42,0.85); backdrop-filter: blur(8px);">
                <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[85vh] flex flex-col overflow-hidden">
                    <div class="px-5 py-4 bg-slate-900 flex items-center justify-between shrink-0">
                        <div>
                            <h4 class="text-white text-sm font-bold">Revisión de examen — <span x-text="archivoNombre"></span></h4>
                            <p class="text-slate-400 text-xs mt-0.5"><span x-text="preguntas.length"></span> preguntas extraídas del Word</p>
                        </div>
                        <button type="button" @click="mostrarModal=false" class="w-8 h-8 rounded-full bg-white/10 text-slate-300 inline-flex items-center justify-center hover:bg-white/20"><i class="bx bx-x text-lg"></i></button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4 bg-slate-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            <template x-for="(p, i) in preguntas" :key="i">
                                <div class="bg-white border border-slate-200 rounded-xl p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-black text-slate-400">PREGUNTA <span x-text="i+1"></span></span>
                                        <select x-model="p.tipo" class="text-xs font-bold border border-slate-200 rounded-md px-2 py-1">
                                            <option value="A">Básica</option>
                                            <option value="B">Complementaria</option>
                                        </select>
                                    </div>
                                    <p class="text-[13px] font-bold text-slate-800 mb-2" x-text="p.texto"></p>
                                    <template x-for="(opt, oi) in p.opciones" :key="oi">
                                        <label class="flex items-center gap-2 p-1.5 rounded-lg border mb-1 cursor-pointer text-xs"
                                            :class="p.respuesta_correcta == chr(65+oi) ? 'border-emerald-300 bg-emerald-50' : 'border-slate-100 bg-slate-50'">
                                            <input type="radio" :name="'new_resp_'+i" :value="chr(65+oi)" x-model="p.respuesta_correcta" class="accent-emerald-600">
                                            <span class="text-slate-600" x-text="opt"></span>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-white border-t border-slate-200 flex justify-end gap-2 shrink-0">
                        <button type="button" @click="undoChanges()" class="h-9 px-4 text-xs font-bold text-amber-600 border border-amber-300 rounded-lg hover:bg-amber-50">Deshacer cambios</button>
                        <button type="button" @click="mostrarModal=false" class="h-9 px-5 text-xs font-bold text-white bg-primary rounded-lg hover:bg-primary/90">Confirmar cambios</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
