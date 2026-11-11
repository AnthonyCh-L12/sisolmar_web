{{-- Modal: personal aún no matriculado en el curso (consulta de matrículas).
      Consulta las matrículas activas en Moodle para excluir a los ya inscritos
      y permite enviar a matrícula a los pendientes. --}}
<div id="modal-personal-no-matriculado" x-data="modalPersonalNoMatriculado()" x-show="open" x-cloak
    class="fixed inset-0 z-[130] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" style="background: rgba(36,39,70,0.45);">

    <div class="relative flex flex-col shadow-2xl shadow-primary/10 rounded-2xl overflow-hidden w-full max-w-7xl border border-default-200 bg-white transition-all duration-300 max-h-[92vh]"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        <!-- Loader full-modal -->
        <div x-show="isLoading" x-transition.opacity
            class="absolute inset-0 z-30 bg-white flex flex-col items-center justify-center text-center px-8">
            <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-slate-700 animate-spin"></div>
            <p class="mt-5 text-base font-bold text-default-900">Cargando personal pendiente...</p>
            <p class="mt-2 text-xs leading-5 text-default-400 max-w-md">Estamos consultando las matrículas activas del
                aula virtual para excluir a quienes ya están inscritos.</p>
        </div>

        <!-- Header -->
        <div
            class="flex justify-between items-start py-5 px-6 border-b border-default-100 shrink-0 bg-gradient-to-r from-white to-default-50/30">
            <div class="flex items-start gap-3">
                <div
                    class="w-11 h-11 rounded-xl bg-primary/10 text-primary inline-flex items-center justify-center shrink-0">
                    <i class="ti ti-user-plus text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-default-900">Matricular personal</h3>
                    <p class="text-sm text-default-500 mt-1">
                        <span x-text="nombre || 'Personal aún no inscrito en el curso'"></span>
                    </p>
                </div>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">

            <div x-show="error" x-cloak
                class="mb-4 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <i class="ti ti-alert-circle text-lg shrink-0"></i>
                <span x-text="error"></span>
            </div>

            <!-- Detalles del curso -->
            <div class="bg-default-50/50 p-5 rounded-xl border border-default-200/60 mb-4">
                <h4 class="text-xs font-bold text-default-500 uppercase tracking-wider flex items-center gap-2 mb-4">
                    <i class="ti ti-info-circle text-primary"></i>
                    Detalles del curso
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="lg:col-span-2">
                        <span class="block text-[11px] text-default-500 font-medium">Nombre del curso</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="nombre || '—'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Código</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="codigo || '—'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Sistema de gestión</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="sistema || '—'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Área</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="area || '—'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Responsable</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="responsable || '—'"></span>
                    </div>
                    <div class="lg:col-span-2">
                        <span class="block text-[11px] text-default-500 font-medium">Programación en uso</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="programacionTexto || '—'"></span>
                    </div>
                    <div class="lg:col-span-2">
                        <span class="block text-[11px] text-default-500 font-medium">Descripción</span>
                        <span class="text-default-700 text-sm" x-text="descripcion || '—'"></span>
                    </div>
                </div>
            </div>

            <div x-show="!esVigente && !isLoading" x-cloak
                class="mb-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <i class="ti ti-alert-triangle text-lg shrink-0"></i>
                <span>El curso no tiene una programación <strong>VIGENTE</strong>. No se puede matricular
                    personal.</span>
            </div>

            <!-- Filtros -->
            <div class="border border-default-200/60 rounded-xl bg-default-50/50 px-4 pt-2.5 pb-3 mb-4">
                <p class="text-[11px] font-semibold text-default-400 uppercase tracking-widest mb-2">Filtros</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="relative">
                        <input type="text" id="txtBuscarPersonalNoMatriculado"
                            placeholder="Buscar por nombre, DNI o código..."
                            class="w-full h-9 pl-8 pr-8 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                    </div>
                    <div>
                        <select id="slcFiltroCargoNoMatriculado"
                            class="w-full h-9 px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                            <option value="">Todos los cargos</option>
                        </select>
                    </div>
                    <div>
                        <select id="slcFiltroTipoNoMatriculado"
                            class="w-full h-9 px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                            <option value="">Todos los tipos de trabajador</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Acciones de selección -->
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <p class="text-xs font-medium text-default-500">Selecciona a las personas que deseas matricular en el
                    curso.</p>
                <button type="button" id="btnSeleccionarTodosNoMatriculado"
                    class="ml-auto inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-primary/20 bg-primary/5 text-primary text-xs font-semibold shadow-sm hover:bg-primary/10 transition">
                    <i class="ti ti-checks text-sm"></i>
                    <span>Seleccionar filtrados</span>
                </button>
                <button type="button" id="btnLimpiarSeleccionNoMatriculado"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-default-200 bg-white text-default-600 text-xs font-semibold shadow-sm hover:bg-default-50 transition">
                    <i class="ti ti-eraser text-sm"></i>
                    <span>Limpiar</span>
                </button>
                <span
                    class="inline-flex items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"
                    x-text="`${seleccionados.length} seleccionado(s)`"></span>
            </div>

            <!-- Tabla -->
            <div class="border border-default-200/60 rounded-xl overflow-hidden shadow-sm bg-white">
                <div id="tblPersonalNoMatriculado" class="w-full min-h-[360px]"></div>
            </div>

            <p class="mt-2 text-[11px] text-default-400">
                Solo se muestra personal que no tiene una matrícula activa en Moodle para este curso.
            </p>
        </div>

        <!-- Footer -->
        <div
            class="flex flex-wrap justify-end items-center gap-3 px-6 py-4 border-t border-default-100 shrink-0 bg-default-50/50">
            <div class="mr-auto flex flex-col gap-2">
                <label class="flex items-center gap-2 text-xs font-medium text-default-500 cursor-pointer select-none"
                    title="Se enviará un resumen al correo indicado al finalizar (solo si son más de 10 personas)">
                    <input type="checkbox" x-model="notificarCorreo"
                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                    Avisar por correo al finalizar
                </label>
                <div x-show="notificarCorreo" x-transition x-cloak class="flex flex-col gap-1">
                    <label for="txtCorreoNotificacionNoMatriculado"
                        class="text-[11px] font-medium text-default-500">Correo para el aviso</label>
                    <input id="txtCorreoNotificacionNoMatriculado" type="email" x-model.trim="correoNotificacion"
                        placeholder="tu.correo@solsecurity.pe"
                        class="h-9 w-64 px-3 text-sm border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                </div>
            </div>
            <button type="button" @click="cerrar()"
                class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition cursor-pointer">
                Cancelar
            </button>
            <button type="button" @click="matricularSeleccionados()"
                :disabled="guardando || !seleccionados.length || !esVigente"
                :title="!esVigente ? 'El curso debe tener una programación VIGENTE' : (!seleccionados.length ? 'Selecciona al menos una persona' : 'Enviar a matrícula')"
                class="h-9 px-5 inline-flex items-center justify-center gap-1.5 bg-primary text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                <i class="ti" :class="guardando ? 'ti-loader-2 animate-spin' : 'ti-user-plus'"></i>
                <span
                    x-text="guardando ? 'Matriculando...' : `Matricular seleccionados (${seleccionados.length})`"></span>
            </button>
        </div>
    </div>
</div>

<script>
    window.modalPersonalNoMatriculado = function() {
        return {
            open: false,
            isLoading: false,
            guardando: false,
            error: '',
            cursoMoodleId: null,
            localId: null,
            codigo: '',
            nombre: '',
            descripcion: '',
            sistema: '',
            area: '',
            responsable: '',
            programacionId: '',
            programacionTexto: '',
            esVigente: false,
            totalPendientes: 0,
            totalInscritos: 0,
            seleccionados: [],
            notificarCorreo: false,
            correoNotificacion: '',

            abrir(data) {
                this.open = true;
                this.isLoading = true;
                this.guardando = false;
                this.error = '';
                this.cursoMoodleId = data?.MoodleId ?? data?.Id ?? null;
                this.localId = data?.LocalId ?? null;
                this.codigo = data?.Codigo || '';
                this.nombre = data?.Nombre || '';
                this.descripcion = data?.Descripcion || '';
                this.sistema = data?.Sistema || '';
                this.area = data?.Area || '';
                this.responsable = data?.Responsable || '';
                this.programacionId = '';
                this.programacionTexto = '';
                this.esVigente = false;
                this.totalPendientes = 0;
                this.totalInscritos = 0;
                this.seleccionados = [];
                this.notificarCorreo = false;
                this.correoNotificacion = '';

                this.limpiarFiltros();

                if (window.cargarPersonalNoMatriculado) {
                    window.cargarPersonalNoMatriculado(this);
                }
            },

            cerrar() {
                this.open = false;
                this.isLoading = false;
                this.guardando = false;
                if (window.limpiarModalPersonalNoMatriculado) {
                    window.limpiarModalPersonalNoMatriculado();
                }
            },

            limpiarFiltros() {
                const buscarEl = document.getElementById('txtBuscarPersonalNoMatriculado');
                if (buscarEl) buscarEl.value = '';
                const cargoEl = document.getElementById('slcFiltroCargoNoMatriculado');
                if (cargoEl) cargoEl.value = '';
                const tipoEl = document.getElementById('slcFiltroTipoNoMatriculado');
                if (tipoEl) tipoEl.value = '';
            },

            matricularSeleccionados() {
                if (window.matricularPersonalNoMatriculado) {
                    window.matricularPersonalNoMatriculado(this);
                }
            },
        };
    };
</script>