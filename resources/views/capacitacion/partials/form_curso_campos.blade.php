{{-- Formulario de curso: campos compartidos por el modal de Registro (prefijo 'reg') y el de Actualizacion (prefijo 'edi'). Todos los id= y for= llevan el prefijo para evitar colisiones de DOM. --}}
                            <input type="hidden" name="targetGroupHidden" x-model="targetGroup">
                            <input type="hidden" name="codGestionEditar" x-model="codigo" id="{{ $prefijo }}codGestionEditar">
                            <input type="hidden" id="{{ $prefijo }}slcArea" x-model="area">

                            <div class="grid grid-cols-1 gap-6 lg:gap-8 w-full mt-4" :class="codigo ? 'lg:grid-cols-2' : 'lg:grid-cols-3'">
                                <!-- Columna 1: Datos del curso -->
                                <div>
                                    <div class="w-full grid gap-4 grid-cols-1 pb-6">

                                        <!-- Nombre del curso -->
                                        <div>
                                            <label for="{{ $prefijo }}txtNombreCurso"
                                                class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                Nombre del curso <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" id="{{ $prefijo }}txtNombreCurso"
                                                placeholder="Escriba el nombre del curso..."
                                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                                                x-model="nombre" />
                                        </div>

                                        <!-- Descripción del curso -->
                                        <div>
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-1">Descripción
                                                del curso</label>
                                            <textarea rows="2" x-model="descripcion"
                                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm resize-none focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-shadow"
                                                placeholder="Describa el contenido, objetivos y temática del curso..."></textarea>
                                        </div>

                                        <!-- Tipo de curso -->
                                        <div>
                                            <label for="{{ $prefijo }}slcTipoCursoCategoria"
                                                class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                Tipo de curso <span class="text-danger">*</span>
                                            </label>
                                            <select id="{{ $prefijo }}slcTipoCursoCategoria" x-model="categoria"
                                                :disabled="tieneVigente"
                                                :class="tieneVigente ? 'w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 cursor-not-allowed' : 'w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white'">
                                                <option value="">Seleccione el tipo de curso</option>
                                                <option value="1">Inducción</option>
                                                <option value="2">Charla</option>
                                                <option value="3">Capacitación</option>
                                                <option value="4">Entrenamiento</option>
                                                <option value="5">Simulacros de emergencia</option>
                                            </select>
                                        </div>

                                        <!-- Responsable del curso -->
                                        <div>
                                            <label class="text-gray-800 text-sm font-medium inline-block mb-1.5">
                                                Responsable del curso <span class="text-danger">*</span>
                                            </label>

                                            <select x-model="codResponsable"
                                                @change="nombreResponsable = (personalJefaturas.find(p => String(p.codigo) === String(codResponsable)) || {}).nombre_completo || ''"
                                                class="w-full bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">Seleccione responsable...</option>
                                                <template x-for="p in personalJefaturas" :key="p.codigo">
                                                    <option :value="p.codigo" x-text="`${p.codigo} - ${p.nombre_completo}`"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <!-- Curso Periódico -->
                                        <div x-show="!tieneVigente" class="flex items-center justify-between p-3 bg-blue-50/50 border border-blue-100 rounded-lg cursor-pointer select-none"
                                            @click="esPeriodico = !esPeriodico">
                                            <div class="flex flex-col pointer-events-none">
                                                <span class="text-sm font-bold text-blue-900">Curso Periódico</span>
                                                <span class="text-[11px] text-blue-600/80 font-medium mt-0.5">El curso se repetirá automáticamente según la frecuencia</span>
                                            </div>
                                            <div class="relative" @click.stop>
                                                <input class="form-switch cursor-pointer scale-110" type="checkbox"
                                                    role="switch" id="{{ $prefijo }}chkEsPeriodico" x-model="esPeriodico">
                                            </div>
                                        </div>

                                        <!-- Frecuencia del curso -->
                                        <div x-show="!esDemanda && esPeriodico" x-transition>
                                            <label for="{{ $prefijo }}slcFrecuencia"
                                                class="text-gray-800 text-sm font-medium inline-block mb-1">Frecuencia
                                                del curso</label>
                                            <select id="{{ $prefijo }}slcFrecuencia" x-model="frecuencia"
                                                :disabled="tieneVigente"
                                                :class="tieneVigente ? 'w-full border border-gray-300 rounded px-3 py-2 text-sm bg-gray-100 cursor-not-allowed' : 'w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white'">
                                                <option value="">Seleccione la frecuencia del curso</option>
                                                <option value="MENSUAL">Mensual</option>
                                                <option value="BIMESTRAL">Bimestral</option>
                                                <option value="TRIMESTRAL">Trimestral</option>
                                                <option value="CUATRIMESTRAL">Cuatrimestral</option>
                                                <option value="SEMESTRAL">Semestral</option>
                                                <option value="ANUAL">Anual</option>
                                                <option value="PERSONALIZADO">Fecha personalizada</option>
                                            </select>
                                        </div>

                                        <!-- Recursos Visuales -->
                                        <div x-show="!codigo">
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-1.5 flex items-center gap-1.5">
                                                Recursos Visuales
                                                <span class="text-gray-400 font-normal text-xs">(Opcionales)</span>
                                            </label>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <!-- Slot 1: Portada del Curso -->
                                                <div class="flex flex-col gap-1.5">
                                                    <div class="relative w-full aspect-[4/3] bg-white border-2 border-dashed border-slate-200 rounded-lg overflow-hidden flex items-center justify-center group transition-all duration-200 cursor-pointer"
                                                        :class="imagePreviewPortada ? 'border-solid border-primary/40 bg-primary/[0.02]' : 'hover:border-primary/40 hover:bg-primary/[0.02]'"
                                                        @click="!imagePreviewPortada && $refs.inputImagePortada.click()">

                                                        <template x-if="imagePreviewPortada">
                                                            <div class="w-full h-full relative">
                                                                <img :src="imagePreviewPortada"
                                                                    class="w-full h-full object-cover">
                                                                <div
                                                                    class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center pb-3 gap-2">
                                                                    <button type="button"
                                                                        @click.stop="previewImage(imagePreviewPortada, 'Portada del Curso')"
                                                                        class="btn btn-sm bg-white/90 text-slate-700 rounded-full p-2 hover:bg-white shadow-lg transform hover:scale-110 transition-all">
                                                                        <i class="bx bx-show text-base"></i>
                                                                    </button>
                                                                    <button type="button"
                                                                        @click.stop="imagePreviewPortada = null; imageFilePortada = null; $refs.inputImagePortada.value = ''"
                                                                        class="btn btn-sm bg-red-500 text-white rounded-full p-2 hover:bg-red-600 shadow-lg transform hover:scale-110 transition-all">
                                                                        <i class="bx bx-trash text-base"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <template x-if="!imagePreviewPortada">
                                                            <div class="flex flex-col items-center py-5 px-4">
                                                                <div
                                                                    class="w-11 h-11 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center mb-2.5 group-hover:bg-primary/10 group-hover:border-primary/20 group-hover:text-primary transition-all duration-200">
                                                                    <i
                                                                        class="bx bx-image-alt text-xl text-slate-400 group-hover:text-primary transition-colors"></i>
                                                                </div>
                                                                <span
                                                                    class="text-xs font-semibold text-slate-600 mb-1">Subir
                                                                    Portada</span>
                                                                <span
                                                                    class="text-[10px] text-slate-400 font-medium">1200x300
                                                                    px &bull; JPG, PNG</span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <input type="file" id="{{ $prefijo }}inputImagePortada" x-ref="inputImagePortada"
                                                        class="hidden" accept=".jpg,.jpeg,.png"
                                                        @change="handleImageUpload($event, 'portada')">
                                                </div>

                                                <!-- Slot 2: Afiche Informativo -->
                                                <div class="flex flex-col gap-1.5">
                                                    <div class="relative w-full aspect-[4/3] bg-white border-2 border-dashed border-slate-200 rounded-lg overflow-hidden flex items-center justify-center group transition-all duration-200 cursor-pointer"
                                                        :class="imagePreviewAfiche ? 'border-solid border-primary/40 bg-primary/[0.02]' : 'hover:border-primary/40 hover:bg-primary/[0.02]'"
                                                        @click="!imagePreviewAfiche && $refs.inputImageAfiche.click()">

                                                        <template x-if="imagePreviewAfiche">
                                                            <div class="w-full h-full relative">
                                                                <img :src="imagePreviewAfiche"
                                                                    class="w-full h-full object-cover">
                                                                <div
                                                                    class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center pb-3 gap-2">
                                                                    <button type="button"
                                                                        @click.stop="previewImage(imagePreviewAfiche, 'Afiche Informativo')"
                                                                        class="btn btn-sm bg-white/90 text-slate-700 rounded-full p-2 hover:bg-white shadow-lg transform hover:scale-110 transition-all">
                                                                        <i class="bx bx-show text-base"></i>
                                                                    </button>
                                                                    <button type="button"
                                                                        @click.stop="imagePreviewAfiche = null; imageFileAfiche = null; $refs.inputImageAfiche.value = ''"
                                                                        class="btn btn-sm bg-red-500 text-white rounded-full p-2 hover:bg-red-600 shadow-lg transform hover:scale-110 transition-all">
                                                                        <i class="bx bx-trash text-base"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <template x-if="!imagePreviewAfiche">
                                                            <div class="flex flex-col items-center py-5 px-4">
                                                                <div
                                                                    class="w-11 h-11 rounded-lg border border-slate-200 bg-slate-50 flex items-center justify-center mb-2.5 group-hover:bg-primary/10 group-hover:border-primary/20 group-hover:text-primary transition-all duration-200">
                                                                    <i
                                                                        class="bx bx-image text-xl text-slate-400 group-hover:text-primary transition-colors"></i>
                                                                </div>
                                                                <span
                                                                    class="text-xs font-semibold text-slate-600 mb-1">Subir
                                                                    Afiche Informativo</span>
                                                                <span
                                                                    class="text-[10px] text-slate-400 font-medium">Máx.
                                                                    1.9 MB &bull; JPG, PNG</span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                    <input type="file" id="{{ $prefijo }}inputImageAfiche" x-ref="inputImageAfiche"
                                                        class="hidden" accept=".jpg,.jpeg,.png"
                                                        @change="handleImageUpload($event, 'afiche')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna 2: Planificación -->
                                <div>
                                    <div class="w-full grid gap-4 grid-cols-1 pb-6">

                                        <!-- Plan de capacitación -->
                                        <div>
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-2 text-primary">
                                                Plan de capacitación <span class="text-danger">*</span>
                                            </label>
                                            <div class="flex flex-wrap gap-3"
                                                x-data="{ tipos: window.opcionesTipoCurso || [] }"
                                                @tipo-curso-loaded.window="tipos = $event.detail">
                                                <template x-for="tipo in tipos" :key="tipo.codigo">
                                                    <label
                                                        class="flex items-center space-x-2 bg-white border border-gray-200 rounded-lg px-3 py-2 transition-all shadow-sm"
                                                        :class="{
                                                                 'border-primary ring-1 ring-primary/30 bg-primary/5': tipoCurso == tipo.codigo,
                                                                 'cursor-pointer hover:bg-slate-50': tipo.codigo != '7' && !tieneVigente,
                                                                 'opacity-50 cursor-not-allowed border-dashed': tipo.codigo == '7' || tieneVigente
                                                             }" :title="tieneVigente ? 'No se puede cambiar: el curso tiene una programación vigente' : (tipo.codigo == '7' ? 'Aún en desarrollo' : '')">
                                                        <input type="radio" :value="tipo.codigo" x-model="tipoCurso"
                                                            @change="checkEsPACByText(tipo.descripcion)"
                                                            name="plan_capacitacion"
                                                            class="w-4 h-4 text-primary focus:ring-primary border-gray-300"
                                                            :disabled="tipo.codigo == '7' || tieneVigente">
                                                        <span class="text-sm font-medium text-gray-700"
                                                            :class="{ 'text-gray-400': tipo.codigo == '7' }"
                                                            x-text="tipo.descripcion"></span>
                                                    </label>
                                                </template>
                                            </div>

                                            <!-- Selector PCA (Clientes) -->
                                            <div x-show="tipoCurso == '6'" x-transition
                                                class="mt-4 bg-blue-50/50 border border-blue-100 rounded-lg p-5">
                                                <label
                                                    class="text-blue-800 text-sm font-bold tracking-wide inline-block mb-2">
                                                    <i class="bx bx-buildings mr-1"></i> Seleccionar Clientes <span
                                                        class="text-red-500">*</span>
                                                </label>
                                                <p class="text-xs text-blue-500 mb-3 font-medium">El curso estará dirigido
                                                    al personal asignado a estos clientes.</p>
                                                <div class="mb-3">
                                                    <input type="text" x-model="busquedaCliente"
                                                        placeholder="Buscar cliente..."
                                                        class="w-full border border-blue-200 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-blue-400 focus:border-blue-400 outline-none shadow-sm"
                                                        @keydown.enter.prevent>
                                                </div>
                                                <div class="border border-blue-100 rounded-md p-3 overflow-y-auto bg-white custom-scrollbar shadow-inner"
                                                    style="max-height: 160px;">
                                                    <div class="grid grid-cols-1 gap-2">
                                                        <template x-for="clie in clientesFiltrados" :key="clie.codigo">
                                                            <label
                                                                class="flex items-start space-x-2 cursor-pointer hover:bg-slate-50 p-2 rounded-md border border-transparent hover:border-slate-200 transition-all">
                                                                <input type="radio" :value="clie.codigo"
                                                                    x-model="clienteSeleccionado"
                                                                    class="mt-0.5 w-4 h-4 rounded-full border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                <span class="text-sm font-medium text-gray-700">
                                                                    <span x-text="clie.codigo"
                                                                        class="text-xs text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded mr-1"></span>
                                                                    <span x-text="clie.descripcion"></span>
                                                                </span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                    <div x-show="clientesFiltrados.length === 0"
                                                        class="text-gray-400 text-sm text-center py-4">
                                                        No se encontraron clientes asociados.
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Selector PCI (Áreas Operativas) -->
                                            <div x-show="tipoCurso == '7'" x-transition
                                                class="mt-4 bg-teal-50/50 border border-teal-100 rounded-lg p-5">
                                                <label
                                                    class="text-teal-800 text-sm font-bold tracking-wide inline-block mb-2">
                                                    <i class="bx bx-category mr-1"></i> Seleccionar Áreas Operativas
                                                    <span class="text-red-500">*</span>
                                                </label>
                                                <p class="text-xs text-teal-500 mb-3 font-medium">El curso estará dirigido
                                                    al personal perteneciente a estas áreas.</p>
                                                <div class="mb-3">
                                                    <input type="text" x-model="busquedaAreaPCI"
                                                        placeholder="Buscar área..."
                                                        class="w-full border border-teal-200 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-teal-400 focus:border-teal-400 outline-none shadow-sm"
                                                        @keydown.enter.prevent>
                                                </div>
                                                <div class="border border-teal-100 rounded-md p-3 overflow-y-auto bg-white custom-scrollbar shadow-inner"
                                                    style="max-height: 160px;">
                                                    <div class="grid grid-cols-1 gap-2">
                                                        <template x-for="ar in areasPCIFiltradas" :key="ar.codigo">
                                                            <label
                                                                class="flex items-start space-x-2 cursor-pointer hover:bg-slate-50 p-2 rounded-md border border-transparent hover:border-slate-200 transition-all">
                                                                <input type="checkbox" :value="ar.codigo"
                                                                    x-model="areasAsignadas"
                                                                    class="mt-0.5 w-4 h-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                                                <span class="text-sm font-medium text-gray-700">
                                                                    <span x-text="ar.codigo"
                                                                        class="text-xs text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded mr-1"></span>
                                                                    <span x-text="ar.descripcion"></span>
                                                                </span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                    <div x-show="areasPCIFiltradas.length === 0"
                                                        class="text-gray-400 text-sm text-center py-4">
                                                        No se encontraron áreas asociadas.
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Sucursales (solo PAC) -->
                                            <div x-show="esPAC" x-transition
                                                class="mt-4 bg-indigo-50/50 border border-indigo-100 rounded-lg p-5">
                                                <label
                                                    class="text-indigo-800 text-sm font-bold tracking-wide inline-block mb-2">
                                                    <i class="bx bx-buildings mr-1"></i> Sucursales Asignadas <span
                                                        class="text-red-500">*</span>
                                                </label>
                                                <div class="mb-3">
                                                    <input type="text" x-model="busquedaSucursal"
                                                        placeholder="Buscar sucursal por nombre..."
                                                        class="w-full border border-indigo-200 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 outline-none shadow-sm"
                                                        @keydown.enter.prevent>
                                                </div>
                                                <div class="border border-indigo-100 rounded-md p-3 overflow-y-auto bg-white custom-scrollbar shadow-inner"
                                                    style="max-height: 160px;">
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                        <template x-for="suc in sucursalesFiltradas" :key="suc">
                                                            <label
                                                                class="flex items-center space-x-2 cursor-pointer hover:bg-slate-50 p-2 rounded-md border border-transparent hover:border-slate-200 transition-all">
                                                                <input type="checkbox" :value="suc"
                                                                    x-model="sucursalesAsignadas"
                                                                    class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                                <span class="text-xs font-medium text-gray-700"
                                                                    x-text="suc"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                    <div x-show="sucursalesFiltradas.length === 0"
                                                        class="text-gray-400 text-sm text-center py-4">
                                                        No se encontraron sucursales asociadas.
                                                    </div>
                                                </div>
                                                <p class="text-xs text-indigo-500 mt-2 font-medium">Seleccione
                                                    explícitamente las sucursales
                                                    donde este curso estará activo.</p>
                                            </div>
                                        </div>

                                        <!-- Sistema de gestión -->
                                        <div x-show="tipoCurso != '6'">
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-1 text-primary">
                                                Sistema de gestión <span class="text-danger">*</span>
                                            </label>

                                            <div x-data="{
                                                    opciones: window.opcionesArea || []
                                                }" @areas-loaded.window="opciones = $event.detail"
                                                class="relative w-full">

                                                <select x-model="areaConocimiento"
                                                    @change="area = areaConocimiento; cargarAreasResponsables(areaConocimiento);"
                                                    class="w-full bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Seleccione Sistema</option>
                                                    <template x-for="option in opciones" :key="option.codigo">
                                                        <option :value="option.codigo" x-text="option.descripcion"></option>
                                                    </template>
                                                </select>

                                            </div>
                                        </div>

                                        <!-- Área responsable -->
                                        <div>
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-1 text-primary">
                                                Área responsable <span class="text-danger">*</span>
                                            </label>

                                            <select x-model="areaResponsable"
                                                    @change="codMoodleArea = (areasResponsables.find(a => String(a.codArea) === String(areaResponsable)) || {}).codModdle || ''"
                                                    class="w-full bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Seleccione Área</option>
                                                    <template x-for="option in areasResponsables" :key="option.codArea">
                                                        <option :value="option.codArea" x-text="option.Area || option.nombre || option.descripcion"></option>
                                                    </template>
                                                </select>
                                        </div>

                                        <!-- Sucursal -->
                                        <div x-show="!esDemanda" x-transition>
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-1 text-primary">
                                                Sucursal <span class="text-danger">*</span>
                                            </label>

                                            <select x-model="sucursal"
                                                class="w-full bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                <option value="">Seleccione Sucursal</option>
                                                <option value="TODAS">Todas las sucursales</option>
                                                <template x-for="option in sucursalesOpciones" :key="option.Codigo">
                                                    <option :value="option.Codigo" x-text="option.Sucursal"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <!-- Dirigido a -->
                                        <div x-show="!esDemanda" x-transition>
                                            <label
                                                class="text-gray-800 text-sm font-medium inline-block mb-1 text-primary">
                                                Dirigido a <span class="text-danger">*</span>
                                            </label>

                                            <div x-data="{
                                                    _fullOptions: {{ Js::from($dirigidos->map(fn($d) => ['codigo' => $d->codigo, 'texto' => $d->texto])->values()->toArray()) }},
                                                    get opciones() {
                                                        const tc = this.tipoCurso;
                                                        const clSel = this.clienteSeleccionado;

                                                        if (tc == '5') {
                                                            return [
                                                                { codigo: '1', texto: 'Todos' },
                                                                { codigo: '7', texto: 'Personal Administrativo (Todos)' },
                                                                { codigo: '8', texto: 'Personal Administrativo (4°)' },
                                                                { codigo: '9', texto: 'Personal Administrativo (5°)' },
                                                                { codigo: '10', texto: 'Personal Operativo (Todos)' },
                                                                { codigo: '11', texto: 'Personal Operativo (4°)' },
                                                                { codigo: '12', texto: 'Personal Operativo (5°)' },
                                                                { codigo: 'OTROS', texto: 'Otros' },
                                                            ];
                                                        }

                                                        if (tc == '6') {
                                                            const clientes = this.clientesDisponibles || [];
                                                            const cliente = clientes.find(c => c.codigo == clSel);
                                                            if (cliente && cliente.descripcion && (cliente.descripcion.toLowerCase().includes('linea 2') || cliente.descripcion.toLowerCase().includes('línea 2'))) {
                                                                return this._fullOptions.filter(opt =>
                                                                    opt.texto === 'Todos' || opt.texto === 'PTSA' || opt.texto === 'Estaciones'
                                                                );
                                                            } else if (clSel) {
                                                                const base = this._fullOptions.filter(opt => opt.texto === 'Todos');
                                                                base.push({ codigo: 'OTROS', texto: 'Otros' });
                                                                return base;
                                                            }
                                                        }

                                                        return this._fullOptions;
                                                    }
                                                }" class="relative w-full">

                                                <select x-model="dirigido"
                                                    class="w-full bg-white border border-gray-300 rounded-lg shadow-sm px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Seleccione Dirigido a</option>
                                                    <template x-for="option in opciones" :key="option.codigo">
                                                        <option :value="option.codigo" x-text="option.texto"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna 3: Evaluación -->
                                <div x-show="!codigo" class="flex flex-col mt-8 lg:mt-0">

                                    <!-- Evaluación del Curso -->
                                    <div class="flex items-center justify-between mb-2 bg-indigo-50/80 border border-indigo-100 px-5 py-3 rounded-xl shadow-sm w-full transition-all hover:bg-indigo-50 cursor-pointer select-none"
                                        @click="aplicaEvaluacion = !aplicaEvaluacion"
                                        title="Activar para requerir una evaluación obligatoria en este curso">
                                        <div class="flex flex-col pointer-events-none">
                                            <span class="text-sm font-bold text-indigo-900">Evaluación de Curso</span>
                                            <span class="text-[11px] text-indigo-600/80 font-medium mt-0.5">Requerir
                                                examen obligatorio para aprobar</span>
                                        </div>
                                        <div class="relative" @click.stop>
                                            <input class="form-switch cursor-pointer scale-110" type="checkbox"
                                                role="switch" id="{{ $prefijo }}chkAplicaEvaluacion" x-model="aplicaEvaluacion">
                                        </div>
                                    </div>

                                    <!-- Placeholder cuando no aplica evaluación -->
                                    <div x-show="!aplicaEvaluacion" x-transition x-cloak
                                        class="flex flex-col items-center justify-center h-full border-2 border-dashed border-gray-200 rounded-xl bg-gray-50/50 text-gray-400 p-6">
                                        <i class="bx bx-file-blank mb-3 text-gray-300" style="font-size: 3.5rem;"></i>
                                        <h4 class="text-base font-bold text-gray-500 mb-1">Sin evaluación requerida
                                        </h4>
                                        <p class="text-sm text-gray-400 text-center">
                                            Activa el interruptor de <strong
                                                class="text-indigo-400 font-semibold">Evaluación de Curso</strong> si
                                            deseas configurar un examen obligatorio para aprobar este curso.
                                        </p>
                                    </div>

                                    <!-- Contenedor condicional para Examen -->
                                    <div x-show="aplicaEvaluacion" x-transition.duration.300ms
                                        class="w-full border border-gray-100 bg-gray-50/30 p-5 rounded-xl shadow-sm mb-2.5">
                                        <div class="w-full grid gap-4 grid-cols-1 sm:grid-cols-2">
                                            <div>
                                                <label for="{{ $prefijo }}txtLimite"
                                                    class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                    Límite de tiempo (minutos)
                                                </label>
                                                <input type="number" id="{{ $prefijo }}txtLimite"
                                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 focus:outline-none"
                                                    x-model="limiteTiempo" placeholder="" />
                                            </div>
                                            <div>
                                                <label for="{{ $prefijo }}txtNota"
                                                    class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                    Nota mínima
                                                </label>
                                                <input type="number" id="{{ $prefijo }}txtNota"
                                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 focus:outline-none"
                                                    x-model="nota" placeholder="" />
                                            </div>
                                            <div>
                                                <label for="{{ $prefijo }}txtIntentos"
                                                    class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                    Número de intentos
                                                </label>
                                                <input type="number" id="{{ $prefijo }}txtIntentos"
                                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 focus:outline-none"
                                                    x-model="intentos" placeholder="" />
                                            </div>
                                            <div>
                                                <label for="{{ $prefijo }}txtCantidadPreguntas"
                                                    class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                    Cantidad De Preguntas
                                                </label>
                                                <input type="number" id="{{ $prefijo }}txtCantidadPreguntas"
                                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 focus:outline-none"
                                                    x-model="cantidadPreguntas" placeholder="" />
                                            </div>
                                            <div>
                                                <label for="{{ $prefijo }}txtPreguntasBalotario"
                                                    class="text-gray-800 text-sm font-medium inline-block mb-1">
                                                    Preguntas en el balotario
                                                </label>
                                                <input type="number" id="{{ $prefijo }}txtPreguntasBalotario"
                                                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400 focus:outline-none"
                                                    :class="preguntasExamen.length > 0 ?
                                                            'bg-gray-100 cursor-not-allowed' : 'bg-white'"
                                                    :readonly="preguntasExamen.length > 0" x-model="preguntasBalotario"
                                                    placeholder="" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-col" x-show="aplicaEvaluacion" x-transition.opacity>
                                        <div
                                            class="border border-dashed border-blue-300 bg-blue-50/40 rounded-xl p-5 shadow-sm">

                                            <label
                                                class="text-primary text-[11px] font-black uppercase tracking-widest mb-3 flex items-center">
                                                <i class="bx bxs-file-doc mr-1.5 text-base"></i> Banco de preguntas
                                            </label>

                                            <div class="flex flex-col gap-3">

                                                <!-- Nombre del archivo -->
                                                <div
                                                    class="w-full bg-white border border-blue-200 rounded-lg px-4 py-2.5 flex items-center justify-between shadow-sm focus-within:ring-2 focus-within:ring-blue-100 transition-all">

                                                    <span class="text-xs text-blue-700 font-medium"
                                                        :class="!archivoWordNombre ? 'italic text-blue-400' : ''"
                                                        x-text="archivoWordNombre || 'Selecciona un archivo Word (.docx) con las preguntas & respuestas del examen'">
                                                    </span>

                                                    <button x-show="archivoWordNombre" type="button"
                                                        @click="archivoWordNombre = ''; archivoWord = null; preguntasExamen = []"
                                                        class="text-red-400 hover:text-red-600 transition-colors ml-2"
                                                        title="Quitar archivo">

                                                        <i class="bx bx-trash text-lg"></i>
                                                    </button>
                                                </div>

                                                <!-- Botones -->
                                                <div class="flex flex-wrap gap-2 w-full">
                                                    <!-- Botón de Selección -->
                                                    <button type="button" @click="$refs.inputWord.click()"
                                                        class="flex-1 sm:flex-none btn btn-sm bg-white text-blue-600 border border-blue-200 hover:bg-blue-50 transition-all rounded-lg px-5 shadow-sm font-bold h-[42px] flex items-center justify-center">

                                                        <i class="bx bx-file-find mr-2 text-lg"></i>
                                                        <span
                                                            x-text="archivoWordNombre ? 'Cambiar archivo' : 'Seleccionar archivo'"></span>
                                                    </button>

                                                    <!-- Botón de Procesamiento Word -->
                                                    <button x-show="archivoWordNombre && !preguntasExamen.length"
                                                        type="button" @click="analizarExamenWord()"
                                                        :disabled="cargandoWord"
                                                        class="flex-1 sm:flex-none btn btn-sm bg-blue-600 text-white hover:bg-blue-700 transition-all rounded-lg px-5 shadow-sm font-bold h-[42px] flex items-center justify-center disabled:opacity-70">

                                                        <template x-if="!cargandoWord">
                                                            <div class="flex items-center">
                                                                <i class="bx bx-file mr-2 text-lg"></i>
                                                                Analizar Word
                                                            </div>
                                                        </template>
                                                        <template x-if="cargandoWord">
                                                            <div class="flex items-center">
                                                                <i class="bx bx-loader-alt bx-spin mr-2 text-lg"></i>
                                                                Analizando...
                                                            </div>
                                                        </template>
                                                    </button>

                                                    <!-- Botón Ver Vista Previa (Si ya fue procesado) -->
                                                    <button x-show="preguntasExamen.length > 0" type="button"
                                                        @click="verVistaPrevia()"
                                                        class="flex-1 sm:flex-none btn btn-sm bg-emerald-500 text-white hover:bg-emerald-600 transition-all rounded-lg px-5 shadow-sm font-bold h-[42px] flex items-center justify-center">
                                                        <i class="bx bx-show mr-2 text-lg"></i>
                                                        Ver Preguntas
                                                    </button>

                                                    <input type="file" id="{{ $prefijo }}inputWordExamen" x-ref="inputWord"
                                                        class="hidden" accept=".docx"
                                                        @change="archivoWord = $event.target.files[0]; archivoWordNombre = $event.target.files[0].name; preguntasExamen = []; $event.target.value = '';"
                                                        title="archivoWord">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NUEVO: Auditoría / Metadatos -->
                                    <div x-show="codigo" x-cloak
                                        style="width:100%; margin-top:0.5rem; margin-bottom:1rem; padding:1.25rem; background:rgba(249,250,251,0.7); border:1px solid #e5e7eb; border-radius:0.5rem; opacity:0.7; pointer-events:none; user-select:none;">
                                        <h5
                                            style="font-size:0.75rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:1px solid #e5e7eb;">
                                            Información de Sistema
                                        </h5>

                                        <div style="display:flex; flex-wrap:wrap; gap:2.5rem;">
                                            <div style="display:flex; flex-direction:column; min-width:120px;">
                                                <span
                                                    style="font-size:0.68rem; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.025em; margin-bottom:0.5rem;">Código
                                                    Interno</span>
                                                <div x-text="sys_codigo"
                                                    style="background:#f3f4f6; border:1px solid #e5e7eb; padding:0.375rem 0.75rem; border-radius:0.25rem; color:#9ca3af; font-weight:700; font-size:0.8rem; display:inline-block; text-align:center; box-shadow:0 1px 2px 0 rgba(0,0,0,0.05); width:max-content; min-width:70px;">
                                                    -
                                                </div>
                                            </div>

                                            <div style="display:flex; flex-direction:column; min-width:180px;">
                                                <span
                                                    style="font-size:0.68rem; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.025em; margin-bottom:0.5rem;">Registrado
                                                    por</span>
                                                <div style="display:flex; flex-direction:column; gap:0.25rem;">
                                                    <div
                                                        style="display:flex; align-items:center; gap:0.35rem; color:#9ca3af; font-size:0.75rem; font-weight:600;">
                                                        <i class="bx bx-user"
                                                            style="color:#d1d5db; font-size:0.9rem;"></i>
                                                        <span x-text="sys_creado_por || '-'">(-) -</span>
                                                    </div>
                                                    <span x-text="sys_fecha_creacion || '-'"
                                                        style="color:#9ca3af; font-size:0.75rem; padding-left:1.35rem;">-</span>
                                                </div>
                                            </div>

                                            <div style="display:flex; flex-direction:column; min-width:180px;">
                                                <span
                                                    style="font-size:0.68rem; color:#6b7280; font-weight:600; text-transform:uppercase; letter-spacing:0.025em; margin-bottom:0.5rem;">Última
                                                    modificación</span>
                                                <div style="display:flex; flex-direction:column; gap:0.25rem;">
                                                    <div
                                                        style="display:flex; align-items:center; gap:0.35rem; color:#9ca3af; font-size:0.75rem; font-weight:600;">
                                                        <i class="bx bx-user-pin"
                                                            style="color:#d1d5db; font-size:0.9rem;"></i>
                                                        <span x-text="sys_modificado_por || '-'">(-) -</span>
                                                    </div>
                                                    <span x-text="sys_fecha_modificacion || '-'"
                                                        style="color:#9ca3af; font-size:0.75rem; padding-left:1.35rem;">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> <!-- End Columna 3 -->
                            </div> <!-- End Grid 3-col -->

                            <!-- Modal Preview Imagen -->
                            <div x-show="modalPreviewAbierto" x-cloak
                                class="fixed inset-0 z-[1050] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4"
                                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                @keydown.escape.window="modalPreviewAbierto = false">
                                <div class="relative max-w-3xl w-full mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/10"
                                    @click.away="modalPreviewAbierto = false">
                                    <div
                                        class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-200">
                                        <h4
                                            class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                            <i class="bx bx-image text-primary"></i>
                                            <span x-text="modalPreviewTitulo"></span>
                                        </h4>
                                        <button type="button" @click="modalPreviewAbierto = false"
                                            class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 hover:bg-slate-300 text-slate-600 transition-colors">
                                            <i class="bx bx-x text-lg"></i>
                                        </button>
                                    </div>
                                    <div class="p-2 bg-slate-900/5 flex items-center justify-center"
                                        style="max-height: 75vh;">
                                        <img :src="modalPreviewSrc"
                                            class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-inner">
                                    </div>
                                    <div
                                        class="px-5 py-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                                        <span class="text-[10px] text-slate-400 font-medium">Vista previa</span>
                                        <span class="text-[10px] text-slate-500 font-semibold">
                                            <i class="bx bx-show mr-1"></i>
                                            <span x-text="modalPreviewTitulo"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
