<?php

namespace App\Helpers;

class TipoTrabajadorHelper
{
    /**
     * Normaliza las distintas representaciones del tipo de trabajador
     * que llegan desde los stored procedures y tablas maestras.
     */
    public static function normalizar($valor): string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return '';
        }

        $plano = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($texto, 'UTF-8'));

        if ($plano === '') {
            return $texto;
        }

        $esAdmin = str_contains($plano, 'ADMINISTRATIVO')
            || str_starts_with($plano, 'ADMIN');
        $esOperativo = str_contains($plano, 'OPERATIVO')
            || str_starts_with($plano, 'OPER');
        $esEspecial = str_contains($plano, 'ESPECIAL');

        if ($esEspecial) {
            return 'ESPECIAL';
        }

        $categoria = null;

        if (preg_match('/(01|02|03|05|06)$/', $plano, $match)) {
            $codigo = $match[1];

            if ($codigo === '06') {
                return 'ESPECIAL';
            }

            $categoria = in_array($codigo, ['03', '05'], true) ? '5' : '4';

            if (in_array($codigo, ['01', '03'], true)) {
                $esOperativo = true;
            }

            if (in_array($codigo, ['02', '05'], true)) {
                $esAdmin = true;
            }
        }

        if ($categoria === null) {
            if (
                str_contains($plano, 'CUARTA')
                || str_contains($plano, '4TA')
                || preg_match('/4$/', $plano)
            ) {
                $categoria = '4';
            } elseif (
                str_contains($plano, 'QUINTA')
                || str_contains($plano, '5TA')
                || preg_match('/5$/', $plano)
            ) {
                $categoria = '5';
            }
        }

        if ($categoria === null) {
            return $texto;
        }

        if ($esAdmin && !$esOperativo) {
            return $categoria === '5'
                ? 'ADMINISTRATIVO 5TA'
                : 'ADMINISTRATIVO 4TA';
        }

        if ($esOperativo && !$esAdmin) {
            return $categoria === '5'
                ? 'OPERATIVO 5TA'
                : 'OPERATIVO 4TA';
        }

        return $texto;
    }
}
