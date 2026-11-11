{{-- Modal de participantes inscritos en el aula virtual (Moodle).
     Lista los participantes directamente desde Moodle (sin sw_matriculas) y
     permite desmatricularlos del aula virtual. --}}
<div id="modal-participantes-moodle" x-data="modalParticipantesMoodle()" x-show="open" x-cloak
    class="fixed inset-0 z-[130] flex items-center justify-center p-4"
    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    style="background: rgba(36,39,70,0.45);">

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
            <p class="mt-5 text-base font-bold text-default-900">Cargando participantes...</p>
            <p class="mt-2 text-xs leading-5 text-default-400 max-w-md">Estamos consultando el aula virtual para traer a los participantes inscritos en este curso.</p>
        </div>

        <!-- Header -->
        <div class="flex justify-between items-start py-5 px-6 border-b border-default-100 shrink-0 bg-gradient-to-r from-white to-default-50/30">
            <div>
                <h3 class="text-xl font-bold text-default-900">Participantes inscritos</h3>
                <p class="text-sm text-default-500 mt-1">Personal inscrito en el aula virtual (Moodle)</p>
            </div>
            <button type="button" @click="cerrar()"
                class="flex-shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-lg text-default-400 hover:text-default-700 hover:bg-default-100 focus:outline-none focus:ring-2 focus:ring-primary/30 transition-colors cursor-pointer">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
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
                        <span class="block text-[11px] text-default-500 font-medium">Total de participantes</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="total"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Código de programación</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="codigoProgramacion || '—'"></span>
                    </div>
                    <div class="lg:col-span-2">
                        <span class="block text-[11px] text-default-500 font-medium">Descripción</span>
                        <span class="text-default-700 text-sm" x-text="descripcion || '—'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Sistema de gestión</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="sistema || '—'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-default-500 font-medium">Área</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="area || '—'"></span>
                    </div>
                    <div class="lg:col-span-2">
                        <span class="block text-[11px] text-default-500 font-medium">Programación en uso</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="programacionTexto || '—'"></span>
                    </div>
                    <div class="lg:col-span-2">
                        <span class="block text-[11px] text-default-500 font-medium">Responsable</span>
                        <span class="font-semibold text-default-900 text-sm" x-text="responsable || '—'"></span>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="border border-default-200/60 rounded-xl bg-default-50/50 px-4 pt-2.5 pb-3 mb-4">
                <p class="text-[11px] font-semibold text-default-400 uppercase tracking-widest mb-2">Filtros</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="relative">
                        <input type="text" id="txtBuscarParticipanteMoodle"
                            placeholder="Buscar por nombre o DNI..."
                            class="w-full h-9 pl-8 pr-8 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                        <i class="ti ti-search absolute left-2.5 top-1/2 -translate-y-1/2 text-default-400 text-sm"></i>
                        <button type="button" id="btnLimpiarBusquedaParticipanteMoodle"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-default-400 hover:text-default-700 transition cursor-pointer hidden">
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                    <div>
                        <select id="slcFiltroCargoMoodle"
                            class="w-full h-9 px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                            <option value="">Todos los cargos</option>
                        </select>
                    </div>
                    <div>
                        <select id="slcFiltroTipoTrabajadorMoodle"
                            class="w-full h-9 px-2.5 text-xs border border-default-200 rounded-lg bg-white text-default-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/50">
                            <option value="">Todos los tipos de trabajador</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="border border-default-200/60 rounded-xl overflow-hidden shadow-sm bg-white">
                <div id="tblParticipantesMoodle" class="w-full min-h-[360px]"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end items-center gap-3 px-6 py-4 border-t border-default-100 shrink-0 bg-default-50/50">
            <button type="button" @click="cerrar()"
                class="h-9 px-4 inline-flex items-center justify-center gap-1.5 bg-white border border-default-200 text-default-700 text-sm font-medium rounded-lg shadow-sm hover:bg-default-50 transition cursor-pointer">
                Cerrar
            </button>
        </div>
    </div>
</div>

<script>
    window.modalParticipantesMoodle = function() {
        return {
            open: false,
            isLoading: false,
            cursoMoodleId: null,
            localId: null,
            nombre: '',
            descripcion: '',
            sistema: '',
            area: '',
            responsable: '',
            codigoProgramacion: '',
            programacionTexto: '',
            total: 0,

            abrir(data) {
                this.open = true;
                this.isLoading = true;
                this.cursoMoodleId = data?.MoodleId ?? data?.Id ?? null;
                this.localId = data?.LocalId ?? null;
                this.nombre = data?.Nombre || '';
                this.descripcion = data?.Descripcion || '';
                this.sistema = data?.Sistema || '';
                this.area = data?.Area || '';
                this.responsable = data?.Responsable || '';
                this.codigoProgramacion = '';
                this.programacionTexto = '';
                this.total = 0;

                const buscarEl = document.getElementById('txtBuscarParticipanteMoodle');
                if (buscarEl) buscarEl.value = '';
                const btnLimpiar = document.getElementById('btnLimpiarBusquedaParticipanteMoodle');
                if (btnLimpiar) btnLimpiar.style.display = 'none';
                const cargoEl = document.getElementById('slcFiltroCargoMoodle');
                if (cargoEl) cargoEl.value = '';
                const tipoEl = document.getElementById('slcFiltroTipoTrabajadorMoodle');
                if (tipoEl) tipoEl.value = '';

                if (window.cargarParticipantesMoodle) {
                    window.cargarParticipantesMoodle(this);
                }
            },

            cerrar() {
                this.open = false;
                this.isLoading = false;
                if (window.limpiarModalParticipantesMoodle) window.limpiarModalParticipantesMoodle();
            },
        }
    }
</script>
