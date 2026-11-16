<?php
namespace App\Http\Middleware;

  use Closure;
  use Illuminate\Http\Request;
  use Symfony\Component\HttpFoundation\Response;
  use Illuminate\Support\Facades\View;
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Facades\DB;
  use App\Models\Permisos;

  class CargarMenus
  {
      public function handle(Request $request, Closure $next): Response
      {
          if (Auth::check()) {
              $permisos = Permisos::getPermissionsByRole(Auth::user()->tipo_rol);
              View::share('permisos', $permisos);
              session(['permisos' => $permisos]); // mantiene RoutingController funcionando

              $nombreRol = DB::table('sw_roles')
                  ->where('codigo', Auth::user()->tipo_rol)
                  ->value('nombre');
              $rolSinNotificacionesDj = mb_strtoupper(trim((string) $nombreRol))
                  === mb_strtoupper(trim((string) config('report_access.role_without_dj_notifications')));
              View::share('ocultarNotificacionesDj', $rolSinNotificacionesDj);

              // Funcionalidades del rol (exento_validaciones, cambiar_tipo_personal, etc.)
              try {
                  $funcionalidades = DB::select(
                      'SELECT f.nombre FROM sw_funcionalidades f
                       INNER JOIN sw_roles_funcionalidades rf ON f.codigo = rf.codFuncionalidad
                       WHERE rf.codRol = ? AND rf.habilitado = 1 AND f.habilitado = 1',
                      [Auth::user()->tipo_rol]
                  );
                  $funcionalidades = array_column($funcionalidades, 'nombre');
              } catch (\Exception $e) {
                  $funcionalidades = [];
              }
              View::share('funcionalidades', $funcionalidades);
              session(['funcionalidades' => $funcionalidades]);
          } else {
              View::share('ocultarNotificacionesDj', false);
          }

          return $next($request);
      }
  }
