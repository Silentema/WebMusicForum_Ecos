<?php
header('Content-Type: application/json; charset=utf-8');

if (!empty($_GET['artista'])) {
    $url = 'https://api.deezer.com/search/artist?limit=1&q=' . urlencode($_GET['artista']);
} elseif (!empty($_GET['genero'])) {
    $url = 'https://api.deezer.com/genre/' . (int) $_GET['genero'] . '/artists';
} else {
    $url = 'https://api.deezer.com/genre';
}

$json = @file_get_contents($url);
if ($json === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo consultar Deezer']);
    exit;
}
echo $json;