<?php
// API para gestión de publicaciones, likes y comentarios
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada']);
    exit();
}

require_once 'conexion.php';

$id_usuario = (int)$_SESSION['id_usuario'];
$accion = $_REQUEST['accion'] ?? '';

// Función auxiliar para subir imagen de forma segura
function procesarSubidaImagen($file, $subcarpeta = 'posts') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($tiposPermitidos[$mime])) {
        throw new Exception('Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10 MB máx
        throw new Exception('La imagen es demasiado grande. Máximo 10 MB.');
    }

    $ext = $tiposPermitidos[$mime];
    $dirDestino = __DIR__ . DIRECTORY_SEPARATOR . 'Image' . DIRECTORY_SEPARATOR . $subcarpeta . DIRECTORY_SEPARATOR;
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    $nombreArchivo = uniqid($subcarpeta . '_' . time() . '_', true) . '.' . $ext;
    $rutaCompleta = $dirDestino . $nombreArchivo;

    if (!move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
        throw new Exception('Error al guardar la imagen en el servidor.');
    }

    return 'Image/' . $subcarpeta . '/' . $nombreArchivo;
}

try {
    switch ($accion) {
        case 'crear_publicacion':
            $titulo = trim($_POST['titulo'] ?? '');
            $texto = trim($_POST['texto'] ?? '');
            $imagenRuta = null;

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenRuta = procesarSubidaImagen($_FILES['imagen'], 'posts');
            }

            if ($titulo === '' && $texto === '' && $imagenRuta === null) {
                throw new Exception('La publicación debe contener al menos un título, descripción o imagen.');
            }

            $sql = "INSERT INTO publicacion (id_usuario, titulo, texto, imagen, fecha_creacion) 
                    VALUES (:id_usuario, :titulo, :texto, :imagen, NOW())";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $id_usuario,
                ':titulo'     => $titulo !== '' ? $titulo : null,
                ':texto'      => $texto !== '' ? $texto : null,
                ':imagen'     => $imagenRuta
            ]);

            $id_pub = $conexion->lastInsertId();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Publicación creada exitosamente',
                'id_publicacion' => $id_pub
            ]);
            break;

        case 'editar_publicacion':
            $id_pub = (int)($_POST['id_publicacion'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $texto = trim($_POST['texto'] ?? '');
            $eliminar_imagen = !empty($_POST['eliminar_imagen']);

            // Verificar pertenencia
            $check = $conexion->prepare("SELECT * FROM publicacion WHERE id_publicacion = :id AND id_usuario = :user");
            $check->execute([':id' => $id_pub, ':user' => $id_usuario]);
            $publicacion = $check->fetch(PDO::FETCH_ASSOC);

            if (!$publicacion) {
                throw new Exception('Publicación no encontrada o no tienes permisos para editarla.');
            }

            $imagenRuta = $publicacion['imagen'];

            if ($eliminar_imagen && $imagenRuta) {
                $archivoFisico = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagenRuta);
                if (file_exists($archivoFisico)) {
                    @unlink($archivoFisico);
                }
                $imagenRuta = null;
            }

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                // Borrar imagen anterior si existía
                if ($imagenRuta) {
                    $archivoFisico = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagenRuta);
                    if (file_exists($archivoFisico)) {
                        @unlink($archivoFisico);
                    }
                }
                $imagenRuta = procesarSubidaImagen($_FILES['imagen'], 'posts');
            }

            if ($titulo === '' && $texto === '' && $imagenRuta === null) {
                throw new Exception('La publicación no puede quedar vacía.');
            }

            $upd = $conexion->prepare("UPDATE publicacion SET titulo = :titulo, texto = :texto, imagen = :imagen WHERE id_publicacion = :id");
            $upd->execute([
                ':titulo' => $titulo !== '' ? $titulo : null,
                ':texto'  => $texto !== '' ? $texto : null,
                ':imagen' => $imagenRuta,
                ':id'     => $id_pub
            ]);

            echo json_encode([
                'success' => true,
                'mensaje' => 'Publicación actualizada exitosamente',
                'publicacion' => [
                    'id_publicacion' => $id_pub,
                    'titulo' => $titulo,
                    'texto' => $texto,
                    'imagen' => $imagenRuta
                ]
            ]);
            break;

        case 'eliminar_publicacion':
            $id_pub = (int)($_POST['id_publicacion'] ?? 0);

            $check = $conexion->prepare("SELECT * FROM publicacion WHERE id_publicacion = :id AND id_usuario = :user");
            $check->execute([':id' => $id_pub, ':user' => $id_usuario]);
            $pub = $check->fetch(PDO::FETCH_ASSOC);

            if (!$pub) {
                throw new Exception('Publicación no encontrada o no tienes permiso para eliminarla.');
            }

            // 1. Borrar imagen física del post si existe
            if (!empty($pub['imagen'])) {
                $archivo = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pub['imagen']);
                if (file_exists($archivo)) {
                    @unlink($archivo);
                }
            }

            // 2. Borrar imágenes físicas de comentarios
            $coms = $conexion->prepare("SELECT imagen FROM comentario WHERE id_publicacion = :id AND imagen IS NOT NULL");
            $coms->execute([':id' => $id_pub]);
            while ($c = $coms->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($c['imagen'])) {
                    $archivoC = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $c['imagen']);
                    if (file_exists($archivoC)) {
                        @unlink($archivoC);
                    }
                }
            }

            // 3. Borrar registros dependientes
            $conexion->prepare("DELETE FROM publicacion_hashtag WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
            $conexion->prepare("DELETE FROM comentario WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
            $conexion->prepare("DELETE FROM reaccion WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
            $conexion->prepare("DELETE FROM publicacion WHERE id_publicacion = :id")->execute([':id' => $id_pub]);

            echo json_encode(['success' => true, 'mensaje' => 'Publicación eliminada correctamente']);
            break;

        case 'toggle_like':
            $id_pub = (int)($_POST['id_publicacion'] ?? 0);

            $check = $conexion->prepare("SELECT id_reaccion FROM reaccion WHERE id_publicacion = :pub AND id_usuario = :user");
            $check->execute([':pub' => $id_pub, ':user' => $id_usuario]);
            $reaccion = $check->fetch(PDO::FETCH_ASSOC);

            if ($reaccion) {
                // Quitar like
                $del = $conexion->prepare("DELETE FROM reaccion WHERE id_reaccion = :id");
                $del->execute([':id' => $reaccion['id_reaccion']]);
                $liked = false;
            } else {
                // Dar like
                $ins = $conexion->prepare("INSERT INTO reaccion (id_publicacion, id_usuario) VALUES (:pub, :user)");
                $ins->execute([':pub' => $id_pub, ':user' => $id_usuario]);
                $liked = true;
            }

            // Contar total actual
            $cnt = $conexion->prepare("SELECT COUNT(*) FROM reaccion WHERE id_publicacion = :pub");
            $cnt->execute([':pub' => $id_pub]);
            $totalLikes = (int)$cnt->fetchColumn();

            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'total_likes' => $totalLikes
            ]);
            break;

        case 'obtener_comentarios':
            $id_pub = (int)($_GET['id_publicacion'] ?? 0);

            $stmt = $conexion->prepare("
                SELECT c.id_comentario, c.id_publicacion, c.id_usuario, c.contenido, c.imagen, c.fecha_creacion,
                       u.nombre, u.alias, u.avatar
                FROM comentario c
                JOIN usuario u ON c.id_usuario = u.id_usuario
                WHERE c.id_publicacion = :pub
                ORDER BY c.id_comentario ASC
            ");
            $stmt->execute([':pub' => $id_pub]);
            $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear fechas y permisos
            foreach ($comentarios as &$c) {
                $c['es_propio'] = ((int)$c['id_usuario'] === $id_usuario);
                $c['avatar'] = !empty($c['avatar']) ? $c['avatar'] : 'Image/cuenta.png';
                $fechaObj = new DateTime($c['fecha_creacion'] ?? 'now');
                $c['fecha_formateada'] = $fechaObj->format('d / M / Y H:i');
            }

            echo json_encode([
                'success' => true,
                'comentarios' => $comentarios
            ]);
            break;

        case 'crear_comentario':
            $id_pub = (int)($_POST['id_publicacion'] ?? 0);
            $contenido = trim($_POST['contenido'] ?? '');
            $imagenRuta = null;

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenRuta = procesarSubidaImagen($_FILES['imagen'], 'comments');
            }

            if ($contenido === '' && $imagenRuta === null) {
                throw new Exception('El comentario no puede estar vacío.');
            }

            // Comprobar existencia del post
            $check = $conexion->prepare("SELECT id_publicacion FROM publicacion WHERE id_publicacion = :id");
            $check->execute([':id' => $id_pub]);
            if (!$check->fetch()) {
                throw new Exception('La publicación no existe.');
            }

            $stmt = $conexion->prepare("
                INSERT INTO comentario (id_publicacion, id_usuario, contenido, imagen, fecha_creacion)
                VALUES (:pub, :user, :cont, :img, NOW())
            ");
            $stmt->execute([
                ':pub'  => $id_pub,
                ':user' => $id_usuario,
                ':cont' => $contenido !== '' ? $contenido : null,
                ':img'  => $imagenRuta
            ]);

            $id_com = $conexion->lastInsertId();

            // Obtener datos del usuario
            $uStmt = $conexion->prepare("SELECT nombre, alias, avatar FROM usuario WHERE id_usuario = :id");
            $uStmt->execute([':id' => $id_usuario]);
            $uData = $uStmt->fetch(PDO::FETCH_ASSOC);

            // Total de comentarios
            $totStmt = $conexion->prepare("SELECT COUNT(*) FROM comentario WHERE id_publicacion = :pub");
            $totStmt->execute([':pub' => $id_pub]);
            $totalComentarios = (int)$totStmt->fetchColumn();

            $fechaObj = new DateTime();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Comentario publicado',
                'total_comentarios' => $totalComentarios,
                'comentario' => [
                    'id_comentario' => $id_com,
                    'id_publicacion' => $id_pub,
                    'id_usuario' => $id_usuario,
                    'contenido' => $contenido,
                    'imagen' => $imagenRuta,
                    'fecha_formateada' => $fechaObj->format('d / M / Y H:i'),
                    'nombre' => $uData['nombre'] ?? 'Usuario',
                    'alias' => $uData['alias'] ?? '',
                    'avatar' => !empty($uData['avatar']) ? $uData['avatar'] : 'Image/cuenta.png',
                    'es_propio' => true
                ]
            ]);
            break;

        case 'editar_comentario':
            $id_com = (int)($_POST['id_comentario'] ?? 0);
            $contenido = trim($_POST['contenido'] ?? '');
            $eliminar_imagen = !empty($_POST['eliminar_imagen']);

            $check = $conexion->prepare("SELECT * FROM comentario WHERE id_comentario = :id AND id_usuario = :user");
            $check->execute([':id' => $id_com, ':user' => $id_usuario]);
            $com = $check->fetch(PDO::FETCH_ASSOC);

            if (!$com) {
                throw new Exception('Comentario no encontrado o no tienes permisos para editarlo.');
            }

            $imagenRuta = $com['imagen'];

            if ($eliminar_imagen && $imagenRuta) {
                $archivoFisico = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagenRuta);
                if (file_exists($archivoFisico)) {
                    @unlink($archivoFisico);
                }
                $imagenRuta = null;
            }

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                if ($imagenRuta) {
                    $archivoFisico = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagenRuta);
                    if (file_exists($archivoFisico)) {
                        @unlink($archivoFisico);
                    }
                }
                $imagenRuta = procesarSubidaImagen($_FILES['imagen'], 'comments');
            }

            if ($contenido === '' && $imagenRuta === null) {
                throw new Exception('El comentario no puede quedar vacío.');
            }

            $upd = $conexion->prepare("UPDATE comentario SET contenido = :cont, imagen = :img WHERE id_comentario = :id");
            $upd->execute([
                ':cont' => $contenido !== '' ? $contenido : null,
                ':img'  => $imagenRuta,
                ':id'   => $id_com
            ]);

            echo json_encode([
                'success' => true,
                'mensaje' => 'Comentario editado correctamente',
                'comentario' => [
                    'id_comentario' => $id_com,
                    'contenido' => $contenido,
                    'imagen' => $imagenRuta
                ]
            ]);
            break;

        case 'eliminar_comentario':
            $id_com = (int)($_POST['id_comentario'] ?? 0);

            // Permitir borrar si es autor del comentario O autor del post
            $check = $conexion->prepare("
                SELECT c.id_comentario, c.imagen, c.id_usuario AS id_autor_comentario, 
                       p.id_usuario AS id_autor_post, c.id_publicacion
                FROM comentario c
                JOIN publicacion p ON c.id_publicacion = p.id_publicacion
                WHERE c.id_comentario = :id
            ");
            $check->execute([':id' => $id_com]);
            $com = $check->fetch(PDO::FETCH_ASSOC);

            if (!$com) {
                throw new Exception('Comentario no encontrado.');
            }

            if ((int)$com['id_autor_comentario'] !== $id_usuario && (int)$com['id_autor_post'] !== $id_usuario) {
                throw new Exception('No tienes permisos para eliminar este comentario.');
            }

            if (!empty($com['imagen'])) {
                $archivoFisico = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $com['imagen']);
                if (file_exists($archivoFisico)) {
                    @unlink($archivoFisico);
                }
            }

            $del = $conexion->prepare("DELETE FROM comentario WHERE id_comentario = :id");
            $del->execute([':id' => $id_com]);

            // Total actualizado
            $totStmt = $conexion->prepare("SELECT COUNT(*) FROM comentario WHERE id_publicacion = :pub");
            $totStmt->execute([':pub' => $com['id_publicacion']]);
            $totalComentarios = (int)$totStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'mensaje' => 'Comentario eliminado',
                'id_publicacion' => $com['id_publicacion'],
                'total_comentarios' => $totalComentarios
            ]);
            break;

        default:
            throw new Exception('Acción no reconocida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
