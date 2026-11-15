/**
 * Feed principal (3main.html): carga publicaciones reales desde la base de datos.
 * Las tarjetas, likes y enlaces vienen de JS/tarjetas.js (cargarlo antes que este archivo).
 *
 * Necesita en la página: <div id="feed-publicaciones"></div> y <button id="feed-ver-mas">.
 */
(function () {
  const POR_PAGINA = 10;
  const { publicacion, activar, url } = window.EcosTarjetas;

  let siguiente = 0;
  let cargando = false;

  async function cargarPagina() {
    const lista = document.getElementById('feed-publicaciones');
    const botonMas = document.getElementById('feed-ver-mas');
    if (!lista || cargando) return;
    cargando = true;
    if (botonMas) { botonMas.disabled = true; botonMas.textContent = 'Cargando...'; }

    try {
      const resp = await fetch(url(`api_publicaciones.php?accion=obtener_feed&limite=${POR_PAGINA}&offset=${siguiente}`));
      const data = await resp.json();
      if (!data.success) throw new Error(data.error || 'Error al cargar el feed');

      const aviso = lista.querySelector('.feed-aviso');
      if (aviso) aviso.remove();

      if (siguiente === 0 && data.publicaciones.length === 0) {
        lista.insertAdjacentHTML('afterbegin', `
          <div class="empty-feed-notice feed-aviso">
            <h3>Aún no hay publicaciones</h3>
            <p>Sé el primero en compartir algo con el botón '+'.</p>
          </div>`);
      }

      lista.insertAdjacentHTML('beforeend', data.publicaciones.map(publicacion).join(''));
      siguiente = data.siguiente;

      if (botonMas) botonMas.style.display = data.hay_mas ? '' : 'none';
    } catch (err) {
      console.error('Feed:', err);
      if (siguiente === 0) {
        lista.innerHTML = `
          <div class="empty-feed-notice feed-aviso">
            <h3>No se pudieron cargar las publicaciones</h3>
            <p>Revisa que hayas iniciado sesión e intenta de nuevo.</p>
          </div>`;
      }
    } finally {
      cargando = false;
      if (botonMas) { botonMas.disabled = false; botonMas.textContent = 'Ver más'; }
    }
  }

  function iniciar() {
    const lista = document.getElementById('feed-publicaciones');
    if (!lista) return;
    activar(lista);

    const botonMas = document.getElementById('feed-ver-mas');
    if (botonMas) botonMas.addEventListener('click', cargarPagina);

    cargarPagina();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
