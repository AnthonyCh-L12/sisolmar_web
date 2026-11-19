<?php

$url      = 'https://www.gruposolmar.com.pe/see/webservice/rest/server.php';
$token    = '2423da63239c8a12a087321d2deefed6';
$courseId = 120;      // <-- confirma el ID del curso
$dryRun   = false;     // true = simulación, false = real
$limit    = 0;        // 0 = todos
$archivo  = __DIR__ . '/usernames.txt';

if (!is_file($archivo)) { exit("No existe el archivo: $archivo\n"); }

// Lee los usernames (uno por línea), como texto para conservar ceros iniciales
$usernames = array_values(array_unique(array_filter(
    array_map('trim', file($archivo, FILE_IGNORE_NEW_LINES))
)));

if ($limit > 0) { $usernames = array_slice($usernames, 0, $limit); }

echo "Usernames leídos: " . count($usernames) . " | Curso: $courseId\n";

function ws($fn, $params) {
    global $url, $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // solo para esta tarea puntual
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_POSTFIELDS     => http_build_query(array_merge($params, [
            'wstoken'            => $token,
            'wsfunction'         => $fn,
            'moodlewsrestformat' => 'json',
        ])),
    ]);
    $res = curl_exec($ch);
    if ($res === false) { exit("cURL error: " . curl_error($ch) . "\n"); }
    curl_close($ch);
    $json = json_decode($res, true);
    if ($json === null && $res !== 'null') { exit("Respuesta no JSON:\n" . substr($res, 0, 500) . "\n"); }
    return $json;
}

// 1) username -> id
$users = [];
foreach (array_chunk($usernames, 50) as $lote) {
    $r = ws('core_user_get_users_by_field', ['field' => 'username', 'values' => $lote]);
    if (isset($r['exception'])) { exit("Error: {$r['message']}\n"); }
    $users = array_merge($users, $r);
}

$encontrados = array_column($users, 'username');
$noExisten   = array_diff($usernames, $encontrados);
echo "Encontrados: " . count($users) . " | No existen: " . count($noExisten) . "\n";
if ($noExisten) { echo "No existen: " . implode(', ', $noExisten) . "\n"; }

if ($dryRun) { exit("DRY_RUN: no se desmatriculó a nadie.\n"); }

// 2) desmatricular en lotes de 50
foreach (array_chunk($users, 50) as $lote) {
    $p = [];
    foreach ($lote as $i => $u) {
        $p["enrolments[$i][userid]"]   = $u['id'];
        $p["enrolments[$i][courseid]"] = $courseId;
    }
    $r = ws('enrol_manual_unenrol_users', $p);
    echo ($r === null) ? "Lote OK (" . count($lote) . ")\n" : "Respuesta: " . json_encode($r) . "\n";
}