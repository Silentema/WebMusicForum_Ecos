<?php
$host = "localhost";
$baseDatos = "red_ecos";
$usuario = "root";
$password = "";
$puerto = "3307";

try {
    $conexion = new PDO(
        "mysql:host=$host;port=$puerto;dbname=$baseDatos;charset=utf8mb4",$usuario,$password
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("No fue posible conectar con la base de datos.");
}