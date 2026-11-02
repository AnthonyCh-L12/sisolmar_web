<?php

$venvPython = PHP_OS_FAMILY === 'Windows'
    ? base_path('.venv/Scripts/python.exe')
    : base_path('.venv/bin/python');

return [
    // En desarrollo se usa el entorno virtual del proyecto. Si no existe,
    // se intenta con el lanzador de Windows o Python 3 en Linux.
    'python' => env('FIRMAHUELLA_PYTHON') ?: (file_exists($venvPython)
        ? $venvPython
        : (PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3')),
    'timeout' => (int) env('FIRMAHUELLA_TIMEOUT', 120),
    'upload_proxy' => [
        'url' => env('BIBLIOTECA_GRAFICA_UPLOAD_URL', 'http://190.116.178.163/apps/api/file-control/charge_file.php'),
        // Mantiene compatibilidad con el token que ya usa DjController para fotos y DNI.
        'token' => env('BIBLIOTECA_GRAFICA_UPLOAD_TOKEN', '457862h45hj7u5126h58d2s51s2s'),
    ],
    'destinos' => [
        'dj' => '\\\\192.168.10.5\\Extranet_2024\\apps\\sisolmar\\storage\\app\\dj\\2026',
        // charge_file.php concatena estas rutas con Biblioteca_Grafica en el servidor 10.5.
        'firmas' => 'FIRMAS\\PERSONAL',
        'huellas' => 'HUELLAS_DIGITALES\\PERSONAL',
    ],
];
