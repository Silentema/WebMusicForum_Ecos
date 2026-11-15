<?php
require_once __DIR__ . '/error_popup.php';
require __DIR__ . '/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $correo = $_POST['correo'];
    $nombre = $_POST['nombre'];
    $genero = $_POST['genero'];

    $fecha_nacimiento = $_POST['year'] . '-' . $_POST['month'] . '-' . $_POST['day'];

    $password_hash = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);

    $id_rol = 1; 

    try {
        $sql = "INSERT INTO usuario (id_rol, nombre, email, contraseña_hash, fecha_nacimiento, genero) 
                VALUES (:rol, :nom, :correo, :pass, :fecha, :gen)";
        
        $stmt = $conexion->prepare($sql);

        $stmt->bindParam(':rol', $id_rol);
        $stmt->bindParam(':nom', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':pass', $password_hash);
        $stmt->bindParam(':fecha', $fecha_nacimiento);
        $stmt->bindParam(':gen', $genero);

        $stmt->execute();

        header("Location: ../2inicioSesion.html");
        exit();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        mostrarPopupError(
            "Error al registrar: " . $e->getMessage(),
            'index.html'
        );
    }
}
?>