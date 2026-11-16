<?php
require_once __DIR__ . '/error_popup.php';

$host = "sql309.infinityfree.com";
$baseDatos = "if0_43452573_red_ecos";
$usuario = "if0_43452573";
$password = "8ihuGc78xyuP3I";
$puerto = "3306";

try {
    $conexion = new PDO(
        "mysql:host=$host;port=$puerto;dbname=$baseDatos;charset=utf8mb4",$usuario,$password
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage());

    if (defined('API_IMAGE_RESPONSE') && API_IMAGE_RESPONSE) {
        header('Location: ../Image/cuenta.png');
        exit();
    }

    if (defined('API_JSON_RESPONSE') && API_JSON_RESPONSE) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'No fue posible conectar con la base de datos.'
        ]);
        exit();
    }

    mostrarPopupError(
        "No fue posible conectar con la base de datos."
    );
}