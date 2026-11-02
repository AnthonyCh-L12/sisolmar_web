<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\FileControl;
use Symfony\Component\Process\Process;

class FirmaHuellaDjController extends Controller
{
    public function extraer(Request $request)
    {
        $validated = $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:1229',
        ]);

        $tempDir = storage_path('app/temp/firmahuella/' . Str::uuid());

        try {
            File::ensureDirectoryExists($tempDir, 0700, true);
            $pdfPath = $tempDir . DIRECTORY_SEPARATOR . 'entrada.pdf';
            $outputDir = $tempDir . DIRECTORY_SEPARATOR . 'recortes';

            if (!copy($validated['pdf']->getRealPath(), $pdfPath)) {
                throw new \RuntimeException('No se pudo preparar el PDF para la extracción.');
            }

            $python = (string) config('firmahuella.python');
            if ($python === '') {
                throw new \RuntimeException('No está configurado el intérprete de Python.');
            }

            $command = [$python];
            if (PHP_OS_FAMILY === 'Windows' && in_array(strtolower(pathinfo($python, PATHINFO_FILENAME)), ['py', 'pyw'], true)) {
                $command[] = '-3';
            }

            $command = array_merge($command, [
                base_path('scripts/firmahuella/extractor.py'),
                $pdfPath,
                '--salida',
                $outputDir,
                '--dpi',
                '300',
            ]);

            $process = new Process($command, base_path());
            $process->setTimeout(max(10, (int) config('firmahuella.timeout', 120)));
            $process->run();

            if (!$process->isSuccessful()) {
                $detalle = trim($process->getErrorOutput());
                $sinRecuadros = str_contains(mb_strtolower($detalle), 'no se encontraron recuadros');

                return response()->json([
                    'success' => false,
                    'message' => $sinRecuadros
                        ? 'No se encontraron recuadros de firma y huella con las dimensiones esperadas.'
                        : 'No se pudo extraer la firma y la huella del PDF.',
                ], $sinRecuadros ? 422 : 500);
            }

            $firmaPath = $outputDir . DIRECTORY_SEPARATOR . 'firma.jpg';
            $huellaPath = $outputDir . DIRECTORY_SEPARATOR . 'huella.jpg';
            if (!is_file($firmaPath) || !is_file($huellaPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El extractor no encontró ambos recuadros de firma y huella.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'firma' => 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($firmaPath)),
                    'huella' => 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($huellaPath)),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error ejecutando extractor de firma y huella de DJ', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo ejecutar el extractor. Verifica que Python y sus dependencias estén instalados.',
            ], 500);
        } finally {
            if (is_dir($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    public function guardar(Request $request)
    {
        $validated = $request->validate([
            'codPersonal' => 'required|digits:5',
            'fecha_emision' => 'required|date',
            'pdf' => 'required|file|mimes:pdf|max:5120',
            'firma' => 'required|file|mimes:jpg,jpeg|max:10240',
            'huella' => 'required|file|mimes:jpg,jpeg|max:10240',
        ]);

        $codigo = $validated['codPersonal'];
        $nombreBase = $codigo;
        $destinos = config('firmahuella.destinos');
        $archivos = [
            'dj' => ['archivo' => $validated['pdf'], 'ruta' => $destinos['dj'] . DIRECTORY_SEPARATOR . $nombreBase . '.pdf'],
            'firma' => ['archivo' => $validated['firma'], 'ruta' => $destinos['firmas']],
            'huella' => ['archivo' => $validated['huella'], 'ruta' => $destinos['huellas']],
        ];

        // La DJ se guarda en el servidor web; las biometrías pasan por charge_file.php,
        // igual que las fotos y DNI, para que el servidor 10.5 escriba en Biblioteca_Grafica.
        $respaldos = [];
        $escritos = [];
        $biometricosEnviados = [];

        try {
            foreach ($archivos as $tipo => $item) {
                if ($tipo !== 'dj') {
                    $this->enviarBiometricoAlServidor(
                        $item['archivo'],
                        $nombreBase . '.jpg',
                        $item['ruta'],
                        $tipo
                    );
                    $biometricosEnviados[] = $tipo;
                    continue;
                }

                $directorio = dirname($item['ruta']);
                File::ensureDirectoryExists($directorio, 0770, true);

                $respaldo = is_file($item['ruta']) ? file_get_contents($item['ruta']) : null;
                if (is_file($item['ruta']) && $respaldo === false) {
                    throw new \RuntimeException('No se pudo respaldar la DJ existente.');
                }
                $respaldos['dj'] = $respaldo;

                $contenido = file_get_contents($item['archivo']->getRealPath());
                $escritos[] = 'dj';
                if ($contenido === false || file_put_contents($item['ruta'], $contenido, LOCK_EX) !== strlen($contenido)) {
                    throw new \RuntimeException('No se pudo guardar la DJ en su carpeta del servidor.');
                }
            }

            $rutaPdfRegistrada = 'file://192.168.10.5/Extranet_2024/apps/sisolmar/storage/app/dj/2026/' . $nombreBase . '.pdf';
            if (!FileControl::saveDjFolioPersonalAux(
                $validated['fecha_emision'],
                $codigo,
                $rutaPdfRegistrada,
                session('usuario')
            )) {
                throw new \RuntimeException('No se pudo registrar la DJ en el listado de folios.');
            }

            return response()->json([
                'success' => true,
                'message' => 'La DJ, la firma y la huella se guardaron correctamente.',
                'archivos' => [
                    'dj' => $nombreBase . '.pdf',
                    'firma' => $nombreBase . '.jpg',
                    'huella' => $nombreBase . '.jpg',
                ],
            ]);
        } catch (\Throwable $e) {
            foreach (array_reverse($escritos) as $tipo) {
                $ruta = $archivos[$tipo]['ruta'];
                if ($respaldos[$tipo] === null) {
                    if (is_file($ruta)) @unlink($ruta);
                } else {
                    @file_put_contents($ruta, $respaldos[$tipo], LOCK_EX);
                }
            }

            Log::error('Error guardando DJ y biométricos extraídos', [
                'codi_pers' => $codigo,
                'error' => $e->getMessage(),
                'biometricos_enviados' => $biometricosEnviados,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar la DJ, la firma y la huella mediante el servidor de archivos. Revisa el registro del servidor.',
            ], 500);
        }
    }

    private function enviarBiometricoAlServidor($archivo, string $nombreArchivo, string $ruta, string $tipo): void
    {
        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false) {
            throw new \RuntimeException("No se pudo leer el archivo de {$tipo} para enviarlo al servidor de archivos.");
        }

        $proxy = config('firmahuella.upload_proxy');
        $response = Http::timeout(30)
            ->withToken((string) ($proxy['token'] ?? ''))
            ->attach('archivo', $contenido, $nombreArchivo)
            ->post((string) ($proxy['url'] ?? ''), [
                'nameFile' => $nombreArchivo,
                'ruta' => $ruta,
            ]);

        $respuesta = $response->json();
        if ($response->failed() || ! is_array($respuesta) || ($respuesta['success'] ?? false) !== true) {
            Log::error("charge_file.php rechazó el archivo de {$tipo}.", [
                'status' => $response->status(),
                // No guardar el cuerpo completo: charge_file.php incluye Authorization
                // en su respuesta cuando el token es inválido.
                'error_proxy' => $respuesta['error'] ?? $respuesta['mensaje'] ?? 'Respuesta inválida del servidor de archivos.',
                'nombre_archivo' => $nombreArchivo,
                'ruta_destino' => $ruta,
            ]);

            throw new \RuntimeException("El servidor de archivos no pudo guardar {$tipo} (HTTP {$response->status()}).");
        }
    }
}
