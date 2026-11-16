<?php
// Página de una publicación real: PHP/5verPost.php?id=N
require_once __DIR__ . '/error_popup.php';

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../2inicioSesion.html");
    exit();
}

require __DIR__ . '/conexion.php';
require_once __DIR__ . '/admin.php';

const AVATAR_DEFECTO = 'Image/cuenta.png';

function rutaAvatar($ruta) {
    return '../' . ltrim(!empty($ruta) ? $ruta : AVATAR_DEFECTO, '/');
}

$id_sesion = (int)$_SESSION['id_usuario'];
$id_pub = (int)($_GET['id'] ?? 0);

try {
    $stmt = $conexion->prepare("
        SELECT p.id_publicacion, p.id_usuario, p.titulo, p.texto, p.imagen, p.estado, p.fecha_creacion,
               u.nombre, u.alias, u.avatar,
               fn_total_likes(p.id_publicacion) AS total_likes,
               (SELECT COUNT(*) FROM reaccion r2 WHERE r2.id_publicacion = p.id_publicacion AND r2.id_usuario = :yo) AS user_liked,
               (SELECT COUNT(*) FROM comentario c WHERE c.id_publicacion = p.id_publicacion) AS total_comentarios
        FROM publicacion p
        JOIN usuario u ON u.id_usuario = p.id_usuario
        WHERE p.id_publicacion = :id
    ");
    $stmt->execute([':yo' => $id_sesion, ':id' => $id_pub]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        mostrarPopupError('La publicación que buscas no existe.', '3main.html');
    }

    // Si hay un bloqueo entre tú y el autor, la publicación no se muestra
    $stmtBloq = $conexion->prepare("
        SELECT 1 FROM relacion_usuario
        WHERE tipo_relacion = 'bloqueo'
          AND ((id_usuario_origen = :yo AND id_usuario_destino = :autor)
            OR (id_usuario_origen = :autor2 AND id_usuario_destino = :yo2))
    ");
    $stmtBloq->execute([':yo' => $id_sesion, ':autor' => $post['id_usuario'], ':autor2' => $post['id_usuario'], ':yo2' => $id_sesion]);
    if ($stmtBloq->fetchColumn()) {
        mostrarPopupError('Esta publicación no está disponible.', '3main.html');
    }

    // Pendiente de aprobación: solo la ven su autor y los administradores
    $es_admin  = sesionEsAdministrador($conexion);
    $pendiente = ($post['estado'] ?? '') !== 'aprobada';
    if ($pendiente && (int)$post['id_usuario'] !== $id_sesion && !$es_admin) {
        mostrarPopupError('Esta publicación todavía no está disponible.', '3main.html');
    }

    $stmtTags = $conexion->prepare("
        SELECT h.nombre FROM publicacion_hashtag ph
        JOIN hashtag h ON h.id_hashtag = ph.id_hashtag
        WHERE ph.id_publicacion = :id
        ORDER BY ph.id_pub_hash ASC
    ");
    $stmtTags->execute([':id' => $id_pub]);
    $hashtags = $stmtTags->fetchAll(PDO::FETCH_COLUMN);

    // Quien está viendo (para el avatar del cuadro de comentario)
    $stmtYo = $conexion->prepare("SELECT nombre, avatar FROM usuario WHERE id_usuario = :id");
    $stmtYo->execute([':id' => $id_sesion]);
    $yo = $stmtYo->fetch(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    error_log($e->getMessage());
    mostrarPopupError("Error al cargar la publicación: " . $e->getMessage(), '3main.html');
}

$id_pub    = (int)$post['id_publicacion'];
$es_propia = ((int)$post['id_usuario'] === $id_sesion);
$liked     = (int)$post['user_liked'] > 0;
$imgPost   = !empty($post['imagen']) ? '../' . ltrim($post['imagen'], '/') : '';
$fecha     = (new DateTime($post['fecha_creacion'] ?? 'now'))->format('d / M / Y');
$urlAutor  = '6perfilPropio.php?id=' . (int)$post['id_usuario'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ECOS • <?= htmlspecialchars($post['titulo'] ?: 'Publicación') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../CSS/descubre.css" />
  <link rel="stylesheet" href="../CSS/Main.css" />
  <link rel="stylesheet" href="../CSS/verPost.css" />
  <link rel="stylesheet" href="../CSS/back-btn.css" />
  <link rel="stylesheet" href="../CSS/publicaciones.css" />
  <style>
    .ver-publicacion-card .post-img {
      display: block;
      width: 100%;
      max-height: 600px;
      object-fit: cover;
      border-radius: 24px;
      cursor: zoom-in;
    }
    .ver-publicacion-card .post-text {
      white-space: normal;
      word-break: break-word;
    }
    .ver-post-acciones {
      display: flex;
      gap: 8px;
      margin-left: auto;
    }
    .ver-post-header-row {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    #comentarios .comments-section-wrapper {
      border-top: none;
      margin-top: 0;
    }
    .ver-post-subtitulo {
      font-size: 1.1rem;
      font-weight: 700;
      margin: 10px 0 0;
      color: var(--text);
    }
    .ver-post-pendiente {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin: 0 0 18px;
      padding: 12px 18px;
      border-radius: 16px;
      background: rgba(255, 176, 32, 0.15);
      color: var(--text);
    }
    .ver-post-moderar { display: flex; gap: 8px; }
    .moderar-btn {
      border: none;
      border-radius: 999px;
      padding: 8px 18px;
      font-family: 'Montserrat', sans-serif;
      font-weight: 600;
      cursor: pointer;
      color: #fff;
    }
    .moderar-btn.aprobar { background: #2e9d57; }
    .moderar-btn.rechazar { background: #D24242; }
  </style>
</head>
<body style="padding-top:70px;">

  <div id="header-container"></div>
  <script src="../JS/headerLoader.js"></script>
  <script>
    function applyContrastFromStorage() {
      document.body.classList.toggle('contrast-theme', localStorage.getItem('contrastMode') === 'true');
    }
    document.addEventListener('DOMContentLoaded', function () {
      applyContrastFromStorage();
      window.addEventListener('storage', function (e) {
        if (e.key === 'contrastMode') applyContrastFromStorage();
      });
    });
  </script>

  <main class="container">
    <button class="back-btn" onclick="history.back()" style="top: 90px;">←</button>

    <div class="page-header">
      <h2 class="page-title">Publicación</h2>
    </div>

    <?php if ($pendiente): ?>
      <div class="ver-post-pendiente">
        <span>⏳ Esta publicación está <strong>pendiente de aprobación</strong>.
          <?= $es_admin ? 'Revísala y decide si aparece en el inicio.' : 'Solo tú puedes verla hasta que un administrador la apruebe.' ?></span>
        <?php if ($es_admin): ?>
          <span class="ver-post-moderar">
            <button type="button" class="moderar-btn aprobar" onclick="moderarPublicacion('aprobar', <?= $id_pub ?>)">✓ Aprobar</button>
            <button type="button" class="moderar-btn rechazar" onclick="moderarPublicacion('rechazar', <?= $id_pub ?>)">⦸ Rechazar</button>
          </span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="layout">
      <section>
        <article class="post-card ver-publicacion-card" id="post-card-<?= $id_pub ?>">
          <div class="ver-post-header-row">
            <a href="<?= htmlspecialchars($urlAutor) ?>" class="post-header" style="text-decoration: none; color: inherit;">
              <img class="post-avatar" src="<?= htmlspecialchars(rutaAvatar($post['avatar'])) ?>" alt="<?= htmlspecialchars($post['nombre']) ?>" />
              <h3 class="post-author"><?= htmlspecialchars($post['nombre']) ?></h3>
            </a>
            <?php if ($es_propia): ?>
              <div class="ver-post-acciones">
                <button type="button" class="post-action-btn" onclick="abrirModalEditarPublicacion(<?= $id_pub ?>, <?= htmlspecialchars(json_encode($post['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($post['texto'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($imgPost), ENT_QUOTES, 'UTF-8') ?>)">✏️ Editar</button>
                <button type="button" class="post-action-btn delete" onclick="borrarEstaPublicacion(<?= $id_pub ?>)">🗑️ Borrar</button>
              </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($post['titulo'])): ?>
            <h4 class="post-title"><?= htmlspecialchars($post['titulo']) ?></h4>
          <?php endif; ?>

          <?php if ($imgPost): ?>
            <img class="post-img" src="<?= htmlspecialchars($imgPost) ?>" alt="<?= htmlspecialchars($post['titulo'] ?: 'Imagen de la publicación') ?>" onclick="zoomImage(this.src)" />
          <?php endif; ?>

          <?php if (!empty($post['texto'])): ?>
            <p class="post-text"><?= nl2br(htmlspecialchars($post['texto'])) ?></p>
          <?php endif; ?>

          <div class="post-footer" style="margin: 15px;">
            <?php if (!empty($hashtags)): ?>
              <span class="post-tags">
                <?php foreach ($hashtags as $tag): ?>
                  <a class="post-tag" href="../4busqueda.html?tag=<?= urlencode($tag) ?>" style="text-decoration: none; color: inherit">#<?= htmlspecialchars($tag) ?></a>
                <?php endforeach; ?>
              </span>
            <?php else: ?>
              <span class="post-tags"></span>
            <?php endif; ?>
            <span class="post-date"><?= $fecha ?></span>
            <div class="post-stats">
              <div class="post-stat like-button <?= $liked ? 'liked' : '' ?>" role="button" title="Me gusta" onclick="toggleLikePost(this, <?= $id_pub ?>)">
                <span class="post-stat-number"><?= (int)$post['total_likes'] ?></span>
                <svg class="post-icon heart-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M10 20L8.55 18.5831C6.86667 16.9301 5.475 15.5041 4.375 14.3052C3.275 13.1063 2.4 12.03 1.75 11.0763C1.1 10.1226 0.645833 9.24614 0.3875 8.44687C0.129167 7.64759 0 6.83015 0 5.99455C0 4.28701 0.525 2.86104 1.575 1.71662C2.625 0.572207 3.93333 0 5.5 0C6.36667 0 7.19167 0.199818 7.975 0.599455C8.75833 0.999092 9.43333 1.56222 10 2.28883C10.5667 1.56222 11.2417 0.999092 12.025 0.599455C12.8083 0.199818 13.6333 0 14.5 0C16.0667 0 17.375 0.572207 18.425 1.71662C19.475 2.86104 20 4.28701 20 5.99455C20 6.83015 19.8708 7.64759 19.6125 8.44687C19.3542 9.24614 18.9 10.1226 18.25 11.0763C17.6 12.03 16.725 13.1063 15.625 14.3052C14.525 15.5041 13.1333 16.9301 11.45 18.5831L10 20Z" fill="<?= $liked ? '#ff3040' : '#777777' ?>"/>
                </svg>
              </div>
              <a class="post-stat comment-button" href="#comentarios" style="text-decoration: none; color: inherit" title="Comentarios">
                <span class="post-stat-number" id="comment-count-<?= $id_pub ?>"><?= (int)$post['total_comentarios'] ?></span>
                <svg class="post-icon comment-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M20 20L16 16H2C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V2C0 1.45 0.979167 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V20ZM2 14H16.85L18 15.125V2H2V14Z" fill="#777777"/>
                </svg>
              </a>
            </div>
          </div>
        </article>

        <div class="tabs-divider" style="width:auto;"></div>

        <!-- Comentarios reales (funciones de publicaciones.js) -->
        <section id="comentarios">
          <h3 class="ver-post-subtitulo">Comentarios</h3>
          <div class="comments-section-wrapper show" id="comments-section-<?= $id_pub ?>">
            <div class="new-comment-box">
              <div class="comment-input-area">
                <img src="<?= htmlspecialchars(rutaAvatar($yo['avatar'] ?? '')) ?>" class="comment-user-avatar" alt="Avatar">
                <textarea class="comment-textarea-field" id="comment-text-<?= $id_pub ?>" placeholder="Comenta algo..."></textarea>
              </div>
              <div class="comment-img-preview-box" id="comment-preview-box-<?= $id_pub ?>" style="display:none;"></div>
              <div class="comment-tools-bar">
                <div class="comment-tool-actions">
                  <button type="button" class="comment-tool-btn" onclick="document.getElementById('comment-file-<?= $id_pub ?>').click()">
                    <img src="../Image/imagen.png" style="width:20px;height:20px;vertical-align:middle;" alt="Imagen"> Imagen
                  </button>
                  <input type="file" id="comment-file-<?= $id_pub ?>" accept="image/*" style="display:none;" onchange="previewCommentImage(this, <?= $id_pub ?>)">
                  <button type="button" class="comment-tool-btn" onclick="openEmojiPicker(event, 'comment-text-<?= $id_pub ?>')">
                    <img src="../Image/emoji.png" style="width:20px;height:20px;vertical-align:middle;" alt="Emoji"> Emoji
                  </button>
                </div>
                <button type="button" class="btn-send-comment" onclick="enviarComentario(<?= $id_pub ?>)">Comentar</button>
              </div>
            </div>

            <div class="comments-items-list" id="comments-list-<?= $id_pub ?>">
              <p style="opacity:.7;">Cargando comentarios...</p>
            </div>
          </div>
        </section>
      </section>

      <aside class="sidebar">
        <div class="sidebar-section">
          <h3 class="sidebar-title">Descubre</h3>
          <div class="sidebar-card" id="descubre-lista">
            <p class="discover-name" style="margin:0;opacity:.7;">Cargando...</p>
          </div>
        </div>

        <div class="sidebar-section" style="margin-top:25px;">
          <h3 class="sidebar-title">Temas Actuales</h3>
          <div class="sidebar-card" id="temas-lista">
            <p class="hashtag-item" style="margin:0;opacity:.7;">Cargando...</p>
          </div>
        </div>
      </aside>
    </div>
  </main>

  <?php if ($es_propia): ?>
  <!-- Modal para editar la publicación (usa publicaciones.js) -->
  <div id="modal-publicacion" class="ecos-modal-overlay">
    <div class="ecos-modal-card">
      <div class="modal-header-section">
        <div class="modal-author-profile">
          <img src="<?= htmlspecialchars(rutaAvatar($yo['avatar'] ?? '')) ?>" class="modal-avatar-img" alt="Avatar">
          <span class="modal-author-name" id="modal-post-header-title">Editar Publicación</span>
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
          <button type="submit" id="modal-post-submit-btn" class="modal-submit-btn">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script src="../publicaciones.js"></script>
  <script src="../JS/descubre.js"></script>
  <script src="../JS/temas.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      cargarComentarios(<?= $id_pub ?>);
    });

    // Administrador: aprobar o rechazar desde la misma publicación
    async function moderarPublicacion(accion, id) {
    if (accion === 'rechazar' && !confirm('¿Rechazar esta publicación?\n\nSe borrará definitivamente junto con su imagen, comentarios y likes.')) return;
      try {
        const datos = new FormData();
        datos.append('id_publicacion', id);
        const resp = await fetch(getPublicacionesApiEndpoint('accion=admin_' + accion), { method: 'POST', body: datos });
        const data = await parseJsonResponse(resp);
        if (!data.success) throw new Error(data.error || 'No se pudo completar la acción.');
        alert(data.mensaje);
        window.location.href = accion === 'aprobar' ? window.location.href : '../aceptarPublicacionesAdmin.php';
      } catch (err) {
        alert(err.message);
      }
    }

    // Borrar la publicación y volver al inicio
    async function borrarEstaPublicacion(id) {
      if (!confirm('¿Estás seguro de que deseas eliminar esta publicación? No podrás deshacer esta acción.')) return;
      try {
        const datos = new FormData();
        datos.append('id_publicacion', id);
        const resp = await fetch(getPublicacionesApiEndpoint('accion=eliminar_publicacion'), { method: 'POST', body: datos });
        const data = await parseJsonResponse(resp);
        if (!data.success) throw new Error(data.error || 'No se pudo eliminar la publicación.');
        window.location.href = '../3main.html';
      } catch (err) {
        alert(err.message);
      }
    }

    // Ver la imagen en grande
    function zoomImage(src) {
      document.body.style.overflow = 'hidden';
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0);display:flex;align-items:center;justify-content:center;z-index:10000;transition:background-color .3s ease;cursor:zoom-out;';
      const img = document.createElement('img');
      img.src = src;
      img.style.cssText = 'max-width:85%;max-height:85%;border-radius:10px;transform:scale(.8);opacity:0;transition:all .3s cubic-bezier(.25,.8,.25,1);';
      overlay.appendChild(img);
      document.body.appendChild(overlay);
      setTimeout(() => {
        overlay.style.backgroundColor = 'rgba(0,0,0,0.8)';
        img.style.transform = 'scale(1)';
        img.style.opacity = '1';
      }, 10);
      overlay.onclick = () => {
        img.style.opacity = '0';
        overlay.style.backgroundColor = 'rgba(0,0,0,0)';
        setTimeout(() => { overlay.remove(); document.body.style.overflow = ''; }, 300);
      };
    }
  </script>
</body>
</html>
