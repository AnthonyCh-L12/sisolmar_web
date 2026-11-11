<?php

namespace App\Services;

use App\Models\DerechoHabiente;
use App\Models\Personal;
use App\Models\Telefono;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;



class DjService
{
    public function guardarDeclaracionJurada(array $data)
    {
        DB::beginTransaction();

        try {
            // Dividir nombres y apellidos
            $nameParts = $this->splitName($data['nombres_apellidos']);

            // Mapeo de valores de texto a códigos de BD
            $estadoCivilMap = [
                'SOLTERO' => 'S',
                'CASADO' => 'C',
                'DIVORCIADO' => 'D',
                'VIUDO' => 'V',
                'CONVIVIENTE' => 'O'
            ];

            $sexoMap = [
                'MASCULINO' => 'M',
                'FEMENINO' => 'F'
            ];

            // Normalización de Licencia de Armas (vienen como JSON de Tagify)
            $licenciasRaw = json_decode($data['licencia_arma'] ?? '[]', true);
            $licenciasStr = is_array($licenciasRaw) ? implode(', ', array_column($licenciasRaw, 'value')) : '';
            $conLicArma = !empty($licenciasStr) ? 'SI' : 'NO';

            // Formateo de fechas para SQL Server (Ymd es el formato más robusto)
            $fechaNacimiento = !empty($data['fecha_nacimiento']) ? Carbon::parse($data['fecha_nacimiento'])->format('Y-m-d') : null;
            $fechaCaducaDni = !empty($data['caduca']) ? Carbon::parse($data['caduca'])->format('Ymd') : null;

            $personalData = [
                'NRO_DOCU_IDEN'         => $data['dni'],
                'NOMB_1'                => $nameParts['NOMB_1'],
                'NOMB_2'                => $nameParts['NOMB_2'],
                'APEL_1'                => $nameParts['APEL_1'],
                'APEL_2'                => $nameParts['APEL_2'],
                'ESTA_CIVI'             => $estadoCivilMap[strtoupper($data['estado_civil'] ?? '')] ?? null,
                'SEXO'                  => $sexoMap[strtoupper($data['sexo'] ?? '')] ?? null,
                'FECH_NACI'             => $fechaNacimiento,
                'ESSALUD'               => strtoupper(substr($data['essalud'] ?? 'NO', 0, 2)),
                'tipo_sangr'            => substr($data['tipo_sangre'] ?? '', 0, 5),
                'peso_kilo'             => $data['peso'] ?? null,
                'tall_metr'             => $data['talla'] ?? null,
                'PERS_PROFESION'        => Str::limit($data['profesion_alterna'] ?? '', 150, ''),
                'PERS_GRADO_INSTRUCCION'=> substr($data['grado_instruccion'] ?? '', 0, 20),
                'IEDU_CODIGO'           => substr($data['institucion'] ?? '', 0, 10),
                'CARR_CODIGO'           => substr($data['carrera'] ?? '', 0, 10),
                'EGRESO_EDUCATIVO'      => $data['anio_egreso'] ?? null,
                'PERS_SNADAR'           => strtoupper(substr($data['sabe_nadar'] ?? 'NO', 0, 2)),
                'PERS_CONSMO'           => strtoupper(trim($data['presto_smo'] ?? 'NO')),
                'PERS_LUGARSMO'         => strtoupper(trim($data['lugar_smo'] ?? '')) ?: null,
                'DEPARTAMENTO'          => substr($data['departamento_actual'] ?? '', 0, 10),
                'PROVINCIA'             => substr($data['provincia_actual'] ?? '', 0, 10),
                'DISTRITO'              => substr($data['distrito_actual'] ?? '', 0, 10),
                'DIRECCION'             => Str::limit($data['direccion_actual'] ?? '', 150, ''),
                'PERS_EMAIL'            => Str::limit($data['correo'] ?? '', 150, ''),
                'PERS_NOMCONTACTO'      => Str::limit($data['contacto_emergencia'] ?? '', 100, ''),
                'PERS_NROEMERGENCIA'    => substr($data['celular_emergencia'] ?? '', 0, 20),
                'PERS_CONYUGE'          => Str::limit($data['parentesco_emergencia'] ?? '', 150, ''),
                'PERS_CONDISCAMEC'      => strtoupper(substr($data['curso_sucamec'] ?? 'NO', 0, 2)),
                'PERS_NRODISCAMEC'      => Str::limit($data['institucion_laboral'] ?? '', 20, ''),
                'PERS_CONLICARMAS'      => $conLicArma,
                'PERS_NROLICENCIA'      => Str::limit($licenciasStr, 50, ''),
                'PERS_TIPOARMA'         => Str::limit($data['tipo_arma'] ?? '', 50, ''),
                'PERS_CONARMAS'         => strtoupper(substr($data['arma_propia'] ?? 'NO', 0, 2)),
                'PERS_BREVETE'          => Str::limit($data['brevete'] ?? '', 15, ''),
                'CLASE_BREVETE'         => substr($data['clase_brevete'] ?? '', 0, 1),
                'CATEGORIA_BREVETE'     => Str::limit($data['tipo_vehiculo'] ?? '', 10, ''),
                'PERS_CTRABANT'         => Str::limit($data['empresa_anterior'] ?? '', 150, ''),
                'PERS_CARGOTRABANT'     => Str::limit($data['cargo_anterior'] ?? '', 50, ''),
                'PERS_DURACIONANT'      => Str::limit($data['duracion_anterior'] ?? '', 50, ''),
                'PERS_DIREC_DNI'        => Str::limit($data['direccion_dni'] ?? '', 200, ''),
                'PERS_DPTO_DIRDNI'      => substr($data['departamento_dni'] ?? '', 0, 10),
                'PERS_PROV_DIRDNI'      => substr($data['provincia_dni'] ?? '', 0, 10),
                'PERS_DIST_DIRDNI'      => substr($data['distrito_dni'] ?? '', 0, 10),
                'PERS_FECHCADUCADNI'    => $fechaCaducaDni, 
                'PERS_PENSIONISTA'      => strtoupper(substr($data['pensionista'] ?? 'NO', 0, 2)),
                'CODI_SIST_PENS'        => substr($data['sistema_previsional'] ?? '', 0, 2),
                'PERS_CONTRATADO'       => 0,
                'PERS_VIGENCIA'         => '1',
                'PERS_FECHAREG'         => now()->format('Ymd'), 
                'JUBILADO'              => 0,
                'SCRT'                  => 0,
                'ESTA_ACTI'             => 1,
                'ASIG_FAMI'             => $this->calcularAsigFami($data),
                'PERS_CONHIJOS'         => $this->calcularConHijos($data),
                'AFIL_SIND'             => 0,
                'COMI_PESC'             => 0,
            ];










            $personal = Personal::updateOrCreate(
                ['CODI_PERS' => $data['cod_postulante']],
                $personalData
            );

            // Guardar teléfonos
            $this->saveTelefonos($personal->CODI_PERS, $data);

            // Guardar familiares
            if (!empty($data['parentesco'])) {
                foreach ($data['parentesco'] as $i => $parentesco) {
                    if (empty($parentesco) && empty($data['apellidosNombres'][$i]) && empty($data['fechaNacimiento'][$i])) {
                        continue;
                    }

                    DerechoHabiente::updateOrCreate(
                        [
                            'CODI_PERS' => $personal->CODI_PERS,
                            'NOMB_1'    => $data['apellidosNombres'][$i] ?? null,
                        ],
                        [
                            'TIPO_RELA'       => $parentesco ?? null,
                            'FECH_NACI'       => $data['fechaNacimiento'][$i] ?? null,
                            'DEHA_VIGENCIA'   => 1,
                        ]
                    );
                }
            }

            DB::commit();
            return $personal;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function splitName(string $fullName)
    {
        $parts = explode(' ', trim($fullName));
        $count = count($parts);

        $res = ['NOMB_1' => null, 'NOMB_2' => null, 'APEL_1' => null, 'APEL_2' => null];

        // Se asume el formato: Nombre(s) Apellido1 Apellido2
        if ($count >= 4) {
            $res['NOMB_1'] = $parts[0];
            $res['NOMB_2'] = $parts[1];
            $res['APEL_1'] = $parts[2];
            $res['APEL_2'] = $parts[3];
        } elseif ($count === 3) {
            $res['NOMB_1'] = $parts[0];
            $res['APEL_1'] = $parts[1];
            $res['APEL_2'] = $parts[2];
        } elseif ($count === 2) {
            $res['NOMB_1'] = $parts[0];
            $res['APEL_1'] = $parts[1];
        } else {
            $res['NOMB_1'] = $parts[0] ?? '';
        }

        return $res;
    }

    /**
     * Asignación familiar: SI para hijos menores de 18 años o hijos mayores
     * incapacitados permanentemente. Un hijo mayor de edad sin incapacidad
     * permanente no genera asignación familiar.
     */
    private function calcularAsigFami(array $data): string
    {
        $parentescos = $data['parentesco'] ?? [];
        $fechasNacimiento = $data['fechaNacimiento'] ?? [];

        foreach ($parentescos as $indice => $parentesco) {
            $parentesco = mb_strtoupper(trim((string) $parentesco), 'UTF-8');
            if (str_starts_with($parentesco, 'HIJO MAYOR DE EDAD')
                && (str_contains($parentesco, 'INCAPACITADO') || str_contains($parentesco, 'DISCAPACIDAD'))
                && str_contains($parentesco, 'PERMANENT')) {
                return 'SI';
            }

            // Solo HIJO MENOR DE EDAD se confirma con su fecha de nacimiento.
            if ($parentesco !== 'HIJO MENOR DE EDAD') {
                continue;
            }

            $fechaNacimiento = trim((string) ($fechasNacimiento[$indice] ?? ''));
            if ($fechaNacimiento === '') {
                continue;
            }

            try {
                $edad = Carbon::parse($fechaNacimiento)->age;
                if ($edad >= 0 && $edad < 18) {
                    return 'SI';
                }
            } catch (Exception $e) {
                // Una fecha inválida no se considera evidencia de un hijo menor.
            }
        }

        return 'NO';
    }

    /** PERS_CONHIJOS indica si se registró algún hijo, sin importar la edad. */
    private function calcularConHijos(array $data): string
    {
        foreach (($data['parentesco'] ?? []) as $parentesco) {
            if (preg_match('/^HIJ/i', trim((string) $parentesco))) {
                return 'SI';
            }
        }

        return 'NO';
    }

    private function saveTelefonos($codiPers, array $data)
    {
        // Limpiar teléfonos previos si es necesario o manejar actualizaciones
        Telefono::where('CODI_PERS', $codiPers)->delete();

        if (!empty($data['celular'])) {
            Telefono::create([
                'CODI_PERS'     => $codiPers,
                'NRO_TELE'      => $data['celular'],
                'TIPO_TELE'     => 'MOVIL',
                'OBSERVACION'   => 'Principal',
                'TELE_VIGENCIA' => 'SI',
            ]);
        }

        if (!empty($data['whatsapp'])) {
            Telefono::create([
                'CODI_PERS'     => $codiPers,
                'NRO_TELE'      => $data['whatsapp'],
                'TIPO_TELE'     => 'MOVIL',
                'OBSERVACION'   => 'WhatsApp',
                'TELE_VIGENCIA' => 'SI',
            ]);
        }

        if (!empty($data['celular_emergencia'])) {
            Telefono::create([
                'CODI_PERS'       => $codiPers,
                'NRO_TELE'        => $data['celular_emergencia'],
                'TIPO_TELE'       => 'MOVIL',
                'OBSERVACION'     => 'Emergencia (' . ($data['contacto_emergencia'] ?? 'Contacto') . ')',
                'TELE_EMERGENCIA' => 1,
                'TELE_VIGENCIA'   => 'SI',
            ]);
        }
    }

}
