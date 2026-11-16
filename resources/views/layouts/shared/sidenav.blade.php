<!-- Start Sidebar -->
<aside id="app-menu"
    class="hs-overlay fixed inset-y-0 start-0 z-60 hidden w-sidenav min-w-sidenav bg-primary-900 dark:bg-slate-800 overflow-y-auto -translate-x-full transform transition-all duration-300 hs-overlay-open:translate-x-0 lg:bottom-0 lg:end-auto lg:z-30 lg:block lg:translate-x-0 rtl:translate-x-full rtl:hs-overlay-open:translate-x-0 rtl:lg:translate-x-0 print:hidden [--body-scroll:true] [--overlay-backdrop:true] lg:[--overlay-backdrop:false]">

    <div class="flex flex-col h-full">
        <!-- Sidenav Logo -->
        <div class="sticky top-0 flex h-topbar items-center justify-between px-6">
            <a href="{{ config('app.url') }}">
                <img  src="{{ asset('images/logo-light.png') }}" alt="logo" class="flex" width="150">
            </a>
        </div>

        <div class="p-4 h-[calc(100%-theme('spacing.topbar'))] flex-grow" data-simplebar>
            <!-- Menu -->
            <ul class="admin-menu hs-accordion-group flex w-full flex-col gap-1">
                @if(isset($permisos) && !empty($permisos))
                        @php
                            $menusPorGrupo = [];
                            foreach ($permisos as $menuSidebar) {
                                $nombreGrupoSidebar = $menuSidebar['grupo_modulo']['nombre'] ?? '';
                                $menusPorGrupo[$nombreGrupoSidebar][] = $menuSidebar;
                            }
                        @endphp
                        @foreach($menusPorGrupo as $nombreGrupoSidebar => $menusDelGrupoSidebar)
                            @if($nombreGrupoSidebar !== '')
                                @php
                                    $idGrupoSidebar = $menusDelGrupoSidebar[0]['grupo_modulo']['codigo'] ?? \Illuminate\Support\Str::slug($nombreGrupoSidebar);
                                @endphp
                                <li class="menu-item hs-accordion" id="grupo-modulo-{{ $idGrupoSidebar }}">
                                    <a href="javascript:void(0)"
                                        class="hs-accordion-toggle group flex items-center gap-x-3.5 rounded-md px-3 py-2 text-sm font-medium text-default-100 transition-all hover:bg-default-100/5 hs-accordion-active:bg-default-100/5 hs-accordion-active:text-default-100">
                                        <i class="bx bx-group"></i>
                                        <span class="menu-text uppercase">{{ $nombreGrupoSidebar }}</span>
                                        <span class="menu-arrow"></span>
                                    </a>

                                    <div class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300">
                                        <ul class="mt-1 space-y-1 ps-2 hs-accordion-group">
                                            @include('layouts.shared.partials.sidebar_menu_items', ['menusSidebar' => $menusDelGrupoSidebar])
                                        </ul>
                                    </div>
                                </li>
                            @else
                                @include('layouts.shared.partials.sidebar_menu_items', ['menusSidebar' => $menusDelGrupoSidebar])
                            @endif
                        @endforeach
                @endif


                <!-- <li class="px-3 py-2 text-sm font-medium text-default-400">Página Principal</li>

                <li class="menu-item">
                    <a class="group flex items-center gap-x-3.5 rounded-md px-3 py-2 text-sm font-medium text-default-100 transition-all hover:bg-default-100/5"
                        href="/">
                        <i class="i-ph-gauge-duotone text-2xl"></i>
                        <span>Dashboard</span>
                    </a>
                </li>     -->



                <!-- <li class="menu-item">
                    <a href="{{ route('second', ['file_control', 'dashboard'])}}"
                        class="group flex items-center gap-x-3.5 rounded-md px-3 py-2 text-sm font-medium text-default-100 transition-all hover:bg-default-100/5">
                        <i class="i-ph-clipboard-text-duotone text-2xl"></i>
                        <span class="menu-text"> Dashboard </span>
                    </a>
                </li>  -->

            </ul>
        </div>

    </div>
</aside>
<!-- End Sidebar -->
