
<?php
session_start();
require_once 'conexion.php';

if (
    !isset($_SESSION['id_usuario']) ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: 2inicioSesion.html');
    exit();
}

$id_usuario = (int) $_SESSION['id_usuario'];

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener los datos actuales para conservar los campos no modificados.
    $stmt = $conexion->prepare(
        'SELECT nombre, alias, email, pais, genero, bio, avatar, banner
         FROM usuario
         WHERE id_usuario = :id'
    );
    $stmt->execute([':id' => $id_usuario]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        exit('No se encontró la cuenta del usuario.');
    }

    $campos = [];
    $parametros = [];

    // Actualizar únicamente los campos que realmente envía el formulario.
    $camposPermitidos = [
        'nombre',
        'alias',
        'email',
        'pais',
        'genero',
        'bio'
    ];

    foreach ($camposPermitidos as $campo) {
        if (array_key_exists($campo, $_POST)) {
            $valor = trim((string) $_POST[$campo]);

            if ($campo === 'email') {
                $valor = strtolower($valor);

                if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    exit('El correo electrónico no es válido.');
                }

                // Comprobar que otra cuenta no esté usando ese correo.
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
                    exit(
                        'Ese correo ya está asociado a otra cuenta. ' .
                        'Prueba con otro correo.'
                    );
                }
            }

            if ($campo === 'nombre' && $valor === '') {
                exit('El nombre no puede estar vacío.');
            }

            if ($campo === 'genero') {
                $generosPermitidos = [
                    'masculino',
                    'femenino',
                    'otro',
                    'prefiero-no-decir'
                ];

                if (!in_array($valor, $generosPermitidos, true)) {
                    exit('El género seleccionado no es válido.');
                }
            }

            $campos[] = "$campo = :$campo";
            $parametros[":$campo"] = $valor;
        }
    }

    // Procesar avatar y portada solo si se ha seleccionado un archivo.
    $directorioImagenes = __DIR__ . '/Image/';

    if (
        !is_dir($directorioImagenes) &&
        !mkdir($directorioImagenes, 0755, true) &&
        !is_dir($directorioImagenes)
    ) {
        throw new RuntimeException(
            'No se pudo crear la carpeta de imágenes.'
        );
    }

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $archivos = [
        'avatar' => 'avatar',
        'cover'  => 'banner'
    ];

    foreach ($archivos as $campoArchivo => $campoBD) {
        if (
            !isset($_FILES[$campoArchivo]) ||
            $_FILES[$campoArchivo]['error'] === UPLOAD_ERR_NO_FILE
        ) {
            continue;
        }

        $archivo = $_FILES[$campoArchivo];

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'No se pudo subir la imagen: ' . $campoArchivo
            );
        }

        // Límite de 5 MB por imagen.
        if ($archivo['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException(
                'Cada imagen debe pesar como máximo 5 MB.'
            );
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            throw new RuntimeException('El archivo subido no es válido.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($archivo['tmp_name']);

        if (!isset($tiposPermitidos[$mime])) {
            throw new RuntimeException(
                'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.'
            );
        }

        $extension = $tiposPermitidos[$mime];
        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = $directorioImagenes . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            throw new RuntimeException(
                'No se pudo guardar la imagen en el servidor.'
            );
        }

        $campos[] = "$campoBD = :$campoBD";
        $parametros[":$campoBD"] = 'Image/' . $nombreArchivo;
    }

    // Guardar todos los cambios enviados en una sola consulta.
    if (!empty($campos)) {
        $sql = 'UPDATE usuario SET ' . implode(', ', $campos)
             . ' WHERE id_usuario = :id';

        $parametros[':id'] = $id_usuario;

        $update = $conexion->prepare($sql);
        $update->execute($parametros);
    }

    // Actualizar los datos de sesión conocidos, si existen.
    if (isset($_POST['email'])) {
        $_SESSION['email'] = strtolower(trim($_POST['email']));

        if (isset($_SESSION['correo'])) {
            $_SESSION['correo'] = $_SESSION['email'];
        }
    }

    if (isset($_POST['nombre'])) {
        $_SESSION['nombre'] = trim($_POST['nombre']);
    }

    // Volver a la página desde la que se envió el formulario.
    $referencia = $_SERVER['HTTP_REFERER'] ?? '';
    $ruta = parse_url($referencia, PHP_URL_PATH);
    $pagina = $ruta ? basename($ruta) : '6perfilPropio.php';

    // Evitar redirigir a rutas inesperadas.
    if (
        !$pagina ||
        !preg_match('/^[a-zA-Z0-9_.-]+\.php$/', $pagina)
    ) {
        $pagina = '6perfilPropio.php';
    }

    header('Location: ' . $pagina);
    exit();

} catch (Throwable $e) {
    error_log('Error al actualizar el perfil: ' . $e->getMessage());

    http_response_code(500);
    echo 'No se pudieron guardar los cambios. Revisa el registro de errores del servidor.';
    exit();
}
?>
