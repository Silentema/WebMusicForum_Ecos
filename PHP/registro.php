<?php
require_once __DIR__ . '/error_popup.php';
require __DIR__ . '/conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitización básica y obtención de datos
    $correo               = trim((string)($_POST['correo'] ?? ''));
    $nombre               = trim(strip_tags((string)($_POST['nombre'] ?? '')));
    $genero               = trim((string)($_POST['genero'] ?? ''));
    $day                  = trim((string)($_POST['day'] ?? ''));
    $month                = trim((string)($_POST['month'] ?? ''));
    $year                 = trim((string)($_POST['year'] ?? ''));
    $contrasena           = (string)($_POST['contrasena'] ?? '');
    $confirmar_contrasena = (string)($_POST['confirmar_contrasena'] ?? '');

    // 1. Validar que no haya campos vacíos
    if ($correo === '' || $nombre === '' || $genero === '' || $day === '' || $month === '' || $year === '' || $contrasena === '') {
        mostrarPopupError("Por favor completa todos los campos del formulario.", 'index.html');
    }

    // 2. Validar longitud del nombre (mínimo 2 y máximo 100 caracteres)
    if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 100) {
        mostrarPopupError("El nombre debe tener entre 2 y 100 caracteres.", 'index.html');
    }

    // 3. Validar formato de correo electrónico
    $correo = strtolower($correo);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        mostrarPopupError("El correo electrónico ingresado no tiene un formato válido.", 'index.html');
    }

    // 4. Validar género permitido (lista blanca)
    $generosPermitidos = ['masculino', 'femenino', 'otro'];
    if (!in_array($genero, $generosPermitidos, true)) {
        mostrarPopupError("El género seleccionado no es válido.", 'index.html');
    }

    // 5. Validar fecha de nacimiento real con checkdate(mes, día, año)
    $d = (int)$day;
    $m = (int)$month;
    $y = (int)$year;
    if (!checkdate($m, $d, $y)) {
        mostrarPopupError("La fecha de nacimiento seleccionada no es válida.", 'index.html');
    }

    // 6. Validar edad mínima (al menos 12 años)
    $fechaNacimientoObj = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $d));
    $hoy = new DateTime();
    $edad = $hoy->diff($fechaNacimientoObj)->y;
    if ($fechaNacimientoObj > $hoy || $edad < 12) {
        mostrarPopupError("Debes tener al menos 12 años para registrarte en la plataforma.", 'index.html');
    }

    // 7. Validar longitud y coincidencia de contraseñas
    if (strlen($contrasena) < 6) {
        mostrarPopupError("La contraseña debe tener al menos 6 caracteres.", 'index.html');
    }
    if ($contrasena !== $confirmar_contrasena) {
        mostrarPopupError("Las contraseñas no coinciden. Por favor verifica.", 'index.html');
    }

    try {
        // 8. Validar si el correo ya existe en la base de datos antes de registrar
        $stmtCheck = $conexion->prepare("SELECT id_usuario FROM usuario WHERE email = :correo LIMIT 1");
        $stmtCheck->bindParam(':correo', $correo);
        $stmtCheck->execute();
        if ($stmtCheck->fetch()) {
            mostrarPopupError("El correo '$correo' ya está registrado. Inicia sesión o utiliza otro correo.", 'index.html');
        }

        $fecha_nacimiento = sprintf('%04d-%02d-%02d', $y, $m, $d);
        $password_hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $id_rol = 1; // Rol de usuario normal

        $sql = "INSERT INTO usuario (id_rol, nombre, email, contraseña_hash, fecha_nacimiento, genero) 
                VALUES (:rol, :nom, :correo, :pass, :fecha, :gen)";
        
        $stmt = $conexion->prepare($sql);

        $stmt->bindParam(':rol', $id_rol, PDO::PARAM_INT);
        $stmt->bindParam(':nom', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':pass', $password_hash);
        $stmt->bindParam(':fecha', $fecha_nacimiento);
        $stmt->bindParam(':gen', $genero);

        $stmt->execute();

        header("Location: ../2inicioSesion.html");
        exit();

    } catch (PDOException $e) {
        error_log("Error en registro: " . $e->getMessage());
        mostrarPopupError(
            "Ocurrió un error al procesar el registro. Intenta de nuevo más tarde.",
            'index.html'
        );
    }
} else {
    header("Location: ../index.html");
    exit();
}
?>
