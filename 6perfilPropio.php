<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: 2inicioSesion.html");
    exit();
}

require 'conexion.php';

try {
    $id_usuario = $_SESSION['id_usuario'];
    $sql = "SELECT * FROM usuario WHERE id_usuario = :id";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id', $id_usuario, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    exit("Error al cargar el perfil: " . $e->getMessage());
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
      #popup-overlay {
          display: none;
          position: fixed;
          top: 0; left: 0;
          width: 100%; height: 100%;
          background: rgba(0,0,0,0.5);
          justify-content: center;
          align-items: center;
          z-index: 10000;
      }
      #popup-container {
          background: rgba(0, 0, 0, 0);
          padding: 20px;
          border-radius: 10px;
          min-width: 400px;
          min-height: 200px;
          position: relative;
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
          width: 50%; /* Caja de biografía ajustada a la mitad */
          font-family: inherit;
          border-radius: 8px;
          transition: background 0.3s;
      }
      .user-left .bio:not([readonly]) {
          background: rgba(0,0,0,0.06);
          padding: 8px;
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

      <section class="feed" aria-label="Publicaciones">
        <div class="tabs-divider" style="width:auto ;padding-bottom: 20px; margin-top: 40px;"></div>
      </section>
    </main>

    <button id="abrir-popup" class="fab-button" aria-label="Create Post">
        <svg class="fab-icon" viewBox="0 0 84 84" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M38.5385 57.6462H44.5384V45.6462H56.5384V39.6462H44.5384V27.6462H38.5385V39.6462H26.5385V45.6462H38.5385V57.6462ZM41.5385 72.6462C37.3885 72.6462 33.4885 71.8587 29.8385 70.2837C26.1885 68.7087 23.0135 66.5712 20.3135 63.8712C17.6135 61.1712 15.476 57.9962 13.901 54.3462C12.326 50.6962 11.5385 46.7962 11.5385 42.6462C11.5385 38.4962 12.326 34.5962 13.901 30.9462C15.476 27.2962 17.6135 24.1212 20.3135 21.4212C23.0135 18.7212 26.1885 16.5837 29.8385 15.0087C33.4885 13.4337 37.3885 12.6462 41.5385 12.6462C45.6884 12.6462 49.5885 13.4337 53.2384 15.0087C56.8885 16.5837 60.0634 18.7212 62.7635 21.4212C65.4634 24.1212 67.6009 27.2962 69.1759 30.9462C70.7509 34.5962 71.5384 38.4962 71.5384 42.6462C71.5384 46.7962 70.7509 50.6962 69.1759 54.3462C67.6009 57.9962 65.4634 61.1712 62.7635 63.8712C60.0634 66.5712 56.8885 68.7087 53.2384 70.2837C49.5885 71.8587 45.6884 72.6462 41.5385 72.6462Z"/>
        </svg>
    </button>

    <div id="popup-overlay">
        <div id="popup-container"></div>
    </div>

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

        // Script robusto para abrir el popup con el botón flotante
        const abrirPopupBtn = document.getElementById('abrir-popup');
        if (abrirPopupBtn) {
            abrirPopupBtn.addEventListener('click', () => {
                fetch('crearPublicacion.html')
                .then(res => {
                    if (!res.ok) throw new Error('Archivo no encontrado');
                    return res.text();
                })
                .then(html => {
                    const popupContainer = document.getElementById('popup-container');
                    const popupOverlay = document.getElementById('popup-overlay');
                    
                    popupContainer.innerHTML = html;
                    popupOverlay.style.display = 'flex';
                    
                    const closeBtn = popupContainer.querySelector('.close-btn');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', () => {
                            popupOverlay.style.display = 'none';
                            popupContainer.innerHTML = '';
                        });
                    }
                    
                    popupOverlay.addEventListener('click', (e) => {
                        if(e.target === popupOverlay) {
                            popupOverlay.style.display = 'none';
                            popupContainer.innerHTML = '';
                        }
                    });
                })
                .catch(err => {
                    console.error('Error al cargar la publicación:', err);
                });
            });
        }
      });
    </script>
  </body>
</html>