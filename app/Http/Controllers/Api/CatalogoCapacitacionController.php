<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CatalogoCapacitacionController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
            ] + Consulta::catalogosCapacitacion());
        } catch (\Exception $e) {
            return $this->errorCatalogo($e, 'No se pudieron cargar los catálogos de capacitación.');
        }
    }

    public function sistemas(): JsonResponse
    {
        return $this->responderCatalogo('sistemas', 'No se pudo cargar el catálogo de sistemas.');
    }

    public function areas(): JsonResponse
    {
        return $this->responderCatalogo('areas', 'No se pudo cargar el catálogo de áreas.');
    }

    public function tipos(): JsonResponse
    {
        return $this->responderCatalogo('tipos', 'No se pudo cargar el catálogo de tipos de curso.');
    }

    public function planes(): JsonResponse
    {
        return $this->responderCatalogo('planes', 'No se pudo cargar el catálogo de planes.');
    }

    public function dirigidos(): JsonResponse
    {
        return $this->responderCatalogo('dirigidos', 'No se pudo cargar el catálogo de dirigidos.');
    }

    public function areasPorSistema(string $sistemaId): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'areas' => Consulta::areasPorSistemaCapacitacion($sistemaId),
            ]);
        } catch (\Exception $e) {
            return $this->errorCatalogo($e, 'No se pudieron cargar las áreas del sistema.');
        }
    }

    private function responderCatalogo(string $clave, string $mensaje): JsonResponse
    {
        try {
            $catalogos = Consulta::catalogosCapacitacion();

            return response()->json([
                'success' => true,
                $clave => $catalogos[$clave] ?? [],
            ]);
        } catch (\Exception $e) {
            return $this->errorCatalogo($e, $mensaje);
        }
    }

    private function errorCatalogo(\Exception $e, string $mensaje): JsonResponse
    {
        Log::error($mensaje, [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $mensaje,
        ], 500);
    }
}
