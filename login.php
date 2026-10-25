<?php
//Memoria digital para guardar datos de un usuario mientras navega en la página
session_start();
require 'conexion.php';

//Verificación de los datos mediante el html
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $correo = $_POST['correo'];
    $contrasena_ingresada = $_POST['contrasena'];

    try {
        //Buscar al usuario SOLO por su correo
        $sql = "SELECT * FROM usuario WHERE email = :correo";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();

        //conversion de la consulta a un arreglo asociativo para poder acceder a los datos
        $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);

        //Verificación de la contraseña ingresada con la contraseña almacenada en la base de datos
        if ($usuario_encontrado && password_verify($contrasena_ingresada, $usuario_encontrado['contraseña_hash'])) {
            
            //El inicio de sesión es correcto, se guardan los datos del usuario en la sesión
            $_SESSION['id_usuario'] = $usuario_encontrado['id_usuario'];
            $_SESSION['nombre_usuario'] = $usuario_encontrado['nombre'];
            $_SESSION['rol'] = $usuario_encontrado['id_rol'];

            //Reedireción a la página principal después de un inicio de sesión exitoso
            header("Location: 3main.html");
            exit();
            
        } else {
            // Si no coincide, le decimos que las credenciales son incorrectas
            echo "Credenciales incorrectas. Verifica tu correo y contraseña.";
        }

    } catch (PDOException $e) {
        exit("Error en el login: " . $e->getMessage());
    }
}
?>