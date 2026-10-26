<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function enviarImagenAvatar($ruta)
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    $tiposPermitidos = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    if (!in_array($mime, $tiposPermitidos, true)) {
        header('Location: Image/cuenta.png');
        exit();
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit();
}

$avatarPredeterminado = __DIR__ . DIRECTORY_SEPARATOR
    . 'Image' . DIRECTORY_SEPARATOR . 'cuenta.png';

if (!isset($_SESSION['id_usuario'])) {
    enviarImagenAvatar($avatarPredeterminado);
}

define('API_IMAGE_RESPONSE', true);
require_once __DIR__ . '/conexion.php';

try {
    $stmt = $conexion->prepare(
        'SELECT avatar
         FROM usuario
         WHERE id_usuario = :id'
    );
    $stmt->execute([':id' => (int) $_SESSION['id_usuario']]);
    $avatar = trim((string) $stmt->fetchColumn());

    $nombreArchivo = basename(str_replace('\\', '/', $avatar));
    $rutaAvatar = __DIR__ . DIRECTORY_SEPARATOR . 'Image'
        . DIRECTORY_SEPARATOR . $nombreArchivo;

    if ($nombreArchivo === '' || !is_file($rutaAvatar)) {
        enviarImagenAvatar($avatarPredeterminado);
    }

    enviarImagenAvatar($rutaAvatar);
} catch (PDOException $e) {
    error_log($e->getMessage());
    enviarImagenAvatar($avatarPredeterminado);
}