<?php require __DIR__ . '/PHP/solo_admin.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
        <script>
        function applyContrastFromStorage() {
            if (localStorage.getItem('contrastMode') === 'true') {
                document.body.classList.add('contrast-theme');
            } else {
                document.body.classList.remove('contrast-theme');
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            applyContrastFromStorage();
            var btn = document.getElementById('global-contrast-btn');
            if (btn) {
                btn.addEventListener('click', function() {
                    var isActive = document.body.classList.contains('contrast-theme');
                    if (isActive) {
                        localStorage.setItem('contrastMode', 'false');
                    } else {
                        localStorage.setItem('contrastMode', 'true');
                    }
                    applyContrastFromStorage();
                });
            }
            window.addEventListener('storage', function(e) {
                if (e.key === 'contrastMode') {
                    applyContrastFromStorage();
                }
            });
        });
        </script>
        <style>
          .contrast-theme h1,
          .contrast-theme h2,
          .contrast-theme h3,
          .contrast-theme h4,
          .contrast-theme h5,
          .contrast-theme h6,
          .contrast-theme .card-title,
          .contrast-theme .user-name,
          .contrast-theme .card-footer {
            font-weight: 800 !important;
          }
        </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECOS • Administrar Publicaciones</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Poppins:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/aceptarPublicacionesAdmin.css" />
    <link rel="stylesheet" href="CSS/back-btn.css" />
</head>
<body style="padding-top:70px;">
    <div id="header-container"></div>
    <script src="JS/headerLoader.js"></script>

    <main>
        <h1>Administrar Publicaciones</h1>

        <button class="back-btn" style="margin-top: 70px;" onclick="history.back()">←</button>

        <p id="admin-resumen" class="admin-resumen">Cargando publicaciones pendientes...</p>

        <!-- Publicaciones pendientes de aprobación (se llenan desde la base de datos) -->
        <div class="cards-container" id="admin-pendientes"></div>

        <div id="no-cards-message" class="admin-vacio" style="display:none;">
            <div class="admin-vacio-icono">✅</div>
            <div>No hay publicaciones pendientes</div>
            <p>Cuando alguien publique algo, aparecerá aquí para que lo revises.</p>
        </div>
    </main>

    <div id="admin-toast" class="admin-toast" role="status"></div>

    <style>
        .admin-resumen { text-align: center; margin: 0 0 10px; color: var(--text); opacity: .8; font-family: 'Montserrat', sans-serif; }
        .admin-vacio { text-align: center; font-size: 1.4rem; font-weight: 600; color: #888; padding: 60px 16px; font-family: 'Montserrat', sans-serif; }
        .admin-vacio-icono { font-size: 2.6rem; margin-bottom: 8px; }
        .admin-vacio p { font-size: 1rem; font-weight: 400; margin: 8px 0 0; }
        .card { display: flex; flex-direction: column; }
        .card-link { color: inherit; text-decoration: none; display: block; }
        .card-link:hover .card-title { text-decoration: underline; }
        .card .user-info { min-width: 0; }
        .card .user-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-title { padding: 10px 12px 4px; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-footer { padding: 0 12px 12px; margin-top: auto; overflow-wrap: anywhere; }
        .card-sin-imagen { height: 121px; padding: 14px 16px; box-sizing: border-box; overflow: hidden; background: rgba(127,127,127,.12); color: var(--text); font-size: .9rem; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow-wrap: anywhere; }
        .action-btn { border-radius: 50%; transition: transform .15s, background .15s; }
        .action-btn:hover { transform: scale(1.08); background: rgba(127,127,127,.15); }
        .action-btn:disabled { opacity: .4; cursor: default; transform: none; }
        .reject-icon circle, .reject-icon line { stroke: var(--text); }
        .admin-toast { position: fixed; left: 50%; bottom: 30px; transform: translateX(-50%) translateY(100px); background: #222; color: #fff; padding: 14px 28px; border-radius: 50px; font-family: 'Montserrat', sans-serif; font-weight: 600; opacity: 0; transition: all .35s ease; z-index: 10000; pointer-events: none; }
        .admin-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>

    <script>
    (function () {
        const API = 'api_publicaciones.php';
        const ICONO_APROBAR = '<svg class="check-icon" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M34.8327 17.5434V19.0001C34.8307 22.4144 33.7251 25.7367 31.6808 28.4713C29.6364 31.206 26.7629 33.2065 23.4887 34.1746C20.2144 35.1427 16.715 35.0265 13.5123 33.8432C10.3095 32.66 7.57505 30.4731 5.7167 27.6088C3.85835 24.7445 2.97568 21.3562 3.20034 17.9493C3.42499 14.5423 4.74493 11.2993 6.96329 8.70381C9.18166 6.10832 12.1796 4.29947 15.51 3.54701C18.8404 2.79455 22.3248 3.13881 25.4435 4.52844M34.8327 6.33344L18.9994 22.1826L14.2494 17.4326" stroke="#1E1E1E" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const ICONO_RECHAZAR = '<svg class="reject-icon" viewBox="0 0 38 38" width="34" height="34" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="19" cy="19" r="15.5" stroke-width="3"/><line x1="8.5" y1="8.5" x2="29.5" y2="29.5" stroke-width="3" stroke-linecap="round"/></svg>';

        function escapar(texto) {
            const div = document.createElement('div');
            div.textContent = texto == null ? '' : String(texto);
            return div.innerHTML;
        }

        function aviso(mensaje) {
            const t = document.getElementById('admin-toast');
            t.textContent = mensaje;
            t.classList.add('show');
            clearTimeout(t._timer);
            t._timer = setTimeout(() => t.classList.remove('show'), 2800);
        }

        function actualizarResumen() {
            const n = document.querySelectorAll('#admin-pendientes .card').length;
            document.getElementById('admin-resumen').textContent =
                n ? `${n} publicación${n === 1 ? '' : 'es'} esperando revisión` : '';
            document.getElementById('no-cards-message').style.display = n ? 'none' : 'block';
        }

        function tarjeta(p) {
            const urlPost = 'PHP/5verPost.php?id=' + p.id_publicacion;
            const titulo = p.titulo || (p.texto || '').slice(0, 80) || 'Sin título';
            const media = p.imagen
                ? `<div class="card-image-wrapper">
                       <img src="${escapar(p.imagen)}" alt="${escapar(titulo)}" class="card-image">
                       <div class="image-gradient"></div>
                   </div>`
                : `<div class="card-sin-imagen">${escapar(p.texto || 'Publicación sin imagen')}</div>`;
            const tags = p.hashtags.map(t => '#' + escapar(t)).join(' ');

            return `
            <div class="card" data-id="${p.id_publicacion}">
                <div class="card-header">
                    <a href="PHP/6perfilPropio.php?id=${p.id_usuario}"><img src="${escapar(p.avatar)}" alt="${escapar(p.nombre)}" class="user-avatar" onerror="this.onerror=null;this.src='Image/cuenta.png'"></a>
                    <div class="user-info">
                        <div class="user-name" title="${escapar(p.nombre)}">${escapar(p.nombre)}</div>
                    </div>
                    <div class="card-actions">
                        <button type="button" class="action-btn" data-accion="aprobar" aria-label="Aprobar publicación" title="Aprobar: aparecerá en el inicio">${ICONO_APROBAR}</button>
                        <button type="button" class="action-btn" data-accion="rechazar" aria-label="Rechazar publicación" title="Rechazar: se borrará">${ICONO_RECHAZAR}</button>
                    </div>
                </div>
                <a class="card-link" href="${urlPost}" title="Ver publicación completa">
                    ${media}
                    <div class="card-body">
                        <div class="card-title">${escapar(titulo)}</div>
                    </div>
                </a>
                <div class="card-footer">${tags ? tags + ' - ' : ''}${escapar(p.fecha_formateada)}</div>
            </div>`;
        }

        async function cargar() {
            const cont = document.getElementById('admin-pendientes');
            try {
                const resp = await fetch(API + '?accion=admin_pendientes', { cache: 'no-store' });
                const data = await resp.json();
                if (!data.success) throw new Error(data.error || 'No se pudieron cargar las publicaciones.');
                cont.innerHTML = data.publicaciones.map(tarjeta).join('');
                actualizarResumen();
            } catch (err) {
                document.getElementById('admin-resumen').textContent = err.message;
            }
        }

        async function revisar(boton) {
            const card = boton.closest('.card');
            const id = card.dataset.id;
            const accion = boton.dataset.accion;

            if (accion === 'rechazar' &&
                !confirm('¿Rechazar esta publicación?\n\nSe borrará definitivamente junto con su imagen, comentarios y likes.')) {
                return;
            }

            card.querySelectorAll('.action-btn').forEach(b => b.disabled = true);
            try {
                const datos = new FormData();
                datos.append('id_publicacion', id);
                const resp = await fetch(API + '?accion=admin_' + accion, { method: 'POST', body: datos });
                const data = await resp.json();
                if (!data.success) throw new Error(data.error || 'No se pudo completar la acción.');

                card.classList.add('processing');
                setTimeout(() => { card.remove(); actualizarResumen(); }, 300);
                aviso(data.mensaje);
            } catch (err) {
                card.querySelectorAll('.action-btn').forEach(b => b.disabled = false);
                alert(err.message);
            }
        }

        document.getElementById('admin-pendientes').addEventListener('click', (e) => {
            const boton = e.target.closest('.action-btn');
            if (boton) {
                e.preventDefault();
                revisar(boton);
            }
        });

        cargar();
    })();
    </script>
</body>
</html>