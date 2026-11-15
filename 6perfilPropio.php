<?php
require_once 'error_popup.php';

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: 2inicioSesion.html");
    exit();
}

require 'conexion.php';

try {
    $id_usuario = (int)$_SESSION['id_usuario'];
    $sql = "SELECT * FROM usuario WHERE id_usuario = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Cargar las publicaciones del usuario en su sesión activa
    $sqlPubs = "
        SELECT p.id_publicacion, p.titulo, p.texto, p.imagen, p.fecha_creacion,
               u.nombre, u.alias, u.avatar,
               (SELECT COUNT(*) FROM reaccion r WHERE r.id_publicacion = p.id_publicacion) AS total_likes,
               (SELECT COUNT(*) FROM reaccion r2 WHERE r2.id_publicacion = p.id_publicacion AND r2.id_usuario = :id_actual) AS user_liked,
               (SELECT COUNT(*) FROM comentario c WHERE c.id_publicacion = p.id_publicacion) AS total_comentarios
        FROM publicacion p
        JOIN usuario u ON p.id_usuario = u.id_usuario
        WHERE p.id_usuario = :id_actual
        ORDER BY p.id_publicacion DESC
    ";
    $stmtPubs = $conexion->prepare($sqlPubs);
    $stmtPubs->execute([':id_actual' => $id_usuario]);
    $publicaciones = $stmtPubs->fetchAll(PDO::FETCH_ASSOC);

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
    <title>ECOS • Perfil</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="CSS/Main.css" />
    <link rel="stylesheet" href="CSS/perfil.css" />
    <link rel="stylesheet" href="CSS/publicaciones.css" />
    <link rel="stylesheet" href="CSS/back-btn.css" />
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
    </style>
  </head>
  <body style="padding-top:70px;">

    <div id="header-container"></div>
    <script src="headerLoader.js"></script>

    <button class="back-btn" style="margin-top: 70px;" onclick="history.back()">←</button>

    <main class="page">
      <form action="actualizar_perfil.php" method="POST" enctype="multipart/form-data" id="perfil-form">
        
        <div class="cover-wrap">
          <img class="cover" src="<?php echo htmlspecialchars(!empty($usuario['banner']) ? $usuario['banner'] : 'Image/sinbanner.png'); ?>" alt="Cover" id="cover-img" style="z-index:1; position:relative;" />
          <div class="img-overlay cover-overlay" id="cover-overlay" style="display:none; border-radius:43px; z-index:2; cursor:pointer;"><span class="plus-icon">+</span></div>
          <input type="file" id="cover-file" name="cover" accept="image/*" style="display:none" />
          
          <img class="avatar" src="<?php echo htmlspecialchars(!empty($usuario['avatar']) ? $usuario['avatar'] : 'Image/sinavatar.jpg'); ?>" alt="Avatar" id="avatar-img" style="z-index:3; position:absolute; left:38px; bottom:-58px;" />
          <div class="img-overlay avatar-overlay" id="avatar-overlay" style="display:none; border-radius:999px; left:38px; bottom:-58px; width:132px; height:132px; z-index:4; cursor:pointer;"><span class="plus-icon">+</span></div>
          <input type="file" id="avatar-file" name="avatar" accept="image/*" style="display:none" />
          
          <button type="button" class="edit-btn" style="font-family: 'Montserrat', sans-serif; position: absolute; z-index: 10; right: 30px; bottom: -18px;">Editar perfil</button>
        </div>

        <section class="profile-row">
          <div class="user-left">
            <h1 class="name"><?php echo htmlspecialchars($usuario['nombre'] ?? 'Usuario'); ?></h1>
            <p class="handle"><?php echo htmlspecialchars($usuario['alias'] ?? ''); ?></p>
            <textarea class="bio" id="bio-input" name="bio" readonly rows="3" placeholder="Escribe tu biografía..."><?php echo htmlspecialchars($usuario['bio'] ?? ''); ?></textarea>
          </div>
          
          <div class="user-right">
            <div class="meta">
              <div>Género: <?php echo htmlspecialchars($usuario['genero'] ?? 'No especificado'); ?></div>
              <div>País: <?php echo htmlspecialchars($usuario['pais'] ?? 'No especificado'); ?></div>
              <div>Nacimiento: <?php echo htmlspecialchars($usuario['fecha_nacimiento'] ?? 'N/A'); ?></div>
            </div>
          </div>
        </section>

      </form>

      <!-- Feed de Publicaciones del Usuario -->
      <section class="feed" aria-label="Publicaciones">
        <div class="tabs-divider" style="width:auto; padding-bottom: 20px; margin-top: 40px;"></div>

        <?php if (!empty($publicaciones)): ?>
          <?php foreach ($publicaciones as $p): ?>
            <?php
              $fechaObj = new DateTime($p['fecha_creacion'] ?? 'now');
              $fechaFormat = $fechaObj->format('d / M / Y');
              $avatarPost = !empty($p['avatar']) ? $p['avatar'] : 'Image/sinavatar.jpg';
              $liked = !empty($p['user_liked']);
            ?>
            <article class="user-post-card" id="post-card-<?= $p['id_publicacion'] ?>">
              <div class="post-header-row">
                <div class="post-author-info">
                  <img src="<?= htmlspecialchars($avatarPost) ?>" class="post-user-avatar" alt="Avatar">
                  <div class="post-author-names">
                    <h2 class="post-author-name"><?= htmlspecialchars($p['nombre'] ?? 'Usuario') ?></h2>
                    <p class="post-author-handle"><?= htmlspecialchars($p['alias'] ?? '') ?></p>
                  </div>
                </div>
                <div class="post-actions-menu">
                  <button type="button" class="post-action-btn" onclick="abrirModalEditarPublicacion(<?= (int)$p['id_publicacion'] ?>, <?= htmlspecialchars(json_encode($p['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($p['texto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($p['imagen'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)">
                    ✏️ Editar
                  </button>
                  <button type="button" class="post-action-btn delete" onclick="eliminarPublicacion(<?= (int)$p['id_publicacion'] ?>)">
                    🗑️ Borrar
                  </button>
                </div>
              </div>

              <?php if (!empty($p['titulo'])): ?>
                <h3 class="post-title-text"><?= htmlspecialchars($p['titulo']) ?></h3>
              <?php endif; ?>

              <?php if (!empty($p['imagen'])): ?>
                <div class="post-media-container">
                  <img src="<?= htmlspecialchars($p['imagen']) ?>" class="post-media-img" alt="Imagen de la publicación" onclick="window.open(this.src, '_blank')">
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
                    <img src="<?= htmlspecialchars(!empty($usuario['avatar']) ? $usuario['avatar'] : 'Image/sinavatar.jpg') ?>" class="comment-user-avatar" alt="Avatar">
                    <textarea class="comment-textarea-field" id="comment-text-<?= $p['id_publicacion'] ?>" placeholder="Escribe un comentario..."></textarea>
                  </div>
                  <div class="comment-img-preview-box" id="comment-preview-box-<?= $p['id_publicacion'] ?>" style="display:none;"></div>
                  <div class="comment-tools-bar">
                    <div class="comment-tool-actions">
                      <button type="button" class="comment-tool-btn" onclick="document.getElementById('comment-file-<?= $p['id_publicacion'] ?>').click()">
                        <img src="Image/imagen.png" style="width:20px;height:20px;vertical-align:middle;" alt="Imagen"> Imagen
                      </button>
                      <input type="file" id="comment-file-<?= $p['id_publicacion'] ?>" accept="image/*" style="display:none;" onchange="previewCommentImage(this, <?= $p['id_publicacion'] ?>)">
                      <button type="button" class="comment-tool-btn" onclick="openEmojiPicker(event, 'comment-text-<?= $p['id_publicacion'] ?>')">
                        <img src="Image/emoji.png" style="width:20px;height:20px;vertical-align:middle;" alt="Emoji"> Emoji
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
        <?php else: ?>
          <div class="empty-feed-notice">
            <svg viewBox="0 0 24 24"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
            <h3>No tienes publicaciones aún</h3>
            <p>Haz clic en el botón '+' flotante para compartir música o imágenes con la comunidad.</p>
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
            <img src="<?= htmlspecialchars(!empty($usuario['avatar']) ? $usuario['avatar'] : 'Image/sinavatar.jpg') ?>" class="modal-avatar-img" alt="Avatar">
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

          <textarea id="modal-post-desc" class="modal-desc-textarea" placeholder="¿Qué quieres compartir hoy? Escribe algo..."></textarea>

          <input type="file" id="modal-post-file" accept="image/*" style="display:none;" onchange="handleModalFileSelect(this)">

          <div class="modal-bottom-actions">
            <div class="modal-tools-group">
              <button type="button" class="modal-tool-icon-btn" onclick="document.getElementById('modal-post-file').click()" title="Subir imagen desde tu computadora">
                <img src="Image/imagen.png" alt="Imagen">
              </button>
              <button type="button" class="modal-tool-icon-btn" onclick="openEmojiPicker(event, 'modal-post-desc')" title="Insertar emojis">
                <img src="Image/emoji.png" alt="Emojis">
              </button>
            </div>
            <button type="submit" id="modal-post-submit-btn" class="modal-submit-btn">Publicar</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Script de Publicaciones -->
    <script src="publicaciones.js"></script>

    <script>
      localStorage.setItem('userAvatar', '<?php echo htmlspecialchars(!empty($usuario['avatar']) ? $usuario['avatar'] : 'Image/sinavatar.jpg'); ?>');

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
