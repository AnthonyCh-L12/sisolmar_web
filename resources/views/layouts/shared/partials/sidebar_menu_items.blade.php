@foreach($menusSidebar as $menu)
    @if(!$menu['tiene_submenu'])
        <li class="menu-item">
            <a href="javascript:void(0)"
                class="group flex items-center gap-x-3.5 rounded-md px-3 py-2 text-sm font-medium text-default-100 transition-all hover:bg-default-100/5">
                {!! $menu['icono'] ?? "<i class='bx bx-folder-cog'></i>" !!}
                <span class="menu-text">{{ $menu['nombre'] ?? ucfirst($menu['modulo']) }}</span>
            </a>
        </li>
    @else
        <li class="menu-item hs-accordion" id="menu-{{ $menu['modulo'] }}">
            <a href="javascript:void(0)"
                class="hs-accordion-toggle group flex items-center gap-x-3.5 rounded-md px-3 py-2 text-sm font-medium text-default-100 transition-all hover:bg-default-100/5 hs-accordion-active:bg-default-100/5 hs-accordion-active:text-default-100">
                {!! $menu['icono'] ?? "<i class='bx bx-folder-cog'></i>" !!}
                <span class="menu-text">{{ $menu['nombre'] ?? ucfirst($menu['modulo']) }}</span>
                <span class="menu-arrow"></span>
            </a>

            <div class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300">
                <ul class="mt-1 space-y-1">
                    @foreach($menu['submenus'] ?? [] as $submenu)
                        @if(($submenu['vista'] ?? '') === 'cargos') @continue @endif
                        @if(($submenu['vista'] ?? '') === 'gestion_curs_matricula') @continue @endif
                        @if(($submenu['nombre'] ?? '') === 'Planes de Capacitaciones') @continue @endif
                        <li class="menu-item {{ (($submenu['nombre'] ?? '') === 'Gestionar Actualizaciones DJ' || ($submenu['vista'] ?? '') === 'gestionar_actualizaciones_dj') ? 'hidden' : '' }}">
                            <a class="flex items-center gap-x-3.5 rounded-md px-3 py-1.5 text-sm font-medium text-default-100 transition-all hover:bg-default-100/5"
                                href="{{ route('second', [$menu['modulo'], $submenu['vista']]) }}">
                                <i class="menu-dot"></i>
                                {{ $submenu['nombre'] ?? ucfirst($submenu['vista']) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </li>
    @endif
@endforeach
