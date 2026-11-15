<?php
/**
 * Candado para páginas de administrador.
 * Uso (primera línea de una página .php en la raíz del proyecto):
 *     <?php require __DIR__ . '/PHP/solo_admin.php'; ?>
 *
 * Si quien entra no inició sesión como administrador con la cuenta
 * administradora, se le muestra un aviso y se le regresa al inicio.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/admin.php';

if (empty($_SESSION['id_usuario'])) {
    header('Location: 2inicioSesion.html');
    exit();
}

if (!sesionEsAdministrador($conexion)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Acceso denegado</title>';
    echo '<script>alert("Esta sección es solo para administradores.");window.location.replace("3main.html");</script>';
    echo '</html>';
    exit();
}
