<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\FileController;
use App\Http\Controllers\CapacitacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ActualizacionesDjController;

class RoutingController extends Controller
{
     private function getVistasPermitidas(): array
    {
        $permisos = session('permisos', []);
        $vistas = [];
        foreach ($permisos as $menu) {
            foreach ($menu['submenus'] ?? [] as $sub) {
                if (!empty($sub['vista'])) {
                    $vistas[] = $menu['modulo'] . '.' . $sub['vista'];
                }
            }
        }
        return $vistas;
    }

    private function tienePermiso(string $vista): bool
    {
        // El rol 1 (o el que sea admin) pasa siempre — ajustá el valor si es distinto
        if (Auth::user()?->tipo_rol == 1) {
            return true;
        }

        return in_array($vista, $this->getVistasPermitidas());
    }

    public function index(Request $request)
    {
        return redirect('index');
    }

    public function root(Request $request, $first)
    {
        if ($first === 'assets') {
            return redirect('home');
        }

        return view($first);
    }

    public function secondLevel(Request $request, $first, $second)
    {$controllers = [
            'dj' => [
                'gestion_dj' => [FileController::class, 'indexGestionDj'],
                'actualizar_dj' => [FileController::class, 'indexActualizarDj'],
                'reporte_personal' => [FileController::class, 'indexReportePersonal'],
                'reportes' => [FileController::class, 'indexReportesDj'],
                'gestionar_actualizaciones_dj' => [ActualizacionesDjController::class, 'index'],
            ],
            'file_control' => [
                'chargefile' => [FileController::class, 'index'],
                'cargos' => [FileController::class, 'ViewCargo'],
                'legajos' => [FileController::class, 'ViewLegajo'],
                'legajos_comercial' => [FileController::class, 'ViewLegajo_comercial'],
                'folios' => [FileController::class, 'ViewFolios'],
                'search_legajos' => [FileController::class, 'ViewBusquedaLegajo'],
                'legajos_pdf' => [FileController::class, 'ViewLegajoPdf'],
                'dashboard' => [FileController::class, 'ViewDashboard'],
                'reportes' => [ReporteController::class, 'index'],
                'carga_escaneo_dj' => [FileController::class, 'ViewEscaneoDJ'],
            ],
            'capacitacion' => [
                'consulta_matriculas'      => [CapacitacionController::class, 'vistaConsultaMatriculas'],
                'historial_capacitaciones' => [CapacitacionController::class, 'vistaHistorialCapacitaciones'],
                'gestion_cursos'           => [CapacitacionController::class, 'vistaGestionCursos'],
                'cursos_sucamec'           => [CapacitacionController::class, 'vistaCursosSucamec'],
                'seguimiento_matriculas'   => [CapacitacionController::class, 'vistaSeguimientoMatriculas'],
                'reportes_capacitaciones'  => [CapacitacionController::class, 'vistaReportesCapacitaciones'],
                'planes_capacitacion'      => [CapacitacionController::class, 'vistaPlanesCapacitacion'],
            ],
            'maestros' => [
                'usuarios' => [UsuarioController::class, 'index'],
            ],
        ];

        $vistaKey = $first.'.'.$second;

        if (! $this->tienePermiso($vistaKey)) {
            abort(403);
        }

        if (isset($controllers[$first][$second])) {
            [$clase, $metodo] = $controllers[$first][$second];

            return app($clase)->{$metodo}($request);
        }

        return view($vistaKey);
    }

    public function thirdLevel(Request $request, $first, $second, $third)
    {
        if ($first === 'assets') {
            return redirect('home');
        }

        $vistaKey = $first.'.'.$second.'.'.$third;

        if (! $this->tienePermiso($vistaKey)) {
            abort(403);
        }

        return view($vistaKey);
    }
}
