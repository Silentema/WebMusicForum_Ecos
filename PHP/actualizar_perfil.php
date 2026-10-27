
<?php
session_start();
require_once 'error_popup.php';
require_once 'conexion.php';

if (
    !isset($_SESSION['id_usuario']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: 2inicioSesion.html');
    exit();
}

$id_usuario = (int) $_SESSION['id_usuario'];
$passwordModificada = false;
$referencia = $_SERVER['HTTP_REFERER'] ?? '';
$ruta = parse_url($referencia, PHP_URL_PATH);
$pagina = $ruta ? basename($ruta) : '6perfilPropio.php';

if (
    !$pagina ||
    !preg_match('/^[a-zA-Z0-9_.-]+\.php$/', $pagina)
) {
    $pagina = '6perfilPropio.php';
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Comprobar que la cuenta existe y conservar sus datos actuales.
    $stmt = $conexion->prepare(
        'SELECT nombre, alias, email, pais, genero, bio, avatar, banner,
                contraseña_hash
         FROM usuario
         WHERE id_usuario = :id'
    );
    $stmt->execute([':id' => $id_usuario]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        throw new RuntimeException('No se encontró la cuenta del usuario.');
    }

    $campos = [];
    $parametros = [];

    // Solo se actualizan los campos que envía el formulario.
    $camposPermitidos = [
        'nombre',
        'alias',
        'email',
        'pais',
        'genero',
        'bio'
    ];

    foreach ($camposPermitidos as $campo) {
        if (!array_key_exists($campo, $_POST)) {
            continue;
        }

        $valor = trim((string) $_POST[$campo]);

        if ($campo === 'email') {
            $valor = strtolower($valor);

            if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'El correo electrónico no es válido.'
                );
            }

            // Evitar que dos cuentas tengan el mismo correo.
            $comprobar = $conexion->prepare(
                'SELECT id_usuario
                 FROM usuario
                 WHERE email = :email
                   AND id_usuario <> :id
                 LIMIT 1'
            );

            $comprobar->execute([
                ':email' => $valor,
                ':id' => $id_usuario
            ]);

            if ($comprobar->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'Ese correo ya está asociado a otra cuenta.'
                );
            }
        }

        if ($campo === 'nombre' && $valor === '') {
            throw new RuntimeException('El nombre no puede estar vacío.');
        }

        if ($campo === 'genero') {
            $generosPermitidos = [
                'masculino',
                'femenino',
                'otro',
                'prefiero-no-decir'
            ];

            if (!in_array($valor, $generosPermitidos, true)) {
                throw new RuntimeException(
                    'El género seleccionado no es válido.'
                );
            }
        }

        $campos[] = "$campo = :$campo";
        $parametros[":$campo"] = $valor;
    }

    $camposPassword = [
        'current_password',
        'new_password',
        'confirm_password'
    ];
    $passwordEnviado = false;

    foreach ($camposPassword as $campo) {
        if (array_key_exists($campo, $_POST)) {
            $passwordEnviado = true;
            break;
        }
    }

    if ($passwordEnviado) {
        foreach ($camposPassword as $campo) {
            if (
                !isset($_POST[$campo]) ||
                !is_string($_POST[$campo]) ||
                $_POST[$campo] === ''
            ) {
                throw new RuntimeException(
                    'Completa los tres campos para cambiar la contraseña.'
                );
            }
        }

        if (
            empty($actual['contraseña_hash']) ||
            !password_verify(
                $_POST['current_password'],
                $actual['contraseña_hash']
            )
        ) {
            throw new RuntimeException(
                'La contraseña actual es incorrecta.'
            );
        }

        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            throw new RuntimeException(
                'La nueva contraseña y su confirmación no coinciden.'
            );
        }

        $campos[] = 'contraseña_hash = :password_hash';
        $parametros[':password_hash'] = password_hash(
            $_POST['new_password'],
            PASSWORD_DEFAULT
        );
        $passwordModificada = true;
    }

    // Carpeta física donde se guardan las imágenes.
    $directorioImagenes = __DIR__ . DIRECTORY_SEPARATOR . 'Image'
        . DIRECTORY_SEPARATOR;

    if (!is_dir($directorioImagenes)) {
        if (
            !mkdir($directorioImagenes, 0755, true) &&
            !is_dir($directorioImagenes)
        ) {
            throw new RuntimeException(
                'No se pudo crear la carpeta Image.'
            );
        }
    }

    if (!is_writable($directorioImagenes)) {
        throw new RuntimeException(
            'La carpeta Image no tiene permisos de escritura.'
        );
    }

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    // Nombre del campo del formulario => columna de la base de datos.
    $archivos = [
        'avatar' => 'avatar',
        'cover'  => 'banner'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($archivos as $campoArchivo => $campoBD) {
        if (!isset($_FILES[$campoArchivo])) {
            continue;
        }

        $archivo = $_FILES[$campoArchivo];

        // Si no se seleccionó una imagen, se conserva la existente.
        if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $erroresSubida = [
                UPLOAD_ERR_INI_SIZE =>
                    'La imagen supera el límite permitido por PHP.',
                UPLOAD_ERR_FORM_SIZE =>
                    'La imagen supera el límite del formulario.',
                UPLOAD_ERR_PARTIAL =>
                    'La imagen se subió parcialmente.',
                UPLOAD_ERR_NO_TMP_DIR =>
                    'Falta la carpeta temporal de subida.',
                UPLOAD_ERR_CANT_WRITE =>
                    'El servidor no pudo escribir el archivo.',
                UPLOAD_ERR_EXTENSION =>
                    'Una extensión de PHP impidió subir la imagen.'
            ];

            $mensaje = $erroresSubida[$archivo['error']]
                ?? 'Error desconocido al subir la imagen.';

            throw new RuntimeException(
                $campoArchivo . ': ' . $mensaje
            );
        }

        // Límite de 5 MB por archivo.
        if ($archivo['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException(
                'Cada imagen debe pesar como máximo 5 MB.'
            );
        }

        if (
            empty($archivo['tmp_name']) ||
            !is_uploaded_file($archivo['tmp_name'])
        ) {
            throw new RuntimeException(
                'El archivo subido no es válido.'
            );
        }

        $mime = $finfo->file($archivo['tmp_name']);

        if (!isset($tiposPermitidos[$mime])) {
            throw new RuntimeException(
                'Formato no permitido. Usa JPG, PNG, WEBP o GIF.'
            );
        }

        $extension = $tiposPermitidos[$mime];
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = $directorioImagenes . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new RuntimeException(
                'No se pudo guardar la imagen en la carpeta Image.'
            );
        }

        // Guardar la ruta relativa en la base de datos.
        $campos[] = "$campoBD = :$campoBD";
        $parametros[":$campoBD"] = 'Image/' . $nombreArchivo;
    }

    // Actualizar únicamente los campos modificados.
    if (!empty($campos)) {
        $sql = 'UPDATE usuario SET ' . implode(', ', $campos)
             . ' WHERE id_usuario = :id';

        $parametros[':id'] = $id_usuario;

        $update = $conexion->prepare($sql);
        $update->execute($parametros);
    }

    if ($passwordModificada) {
        $_SESSION['password_actualizada'] = true;
    }

    // Actualizar los datos de sesión.
    if (isset($_POST['email'])) {
        $_SESSION['email'] = strtolower(trim($_POST['email']));

        if (isset($_SESSION['correo'])) {
            $_SESSION['correo'] = $_SESSION['email'];
        }
    }

    if (isset($_POST['nombre'])) {
        $_SESSION['nombre'] = trim($_POST['nombre']);
    }

    header(
        'Location: ' . ($passwordModificada ? 'ajustes.php' : $pagina)
    );
    exit();

} catch (Throwable $e) {
    error_log(
        'Error al actualizar el perfil: ' . $e->getMessage()
    );

    http_response_code(500);
    $destinoError = $e instanceof PDOException
        ? '3main.html'
        : $pagina;

    mostrarPopupError(
        "No se pudieron guardar los cambios: " . $e->getMessage(),
        $destinoError
    );
}
?>
