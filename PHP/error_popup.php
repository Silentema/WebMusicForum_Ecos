<?php
function mostrarPopupError($mensaje, $destino = '2inicioSesion.html')
{
    $destino = basename($destino);

    if (!preg_match('/^[a-zA-Z0-9_.-]+\.(html|php)$/', $destino)) {
        $destino = '2inicioSesion.html';
    }

    $mensajeJson = json_encode(
        $mensaje,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
    $destinoJson = json_encode(
        pathinfo($destino, PATHINFO_EXTENSION) === 'php'
            ? $destino
            : '../' . $destino,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title>';
    echo '<script>alert(' . $mensajeJson . ');window.location.replace(' . $destinoJson . ');</script>';
    echo '</html>';
    exit();
}
