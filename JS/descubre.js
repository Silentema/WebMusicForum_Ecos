/**
 * Sección "Descubre": carga usuarios reales desde la base de datos
 * y enlaza cada uno a su propio perfil (PHP/6perfilPropio.php?id=N).
 *
 * Uso en cualquier página: un contenedor con id="descubre-lista"
 * y <script src="JS/descubre.js"></script> (ruta relativa a la página).
 */
(function () {
  // Raíz del proyecto calculada desde la ubicación de este script (JS/descubre.js)
  const raizProyecto = new URL('../', document.currentScript.src);
  const url = (ruta) => new URL(ruta, raizProyecto).href;

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto == null ? '' : String(texto);
    return div.innerHTML;
  }

  async function cargarDescubre() {
    const lista = document.getElementById('descubre-lista');
    if (!lista) return;

    try {
      const resp = await fetch(url('api_publicaciones.php?accion=descubrir_usuarios&limite=5'));
      const data = await resp.json();
      if (!data.success) throw new Error(data.error || 'Error al cargar usuarios');

      if (data.usuarios.length === 0) {
        lista.innerHTML = '<p class="discover-name" style="margin:0;opacity:.7;">No hay usuarios para mostrar.</p>';
        return;
      }

      lista.innerHTML = data.usuarios.map((u) => `
        <a href="${url('PHP/6perfilPropio.php?id=' + encodeURIComponent(u.id_usuario))}" style="text-decoration: none; color: inherit">
          <div class="discover-item">
            <img src="${escapar(url(u.avatar))}" alt="${escapar(u.nombre)}" class="discover-avatar" onerror="this.src='${url('Image/cuenta.png')}'">
            <span class="discover-name">${escapar(u.nombre)}</span>
          </div>
        </a>
      `).join('');
    } catch (err) {
      console.error('Descubre:', err);
      lista.innerHTML = '<p class="discover-name" style="margin:0;opacity:.7;">Inicia sesión para descubrir usuarios.</p>';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cargarDescubre);
  } else {
    cargarDescubre();
  }
})();
