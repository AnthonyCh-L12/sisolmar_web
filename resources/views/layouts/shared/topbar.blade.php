<!-- Topbar Start -->
<header
    class="app-header sticky top-0 z-50 min-h-topbar flex items-center bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-700 transition-colors duration-300">
    <div class="container flex items-center justify-between gap-4 mx-0 py-0">

        {{-- Lado izquierdo: toggle móvil + título de página --}}
        <div class="flex items-center gap-5">
            <div class="lg:hidden flex">
                <button
                    class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-white border border-gray-200 hover:bg-gray-50 text-gray-500 transition-all"
                    data-hs-overlay="#app-menu" aria-label="Toggle navigation">
                    <i class="i-ph-list-duotone text-xl"></i>
                </button>
            </div>

            @stack('page-heading')
        </div>

        {{-- Lado derecho: botones uniformes --}}
        <div class="flex items-center gap-2">

            {{-- Notificaciones --}}
            <div class="hs-dropdown relative inline-flex [--placement:bottom-right]">
                
                <button id="btn-notifications" type="button" class="hs-dropdown-toggle relative inline-flex items-center justify-center w-9 h-9 rounded-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 text-gray-500 hover:text-gray-700 dark:text-slate-400 transition-all shadow-sm">
                    <i class="bx bx-bell text-lg"></i>
                    <span id="notif-count" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[17px] h-[17px] flex items-center justify-center px-1 hidden">
                        0
                    </span>
                </button>
                
                <div class="hs-dropdown-menu duration mt-2 w-full max-w-sm rounded-xl border border-gray-200 bg-white opacity-0 shadow-xl transition-[opacity,margin] hs-dropdown-open:opacity-100 hidden z-50 overflow-hidden">
                    <div class="block px-4 py-3 font-bold text-center text-gray-700 bg-gray-50 border-b border-gray-200 shadow-sm">
                        Centro de Notificaciones
                    </div>
                    <div class="max-h-[350px] overflow-y-auto">
                        <div class="flex flex-col" id="notif-list" data-etapa2-url="{{ url('/api/notificaciones/pendientes-etapa2') }}" data-mayoria-url="{{ url('/api/notificaciones/hijos-por-cumplir-mayoria-edad') }}">
                            @if($ocultarNotificacionesDj ?? false)
                                <div class="px-4 py-6 text-center text-sm text-gray-500">
                                    No hay notificaciones pendientes.
                                </div>
                            @else
                                <div class="px-4 py-6 text-center text-sm text-gray-500">
                                    Cargando notificaciones...
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pantalla completa --}}
            <button data-toggle="fullscreen" type="button" title="Pantalla completa" class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700
  text-gray-500 hover:text-gray-700 dark:text-slate-400 transition-all">
                <i class="i-ph-arrows-out-duotone text-lg flex group-[-fullscreen]:hidden"></i>
                <i class="i-ph-arrows-in-duotone text-lg hidden group-[-fullscreen]:flex"></i>
            </button>

            {{-- Tema claro/oscuro --}}
            <button id="theme-toggle" type="button" title="Cambiar tema" class="hidden md:inline-flex items-center justify-center w-9 h-9 rounded-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700
  text-gray-500 dark:text-slate-400 transition-all">
                <i id="theme-icon-light" class="i-ph-sun-duotone text-lg text-amber-500"></i>
                <i id="theme-icon-dark" class="i-ph-moon-duotone text-lg text-slate-300 hidden"></i>
            </button>

            {{-- Perfil --}}
            <div class="hs-dropdown relative inline-flex [--placement:bottom-right]">
                <button type="button" class="hs-dropdown-toggle inline-flex items-center justify-center w-9 h-9 rounded-full border-2 border-gray-200 dark:border-slate-700 hover:border-gray-300 dark:hover:border-slate-500
  overflow-hidden transition-all">
                    <img src="{{ asset('images/user.png') }}" alt="usuario" class="w-full h-full object-cover">
                </button>

                <div
                    class="hs-dropdown-menu duration mt-2 min-w-52 rounded-lg border border-default-200 bg-white p-2 opacity-0 shadow-md transition-[opacity,margin] hs-dropdown-open:opacity-100 hidden">

                    <div class="px-3 py-2 mb-1 border-b border-gray-100">
                        <p class="text-xs font-semibold text-gray-800">{{ session('nombre') }} {{ session('apellido') }}
                        </p>
                        <p class="text-xs text-gray-400">{{ session('usuario') }}</p>
                    </div>

                    <a class="flex items-center gap-2 py-2 px-3 rounded-md text-sm text-default-800 hover:bg-default-100"
                        onclick="abrirModalPasswordChange()" href="#">
                        <i class="i-tabler-lock text-gray-400"></i>
                        Cambiar contraseña
                    </a>

                    <button hidden style="display:none;" type="button" data-hs-overlay="#modal-password-change-user"
                        id="btn-modal-password-change-user">
                    </button>

                    <hr class="my-1.5 border-gray-100">

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                        @csrf
                    </form>
                    <button id="__btn-abrir-modal-logout" data-hs-overlay="#modal-confirmar-logout"
                        class="hidden"></button>

                    <a class="flex items-center gap-2 py-2 px-3 rounded-md text-sm text-red-600 hover:bg-red-50"
                        href="#" onclick="document.getElementById('__btn-abrir-modal-logout').click(); return false;">
                        <i class="i-tabler-logout text-red-400"></i>
                        Cerrar Sesión
                    </a>

                </div>
            </div>

        </div>
    </div>
    <script>window.funcionalidadesSISOL = @json($funcionalidades ?? []);</script>
</header>
{{-- Modal: Cambiar contraseña --}}
<div id="modal-password-change-user"
    class="hs-overlay w-full h-full fixed top-0 left-0 z-70 transition-all duration-500 overflow-x-hidden overflow-y-auto hidden pointer-events-none">
    <div class="-translate-y-5 hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 opacity-0 ease-in-out transition-all duration-500 sm:max-w-lg sm:w-full my-8 sm:mx-auto flex flex-col bg-white
  shadow-sm rounded">
        <div class="flex flex-col border border-default-200 shadow-sm rounded-lg pointer-events-auto">
            <div class="flex justify-between items-center py-3 px-4 border-b border-default-200">
                <h3 class="text-lg font-medium text-default-900">Cambiar contraseña</h3>
                <button type="button" class="text-default-600 cursor-pointer"
                    data-hs-overlay="#modal-password-change-user">
                    <i class="i-tabler-x text-lg"></i>
                </button>
            </div>
            <div class="p-4 overflow-y-auto">
                <div class="grid lg:grid-cols-3 gap-6">
                    <div class="col-span-2">
                        <label for="txt-new-password-user"
                            class="text-default-800 text-sm font-medium inline-block mb-2">
                            Nueva contraseña
                        </label>
                        <div x-data="{ show: false }" class="flex gap-1 items-center">
                            <input :type="show ? 'text' : 'password'" id="txt-new-password-user" class="form-input">
                            <input type="hidden" name="txtUserNombre" id="txt-user-nombre-pass"
                                value="{{ session('usuario') }}">
                            <button type="button" class="btn bg-info text-white" @click="show = !show">
                                <span x-text="show ? 'Ocultar' : 'Ver'"></span>
                            </button>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <button type="button" class="btn bg-primary text-white" onclick="passwordChangeUserGeneralMe()">
                            Actualizar
                        </button>
                    </div>
                </div>
            </div>
            <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-default-200">
                <button type="button" class="btn bg-secondary text-white" data-hs-overlay="#modal-password-change-user">
                    <i class="i-tabler-x me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Confirmar logout --}}
<div id="modal-confirmar-logout"
    class="hs-overlay w-full h-full fixed top-0 left-0 z-[80] transition-all duration-300 overflow-x-hidden overflow-y-auto hidden pointer-events-none">
    <div class="-translate-y-5 hs-overlay-open:translate-y-0 hs-overlay-open:opacity-100 opacity-0 ease-in-out transition-all duration-300 sm:max-w-sm sm:w-full my-8 sm:mx-auto flex flex-col bg-white
  shadow-lg rounded-xl">
        <div class="flex flex-col border border-default-200 shadow-sm rounded-xl pointer-events-auto">
            <div class="p-6 text-center">
                <div class="flex items-center justify-center w-14 h-14 rounded-full bg-danger/10 mx-auto mb-4">
                    <i class="i-tabler-logout text-2xl text-danger"></i>
                </div>
                <h3 class="text-lg font-semibold text-default-900 mb-1">¿Cerrar sesión?</h3>
                <p class="text-sm text-default-500">Se cerrará tu sesión actual y serás redirigido al inicio.</p>
            </div>
            <div class="flex gap-2 px-6 pb-5">
                <button type="button" class="btn bg-default-100 text-default-700 hover:bg-default-200 w-full"
                    data-hs-overlay="#modal-confirmar-logout">
                    Cancelar
                </button>
                <button type="button" class="btn bg-danger text-white hover:bg-danger/90 w-full"
                    onclick="sessionStorage.removeItem('folioToastShown'); document.getElementById('logout-form').submit();">
                    <i class="i-tabler-logout me-1"></i> Cerrar Sesión
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Botón oculto para disparar el modal dinámico desde JS --}}
<button id="btn-open-modal-etapa2" data-hs-overlay="#modal-detalle-etapa2" class="hidden"></button>

{{-- Modal: Detalle Sucursales Etapa 2 --}}
<div id="modal-detalle-etapa2" class="hs-overlay w-full h-full fixed top-0 left-0 z-[80] transition-all duration-300 overflow-x-hidden overflow-y-auto hidden pointer-events-none">
    <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-md sm:w-full m-3 sm:mx-auto mt-10">
        <div class="flex flex-col bg-white border border-gray-200 shadow-xl rounded-xl pointer-events-auto">
            <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 bg-slate-50 rounded-t-xl">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <i class="bx bx-check-shield text-xl text-primary"></i>
                    Detalle de Pendientes (Etapa 2)
                </h3>
                <button type="button" class="flex justify-center items-center w-7 h-7 text-sm font-semibold rounded-full border border-transparent text-gray-800 hover:bg-gray-200" data-hs-overlay="#modal-detalle-etapa2">
                    <i class="bx bx-x text-xl"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="flex justify-between items-center mb-3 px-2">
                    <span class="text-sm text-gray-500 font-bold uppercase tracking-wider">Sucursales</span>
                    <span class="text-xs font-bold text-red-600 bg-red-100 border border-red-200 px-2.5 py-1 rounded-full shadow-sm">
                        Total: <span id="modal-etapa2-total">0</span>
                    </span>
                </div>
                <div id="modal-etapa2-list" class="max-h-[350px] overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                    <!-- JS inyecta la lista detallada aquí -->
                </div>
            </div>
            <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 bg-slate-50 rounded-b-xl">
                <button type="button" class="btn border border-gray-300 bg-white text-gray-700 text-sm px-4 py-2 rounded-lg hover:bg-gray-100 font-medium" data-hs-overlay="#modal-detalle-etapa2">Cerrar</button>
                <!-- Le agregamos ?tab=etapa2 a la ruta -->
                <a href="/dj/actualizar_dj?tab=etapa2" class="btn bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 font-medium shadow-sm">
                    Ir al Módulo <i class="bx bx-right-arrow-alt text-base align-middle"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<button id="btn-open-modal-mayoria-edad" data-hs-overlay="#modal-mayoria-edad" class="hidden"></button>
<div id="modal-mayoria-edad" class="hs-overlay w-full h-full fixed top-0 left-0 z-[80] transition-all duration-300 overflow-x-hidden overflow-y-auto hidden pointer-events-none">
    <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all sm:max-w-3xl sm:w-full m-3 sm:mx-auto mt-10">
        <div class="flex flex-col bg-white border border-gray-200 shadow-xl rounded-xl pointer-events-auto">
            <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 bg-slate-50 rounded-t-xl">
                <div>
                    <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="bx bx-child text-xl text-orange-500"></i> Asignación familiar próxima a vencer</h3>
                    <p class="text-xs text-gray-500 mt-1">Hijos registrados en la DJ que están próximos a cumplir 18 años.</p>
                </div>
                <button type="button" aria-label="Cerrar detalle" class="flex justify-center items-center w-7 h-7 rounded-full text-gray-600 hover:bg-gray-200" data-hs-overlay="#modal-mayoria-edad"><i class="bx bx-x text-xl"></i></button>
            </div>
            <div class="p-4">
                <div class="flex flex-wrap gap-3 mb-3 text-xs font-semibold">
                    <span class="rounded-full bg-blue-50 text-blue-700 border border-blue-100 px-3 py-1">Aviso inicial (hasta 30 días): <b id="mayoria-edad-total-30">0</b></span>
                    <span class="rounded-full bg-orange-50 text-orange-700 border border-orange-100 px-3 py-1">Recordatorio (15 días o menos): <b id="mayoria-edad-total-15">0</b></span>
                </div>
                <div id="modal-mayoria-edad-list" class="max-h-[55vh] overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100"></div>
            </div>
            <div class="flex justify-end items-center px-4 py-3 border-t border-gray-200 bg-slate-50 rounded-b-xl">
                <button type="button" class="btn border border-gray-300 bg-white text-gray-700 text-sm px-4 py-2 rounded-lg hover:bg-gray-100 font-medium" data-hs-overlay="#modal-mayoria-edad">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts Topbar -->
@unless($ocultarNotificacionesDj ?? false)
    @vite(['resources/js/functions/notifications.js'])
@endunless
<!-- Topbar End -->
