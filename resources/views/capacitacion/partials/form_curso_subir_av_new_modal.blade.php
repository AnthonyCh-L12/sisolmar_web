{{-- Modal Nuevo: Subir curso al AV (Recursos Visuales + Evaluación de Curso).
      Usa x-data="formSubirAvNew()" de gestion_cursos_new_modal.js. IDs con prefijo sav para no colisionar. --}}
<div id="modalSubirAvNew" class="contents">
    <div x-data="formSubirAvNew()" x-init="init()" @close-modal-subir-av-new.window="cerrar()">

        <div x-show="showModal" x-cloak class="fixed inset-0 z-[125] flex items-center justify-center p-4"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            style="background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px);"
            @click.self="cerrar()" @keydown.escape.window="showModal && cerrar()">

            <div class="relative flex flex-col shadow-2xl rounded-2xl overflow-hidden w-full max-w-5xl border border-default-200 bg-white max-h-[92vh]"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                {{-- Loader --}}
                <div x-show="cargando" x-transition.opacity
                    class="absolute inset-0 z-30 bg-white/95 flex flex-col items-center justify-center gap-4 p-8 text-center">
                    <span class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></span>
                    <p class="text-base font-bold text-default-900">Cargando información del curso...</p>
                    <p class="text-xs text-default-500 font-medium max-w-sm">Estamos consultando los datos del curso seleccionado. En un momento continuamos.</p>
                </div>

                {{-- Header --}}
                <div class="flex justify-between items-start gap-4 px-6 py-5 border-b border-default-100 shrink-0 bg-gradient-to-r from-emerald-50/60 via-white to-white">
                    <div class="flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 inline-flex items-center justify-center shrink-0">
                            <i class="ti ti-cloud-upload text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-default-900">Subir curso al AV</h3>
                            <p class="text-sm text-default-500 mt-0.5">
                                <span x-text="cursoNombre || 'Selecciona los recursos y la evaluación del curso'"></span>
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="cerrar()"
                        class="flex-shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 transition-colors">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-white">

                    <div x-show="error" x-cloak
                        class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <i class="ti ti-alert-circle text-lg shrink-0"></i>
                        <span x-text="error"></span>
                    </div>

                    {{-- Resumen del curso --}}
                    <div class="rounded-xl border border-default-200/70 bg-default-50/60 px-4 py-3 mb-6">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-widest text-default-400">Código</span>
                                <span class="text-sm font-semibold text-default-900" x-text="cursoCodigo || '—'"></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-widest text-default-400">Plan</span>
                                <span class="text-sm font-semibold text-default-900" x-text="planNombre || '—'"></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-widest text-default-400">Área</span>
                                <span class="text-sm font-semibold text-default-900" x-text="areaNombre || '—'"></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-widest text-default-400">Responsable</span>
                                <span class="text-sm font-semibold text-default-900" x-text="responsableNombre || '—'"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Recursos Visuales --}}
                    <section class="mb-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-7 h-7 rounded-lg bg-primary/10 text-primary inline-flex items-center justify-center">
                                <i class="ti ti-photo text-sm"></i>
                            </span>
                            <div>
                                <h4 class="text-sm font-bold text-default-900">Recursos visuales</h4>
                                <p class="text-[11px] text-default-400 font-medium">Imágenes opcionales que se mostrarán en el aula virtual.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- Portada --}}
                            <div class="rounded-xl border border-default-200 bg-white p-3 shadow-sm">
                                <div class="relative w-full aspect-[4/3] rounded-lg overflow-hidden bg-default-50 border-2 border-dashed border-default-200 flex items-center justify-center group cursor-pointer transition-colors"
                                    :class="imagePreviewPortada ? 'border-solid border-primary/40' : 'hover:border-primary/40 hover:bg-primary/[0.02]'"
                                    @click="!imagePreviewPortada && $refs.inputImagePortada.click()">
                                    <template x-if="imagePreviewPortada">
                                        <div class="w-full h-full relative">
                                            <img :src="imagePreviewPortada" class="w-full h-full object-cover" alt="Portada">
                                            <div class="absolute inset-0 bg-slate-900/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                <button type="button" title="Ver portada"
                                                    @click.stop="previewImage(imagePreviewPortada, 'Portada del curso')"
                                                    class="w-9 h-9 rounded-full bg-white/95 text-slate-700 inline-flex items-center justify-center shadow hover:scale-105 transition-transform">
                                                    <i class="ti ti-eye text-base"></i>
                                                </button>
                                                <button type="button" title="Quitar portada"
                                                    @click.stop="imagePreviewPortada = null; imageFilePortada = null; $refs.inputImagePortada.value = ''"
                                                    class="w-9 h-9 rounded-full bg-red-500 text-white inline-flex items-center justify-center shadow hover:scale-105 transition-transform">
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!imagePreviewPortada">
                                        <div class="flex flex-col items-center text-center px-4 py-6">
                                            <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary inline-flex items-center justify-center mb-2.5">
                                                <i class="ti ti-photo-plus text-xl"></i>
                                            </div>
                                            <span class="text-xs font-bold text-default-700">Subir portada</span>
                                            <span class="text-[10px] text-default-400 font-medium mt-0.5">1200x300 px &bull; JPG, PNG</span>
                                        </div>
                                    </template>
                                </div>
                                <input type="file" id="savInputImagePortada" x-ref="inputImagePortada" class="hidden"
                                    accept=".jpg,.jpeg,.png" @change="handleImageUpload($event, 'portada')">
                            </div>

                            {{-- Afiche --}}
                            <div class="rounded-xl border border-default-200 bg-white p-3 shadow-sm">
                                <div class="relative w-full aspect-[4/3] rounded-lg overflow-hidden bg-default-50 border-2 border-dashed border-default-200 flex items-center justify-center group cursor-pointer transition-colors"
                                    :class="imagePreviewAfiche ? 'border-solid border-primary/40' : 'hover:border-primary/40 hover:bg-primary/[0.02]'"
                                    @click="!imagePreviewAfiche && $refs.inputImageAfiche.click()">
                                    <template x-if="imagePreviewAfiche">
                                        <div class="w-full h-full relative">
                                            <img :src="imagePreviewAfiche" class="w-full h-full object-cover" alt="Afiche">
                                            <div class="absolute inset-0 bg-slate-900/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                <button type="button" title="Ver afiche"
                                                    @click.stop="previewImage(imagePreviewAfiche, 'Afiche informativo')"
                                                    class="w-9 h-9 rounded-full bg-white/95 text-slate-700 inline-flex items-center justify-center shadow hover:scale-105 transition-transform">
                                                    <i class="ti ti-eye text-base"></i>
                                                </button>
                                                <button type="button" title="Quitar afiche"
                                                    @click.stop="imagePreviewAfiche = null; imageFileAfiche = null; $refs.inputImageAfiche.value = ''"
                                                    class="w-9 h-9 rounded-full bg-red-500 text-white inline-flex items-center justify-center shadow hover:scale-105 transition-transform">
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!imagePreviewAfiche">
                                        <div class="flex flex-col items-center text-center px-4 py-6">
                                            <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary inline-flex items-center justify-center mb-2.5">
                                                <i class="ti ti-photo-plus text-xl"></i>
                                            </div>
                                            <span class="text-xs font-bold text-default-700">Subir afiche informativo</span>
                                            <span class="text-[10px] text-default-400 font-medium mt-0.5">Máx. 1.9 MB &bull; JPG, PNG</span>
                                        </div>
                                    </template>
                                </div>
                                <input type="file" id="savInputImageAfiche" x-ref="inputImageAfiche" class="hidden"
                                    accept=".jpg,.jpeg,.png" @change="handleImageUpload($event, 'afiche')">
                            </div>
                        </div>
                    </section>

                    {{-- Evaluación de Curso --}}
                    <section>
                        <div class="flex items-center justify-between gap-4 rounded-xl border px-4 py-3 transition-colors cursor-pointer select-none"
                            :class="aplicaEvaluacion ? 'border-indigo-200 bg-indigo-50/70' : 'border-default-200 bg-default-50/50 hover:bg-default-50'"
                            @click="aplicaEvaluacion = !aplicaEvaluacion">
                            <div class="flex items-center gap-3 pointer-events-none">
                                <span class="w-9 h-9 rounded-xl inline-flex items-center justify-center"
                                    :class="aplicaEvaluacion ? 'bg-indigo-500/15 text-indigo-600' : 'bg-default-200/60 text-default-400'">
                                    <i class="ti ti-clipboard-check text-lg"></i>
                                </span>
                                <div>
                                    <p class="text-sm font-bold text-default-900">Evaluación de curso</p>
                                    <p class="text-[11px] font-medium" :class="aplicaEvaluacion ? 'text-indigo-600/80' : 'text-default-400'"
                                        x-text="aplicaEvaluacion ? 'Se requerirá un examen obligatorio para aprobar' : 'El curso no tendrá examen obligatorio'"></p>
                                </div>
                            </div>
                            <div class="relative" @click.stop>
                                <input class="form-switch cursor-pointer scale-110" type="checkbox" role="switch"
                                    id="savChkAplicaEvaluacion" x-model="aplicaEvaluacion">
                            </div>
                        </div>

                        {{-- Sin evaluación --}}
                        <div x-show="!aplicaEvaluacion" x-cloak x-transition
                            class="mt-3 flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-default-200 bg-default-50/40 px-6 py-8 text-center">
                            <i class="ti ti-file-off text-default-300" style="font-size: 2.75rem;"></i>
                            <p class="mt-2 text-sm font-bold text-default-500">Sin evaluación requerida</p>
                            <p class="mt-1 text-xs text-default-400 max-w-md">Activa el interruptor si deseas configurar un examen obligatorio para aprobar este curso.</p>
                        </div>

                        {{-- Configuración del examen --}}
                        <div x-show="aplicaEvaluacion" x-cloak x-transition
                            class="mt-3 rounded-xl border border-default-200 bg-default-50/40 p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div>
                                    <label for="savLimiteTiempo" class="block text-[11px] font-bold uppercase tracking-wider text-default-500 mb-1">Límite de tiempo (min)</label>
                                    <input id="savLimiteTiempo" type="number" min="0" x-model="limiteTiempo" placeholder="Ej. 30"
                                        class="w-full h-10 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                                <div>
                                    <label for="savNotaMinima" class="block text-[11px] font-bold uppercase tracking-wider text-default-500 mb-1">Nota mínima</label>
                                    <input id="savNotaMinima" type="number" min="0" x-model="nota" placeholder="Ej. 14"
                                        class="w-full h-10 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                                <div>
                                    <label for="savIntentos" class="block text-[11px] font-bold uppercase tracking-wider text-default-500 mb-1">Número de intentos</label>
                                    <input id="savIntentos" type="number" min="1" x-model="intentos" placeholder="Ej. 1"
                                        class="w-full h-10 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                                <div>
                                    <label for="savCantidadPreguntas" class="block text-[11px] font-bold uppercase tracking-wider text-default-500 mb-1">Cantidad de preguntas</label>
                                    <input id="savCantidadPreguntas" type="number" min="1" x-model="cantidadPreguntas" placeholder="Ej. 20"
                                        class="w-full h-10 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                                <div>
                                    <label for="savPreguntasBalotario" class="block text-[11px] font-bold uppercase tracking-wider text-default-500 mb-1">Preguntas en el balotario</label>
                                    <input id="savPreguntasBalotario" type="number" min="1" x-model="preguntasBalotario" placeholder="Ej. 40"
                                        :readonly="preguntasExamen.length > 0"
                                        :class="preguntasExamen.length > 0 ? 'bg-default-100 cursor-not-allowed' : 'bg-white'"
                                        class="w-full h-10 px-3 text-sm border border-default-200 rounded-lg text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                                </div>
                            </div>

                            {{-- Banco de preguntas --}}
                            <div class="mt-4 rounded-xl border border-dashed border-blue-300 bg-blue-50/40 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <i class="ti ti-file-word text-blue-600 text-lg"></i>
                                    <div>
                                        <p class="text-[11px] font-black uppercase tracking-widest text-primary">Banco de preguntas</p>
                                        <p class="text-[11px] text-default-400 font-medium">Selecciona un archivo Word (.docx) con las preguntas y respuestas.</p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <div class="w-full bg-white border border-blue-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 shadow-sm">
                                        <span class="text-xs font-medium truncate"
                                            :class="!archivoWordNombre ? 'italic text-blue-400' : 'text-blue-700'"
                                            x-text="archivoWordNombre || 'Ningún archivo seleccionado'"></span>
                                        <button x-show="archivoWordNombre" type="button"
                                            @click="archivoWord = null; archivoWordNombre = ''; preguntasExamen = []; $refs.inputWord.value = ''"
                                            class="text-red-400 hover:text-red-600 transition-colors shrink-0"
                                            title="Quitar archivo">
                                            <i class="ti ti-trash text-lg"></i>
                                        </button>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="$refs.inputWord.click()"
                                            class="h-10 px-4 inline-flex items-center justify-center gap-2 bg-white text-blue-600 border border-blue-200 rounded-lg shadow-sm hover:bg-blue-50 transition text-xs font-bold">
                                            <i class="ti ti-file-search text-base"></i>
                                            <span x-text="archivoWordNombre ? 'Cambiar archivo' : 'Seleccionar archivo'"></span>
                                        </button>

                                        <button x-show="archivoWordNombre && !preguntasExamen.length" type="button"
                                            @click="analizarExamenWord()" :disabled="cargandoWord"
                                            class="h-10 px-4 inline-flex items-center justify-center gap-2 bg-blue-600 text-white rounded-lg shadow-sm hover:bg-blue-700 transition text-xs font-bold disabled:opacity-70">
                                            <i class="ti text-base" :class="cargandoWord ? 'ti-loader-2 animate-spin' : 'ti-file-analytics'"></i>
                                            <span x-text="cargandoWord ? 'Analizando...' : 'Analizar Word'"></span>
                                        </button>

                                        <button x-show="preguntasExamen.length > 0" type="button"
                                            @click="verVistaPrevia()"
                                            class="h-10 px-4 inline-flex items-center justify-center gap-2 bg-emerald-500 text-white rounded-lg shadow-sm hover:bg-emerald-600 transition text-xs font-bold">
                                            <i class="ti ti-eye text-base"></i>
                                            Ver preguntas (<span x-text="preguntasExamen.length"></span>)
                                        </button>

                                        <input type="file" id="savInputWordExamen" x-ref="inputWord" class="hidden"
                                            accept=".docx"
                                            @change="archivoWord = $event.target.files[0] || null; archivoWordNombre = archivoWord ? archivoWord.name : ''; preguntasExamen = []; $event.target.value = '';">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>

                {{-- Footer --}}
                <div class="flex justify-end items-center gap-3 px-6 py-4 border-t border-default-100 shrink-0 bg-default-50/50">
                    <button type="button" @click="cerrar()"
                        class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition">
                        Cancelar
                    </button>
                    <button type="button" disabled title="La integración con el AV se implementará en una siguiente fase"
                        class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-emerald-500 text-white text-sm font-medium rounded-lg shadow-sm opacity-50 cursor-not-allowed">
                        <i class="ti ti-cloud-upload"></i> Subir al AV
                    </button>
                </div>
            </div>
        </div>

        {{-- Preview imagen --}}
        <div x-show="modalPreviewAbierto" x-cloak @click.self="modalPreviewAbierto = false"
            class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl overflow-hidden max-w-3xl w-full">
                <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-200">
                    <span class="text-sm font-bold text-slate-700" x-text="modalPreviewTitulo"></span>
                    <button type="button" @click="modalPreviewAbierto = false"
                        class="w-8 h-8 rounded-full bg-slate-200 hover:bg-slate-300 inline-flex items-center justify-center">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>
                <div class="p-2 flex items-center justify-center bg-slate-900/5" style="max-height: 70vh;">
                    <img :src="modalPreviewSrc" class="max-w-full max-h-[65vh] object-contain rounded-lg" alt="Vista previa">
                </div>
            </div>
        </div>
    </div>
</div>
