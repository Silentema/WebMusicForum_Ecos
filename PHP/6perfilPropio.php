<?php
require_once __DIR__ . '/error_popup.php';

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../2inicioSesion.html");
    exit();
}

require __DIR__ . '/conexion.php';

// Imagen por defecto cuando el usuario no tiene avatar
const AVATAR_DEFECTO = 'Image/cuenta.png';

function rutaAvatar($ruta) {
    return '../' . ltrim(!empty($ruta) ? $ruta : AVATAR_DEFECTO, '/');
}

try {
    // Usuario con sesión iniciada (quien está viendo la página)
    $id_sesion = (int)$_SESSION['id_usuario'];

    // Perfil que se quiere ver: ?id=N en la URL, o el propio si no viene
    $id_perfil = isset($_GET['id']) ? (int)$_GET['id'] : $id_sesion;
    if ($id_perfil <= 0) {
        $id_perfil = $id_sesion;
    }
    $es_propio = ($id_perfil === $id_sesion);

    $stmt = $conexion->prepare("SELECT * FROM usuario WHERE id_usuario = :id");
    $stmt->execute([':id' => $id_perfil]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        mostrarPopupError('El perfil que buscas no existe.', '3main.html');
    }

    // Datos de quien ve la página (para avatar en comentarios y en el modal)
    if ($es_propio) {
        $yo = $usuario;
    } else {
        $stmtYo = $conexion->prepare("SELECT * FROM usuario WHERE id_usuario = :id");
        $stmtYo->execute([':id' => $id_sesion]);
        $yo = $stmtYo->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Seguidores / seguidos del perfil
    $stmtCnt = $conexion->prepare("
        SELECT
            (SELECT COUNT(*) FROM relacion_usuario r
              WHERE r.id_usuario_destino = u.id_usuario AND r.tipo_relacion = 'seguir') AS seguidores,
            (SELECT COUNT(*) FROM relacion_usuario r
              WHERE r.id_usuario_origen = u.id_usuario AND r.tipo_relacion = 'seguir') AS seguidos
        FROM usuario u
        WHERE u.id_usuario = :id
    ");
    $stmtCnt->execute([':id' => $id_perfil]);
    $conteo = $stmtCnt->fetch(PDO::FETCH_ASSOC);

    // Relación entre quien ve y el perfil (solo si es ajeno)
    $lo_sigo = false;
    $lo_bloquee = false;
    if (!$es_propio) {
        $stmtRel = $conexion->prepare("
            SELECT tipo_relacion FROM relacion_usuario
            WHERE id_usuario_origen = :yo AND id_usuario_destino = :otro
        ");
        $stmtRel->execute([':yo' => $id_sesion, ':otro' => $id_perfil]);
        $relaciones = $stmtRel->fetchAll(PDO::FETCH_COLUMN);
        $lo_sigo = in_array('seguir', $relaciones, true);
        $lo_bloquee = in_array('bloqueo', $relaciones, true);
    }

    // Publicaciones del perfil (user_liked se calcula con quien está viendo)
    $publicaciones = [];
    if (!$lo_bloquee) {
        $sqlPubs = "
            SELECT p.id_publicacion, p.titulo, p.texto, p.imagen, p.estado, p.fecha_creacion,
                   u.nombre, u.alias, u.avatar,
                   (SELECT COUNT(*) FROM reaccion rl WHERE rl.id_publicacion = p.id_publicacion) AS total_likes,
                   (SELECT COUNT(*) FROM reaccion r2 WHERE r2.id_publicacion = p.id_publicacion AND r2.id_usuario = :id_sesion) AS user_liked,
                   (SELECT COUNT(*) FROM comentario c WHERE c.id_publicacion = p.id_publicacion) AS total_comentarios
            FROM publicacion p
            JOIN usuario u ON p.id_usuario = u.id_usuario
            WHERE p.id_usuario = :id_perfil
            " . ($es_propio ? "" : "AND p.estado = 'aprobada'") . "
            ORDER BY p.id_publicacion DESC
        ";
        $stmtPubs = $conexion->prepare($sqlPubs);
        $stmtPubs->execute([':id_sesion' => $id_sesion, ':id_perfil' => $id_perfil]);
        $publicaciones = $stmtPubs->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    mostrarPopupError(
        "Error al cargar el perfil: " . $e->getMessage(),
        '3main.html'
    );
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ECOS • <?php echo htmlspecialchars($usuario['nombre'] ?? 'Perfil'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../CSS/Main.css" />
    <link rel="stylesheet" href="../CSS/perfil.css" />
    <link rel="stylesheet" href="../CSS/back-btn.css" />
    <link rel="stylesheet" href="../CSS/publicaciones.css" />
    <style>
      #abrir-popup.fab-button {
          position: fixed !important;
          bottom: 30px !important;
          right: 30px !important;
          top: auto !important;
          left: auto !important;
          width: 60px !important;
          height: 60px !important;
          border-radius: 50% !important;
          background: #000000 !important;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          cursor: pointer !important;
          border: none !important;
          z-index: 9999 !important;
          transition: transform 0.2s, background 0.2s;
      }
      #abrir-popup.fab-button:hover {
          transform: scale(1.08);
      }
      body.dark-theme #abrir-popup.fab-button {
          background: #ffffff !important;
      }
      body.dark-theme #abrir-popup.fab-button .fab-icon {
          fill: #000000 !important;
      }
      #abrir-popup.fab-button .fab-icon {
          width: 30px !important;
          height: 30px !important;
          fill: #FFFFFF !important;
      }
      
      .profile-row {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          padding: 20px 40px;
      }
      .user-left {
          display: flex;
          flex-direction: column;
          gap: 4px;
          width: 100%;
      }
      .user-left .name {
          font-size: 2rem;
          font-weight: 700;
          margin: 0;
          line-height: 1.2;
      }
      .user-left .handle {
          font-size: 1rem;
          color: var(--sub, #777);
          margin: 0;
          font-weight: 400;
      }
      .user-left .bio {
          margin-top: 10px;
          font-size: 1.05rem;
          color: var(--sub, #555);
          background: none;
          border: none;
          outline: none;
          resize: none;
          width: 50%;
          font-family: inherit;
          border-radius: 8px;
          transition: background 0.3s;
      }
      .user-left .bio:not([readonly]) {
          background: rgba(0,0,0,0.06);
          padding: 8px;
      }
      body.dark-theme .user-left .bio:not([readonly]) {
          background: rgba(255,255,255,0.08);
          color: #eee;
      }
      .user-right .meta {
          display: flex;
          flex-direction: column;
          gap: 6px;
          text-align: right;
          font-size: 0.95rem;
          color: var(--sub, #555);
          font-family: 'Montserrat', sans-serif;
          white-space: nowrap;
      }
      .user-right .meta div {
          line-height: 1.4;
      }
      .cover.cover-vacio {
          background: var(--line, #3a3a3a);
      }
      .follow-btn.selected {
          background: #fff !important;
          color: #FF5050 !important;
          border: 1.5px solid #FF5050 !important;
      }
      .follow-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
      }
      .notif-btn, .follow-btn {
          transition: background 0.3s, color 0.3s, border 0.3s, transform 0.2s;
      }
      .notif-btn:active, .follow-btn:active {
          transform: scale(0.95);
      }
      .perfil-stats {
          display: flex;
          gap: 18px;
          margin-top: 6px;
          font-size: 0.95rem;
          color: var(--sub, #777);
      }
      .perfil-stats strong {
          color: var(--text, inherit);
      }
      .post-pendiente {
          margin: -4px 0 14px;
          padding: 8px 14px;
          border-radius: 12px;
          background: rgba(255, 176, 32, 0.15);
          color: #c98400;
          font-size: 0.9rem;
          font-weight: 600;
      }
      body.dark-theme .post-pendiente { color: #ffc14d; }
    </style>
  </head>
  <body style="padding-top:70px;">

    <div id="header-container"></div>
    <script src="../JS/headerLoader.js"></script>

    <button class="back-btn" style="margin-top: 70px;" onclick="history.back()">←</button>

    <main class="page">
      <?php if ($es_propio): ?>
      <form action="actualizar_perfil.php" method="POST" enctype="multipart/form-data" id="perfil-form">
      <?php else: ?>
      <div id="perfil-ajeno" data-id-usuario="<?= (int)$id_perfil ?>">
      <?php endif; ?>

        <div class="cover-wrap">
          <?php if (!empty($usuario['banner'])): ?>
            <img class="cover" src="../<?php echo htmlspecialchars(ltrim($usuario['banner'], '/')); ?>" alt="Cover" id="cover-img" style="z-index:1; position:relative;" />
          <?php else: ?>
            <img class="cover cover-vacio" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" id="cover-img" style="z-index:1; position:relative;" />
          <?php endif; ?>

          <img class="avatar" src="<?php echo htmlspecialchars(rutaAvatar($usuario['avatar'] ?? '')); ?>" alt="Avatar" id="avatar-img" style="z-index:3; position:absolute; left:38px; bottom:-58px;" />

          <?php if ($es_propio): ?>
            <div class="img-overlay cover-overlay" id="cover-overlay" style="display:none; border-radius:43px; z-index:2; cursor:pointer;"><span class="plus-icon">+</span></div>
            <input type="file" id="cover-file" name="cover" accept="image/*" style="display:none" />
            <div class="img-overlay avatar-overlay" id="avatar-overlay" style="display:none; border-radius:999px; left:38px; bottom:-58px; width:132px; height:132px; z-index:4; cursor:pointer;"><span class="plus-icon">+</span></div>
            <input type="file" id="avatar-file" name="avatar" accept="image/*" style="display:none" />

            <button type="button" class="edit-btn" style="font-family: 'Montserrat', sans-serif; position: absolute; z-index: 10; right: 30px; bottom: -18px;">Editar perfil</button>
          <?php else: ?>
            <button type="button" class="notif-btn" id="notifBtn" onclick="this.classList.toggle('active')" aria-label="Notificaciones" title="Notificaciones">
              <svg class="notification-svg" width="28" height="28" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.26953 12.3919C3.26953 10.549 3.66089 8.84781 4.44359 7.28844C5.22651 5.72927 6.28307 4.41281 7.61328 3.33906L8.76922 4.79812C7.65547 5.69875 6.77286 6.80292 6.12141 8.11062C5.47016 9.41833 5.14453 10.8454 5.14453 12.3919H3.26953ZM24.8558 12.3919C24.8558 10.8454 24.5302 9.41635 23.8789 8.10469C23.2274 6.79302 22.3448 5.68677 21.2311 4.78594L22.387 3.33906C23.7172 4.41281 24.7738 5.72927 25.5567 7.28844C26.3394 8.84781 26.7308 10.549 26.7308 12.3919H24.8558ZM5.62516 23.6056V21.7309H7.88484V12.4038C7.88484 10.7227 8.4037 9.23635 9.44141 7.94469C10.4789 6.65302 11.811 5.82687 13.4377 5.46625V4.6875C13.4377 4.25479 13.5899 3.88625 13.8945 3.58187C14.1989 3.27729 14.5674 3.125 15.0002 3.125C15.4329 3.125 15.8014 3.27729 16.1058 3.58187C16.4104 3.88625 16.5627 4.25479 16.5627 4.6875V5.46625C18.1893 5.82687 19.5214 6.65302 20.5589 7.94469C21.5966 9.23635 22.1155 10.7227 22.1155 12.4038V21.7309H24.3752V23.6056H5.62516ZM15.0002 27.1153C14.3768 27.1153 13.8444 26.8946 13.403 26.4531C12.9613 26.0117 12.7405 25.4792 12.7405 24.8556H17.2598C17.2598 25.4792 17.039 26.0117 16.5973 26.4531C16.1559 26.8946 15.6235 27.1153 15.0002 27.1153ZM9.75984 21.7309H20.2405V12.4038C20.2405 10.9567 19.7289 9.72156 18.7058 8.69844C17.6824 7.6751 16.4472 7.16344 15.0002 7.16344C13.5531 7.16344 12.3179 7.6751 11.2945 8.69844C10.2714 9.72156 9.75984 10.9567 9.75984 12.4038V21.7309Z" fill="#222"/>
              </svg>
            </button>
            <button type="button" class="notif-btn <?= $lo_bloquee ? 'active' : '' ?>" id="blockBtn" onclick="toggleRelacion('bloqueo')" style="right:10px;" aria-label="Bloquear usuario" title="<?= $lo_bloquee ? 'Desbloquear' : 'Bloquear' ?>">
              <svg class="block-svg" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#222" stroke-width="2" fill="none"/>
                <line x1="7" y1="7" x2="17" y2="17" stroke="#FF5050" stroke-width="2"/>
              </svg>
            </button>
            <button type="button" class="follow-btn <?= $lo_sigo ? 'selected' : '' ?>" id="followBtn" onclick="toggleRelacion('seguir')" <?= $lo_bloquee ? 'disabled' : '' ?>>
              <?= $lo_sigo ? 'Siguiendo' : 'Seguir' ?>
            </button>
          <?php endif; ?>
        </div>

        <section class="profile-row">
          <div class="user-left">
            <h1 class="name"><?php echo htmlspecialchars($usuario['nombre'] ?? 'Usuario'); ?></h1>
            <p class="handle"><?php echo htmlspecialchars($usuario['alias'] ?? ''); ?></p>
            <div class="perfil-stats">
              <span><strong id="contador-seguidores"><?= (int)$conteo['seguidores'] ?></strong> seguidores</span>
              <span><strong><?= (int)$conteo['seguidos'] ?></strong> siguiendo</span>
            </div>
            <textarea class="bio" id="bio-input" name="bio" readonly rows="3" placeholder="<?= $es_propio ? 'Escribe tu biografía...' : '' ?>"><?php echo htmlspecialchars($usuario['bio'] ?? ''); ?></textarea>
          </div>
          
          <div class="user-right">
            <div class="meta">
              <div>Género: <?php echo htmlspecialchars($usuario['genero'] ?? 'No especificado'); ?></div>
              <div>País: <?php echo htmlspecialchars($usuario['pais'] ?? 'No especificado'); ?></div>
              <div>Nacimiento: <?php echo htmlspecialchars($usuario['fecha_nacimiento'] ?? 'N/A'); ?></div>
            </div>
          </div>
        </section>

      <?php if ($es_propio): ?>
      </form>
      <?php else: ?>
      </div>
      <?php endif; ?>

      <!-- Feed de Publicaciones del Usuario -->
      <section class="feed" aria-label="Publicaciones">
        <div class="tabs-divider" style="width:auto; padding-bottom: 20px; margin-top: 40px;"></div>

        <?php if (!empty($publicaciones)): ?>
          <?php foreach ($publicaciones as $p): ?>
            <?php
              $fechaObj = new DateTime($p['fecha_creacion'] ?? 'now');
              $fechaFormat = $fechaObj->format('d / M / Y');
              $avatarPost = rutaAvatar($p['avatar'] ?? '');
              $liked = !empty($p['user_liked']);
              $imgPost = !empty($p['imagen']) ? '../' . ltrim($p['imagen'], '/') : '';
            ?>
            <article class="user-post-card" id="post-card-<?= $p['id_publicacion'] ?>">
              <?php if (($p['estado'] ?? '') === 'pendiente'): ?>
                <div class="post-pendiente">⏳ Pendiente de aprobación · solo tú puedes verla hasta que un administrador la apruebe</div>
              <?php endif; ?>
              <div class="post-header-row">
                <div class="post-author-info">
                  <img src="<?= htmlspecialchars($avatarPost) ?>" class="post-user-avatar" alt="Avatar">
                  <div class="post-author-names">
                    <h2 class="post-author-name"><?= htmlspecialchars($p['nombre'] ?? 'Usuario') ?></h2>
                    <p class="post-author-handle"><?= htmlspecialchars($p['alias'] ?? '') ?></p>
                  </div>
                </div>
                <?php if ($es_propio): ?>
                <div class="post-actions-menu">
                  <button type="button" class="post-action-btn" onclick="abrirModalEditarPublicacion(<?= (int)$p['id_publicacion'] ?>, <?= htmlspecialchars(json_encode($p['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($p['texto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($imgPost), ENT_QUOTES, 'UTF-8') ?>)">
                    ✏️ Editar
                  </button>
                  <button type="button" class="post-action-btn delete" onclick="eliminarPublicacion(<?= (int)$p['id_publicacion'] ?>)">
                    🗑️ Borrar
                  </button>
                </div>
                <?php endif; ?>
              </div>

              <?php if (!empty($p['titulo'])): ?>
                <a href="5verPost.php?id=<?= (int)$p['id_publicacion'] ?>" style="text-decoration:none;color:inherit;"><h3 class="post-title-text"><?= htmlspecialchars($p['titulo']) ?></h3></a>
              <?php endif; ?>

              <?php if (!empty($imgPost)): ?>
                <div class="post-media-container">
                  <img src="<?= htmlspecialchars($imgPost) ?>" class="post-media-img" alt="Imagen de la publicación" onclick="window.open(this.src, '_blank')">
                </div>
              <?php endif; ?>

              <?php if (!empty($p['texto'])): ?>
                <p class="post-body-text"><?= nl2br(htmlspecialchars($p['texto'])) ?></p>
              <?php endif; ?>

              <div class="post-footer-row">
                <span class="post-date-tag"><?= $fechaFormat ?></span>
                <div class="post-interaction-stats">
                  <button type="button" class="post-stat-btn like-btn <?= $liked ? 'liked' : '' ?>" onclick="toggleLikePost(this, <?= $p['id_publicacion'] ?>)">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M10 20L8.55 18.5831C6.86667 16.9301 5.475 15.5041 4.375 14.3052C3.275 13.1063 2.4 12.03 1.75 11.0763C1.1 10.1226 0.645833 9.24614 0.3875 8.44687C0.129167 7.64759 0 6.83015 0 5.99455C0 4.28701 0.525 2.86104 1.575 1.71662C2.625 0.572207 3.93333 0 5.5 0C6.36667 0 7.19167 0.199818 7.975 0.599455C8.75833 0.999092 9.43333 1.56222 10 2.28883C10.5667 1.56222 11.2417 0.999092 12.025 0.599455C12.8083 0.199818 13.6333 0 14.5 0C16.0667 0 17.375 0.572207 18.425 1.71662C19.475 2.86104 20 4.28701 20 5.99455C20 6.83015 19.8708 7.64759 19.6125 8.44687C19.3542 9.24614 18.9 10.1226 18.25 11.0763C17.6 12.03 16.725 13.1063 15.625 14.3052C14.525 15.5041 13.1333 16.9301 11.45 18.5831L10 20Z" fill="<?= $liked ? '#ff3040' : '#777777' ?>"/>
                    </svg>
                    <span class="post-stat-number"><?= (int)$p['total_likes'] ?></span>
                  </button>
                  <button type="button" class="post-stat-btn comment-btn" onclick="toggleSeccionComentarios(<?= $p['id_publicacion'] ?>)">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M20 20L16 16H2C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V2C0 1.45 0.979167 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V20ZM2 14H16.85L18 15.125V2H2V14Z" fill="#777777"/>
                    </svg>
                    <span class="post-stat-number" id="comment-count-<?= $p['id_publicacion'] ?>"><?= (int)$p['total_comentarios'] ?></span>
                  </button>
                </div>
              </div>

              <!-- Sección de comentarios desplegable -->
              <div class="comments-section-wrapper" id="comments-section-<?= $p['id_publicacion'] ?>">
                <div class="new-comment-box">
                  <div class="comment-input-area">
                    <img src="<?= htmlspecialchars(rutaAvatar($yo['avatar'] ?? '')) ?>" class="comment-user-avatar" alt="Avatar">
                    <textarea class="comment-textarea-field" id="comment-text-<?= $p['id_publicacion'] ?>" placeholder="Escribe un comentario..."></textarea>
                  </div>
                  <div class="comment-img-preview-box" id="comment-preview-box-<?= $p['id_publicacion'] ?>" style="display:none;"></div>
                  <div class="comment-tools-bar">
                    <div class="comment-tool-actions">
                      <button type="button" class="comment-tool-btn" onclick="document.getElementById('comment-file-<?= $p['id_publicacion'] ?>').click()">
                        <img src="../Image/imagen.png" style="width:20px;height:20px;vertical-align:middle;" alt="Imagen"> Imagen
                      </button>
                      <input type="file" id="comment-file-<?= $p['id_publicacion'] ?>" accept="image/*" style="display:none;" onchange="previewCommentImage(this, <?= $p['id_publicacion'] ?>)">
                      <button type="button" class="comment-tool-btn" onclick="openEmojiPicker(event, 'comment-text-<?= $p['id_publicacion'] ?>')">
                        <img src="../Image/emoji.png" style="width:20px;height:20px;vertical-align:middle;" alt="Emoji"> Emoji
                      </button>
                    </div>
                    <button type="button" class="btn-send-comment" onclick="enviarComentario(<?= $p['id_publicacion'] ?>)">Comentar</button>
                  </div>
                </div>

                <div class="comments-items-list" id="comments-list-<?= $p['id_publicacion'] ?>">
                  <!-- Comentarios cargados dinámicamente -->
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        <?php elseif ($lo_bloquee): ?>
          <div class="empty-feed-notice">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/></svg>
            <h3>Bloqueaste a este usuario</h3>
            <p>Desbloquéalo con el botón de arriba para volver a ver sus publicaciones.</p>
          </div>
        <?php elseif ($es_propio): ?>
          <div class="empty-feed-notice">
            <svg viewBox="0 0 24 24"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            <h3>No tienes publicaciones aún</h3>
            <p>Haz clic en el botón '+' flotante para compartir música o imágenes con la comunidad.</p>
          </div>
        <?php else: ?>
          <div class="empty-feed-notice">
            <svg viewBox="0 0 24 24"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            <h3><?= htmlspecialchars($usuario['nombre'] ?? 'Este usuario') ?> aún no tiene publicaciones</h3>
            <p>Cuando publique algo, aparecerá aquí.</p>
          </div>
        <?php endif; ?>
      </section>
    </main>

    <!-- Botón flotante para crear publicación -->
    <button id="abrir-popup" class="fab-button" aria-label="Crear publicación">
        <svg class="fab-icon" viewBox="0 0 84 84" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M38.5385 57.6462H44.5384V45.6462H56.5384V39.6462H44.5384V27.6462H38.5385V39.6462H26.5385V45.6462H38.5385V57.6462ZM41.5385 72.6462C37.3885 72.6462 33.4885 71.8587 29.8385 70.2837C26.1885 68.7087 23.0135 66.5712 20.3135 63.8712C17.6135 61.1712 15.476 57.9962 13.901 54.3462C12.326 50.6962 11.5385 46.7962 11.5385 42.6462C11.5385 38.4962 12.326 34.5962 13.901 30.9462C15.476 27.2962 17.6135 24.1212 20.3135 21.4212C23.0135 18.7212 26.1885 16.5837 29.8385 15.0087C33.4885 13.4337 37.3885 12.6462 41.5385 12.6462C45.6884 12.6462 49.5885 13.4337 53.2384 15.0087C56.8885 16.5837 60.0634 18.7212 62.7635 21.4212C65.4634 24.1212 67.6009 27.2962 69.1759 30.9462C70.7509 34.5962 71.5384 38.4962 71.5384 42.6462C71.5384 46.7962 70.7509 50.6962 69.1759 54.3462C67.6009 57.9962 65.4634 61.1712 62.7635 63.8712C60.0634 66.5712 56.8885 68.7087 53.2384 70.2837C49.5885 71.8587 45.6884 72.6462 41.5385 72.6462Z"/>
        </svg>
    </button>

    <!-- Modal para Crear / Editar Publicación -->
    <div id="modal-publicacion" class="ecos-modal-overlay">
      <div class="ecos-modal-card">
        <div class="modal-header-section">
          <div class="modal-author-profile">
            <img src="<?= htmlspecialchars(rutaAvatar($yo['avatar'] ?? '')) ?>" class="modal-avatar-img" alt="Avatar">
            <span class="modal-author-name" id="modal-post-header-title">Crear Publicación</span>
          </div>
          <button type="button" class="modal-close-icon-btn" onclick="cerrarModalPublicacion()" aria-label="Cerrar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <form id="form-publicacion-modal" onsubmit="guardarPublicacion(event)">
          <input type="hidden" id="modal-post-id" value="">
          <input type="hidden" id="modal-post-delete-img" value="0">

          <input type="text" id="modal-post-title" class="modal-title-input" placeholder="Título de la publicación...">

          <div class="modal-image-preview-wrapper" id="modal-post-preview-wrap">
            <img id="modal-post-preview-img" src="" alt="Vista previa">
            <button type="button" class="modal-remove-image-btn" onclick="removerImagenModal()" title="Quitar imagen">&times;</button>
          </div>

          <textarea id="modal-post-desc" class="modal-desc-textarea" placeholder="¿Qué quieres compartir hoy? Usa #hashtags para etiquetar tu publicación"></textarea>

          <input type="file" id="modal-post-file" accept="image/*" style="display:none;" onchange="handleModalFileSelect(this)">

          <div class="modal-bottom-actions">
            <div class="modal-tools-group">
              <button type="button" class="modal-tool-icon-btn" onclick="document.getElementById('modal-post-file').click()" title="Subir imagen desde tu computadora">
                <img src="../Image/imagen.png" alt="Imagen">
              </button>
              <button type="button" class="modal-tool-icon-btn" onclick="openEmojiPicker(event, 'modal-post-desc')" title="Insertar emojis">
                <img src="../Image/emoji.png" alt="Emojis">
              </button>
            </div>
            <button type="submit" id="modal-post-submit-btn" class="modal-submit-btn">Publicar</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Script de Publicaciones -->
    <script src="../publicaciones.js"></script>

    <script>
      localStorage.setItem('userAvatar', <?= json_encode(rutaAvatar($yo['avatar'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);

      // Seguir / bloquear a otro usuario (solo existe en perfiles ajenos)
      async function toggleRelacion(tipo) {
        var cont = document.getElementById('perfil-ajeno');
        if (!cont) return;
        if (tipo === 'bloqueo') {
          var bloqueado = document.getElementById('blockBtn').classList.contains('active');
          if (!bloqueado && !confirm('¿Bloquear a este usuario? Dejarás de seguirlo y no verás sus publicaciones.')) return;
        }
        var formData = new FormData();
        formData.append('id_usuario_destino', cont.dataset.idUsuario);
        formData.append('tipo', tipo);
        try {
          var resp = await fetch(getPublicacionesApiEndpoint('accion=toggle_relacion'), { method: 'POST', body: formData });
          var data = await parseJsonResponse(resp);
          if (!data.success) throw new Error(data.error || 'No se pudo completar la acción.');

          if (tipo === 'seguir') {
            var btn = document.getElementById('followBtn');
            btn.classList.toggle('selected', data.activo);
            btn.textContent = data.activo ? 'Siguiendo' : 'Seguir';
            document.getElementById('contador-seguidores').textContent = data.seguidores;
          } else {
            // Al bloquear/desbloquear cambian las publicaciones visibles: recargar
            window.location.reload();
          }
        } catch (err) {
          alert(err.message);
        }
      }

      document.addEventListener('DOMContentLoaded', function() {
        var editBtn = document.querySelector('.edit-btn');
        var coverOverlay = document.getElementById('cover-overlay');
        var avatarOverlay = document.getElementById('avatar-overlay');
        var cover = document.getElementById('cover-img');
        var avatar = document.getElementById('avatar-img');
        var coverFile = document.getElementById('cover-file');
        var avatarFile = document.getElementById('avatar-file');
        var coverWrap = document.querySelector('.cover-wrap');
        var bioInput = document.getElementById('bio-input');
        var perfilForm = document.getElementById('perfil-form');
        var editMode = false;
        
        if(editBtn && coverOverlay && avatarOverlay && cover && avatar && coverWrap) {
          coverWrap.style.position = 'relative';
          coverOverlay.style.position = 'absolute';
          coverOverlay.style.top = '0';
          coverOverlay.style.left = '0';
          coverOverlay.style.width = cover.offsetWidth + 'px';
          coverOverlay.style.height = cover.offsetHeight + 'px';
          avatarOverlay.style.position = 'absolute';
          avatarOverlay.style.left = avatar.style.left || '38px';
          avatarOverlay.style.bottom = avatar.style.bottom || '-58px';
          avatarOverlay.style.width = avatar.offsetWidth + 'px';
          avatarOverlay.style.height = avatar.offsetHeight + 'px';
          
          editBtn.addEventListener('click', function() {
            if (!editMode) {
              editMode = true;
              coverOverlay.style.display = 'flex';
              avatarOverlay.style.display = 'flex';
              bioInput.removeAttribute('readonly');
              editBtn.textContent = 'Guardar cambios';
              editBtn.type = 'button';
            } else {
              perfilForm.submit();
            }
          });

          coverOverlay.addEventListener('click', function() {
            if (editMode) coverFile.click();
          });
          avatarOverlay.addEventListener('click', function() {
            if (editMode) avatarFile.click();
          });

          coverFile.addEventListener('change', function(e) {
            if (coverFile.files && coverFile.files[0]) {
              var reader = new FileReader();
              reader.onload = function(ev) { cover.src = ev.target.result; };
              reader.readAsDataURL(coverFile.files[0]);
            }
          });

          avatarFile.addEventListener('change', function(e) {
            if (avatarFile.files && avatarFile.files[0]) {
              var reader = new FileReader();
              reader.onload = function(ev) { avatar.src = ev.target.result; };
              reader.readAsDataURL(avatarFile.files[0]);
            }
          });
        }
      });
    </script>
  </body>
</html>
