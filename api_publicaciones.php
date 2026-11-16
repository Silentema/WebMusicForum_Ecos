<?php
// API para gestión de publicaciones, likes y comentarios
ob_start();
define('API_JSON_RESPONSE', true);

function responderJSON($data, $statusCode = 200) {
    if (ob_get_length()) ob_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    responderJSON(['success' => false, 'error' => 'Sesión no iniciada'], 401);
}

require_once __DIR__ . '/PHP/conexion.php';

// Asegurar que el esquema de la base de datos tenga los campos y auto_increment necesarios
function asegurarEsquemaBaseDatos($conexion) {
    static $listo = false;
    if ($listo) return;
    try {
        // Asegurar AUTO_INCREMENT en publicacion
        $colPub = $conexion->query("SHOW COLUMNS FROM publicacion WHERE Field = 'id_publicacion'")->fetch(PDO::FETCH_ASSOC);
        if ($colPub && stripos($colPub['Extra'] ?? '', 'auto_increment') === false) {
            $conexion->exec("ALTER TABLE publicacion MODIFY id_publicacion INT(11) NOT NULL AUTO_INCREMENT");
        }

        // Asegurar columnas titulo e imagen en publicacion
        $colsPub = $conexion->query("SHOW COLUMNS FROM publicacion")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('titulo', $colsPub)) {
            $conexion->exec("ALTER TABLE publicacion ADD COLUMN titulo VARCHAR(255) NULL AFTER id_usuario");
        }
        if (!in_array('imagen', $colsPub)) {
            $conexion->exec("ALTER TABLE publicacion ADD COLUMN imagen VARCHAR(255) NULL AFTER texto");
        }
        // Moderación: toda publicación nueva queda 'pendiente' hasta que un administrador la apruebe
        if (!in_array('estado', $colsPub)) {
            $conexion->exec("ALTER TABLE publicacion ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'pendiente' AFTER imagen");
        }

        // Asegurar AUTO_INCREMENT en comentario
        $colCom = $conexion->query("SHOW COLUMNS FROM comentario WHERE Field = 'id_comentario'")->fetch(PDO::FETCH_ASSOC);
        if ($colCom && stripos($colCom['Extra'] ?? '', 'auto_increment') === false) {
            $conexion->exec("ALTER TABLE comentario MODIFY id_comentario INT(11) NOT NULL AUTO_INCREMENT");
        }

        // Asegurar columna imagen en comentario
        $colsCom = $conexion->query("SHOW COLUMNS FROM comentario")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('imagen', $colsCom)) {
            $conexion->exec("ALTER TABLE comentario ADD COLUMN imagen VARCHAR(255) NULL AFTER contenido");
        }

        // Asegurar AUTO_INCREMENT en reaccion
        $colRea = $conexion->query("SHOW COLUMNS FROM reaccion WHERE Field = 'id_reaccion'")->fetch(PDO::FETCH_ASSOC);
        if ($colRea && stripos($colRea['Extra'] ?? '', 'auto_increment') === false) {
            $conexion->exec("ALTER TABLE reaccion MODIFY id_reaccion INT(11) NOT NULL AUTO_INCREMENT");
        }

        // Asegurar AUTO_INCREMENT en relacion_usuario (seguir / bloquear)
        $colRel = $conexion->query("SHOW COLUMNS FROM relacion_usuario WHERE Field = 'id_relacion'")->fetch(PDO::FETCH_ASSOC);
        if ($colRel && stripos($colRel['Extra'] ?? '', 'auto_increment') === false) {
            $conexion->exec("ALTER TABLE relacion_usuario MODIFY id_relacion INT(11) NOT NULL AUTO_INCREMENT");
        }

        // Asegurar AUTO_INCREMENT en hashtag y publicacion_hashtag
        foreach (['hashtag' => 'id_hashtag', 'publicacion_hashtag' => 'id_pub_hash'] as $tabla => $campo) {
            $col = $conexion->query("SHOW COLUMNS FROM $tabla WHERE Field = '$campo'")->fetch(PDO::FETCH_ASSOC);
            if ($col && stripos($col['Extra'] ?? '', 'auto_increment') === false) {
                // id_hashtag es referenciado por una llave foránea: se desactiva la revisión un momento
                $conexion->exec("SET FOREIGN_KEY_CHECKS = 0");
                $conexion->exec("ALTER TABLE $tabla MODIFY $campo INT(11) NOT NULL AUTO_INCREMENT");
                $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");
            }
        }
    } catch (Throwable $e) {
        $conexion->exec("SET FOREIGN_KEY_CHECKS = 1");
        error_log("Error al asegurar esquema de base de datos: " . $e->getMessage());
    }
    $listo = true;
}

asegurarEsquemaBaseDatos($conexion);

$id_usuario = (int)$_SESSION['id_usuario'];
$accion = $_REQUEST['accion'] ?? '';

require_once __DIR__ . '/PHP/admin.php';
$es_admin = sesionEsAdministrador($conexion);

// Una publicación 'pendiente' solo la ven su autor y los administradores
function verificarPublicacionVisible($conexion, $id_pub, $id_usuario, $es_admin) {
    $stmt = $conexion->prepare("SELECT id_usuario, estado FROM publicacion WHERE id_publicacion = :id");
    $stmt->execute([':id' => $id_pub]);
    $pub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pub || ($pub['estado'] !== 'aprobada' && (int)$pub['id_usuario'] !== $id_usuario && !$es_admin)) {
        throw new Exception('La publicación no existe o todavía no está disponible.');
    }
    return $pub;
}

// Borra una publicación con todo lo que depende de ella (imágenes, comentarios, likes, hashtags)
function borrarPublicacionCompleta($conexion, array $pub) {
    $id_pub = (int)$pub['id_publicacion'];
    $borrarArchivo = function ($ruta) {
        if (empty($ruta)) return;
        $archivo = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
        if (file_exists($archivo)) {
            @unlink($archivo);
        }
    };

    $borrarArchivo($pub['imagen'] ?? null);

    $coms = $conexion->prepare("SELECT imagen FROM comentario WHERE id_publicacion = :id AND imagen IS NOT NULL");
    $coms->execute([':id' => $id_pub]);
    while ($c = $coms->fetch(PDO::FETCH_ASSOC)) {
        $borrarArchivo($c['imagen']);
    }

    $conexion->prepare("DELETE FROM publicacion_hashtag WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
    $conexion->prepare("DELETE FROM comentario WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
    $conexion->prepare("DELETE FROM reaccion WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
    $conexion->prepare("DELETE FROM publicacion WHERE id_publicacion = :id")->execute([':id' => $id_pub]);
}

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

// Extrae los #hashtags de un texto (letras, números y _), sin repetir
function extraerHashtags($texto) {
    preg_match_all('/#([\p{L}\p{N}_]{1,50})/u', (string)$texto, $m);
    $unicos = [];
    foreach ($m[1] as $tag) {
        $clave = mb_strtolower($tag, 'UTF-8');
        if (!isset($unicos[$clave])) {
            $unicos[$clave] = $tag;
        }
    }
    return array_values($unicos);
}

// Guarda (reemplaza) los hashtags de una publicación a partir de su título y texto
function guardarHashtags($conexion, $id_pub, $titulo, $texto) {
    $conexion->prepare("DELETE FROM publicacion_hashtag WHERE id_publicacion = :id")->execute([':id' => $id_pub]);

    $buscar   = $conexion->prepare("SELECT id_hashtag FROM hashtag WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
    $insertar = $conexion->prepare("INSERT INTO hashtag (nombre) VALUES (:nombre)");
    $enlazar  = $conexion->prepare("INSERT INTO publicacion_hashtag (id_publicacion, id_hashtag) VALUES (:pub, :tag)");

    foreach (extraerHashtags($titulo . ' ' . $texto) as $tag) {
        $buscar->execute([':nombre' => $tag]);
        $id_tag = $buscar->fetchColumn();
        if (!$id_tag) {
            $insertar->execute([':nombre' => $tag]);
            $id_tag = $conexion->lastInsertId();
        }
        $enlazar->execute([':pub' => $id_pub, ':tag' => $id_tag]);
    }
}

// Escapa % y _ para usar texto del usuario dentro de LIKE
function patronLike($texto) {
    return '%' . addcslashes($texto, '\\%_') . '%';
}

/**
 * Consulta publicaciones para el feed o la búsqueda.
 * $filtros: q (texto), tag (hashtag exacto), rango (semana|mes|todo), orden (reciente|antiguo|popular)
 * Devuelve ['publicaciones' => [...], 'hay_mas' => bool, 'siguiente' => int]
 */
function consultarPublicaciones($conexion, $id_usuario, array $filtros, $limite, $offset) {
    $where  = ["p.estado = 'aprobada'"]; // en feed y búsqueda solo aparecen publicaciones aprobadas
    $params = [':yo' => $id_usuario, ':yo2' => $id_usuario, ':yo3' => $id_usuario];

    // Nunca mostrar publicaciones si hay bloqueo entre tú y el autor
    $where[] = "NOT EXISTS (
        SELECT 1 FROM relacion_usuario b
        WHERE b.tipo_relacion = 'bloqueo'
          AND ((b.id_usuario_origen = :yo2 AND b.id_usuario_destino = p.id_usuario)
            OR (b.id_usuario_origen = p.id_usuario AND b.id_usuario_destino = :yo3))
    )";

    $q = trim(ltrim(trim($filtros['q'] ?? ''), '#'));
    if ($q !== '') {
        $where[] = "(p.titulo LIKE :q1 OR p.texto LIKE :q2 OR u.nombre LIKE :q3 OR u.alias LIKE :q4
                     OR EXISTS (SELECT 1 FROM publicacion_hashtag phq JOIN hashtag hq ON hq.id_hashtag = phq.id_hashtag
                                WHERE phq.id_publicacion = p.id_publicacion AND hq.nombre LIKE :q5))";
        $like = patronLike($q);
        foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) {
            $params[":$k"] = $like;
        }
    }

    $tag = trim(ltrim(trim($filtros['tag'] ?? ''), '#'));
    if ($tag !== '') {
        $where[] = "EXISTS (SELECT 1 FROM publicacion_hashtag pht JOIN hashtag ht ON ht.id_hashtag = pht.id_hashtag
                            WHERE pht.id_publicacion = p.id_publicacion AND LOWER(ht.nombre) = LOWER(:tag))";
        $params[':tag'] = $tag;
    }

    $rango = $filtros['rango'] ?? 'todo';
    if ($rango === 'semana') {
        $where[] = "p.fecha_creacion >= NOW() - INTERVAL 7 DAY";
    } elseif ($rango === 'mes') {
        $where[] = "p.fecha_creacion >= NOW() - INTERVAL 1 MONTH";
    }

    $ordenes = [
        'reciente' => 'p.fecha_creacion DESC, p.id_publicacion DESC',
        'antiguo'  => 'p.fecha_creacion ASC, p.id_publicacion ASC',
        'popular'  => 'total_likes DESC, total_comentarios DESC, p.fecha_creacion DESC',
    ];
    $orden = $ordenes[$filtros['orden'] ?? 'reciente'] ?? $ordenes['reciente'];

    $limite = (int)$limite;
    $offset = (int)$offset;
    $pedir  = $limite + 1; // uno extra para saber si hay más

    $stmt = $conexion->prepare("
        SELECT p.id_publicacion, p.id_usuario, p.titulo, p.texto, p.imagen, p.fecha_creacion,
               u.nombre, u.alias, u.avatar,
               (SELECT COUNT(*) FROM reaccion r  WHERE r.id_publicacion = p.id_publicacion) AS total_likes,
               (SELECT COUNT(*) FROM reaccion r2 WHERE r2.id_publicacion = p.id_publicacion AND r2.id_usuario = :yo) AS user_liked,
               (SELECT COUNT(*) FROM comentario c WHERE c.id_publicacion = p.id_publicacion) AS total_comentarios
        FROM publicacion p
        JOIN usuario u ON u.id_usuario = p.id_usuario
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orden
        LIMIT $pedir OFFSET $offset
    ");
    $stmt->execute($params);
    $publicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hayMas = count($publicaciones) > $limite;
    $publicaciones = array_slice($publicaciones, 0, $limite);
    $hashtags = obtenerHashtagsDe($conexion, array_column($publicaciones, 'id_publicacion'));

    foreach ($publicaciones as &$p) {
        $id = (int)$p['id_publicacion'];
        $p['id_publicacion']    = $id;
        $p['id_usuario']        = (int)$p['id_usuario'];
        $p['total_likes']       = (int)$p['total_likes'];
        $p['user_liked']        = (int)$p['user_liked'] > 0;
        $p['total_comentarios'] = (int)$p['total_comentarios'];
        $p['avatar']            = !empty($p['avatar']) ? $p['avatar'] : 'Image/cuenta.png';
        $p['hashtags']          = $hashtags[$id] ?? [];
        $p['es_propia']         = $p['id_usuario'] === $id_usuario;
        $p['fecha_formateada']  = (new DateTime($p['fecha_creacion'] ?? 'now'))->format('d / M / Y');
    }
    unset($p);

    return [
        'publicaciones' => $publicaciones,
        'hay_mas'       => $hayMas,
        'siguiente'     => $offset + count($publicaciones),
    ];
}

// Devuelve [id_publicacion => ['Tag1', 'Tag2', ...]] para varias publicaciones
function obtenerHashtagsDe($conexion, array $ids) {
    $resultado = [];
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (empty($ids)) return $resultado;

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conexion->prepare("
        SELECT ph.id_publicacion, h.nombre
        FROM publicacion_hashtag ph
        JOIN hashtag h ON h.id_hashtag = ph.id_hashtag
        WHERE ph.id_publicacion IN ($marcas)
        ORDER BY ph.id_pub_hash ASC
    ");
    $stmt->execute($ids);
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $resultado[(int)$fila['id_publicacion']][] = $fila['nombre'];
    }
    return $resultado;
}

try {
    switch ($accion) {
        case 'crear_publicacion':
            // strip_tags previene que se guarde código HTML/JavaScript malicioso (XSS)
            $titulo = trim(strip_tags($_POST['titulo'] ?? ''));
            $texto  = trim(strip_tags($_POST['texto']  ?? ''));
            $imagenRuta = null;

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenRuta = procesarSubidaImagen($_FILES['imagen'], 'posts');
            }

            if ($titulo === '' && $texto === '' && $imagenRuta === null) {
                throw new Exception('La publicación debe contener al menos un título, descripción o imagen.');
            }

            // Las publicaciones nuevas esperan aprobación (las de un administrador se aprueban solas)
            $estado = $es_admin ? 'aprobada' : 'pendiente';

            $sql = "INSERT INTO publicacion (id_usuario, titulo, texto, imagen, estado, fecha_creacion)
                    VALUES (:id_usuario, :titulo, :texto, :imagen, :estado, NOW())";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $id_usuario,
                ':titulo'     => $titulo !== '' ? $titulo : null,
                ':texto'      => $texto !== '' ? $texto : null,
                ':imagen'     => $imagenRuta,
                ':estado'     => $estado
            ]);

            $id_pub = $conexion->lastInsertId();
            guardarHashtags($conexion, $id_pub, $titulo, $texto);

            responderJSON([
                'success' => true,
                'mensaje' => $estado === 'aprobada'
                    ? '¡Publicación creada con éxito!'
                    : 'Tu publicación se envió y está pendiente de aprobación por un administrador.',
                'estado' => $estado,
                'id_publicacion' => $id_pub
            ]);
            break;

        case 'editar_publicacion':
            $id_pub = (int)($_POST['id_publicacion'] ?? 0);
            // strip_tags previene XSS al editar publicaciones
            $titulo = trim(strip_tags($_POST['titulo'] ?? ''));
            $texto  = trim(strip_tags($_POST['texto']  ?? ''));
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

            // Al editar, vuelve a revisión (si no, se podría cambiar el contenido después de aprobado)
            $estado = $es_admin ? 'aprobada' : 'pendiente';

            $upd = $conexion->prepare("UPDATE publicacion SET titulo = :titulo, texto = :texto, imagen = :imagen, estado = :estado WHERE id_publicacion = :id");
            $upd->execute([
                ':titulo' => $titulo !== '' ? $titulo : null,
                ':texto'  => $texto !== '' ? $texto : null,
                ':imagen' => $imagenRuta,
                ':estado' => $estado,
                ':id'     => $id_pub
            ]);
            guardarHashtags($conexion, $id_pub, $titulo, $texto);

            responderJSON([
                'success' => true,
                'mensaje' => $estado === 'aprobada'
                    ? 'Publicación actualizada exitosamente'
                    : 'Publicación actualizada. Volverá a aparecer cuando un administrador la apruebe.',
                'estado' => $estado,
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

            borrarPublicacionCompleta($conexion, $pub);

            responderJSON(['success' => true, 'mensaje' => 'Publicación eliminada correctamente']);
            break;

        case 'toggle_like':
            $id_pub = (int)($_POST['id_publicacion'] ?? 0);
            verificarPublicacionVisible($conexion, $id_pub, $id_usuario, $es_admin);

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

            responderJSON([
                'success' => true,
                'liked' => $liked,
                'total_likes' => $totalLikes
            ]);
            break;

        case 'obtener_comentarios':
            $id_pub = (int)($_GET['id_publicacion'] ?? 0);
            verificarPublicacionVisible($conexion, $id_pub, $id_usuario, $es_admin);

            $stmt = $conexion->prepare("
                SELECT id_comentario, id_publicacion, id_usuario, contenido, imagen, fecha_creacion,
                    nombre, alias, avatar
                FROM v_comentarios_detalle
                WHERE id_publicacion = :pub
                ORDER BY id_comentario ASC
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

            responderJSON([
                'success' => true,
                'comentarios' => $comentarios
            ]);
            break;

        case 'crear_comentario':
            $id_pub   = (int)($_POST['id_publicacion'] ?? 0);
            // strip_tags previene XSS en comentarios
            $contenido = trim(strip_tags($_POST['contenido'] ?? ''));
            $imagenRuta = null;

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $imagenRuta = procesarSubidaImagen($_FILES['imagen'], 'comments');
            }

            if ($contenido === '' && $imagenRuta === null) {
                throw new Exception('El comentario no puede estar vacío.');
            }

            // Comprobar que el post existe y se puede ver (aprobado, o eres su autor o admin)
            verificarPublicacionVisible($conexion, $id_pub, $id_usuario, $es_admin);

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

            responderJSON([
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
            // strip_tags previene XSS al editar comentarios
            $contenido = trim(strip_tags($_POST['contenido'] ?? ''));
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

            responderJSON([
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

            responderJSON([
                'success' => true,
                'mensaje' => 'Comentario eliminado',
                'id_publicacion' => $com['id_publicacion'],
                'total_comentarios' => $totalComentarios
            ]);
            break;

        case 'obtener_feed':
            // Publicaciones de todos los usuarios, más recientes primero, por páginas
            $limite = max(1, min(20, (int)($_GET['limite'] ?? 10)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $res = consultarPublicaciones($conexion, $id_usuario, [], $limite, $offset);
            responderJSON(['success' => true] + $res);
            break;

        case 'buscar':
            // Búsqueda de publicaciones o usuarios (4busqueda.html)
            $tipo   = ($_GET['tipo'] ?? 'publicaciones') === 'usuarios' ? 'usuarios' : 'publicaciones';
            $limite = max(1, min(20, (int)($_GET['limite'] ?? 10)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $q      = trim((string)($_GET['q'] ?? ''));

            if ($tipo === 'publicaciones') {
                $res = consultarPublicaciones($conexion, $id_usuario, [
                    'q'     => $q,
                    'tag'   => (string)($_GET['tag'] ?? ''),
                    'rango' => (string)($_GET['rango'] ?? 'todo'),
                    'orden' => (string)($_GET['orden'] ?? 'reciente'),
                ], $limite, $offset);
                responderJSON(['success' => true, 'tipo' => $tipo] + $res);
            }

            // Usuarios: por nombre o alias (sin '@'), excluyendo bloqueos en ambos sentidos
            $qUsuario = trim(ltrim($q, '@#'));
            $where  = ["NOT EXISTS (
                SELECT 1 FROM relacion_usuario b
                WHERE b.tipo_relacion = 'bloqueo'
                  AND ((b.id_usuario_origen = :yo1 AND b.id_usuario_destino = u.id_usuario)
                    OR (b.id_usuario_origen = u.id_usuario AND b.id_usuario_destino = :yo2))
            )"];
            $params = [':yo1' => $id_usuario, ':yo2' => $id_usuario, ':yo3' => $id_usuario];
            $ordenRelevancia = '0';
            if ($qUsuario !== '') {
                $where[] = "(u.nombre LIKE :q1 OR u.alias LIKE :q2)";
                $params[':q1'] = patronLike($qUsuario);
                $params[':q2'] = patronLike($qUsuario);
                // Primero los que empiezan con lo buscado
                $ordenRelevancia = "(u.nombre LIKE :q3 OR u.alias LIKE :q4 OR u.alias LIKE :q5)";
                $params[':q3'] = addcslashes($qUsuario, '\%_') . '%';
                $params[':q4'] = addcslashes($qUsuario, '\%_') . '%';
                $params[':q5'] = '@' . addcslashes($qUsuario, '\%_') . '%';
            }
            $pedir = $limite + 1;

            $stmt = $conexion->prepare("
                SELECT u.id_usuario, u.nombre, u.alias, u.avatar, u.bio,
                     fn_total_seguidores(u.id_usuario) AS seguidores,
                    (SELECT COUNT(*) FROM relacion_usuario s2 WHERE s2.id_usuario_origen = :yo3 AND s2.id_usuario_destino = u.id_usuario AND s2.tipo_relacion = 'seguir') AS lo_sigo
                FROM usuario u
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $ordenRelevancia DESC, seguidores DESC, u.nombre ASC
                LIMIT $pedir OFFSET $offset
            ");
            $stmt->execute($params);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hayMas = count($usuarios) > $limite;
            $usuarios = array_slice($usuarios, 0, $limite);
            foreach ($usuarios as &$u) {
                $u['id_usuario'] = (int)$u['id_usuario'];
                $u['seguidores'] = (int)$u['seguidores'];
                $u['lo_sigo']    = (int)$u['lo_sigo'] > 0;
                $u['es_propio']  = $u['id_usuario'] === $id_usuario;
                $u['avatar']     = !empty($u['avatar']) ? $u['avatar'] : 'Image/cuenta.png';
            }
            unset($u);

            responderJSON([
                'success'   => true,
                'tipo'      => $tipo,
                'usuarios'  => $usuarios,
                'hay_mas'   => $hayMas,
                'siguiente' => $offset + count($usuarios),
            ]);
            break;

        case 'temas_actuales':
            // Hashtags más usados: primero los de la última semana, luego los de siempre
            $limite = max(1, min(10, (int)($_GET['limite'] ?? 5)));
            $stmt = $conexion->query("
                SELECT h.nombre,
                       COUNT(*) AS usos,
                       SUM(p.fecha_creacion >= NOW() - INTERVAL 7 DAY) AS usos_semana
                FROM publicacion_hashtag ph
                JOIN hashtag h     ON h.id_hashtag = ph.id_hashtag
                JOIN publicacion p ON p.id_publicacion = ph.id_publicacion
                WHERE p.estado = 'aprobada'
                GROUP BY h.id_hashtag, h.nombre
                ORDER BY usos_semana DESC, usos DESC, h.nombre ASC
                LIMIT $limite
            ");
            $temas = array_map(function ($t) {
                return ['nombre' => $t['nombre'], 'usos' => (int)$t['usos']];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            responderJSON(['success' => true, 'temas' => $temas]);
            break;

        case 'descubrir_usuarios':
            // Usuarios para la sección "Descubre" (sin incluirte a ti ni a quienes bloqueaste).
            // El orden es aleatorio pero FIJO: RAND() usa como semilla tu id de usuario,
            // así la lista no cambia de posición al recargar o volver a la página.
            $limite = max(1, min(10, (int)($_GET['limite'] ?? 5)));
            $semilla = $id_usuario;
            $stmt = $conexion->prepare("
                SELECT u.id_usuario, u.nombre, u.alias, u.avatar
                FROM usuario u
                WHERE u.id_usuario <> :yo
                  AND u.id_usuario NOT IN (
                      SELECT id_usuario_destino FROM relacion_usuario
                      WHERE id_usuario_origen = :yo2 AND tipo_relacion = 'bloqueo'
                  )
                ORDER BY RAND($semilla)
                LIMIT $limite
            ");
            $stmt->execute([':yo' => $id_usuario, ':yo2' => $id_usuario]);
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($usuarios as &$u) {
                $u['id_usuario'] = (int)$u['id_usuario'];
                $u['avatar'] = !empty($u['avatar']) ? $u['avatar'] : 'Image/cuenta.png';
            }
            unset($u);

            responderJSON(['success' => true, 'usuarios' => $usuarios]);
            break;

        case 'toggle_relacion':
            // Seguir / dejar de seguir, o bloquear / desbloquear a otro usuario
            $id_destino = (int)($_POST['id_usuario_destino'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';

            if (!in_array($tipo, ['seguir', 'bloqueo'], true)) {
                throw new Exception('Tipo de relación no válido.');
            }
            if ($id_destino === $id_usuario) {
                throw new Exception('No puedes hacer esto con tu propia cuenta.');
            }

            $existe = $conexion->prepare("SELECT 1 FROM usuario WHERE id_usuario = :id");
            $existe->execute([':id' => $id_destino]);
            if (!$existe->fetchColumn()) {
                throw new Exception('El usuario no existe.');
            }

            $check = $conexion->prepare("
                SELECT id_relacion FROM relacion_usuario
                WHERE id_usuario_origen = :yo AND id_usuario_destino = :otro AND tipo_relacion = :tipo
            ");
            $check->execute([':yo' => $id_usuario, ':otro' => $id_destino, ':tipo' => $tipo]);
            $relacion = $check->fetchColumn();

            if ($relacion) {
                $conexion->prepare("DELETE FROM relacion_usuario WHERE id_relacion = :id")
                         ->execute([':id' => $relacion]);
                $activo = false;
            } else {
                if ($tipo === 'seguir') {
                    $bloq = $conexion->prepare("
                        SELECT 1 FROM relacion_usuario
                        WHERE id_usuario_origen = :yo AND id_usuario_destino = :otro AND tipo_relacion = 'bloqueo'
                    ");
                    $bloq->execute([':yo' => $id_usuario, ':otro' => $id_destino]);
                    if ($bloq->fetchColumn()) {
                        throw new Exception('Desbloquea a este usuario antes de seguirlo.');
                    }
                } else {
                    // Al bloquear, se deja de seguir en ambos sentidos
                    $conexion->prepare("
                        DELETE FROM relacion_usuario
                        WHERE tipo_relacion = 'seguir'
                          AND ((id_usuario_origen = :yo AND id_usuario_destino = :otro)
                            OR (id_usuario_origen = :otro2 AND id_usuario_destino = :yo2))
                    ")->execute([':yo' => $id_usuario, ':otro' => $id_destino, ':otro2' => $id_destino, ':yo2' => $id_usuario]);
                }

                $conexion->prepare("
                    INSERT INTO relacion_usuario (id_usuario_origen, id_usuario_destino, tipo_relacion)
                    VALUES (:yo, :otro, :tipo)
                ")->execute([':yo' => $id_usuario, ':otro' => $id_destino, ':tipo' => $tipo]);
                $activo = true;
            }

            $cnt = $conexion->prepare("SELECT fn_total_seguidores(:id)");
            $cnt->execute([':id' => $id_destino]);

            responderJSON([
                'success'    => true,
                'tipo'       => $tipo,
                'activo'     => $activo,
                'seguidores' => (int)$cnt->fetchColumn()
            ]);
            break;

        /* ======================================================
           MODERACIÓN (solo administradores)
           ====================================================== */
        case 'admin_pendientes':
        case 'admin_aprobar':
        case 'admin_rechazar':
            if (!$es_admin) {
                responderJSON(['success' => false, 'error' => 'Solo un administrador puede hacer esto.'], 403);
            }

            if ($accion === 'admin_pendientes') {
                // Las más antiguas primero: así se revisan en el orden en que llegaron
                $stmt = $conexion->query("
                    SELECT id_publicacion, id_usuario, titulo, texto, imagen, fecha_creacion,
                        nombre, alias, avatar
                    FROM v_publicaciones_pendientes
                    ORDER BY fecha_creacion ASC, id_publicacion ASC
                ");

                $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $hashtags = obtenerHashtagsDe($conexion, array_column($pendientes, 'id_publicacion'));
                foreach ($pendientes as &$p) {
                    $id = (int)$p['id_publicacion'];
                    $p['id_publicacion']   = $id;
                    $p['id_usuario']       = (int)$p['id_usuario'];
                    $p['avatar']           = !empty($p['avatar']) ? $p['avatar'] : 'Image/cuenta.png';
                    $p['hashtags']         = $hashtags[$id] ?? [];
                    $p['fecha_formateada'] = (new DateTime($p['fecha_creacion'] ?? 'now'))->format('d / M / Y');
                }
                unset($p);
                responderJSON(['success' => true, 'publicaciones' => $pendientes]);
            }

            $id_pub = (int)($_POST['id_publicacion'] ?? 0);
            $check = $conexion->prepare("SELECT * FROM publicacion WHERE id_publicacion = :id");
            $check->execute([':id' => $id_pub]);
            $pub = $check->fetch(PDO::FETCH_ASSOC);
            if (!$pub) {
                throw new Exception('La publicación ya no existe (quizá otro administrador ya la revisó).');
            }

            if ($accion === 'admin_aprobar') {
                $conexion->prepare("UPDATE publicacion SET estado = 'aprobada' WHERE id_publicacion = :id")
                         ->execute([':id' => $id_pub]);
                responderJSON(['success' => true, 'mensaje' => 'Publicación aprobada: ya aparece en el inicio.']);
            }

            // Rechazar = borrar definitivamente
            borrarPublicacionCompleta($conexion, $pub);
            responderJSON(['success' => true, 'mensaje' => 'Publicación rechazada y eliminada.']);
            break;

        /* ======================================================
           HASHTAGS / CATEGORÍAS (solo administradores)
           ====================================================== */
        case 'admin_hashtags':
        case 'admin_crear_hashtag':
        case 'admin_borrar_hashtags':
            if (!$es_admin) {
                responderJSON(['success' => false, 'error' => 'Solo un administrador puede hacer esto.'], 403);
            }

            if ($accion === 'admin_hashtags') {
                // Todos los hashtags con cuántas publicaciones los usan (los más usados primero)
                $stmt = $conexion->query("
                    SELECT h.id_hashtag, h.nombre,
                           COUNT(ph.id_publicacion) AS usos,
                           SUM(p.estado = 'aprobada') AS usos_aprobadas
                    FROM hashtag h
                    LEFT JOIN publicacion_hashtag ph ON ph.id_hashtag = h.id_hashtag
                    LEFT JOIN publicacion p ON p.id_publicacion = ph.id_publicacion
                    GROUP BY h.id_hashtag, h.nombre
                    ORDER BY usos DESC, h.nombre ASC
                ");
                $hashtags = array_map(function ($h) {
                    return [
                        'id_hashtag'     => (int)$h['id_hashtag'],
                        'nombre'         => $h['nombre'],
                        'usos'           => (int)$h['usos'],
                        'usos_aprobadas' => (int)$h['usos_aprobadas'],
                    ];
                }, $stmt->fetchAll(PDO::FETCH_ASSOC));
                responderJSON(['success' => true, 'hashtags' => $hashtags]);
            }

            if ($accion === 'admin_crear_hashtag') {
                $nombre = trim(ltrim(trim((string)($_POST['nombre'] ?? '')), '#'));
                if (!preg_match('/^[\p{L}\p{N}_]{1,50}$/u', $nombre)) {
                    throw new Exception('Usa solo letras, números o _ (sin espacios), máximo 50 caracteres.');
                }
                $existe = $conexion->prepare("SELECT 1 FROM hashtag WHERE LOWER(nombre) = LOWER(:n)");
                $existe->execute([':n' => $nombre]);
                if ($existe->fetchColumn()) {
                    throw new Exception("El hashtag #$nombre ya existe.");
                }
                $conexion->prepare("INSERT INTO hashtag (nombre) VALUES (:n)")->execute([':n' => $nombre]);
                responderJSON([
                    'success' => true,
                    'mensaje' => "Hashtag #$nombre creado",
                    'hashtag' => ['id_hashtag' => (int)$conexion->lastInsertId(), 'nombre' => $nombre, 'usos' => 0, 'usos_aprobadas' => 0],
                ]);
            }

            // Borrar: se quita de todas las publicaciones y desaparece de Temas Actuales
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
            if (empty($ids)) {
                throw new Exception('Selecciona al menos un hashtag.');
            }
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $conexion->beginTransaction();
            $conexion->prepare("DELETE FROM publicacion_hashtag WHERE id_hashtag IN ($marcas)")->execute($ids);
            $del = $conexion->prepare("DELETE FROM hashtag WHERE id_hashtag IN ($marcas)");
            $del->execute($ids);
            $conexion->commit();
            $n = $del->rowCount();
            responderJSON(['success' => true, 'borrados' => $n, 'mensaje' => $n === 1 ? 'Hashtag eliminado' : "$n hashtags eliminados"]);
            break;

        default:
            throw new Exception('Acción no reconocida');
    }
} catch (Throwable $e) {
    responderJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
