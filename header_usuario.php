<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['authenticated' => false]);
    exit();
}

define('API_JSON_RESPONSE', true);
require_once 'conexion.php';

try {
    $stmt = $conexion->prepare(
        'SELECT nombre, avatar
         FROM usuario
         WHERE id_usuario = :id'
    );
    $stmt->execute([':id' => (int) $_SESSION['id_usuario']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['authenticated' => false]);
        exit();
    }

    $avatar = trim((string) ($usuario['avatar'] ?? ''));
    $avatar = str_replace('\\', '/', $avatar);
    $imagePosition = stripos($avatar, 'Image/');

    if ($avatar !== '' && $imagePosition !== false) {
        $avatar = substr($avatar, $imagePosition);
    } elseif ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
        $avatar = 'Image/' . basename($avatar);
    }

    if (
        $avatar === '' ||
        (
            !preg_match('#^https?://#i', $avatar) &&
            !is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $avatar))
        )
    ) {
        $avatar = 'Image/cuenta.png';
    }

    echo json_encode([
        'authenticated' => true,
        'nombre' => $usuario['nombre'],
        'avatar' => $avatar
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'No se pudo cargar el usuario del encabezado.'
    ]);
}
