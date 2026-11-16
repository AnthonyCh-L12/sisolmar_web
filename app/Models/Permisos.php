<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Permisos extends Model
{
    /**
     * Retorna el árbol de menús y submenús habilitados para un rol dado.
     *
     * @param  int  $rolId
     * @return array<int, array{
     *     id: string,
     *     nombre: string,
     *     modulo: string,
     *     icono: string,
     *     orden: int,
     *     tiene_submenu: bool,
     *     submenus: array
     * }>
     *
     * @throws \RuntimeException  Si falla la consulta a la base de datos.
     */
    public static function getPermissionsByRole(int $rolId): array
    {
        try {
            // 1. Obtener MENÚS habilitados para el rol
            $menus = DB::select("
                SELECT m.codigo, m.descripcion, m.icono, m.modulo, m.orden, m.tiene_submenu
                FROM sw_menus m
                INNER JOIN sw_roles_permisos p ON m.codigo = p.codMenu
                WHERE p.codRol     = ?
                  AND p.habilitado  = 1
                  AND m.habilitado  = 1
                ORDER BY m.orden ASC
            ", [$rolId]);

            // 2. Obtener SUBMENÚS habilitados para el rol
            $submenus = DB::select("
                SELECT DISTINCT sm.codigo, sm.codMenu, sm.descripcion, sm.vista, sm.orden
                FROM sw_submenus sm
                INNER JOIN sw_roles_permisos p
                        ON sm.codigo  = p.codSubmenu
                       AND sm.codMenu = p.codMenu
                WHERE p.codRol    = ?
                  AND p.habilitado = 1
                  AND sm.habilitado = 1
                ORDER BY sm.codMenu ASC, sm.orden ASC
            ", [$rolId]);

        } catch (\Exception $e) {
            Log::error('Error al obtener permisos por rol', [
                'rol_id' => $rolId,
                'error'  => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "No se pudieron cargar los permisos para el rol #{$rolId}.",
                previous: $e
            );
        }

        // 3. Cargar el agrupador maestro opcional sin cambiar el filtro por rol.
        // Si las tablas nuevas aún no están desplegadas en otro entorno,
        // los menús siguen apareciendo con el comportamiento anterior.
        $grupoPorMenu = [];
        try {
            $asignacionesModulo = DB::table('sw_modulos_menus as mm')
                ->join('sw_modulos as mod', 'mod.codigo', '=', 'mm.codModulo')
                ->where('mod.habilitado', 1)
                ->orderBy('mod.orden')
                ->get(['mm.codMenu', 'mod.codigo as grupo_codigo', 'mod.nombre as grupo_nombre']);

            foreach ($asignacionesModulo as $asignacion) {
                $grupoPorMenu[$asignacion->codMenu] = [
                    'codigo' => $asignacion->grupo_codigo,
                    'nombre' => $asignacion->grupo_nombre,
                ];
            }
        } catch (\Exception $e) {
            Log::info('No se cargaron grupos maestros del sidebar; se conserva la navegación actual.', [
                'error' => $e->getMessage(),
            ]);
        }

        // 4. Construir árbol indexado por código de menú
        $menuArbol = [];

        foreach ($menus as $menu) {
            $menuArbol[$menu->codigo] = [
                'id'            => $menu->codigo,
                'nombre'        => $menu->descripcion,
                'modulo'        => $menu->modulo,
                'icono'         => $menu->icono,
                'orden'         => $menu->orden,
                'tiene_submenu' => (bool) $menu->tiene_submenu,
                'grupo_modulo'  => $grupoPorMenu[$menu->codigo] ?? null,
                'submenus'      => [],
            ];
        }

        // 5. Anidar submenús en su menú padre
        //    Nota: si tiene_submenu = false, el submenú se ignora intencionalmente
        //    (el flag controla si el menú debe renderizarse como expandible).
        foreach ($submenus as $submenu) {
            $padre = $menuArbol[$submenu->codMenu] ?? null;

            if ($padre === null || ! $padre['tiene_submenu']) {
                continue;
            }

            $menuArbol[$submenu->codMenu]['submenus'][] = [
                'id'      => $submenu->codigo,
                'id_menu' => $submenu->codMenu,
                'nombre'  => $submenu->descripcion,
                'vista'   => $submenu->vista,
                'orden'   => $submenu->orden,
            ];
        }

        // 6. Devolver array plano (índices numéricos consecutivos)
        return array_values($menuArbol);
    }
}
