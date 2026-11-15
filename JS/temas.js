/**
 * "Temas Actuales": los hashtags más usados, desde la base de datos.
 * Cada tema lleva a 4busqueda.html?tag=Nombre
 *
 * Uso: un contenedor con id="temas-lista" y <script src="JS/temas.js"></script>
 */
(function () {
  const raizProyecto = new URL('../', document.currentScript.src);
  const url = (ruta) => new URL(ruta, raizProyecto).href;

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto == null ? '' : String(texto);
    return div.innerHTML;
  }

  async function cargarTemas() {
    const lista = document.getElementById('temas-lista');
    if (!lista) return;

    try {
      const resp = await fetch(url('api_publicaciones.php?accion=temas_actuales&limite=5'));
      const data = await resp.json();
      if (!data.success) throw new Error(data.error || 'Error al cargar temas');

      if (data.temas.length === 0) {
        lista.innerHTML = '<p class="hashtag-item" style="margin:0;opacity:.7;font-weight:500;">Todavía no hay hashtags. ¡Usa #hashtags en tus publicaciones!</p>';
        return;
      }

      lista.innerHTML = data.temas.map((t) => `
        <a href="${url('4busqueda.html?tag=' + encodeURIComponent(t.nombre))}" style="text-decoration: none; color: inherit">
          <div class="hashtag-item" title="${t.usos} publicación${t.usos === 1 ? '' : 'es'}">#${escapar(t.nombre)}</div>
        </a>
      `).join('');
    } catch (err) {
      console.error('Temas:', err);
      lista.innerHTML = '<p class="hashtag-item" style="margin:0;opacity:.7;font-weight:500;">No disponible.</p>';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cargarTemas);
  } else {
    cargarTemas();
  }
})();
