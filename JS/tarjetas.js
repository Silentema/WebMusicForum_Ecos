/**
 * Tarjetas compartidas de ECOS (feed, búsqueda, etc.)
 *
 *   EcosTarjetas.publicacion(p)   -> HTML de una publicación (mismo diseño que el feed)
 *   EcosTarjetas.usuario(u)       -> HTML de un usuario (resultado de búsqueda)
 *   EcosTarjetas.activar(cont)    -> activa likes y botones Seguir dentro de un contenedor
 *   EcosTarjetas.url(ruta)        -> URL absoluta a partir de la raíz del proyecto
 *   EcosTarjetas.escapar(texto)   -> escapa HTML
 *
 * Cargar ANTES de feed.js / busqueda.js:  <script src="JS/tarjetas.js"></script>
 */
(function () {
  // Raíz del proyecto calculada desde la ubicación de este script (JS/tarjetas.js)
  const raizProyecto = new URL('../', document.currentScript.src);
  const url = (ruta) => new URL(ruta, raizProyecto).href;

  const ICONO_CORAZON = '<path d="M10 20L8.55 18.5831C6.86667 16.9301 5.475 15.5041 4.375 14.3052C3.275 13.1063 2.4 12.03 1.75 11.0763C1.1 10.1226 0.645833 9.24614 0.3875 8.44687C0.129167 7.64759 0 6.83015 0 5.99455C0 4.28701 0.525 2.86104 1.575 1.71662C2.625 0.572207 3.93333 0 5.5 0C6.36667 0 7.19167 0.199818 7.975 0.599455C8.75833 0.999092 9.43333 1.56222 10 2.28883C10.5667 1.56222 11.2417 0.999092 12.025 0.599455C12.8083 0.199818 13.6333 0 14.5 0C16.0667 0 17.375 0.572207 18.425 1.71662C19.475 2.86104 20 4.28701 20 5.99455C20 6.83015 19.8708 7.64759 19.6125 8.44687C19.3542 9.24614 18.9 10.1226 18.25 11.0763C17.6 12.03 16.725 13.1063 15.625 14.3052C14.525 15.5041 13.1333 16.9301 11.45 18.5831L10 20Z" fill="#777777"/>';
  const ICONO_COMENTARIO = '<path d="M20 20L16 16H2C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V2C0 1.45 0.979167 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V20ZM2 14H16.85L18 15.125V2H2V14Z" fill="#777777"/>';

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto == null ? '' : String(texto);
    return div.innerHTML;
  }

  function recortar(texto, max) {
    texto = (texto || '').trim();
    return texto.length > max ? texto.slice(0, max).trimEnd() + '…' : texto;
  }

  const urlPerfil = (id) => url('PHP/6perfilPropio.php?id=' + encodeURIComponent(id));
  const urlPost = (id) => url('PHP/5verPost.php?id=' + encodeURIComponent(id));
  const urlTag = (tag) => url('4busqueda.html?tag=' + encodeURIComponent(tag));
  const avatarOnError = `this.onerror=null;this.src='${url('Image/cuenta.png')}'`;

  function publicacion(p) {
    const titulo = p.titulo || recortar(p.texto, 90);

    // Con imagen: imagen con el título encima. Sin imagen: tarjeta de texto.
    const contenido = p.imagen
      ? `<div class="post-image-container">
           <img src="${escapar(url(p.imagen))}" alt="${escapar(titulo)}" class="post-image">
           <div class="post-image-gradient">
             <div class="post-image-title">${escapar(titulo)}</div>
           </div>
         </div>`
      : `<div class="feed-texto-card">
           ${p.titulo ? `<h4 class="feed-texto-titulo">${escapar(p.titulo)}</h4>` : ''}
           ${p.texto ? `<p class="feed-texto-cuerpo">${escapar(recortar(p.texto, 280))}</p>` : ''}
         </div>`;

    const tags = `<span class="post-tags">${(p.hashtags || []).map((t) =>
      `<a class="post-tag" href="${urlTag(t)}" style="text-decoration: none; color: inherit">#${escapar(t)}</a>`
    ).join('')}</span>`;

    return `
      <article class="post-card" id="feed-post-${p.id_publicacion}">
        <a href="${urlPerfil(p.id_usuario)}" style="text-decoration: none; color: inherit">
          <div class="post-header">
            <img src="${escapar(url(p.avatar))}" alt="${escapar(p.nombre)}" class="post-avatar" onerror="${avatarOnError}">
            <h3 class="post-author">${escapar(p.nombre)}</h3>
          </div>
        </a>
        <a href="${urlPost(p.id_publicacion)}" style="text-decoration: none; color: inherit">
          ${contenido}
        </a>
        <div class="post-footer">
          ${tags}
          <span class="post-date">${escapar(p.fecha_formateada)}</span>
          <div class="post-stats">
            <div class="post-stat like-button ${p.user_liked ? 'liked' : ''}" data-like="${p.id_publicacion}" role="button" title="Me gusta">
              <span class="post-stat-number">${p.total_likes}</span>
              <svg class="post-icon heart-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">${ICONO_CORAZON}</svg>
            </div>
            <a class="post-stat comment-button" href="${urlPost(p.id_publicacion)}#comentarios" style="text-decoration: none; color: inherit" title="Comentarios">
              <span class="post-stat-number">${p.total_comentarios}</span>
              <svg class="post-icon comment-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">${ICONO_COMENTARIO}</svg>
            </a>
          </div>
        </div>
      </article>`;
  }

  function usuario(u) {
    const boton = u.es_propio
      ? '<span class="user-tu">Tú</span>'
      : `<button type="button" class="follow ${u.lo_sigo ? 'siguiendo' : ''}" data-seguir="${u.id_usuario}">${u.lo_sigo ? 'Siguiendo' : 'Seguir'}</button>`;

    return `
      <div class="user-card" id="user-card-${u.id_usuario}">
        <a href="${urlPerfil(u.id_usuario)}" class="user-profile-link">
          <img class="user-avatar" alt="${escapar(u.nombre)}" src="${escapar(url(u.avatar))}" onerror="${avatarOnError}">
          <div class="user-main">
            <h3 class="user-name">${escapar(u.nombre)}</h3>
            ${u.alias ? `<p class="user-username">${escapar(u.alias)}</p>` : ''}
            ${u.bio ? `<p class="user-bio">${escapar(recortar(u.bio, 140))}</p>` : ''}
            <p class="user-seguidores"><span data-seguidores="${u.id_usuario}">${u.seguidores}</span> seguidores</p>
          </div>
        </a>
        ${boton}
      </div>`;
  }

  async function enviar(accion, campos) {
    const datos = new FormData();
    Object.entries(campos).forEach(([k, v]) => datos.append(k, v));
    const resp = await fetch(url('api_publicaciones.php?accion=' + accion), { method: 'POST', body: datos });
    const data = await resp.json();
    if (!data.success) throw new Error(data.error || 'No se pudo completar la acción.');
    return data;
  }

  // Delegación de eventos: funciona también con tarjetas agregadas después
  function activar(contenedor) {
    if (!contenedor || contenedor.dataset.tarjetasActivas) return;
    contenedor.dataset.tarjetasActivas = '1';

    contenedor.addEventListener('click', async (e) => {
      const like = e.target.closest('[data-like]');
      if (like && contenedor.contains(like)) {
        e.preventDefault();
        e.stopPropagation();
        if (like.dataset.ocupado) return;
        like.dataset.ocupado = '1';
        try {
          const data = await enviar('toggle_like', { id_publicacion: like.dataset.like });
          like.classList.toggle('liked', data.liked);
          like.querySelector('.post-stat-number').textContent = data.total_likes;
        } catch (err) {
          console.error('Like:', err);
        } finally {
          delete like.dataset.ocupado;
        }
        return;
      }

      const seguir = e.target.closest('[data-seguir]');
      if (seguir && contenedor.contains(seguir)) {
        e.preventDefault();
        if (seguir.disabled) return;
        seguir.disabled = true;
        try {
          const data = await enviar('toggle_relacion', { id_usuario_destino: seguir.dataset.seguir, tipo: 'seguir' });
          seguir.classList.toggle('siguiendo', data.activo);
          seguir.textContent = data.activo ? 'Siguiendo' : 'Seguir';
          const contador = contenedor.querySelector(`[data-seguidores="${seguir.dataset.seguir}"]`);
          if (contador) contador.textContent = data.seguidores;
        } catch (err) {
          alert(err.message);
        } finally {
          seguir.disabled = false;
        }
      }
    });
  }

  window.EcosTarjetas = { publicacion, usuario, activar, url, escapar };
})();
