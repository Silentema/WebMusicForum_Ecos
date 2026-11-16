<?php
// Proxy hacia la YouTube Data API v3 (search.list).
// La llave vive solo en el servidor (config_youtube.php) y nunca llega al navegador.
header('Content-Type: application/json; charset=utf-8');

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

$archivoConfig = __DIR__ . '/config_youtube.php';
if (!file_exists($archivoConfig)) {
    responder(['error' => 'Falta PHP/config_youtube.php con la llave de YouTube'], 500);
}
require_once $archivoConfig;
if (!defined('YOUTUBE_API_KEY') || YOUTUBE_API_KEY === '') {
    responder(['error' => 'YOUTUBE_API_KEY no está configurada'], 500);
}

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q, 'UTF-8') < 2 || mb_strlen($q, 'UTF-8') > 100) {
    responder(['error' => 'Búsqueda no válida'], 400);
}

// Caché en disco: cada búsqueda cuesta 100 unidades de la cuota diaria (10,000 por defecto)
$dirCache = __DIR__ . '/cache_youtube/';
if (!is_dir($dirCache)) {
    @mkdir($dirCache, 0755, true);
}
$archivoCache = $dirCache . sha1(mb_strtolower($q, 'UTF-8')) . '.json';
$vigencia = 7 * 24 * 60 * 60; // 7 días

if (file_exists($archivoCache) && (time() - filemtime($archivoCache)) < $vigencia) {
    echo file_get_contents($archivoCache);
    exit;
}

$url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
    'part'            => 'snippet',
    'type'            => 'video',
    'videoEmbeddable' => 'true',
    'videoCategoryId' => '10',      // Música
    'safeSearch'      => 'moderate',
    'maxResults'      => 1,
    'q'               => $q,
    'key'             => YOUTUBE_API_KEY,
]);

$contexto = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
$respuesta = @file_get_contents($url, false, $contexto);
if ($respuesta === false) {
    responder(['error' => 'No se pudo consultar YouTube'], 502);
}

$datos = json_decode($respuesta, true);
if (!is_array($datos) || isset($datos['error'])) {
    // Aquí caen llave inválida, cuota agotada, etc. No se muestra el detalle al navegador.
    error_log('YouTube API: ' . substr($respuesta, 0, 300));
    responder(['error' => 'YouTube no respondió correctamente'], 502);
}

if (empty($datos['items'][0]['id']['videoId'])) {
    responder(['error' => 'Sin resultados'], 404);
}

$item = $datos['items'][0];
$salida = json_encode([
    'id'     => $item['id']['videoId'],
    'titulo' => html_entity_decode($item['snippet']['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    'canal'  => html_entity_decode($item['snippet']['channelTitle'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
], JSON_UNESCAPED_UNICODE);

@file_put_contents($archivoCache, $salida);
echo $salida;