<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BiometricoController extends Controller
{
    public function show($codigo)
    {
        // ── RUTAS HTTP (190.116.178.163 → acceso por URL pública) ───────────
        $baseHttp = "http://190.116.178.163/Biblioteca_Grafica";

        $rutaHuellaAntigua     = ['tipo' => 'http', 'base' => "$baseHttp/HUELLAS_DIGITALES/PERSONAL"];
        // Las biometrías extraídas de una DJ se guardan en las carpetas
        // PERSONAL; el modal debe consultar el mismo destino para mostrarlas.
        $rutaHuellaNueva       = ['tipo' => 'http', 'base' => "$baseHttp/HUELLAS_DIGITALES/PERSONAL"];
        $rutaFirmaAntigua      = ['tipo' => 'http', 'base' => "$baseHttp/FIRMAS/PERSONAL"];
        $rutaFirmaNueva        = ['tipo' => 'http', 'base' => "$baseHttp/FIRMAS/PERSONAL"];
        $rutaDNIanversoAntigua = ['tipo' => 'http', 'base' => "$baseHttp/DNI1_1"];
        $rutaDNIreversoAntigua = ['tipo' => 'http', 'base' => "$baseHttp/DNI2_1"];
        $rutaFotoActual        = ['tipo' => 'http', 'base' => "$baseHttp/Fotos"];

        // ── RUTAS UNC (192.168.10.5 → red local, siguen igual) ──────
        $rutaDNIanversoNueva = ['tipo' => 'unc', 'base' => "\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dni\\anverso\\"];
        $rutaDNIreversoNueva = ['tipo' => 'unc', 'base' => "\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dni\\reverso\\"];

        // ── EXTENSIONES A BUSCAR ────────────────────────────────────
        $extensiones = ['jpg', 'jpeg', 'png', 'bmp'];

        return response()->json([
            'huella_antigua'      => $this->buscar($rutaHuellaAntigua,     $codigo, $extensiones, 'antigua', 'huella'),
            'huella_nueva'        => $this->buscar($rutaHuellaNueva,       $codigo, $extensiones, 'nueva',   'huella'),
            'firma_antigua'       => $this->buscar($rutaFirmaAntigua,      $codigo, $extensiones, 'antigua', 'firma'),
            'firma_nueva'         => $this->buscar($rutaFirmaNueva,        $codigo, $extensiones, 'nueva',   'firma'),
            'dni_anverso_antigua' => $this->buscar($rutaDNIanversoAntigua, $codigo, $extensiones, 'antigua', 'dni'),
            'dni_reverso_antigua' => $this->buscar($rutaDNIreversoAntigua, $codigo, $extensiones, 'antigua', 'dni'),
            'dni_anverso_nuevo'   => $this->buscar($rutaDNIanversoNueva,   $codigo, $extensiones, 'nueva',   'dni'),
            'dni_reverso_nuevo'   => $this->buscar($rutaDNIreversoNueva,   $codigo, $extensiones, 'nueva',   'dni'),
            'foto_actual'         => $this->buscar($rutaFotoActual,        $codigo, $extensiones, 'antigua', 'foto'),
        ]);
    }

    //  public function show($codigo)
    // {
    //     // ── RUTAS HTTP (190.116.178.163 → acceso por URL) ───────────
    //     //$baseHttp = "http://190.116.178.163/Biblioteca_Grafica"
    //     //$baseHttp = "file://192.168.10.2/Biblioteca_Grafica";
    //        $baseHttp = "\\\\192.168.10.2\\Biblioteca_Grafica";

    //     $rutaHuellaAntigua     = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\HUELLAS_DIGITALES\\PERSONAL\\"];
    //     $rutaHuellaNueva       = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\DOCUMENTOS_PERS\\DJ_2026\\huellas\\"];
    //     $rutaFirmaAntigua      = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\FIRMAS\\PERSONAL\\"];
    //     $rutaFirmaNueva        = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\DOCUMENTOS_PERS\\DJ_2026\\firmas\\"];
    //     $rutaDNIanversoAntigua = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\DNI1_1\\"];
    //     $rutaDNIreversoAntigua = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\DNI2_1\\"];

    //     $rutaFotoActual = ['tipo' => 'unc', 'base' => "\\\\192.168.10.2\\Biblioteca_Grafica\\Fotos\\"];

    //     // ── RUTAS UNC (192.168.10.5 → red local, siguen igual) ──────
    //     $rutaDNIanversoNueva = ['tipo' => 'unc', 'base' => "\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dni\\anverso\\"];
    //     $rutaDNIreversoNueva = ['tipo' => 'unc', 'base' => "\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dni\\reverso\\"];

        

    //     // ── EXTENSIONES A BUSCAR ────────────────────────────────────
    //     $extensiones = ['jpg', 'jpeg', 'png', 'bmp'];

    //     return response()->json([
    //         'huella_antigua'      => $this->buscar($rutaHuellaAntigua,     $codigo, $extensiones, 'antigua', 'huella'),
    //         'huella_nueva'        => $this->buscar($rutaHuellaNueva,       $codigo, $extensiones, 'nueva',   'huella'),
    //         'firma_antigua'       => $this->buscar($rutaFirmaAntigua,      $codigo, $extensiones, 'antigua', 'firma'),
    //         'firma_nueva'         => $this->buscar($rutaFirmaNueva,        $codigo, $extensiones, 'nueva',   'firma'),
    //         'dni_anverso_antigua' => $this->buscar($rutaDNIanversoAntigua, $codigo, $extensiones, 'antigua', 'dni'),
    //         'dni_reverso_antigua' => $this->buscar($rutaDNIreversoAntigua, $codigo, $extensiones, 'antigua', 'dni'),
    //         'dni_anverso_nuevo'   => $this->buscar($rutaDNIanversoNueva,   $codigo, $extensiones, 'nueva',   'dni'),
    //         'dni_reverso_nuevo'   => $this->buscar($rutaDNIreversoNueva,   $codigo, $extensiones, 'nueva',   'dni'),

    //         'foto_actual'   => $this->buscar($rutaFotoActual,   $codigo, $extensiones, 'antigua',   'foto'),
    //     ]);
    // }

    // ── DISPATCHER: decide si usar HTTP o UNC ───────────────────────
    private function buscar($ruta, $codigo, $extensiones, $tipo, $biometrico)
    {
        if ($ruta['tipo'] === 'http') {
            return $this->buscarArchivoHttp($ruta['base'], $codigo, $extensiones, $tipo, $biometrico);
        }

        return $this->buscarArchivo($ruta['base'], $codigo, $extensiones, $tipo, $biometrico);
    }

    // ── BÚSQUEDA POR HTTP (para IP pública) ─────────────────────────
    private function buscarArchivoHttp($baseUrl, $codigo, $extensiones, $tipo, $biometrico)
    {
        $codigoFormateado = str_pad($codigo, 5, '0', STR_PAD_LEFT);

        $intentos = [];

        foreach ($extensiones as $ext) {
            // 1. Con ceros
            $url = rtrim($baseUrl, '/') . "/{$codigoFormateado}.{$ext}";
            $intentos[] = $url;

            try {
                $response = Http::timeout(5)->get($url);
                if ($response->successful()) {
                    return $this->toBase64FromContent($response->body(), $ext);
                }
            } catch (\Exception $e) {
                // continuar
            }

            // 2. Sin ceros
            $url = rtrim($baseUrl, '/') . "/{$codigo}.{$ext}";
            $intentos[] = $url;

            try {
                $response = Http::timeout(5)->get($url);
                if ($response->successful()) {
                    return $this->toBase64FromContent($response->body(), $ext);
                }
            } catch (\Exception $e) {
                // continuar
            }
        }

        return [
            'error'    => 'NO ENCONTRADO',
            'codigo'   => $codigo,
            'base_url' => $baseUrl,
            'intentos' => $intentos,
        ];
    }

    // ── BÚSQUEDA POR RUTA UNC (para red local) ───────────────────────
    private function buscarArchivo($ruta, $codigo, $extensiones, $tipo, $biometrico)
    {
        $codigoFormateado = str_pad($codigo, 5, '0', STR_PAD_LEFT);

        foreach ($extensiones as $ext) {
            // 1. Con ceros
            $archivo = $ruta . $codigoFormateado . '.' . $ext;
            if (file_exists($archivo) && is_readable($archivo)) {
                return $this->toBase64($archivo, $ext);
            }

            // 2. Sin ceros
            $archivo = $ruta . $codigo . '.' . $ext;
            if (file_exists($archivo) && is_readable($archivo)) {
                return $this->toBase64($archivo, $ext);
            }
        }

        return [
            'error'    => 'NO ENCONTRADO',
            'codigo'   => $codigo,
            'ruta'     => $ruta,
            'intentos' => [
                $ruta . $codigoFormateado . '.jpg',
                $ruta . $codigo . '.jpg',
            ],
        ];
    }

    // ── BASE64 desde archivo local ───────────────────────────────────
    private function toBase64($archivo, $ext)
    {
        $mimeMap = [
            'jpg'  => 'jpeg',
            'jpeg' => 'jpeg',
            'png'  => 'png',
            'bmp'  => 'bmp',
        ];

        $mime   = $mimeMap[strtolower($ext)] ?? 'jpeg';
        $base64 = base64_encode(file_get_contents($archivo));

        return 'data:image/' . $mime . ';base64,' . $base64;
    }

    // ── BASE64 desde contenido HTTP ──────────────────────────────────
    private function toBase64FromContent($contenido, $ext)
    {
        $mimeMap = [
            'jpg'  => 'jpeg',
            'jpeg' => 'jpeg',
            'png'  => 'png',
            'bmp'  => 'bmp',
        ];

        $mime   = $mimeMap[strtolower($ext)] ?? 'jpeg';
        $base64 = base64_encode($contenido);

        return 'data:image/' . $mime . ';base64,' . $base64;
    }
}
