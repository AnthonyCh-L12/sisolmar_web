<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Collection;

trait ReporteFormatoHelpers
{
    protected function obtenerValorColumna($row, array $claves, string $default = ''): string
    {
        if (!$row) {
            return $default;
        }

        foreach ($claves as $clave) {
            if (isset($row->{$clave}) && trim((string) $row->{$clave}) !== '') {
                return trim((string) $row->{$clave});
            }
        }

        return $default;
    }

    protected function obtenerDniColumna($row): string
    {
        return $this->obtenerValorColumna($row, [
            'username', 'USERNAME', 'Username',
            'DNI', 'dni', 'Dni',
            'NRO_DOCU_IDEN', 'nro_docu_iden', 'NroDocuIden',
            'NRO_DOC', 'nro_doc', 'NroDoc',
            'DOCUMENTO', 'documento',
        ]);
    }

    protected function filtrarAprobados(Collection $aprobados): Collection
    {
        if ($aprobados->isEmpty()) {
            return $aprobados;
        }

        $first = $aprobados->first();

        if (property_exists($first, 'aprobado')) {
            return $aprobados->filter(fn($row) => (int) $row->aprobado === 1)->values();
        }

        $clavesEstado = ['ESTADO', 'estado', 'Estado', 'APROBADO', 'aprobado', 'Aprobado'];

        $tieneEstado = collect($clavesEstado)->contains(fn($clave) => property_exists($first, $clave));
        if (!$tieneEstado) {
            return $aprobados;
        }

        return $aprobados->filter(function ($row) use ($clavesEstado) {
            $estado = strtoupper($this->obtenerValorColumna($row, $clavesEstado));

            if (in_array($estado, ['APROBADO', 'APROBADA'], true)) {
                return true;
            }

            return in_array($estado, ['S', 'SI', 'TRUE', 'YES', '1'], true);
        })->values();
    }

    protected function mapearCategoriaCurso(?int $categoria, $courseId): string
    {
        return match ($categoria) {
            1 => 'INDUCCIÓN',
            2 => 'CHARLA',
            3 => 'CAPACITACIÓN',
            4 => 'ENTRENAMIENTO',
            5 => 'SIMULACROS DE EMERGENCIA',
            default => in_array($courseId, [77, 120]) ? 'INDUCCIÓN' : 'CAPACITACIÓN',
        };
    }
}
