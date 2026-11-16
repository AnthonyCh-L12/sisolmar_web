<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Permisos;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('login');
    }

    public function store(Request $request)
    {
        $user = User::where('usuario', $request->username)
            ->where('habilitado', 1)
            ->first();

        if ($user && Hash::check($request->password, $user->clave)) {
            Auth::login($user);

            session()->put('nombre', $user->nombre_1);
            session()->put('apellido', $user->apellido_1);
            session()->put('usuario', $user->usuario);
            session()->put('tipo_rol', $user->tipo_rol);
            session()->put('limitarTipoPer', $user->limitarTipoPer);
            session()->put('limitarSucursales', $user->limitarSucursal);

            $permisos = Permisos::getPermissionsByRole($user->tipo_rol);
            session(['permisos' => $permisos]);

            // Funcionalidades del rol
            try {
                $funcionalidades = DB::select(
                    'SELECT f.nombre FROM sw_funcionalidades f
                     INNER JOIN sw_roles_funcionalidades rf ON f.codigo = rf.codFuncionalidad
                     WHERE rf.codRol = ? AND rf.habilitado = 1 AND f.habilitado = 1',
                    [$user->tipo_rol]
                );
                $funcionalidades = array_column($funcionalidades, 'nombre');
            } catch (\Exception $e) {
                $funcionalidades = [];
            }
            session(['funcionalidades' => $funcionalidades]);

            // Pop-ups de notificaciones: se muestran una vez por login
            session()->forget('notif_popups_ya_mostrados');

            $usuariosSoloReportePersonal = config('report_access.personal_only_users', []);
            if (in_array(mb_strtolower((string) $user->usuario), $usuariosSoloReportePersonal, true)) {
                return redirect('/home');
            }

            return redirect()->intended('/home');
        }

        return redirect()->route('login')->with('error', 'Credenciales inválidas. Verifica tu usuario y contraseña.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
