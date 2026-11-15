/**
 * Página de búsqueda (4busqueda.html)
 *
 * Lee de la URL:
 *   ?q=texto           lo que se busca (viene del buscador del header)
 *   ?tag=Rock          solo publicaciones con ese hashtag
 *   ?tipo=usuarios     pestaña Usuarios (por defecto: publicaciones)
 *   ?orden=reciente|antiguo|popular
 *   ?rango=todo|semana|mes
 *
 * Necesita JS/tarjetas.js cargado antes.
 */
(function () {
  const POR_PAGINA = 10;
  const { publicacion, usuario, activar, url, escapar } = window.EcosTarjetas;

  const params = new URLSearchParams(window.location.search);
  const estado = {
    q: (params.get('q') || '').trim(),
    tag: (params.get('tag') || '').trim().replace(/^#/, ''),
    tipo: params.get('tipo') === 'usuarios' ? 'usuarios' : 'publicaciones',
    orden: ['reciente', 'antiguo', 'popular'].includes(params.get('orden')) ? params.get('orden') : 'reciente',
    rango: ['todo', 'semana', 'mes'].includes(params.get('rango')) ? params.get('rango') : 'todo',
  };

  let siguiente = 0;
  let cargando = false;
  let peticion = 0; // para ignorar respuestas viejas si se cambia la búsqueda rápido

  const $ = (id) => document.getElementById(id);

  // Mantiene la URL al día (sin recargar) para poder compartirla o volver atrás
  function actualizarUrl() {
    const p = new URLSearchParams();
    if (estado.q) p.set('q', estado.q);
    if (estado.tag && estado.tipo === 'publicaciones') p.set('tag', estado.tag);
    if (estado.tipo === 'usuarios') p.set('tipo', 'usuarios');
    if (estado.tipo === 'publicaciones' && estado.orden !== 'reciente') p.set('orden', estado.orden);
    if (estado.tipo === 'publicaciones' && estado.rango !== 'todo') p.set('rango', estado.rango);
    const qs = p.toString();
    history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : ''));
  }

  function pintarCabecera() {
    $('busqueda-input').value = estado.q;

    document.querySelectorAll('[data-tipo]').forEach((tab) => {
      const activa = tab.dataset.tipo === estado.tipo;
      tab.classList.toggle('active', activa);
      tab.classList.toggle('inactive', !activa);
    });

    // Los filtros de orden y fecha solo aplican a publicaciones
    $('filtros-seccion').style.display = estado.tipo === 'publicaciones' ? '' : 'none';
    $('filtro-orden').value = estado.orden;
    $('filtro-rango').value = estado.rango;

    const partes = [];
    if (estado.tipo === 'publicaciones' && estado.tag) {
      partes.push(`con <span class="busqueda-chip">#${escapar(estado.tag)}
        <button type="button" id="quitar-tag" aria-label="Quitar hashtag" title="Quitar hashtag">&times;</button></span>`);
    }
    if (estado.q) {
      partes.push(`para <strong>«${escapar(estado.q)}»</strong>`);
    }
    const que = estado.tipo === 'usuarios' ? 'Usuarios' : 'Publicaciones';
    $('busqueda-resumen').innerHTML = partes.length
      ? `${que} ${partes.join(' ')}`
      : (estado.tipo === 'usuarios' ? 'Todos los usuarios' : 'Explora todas las publicaciones');

    const quitar = $('quitar-tag');
    if (quitar) quitar.addEventListener('click', () => { estado.tag = ''; buscar(); });

    document.title = estado.q ? `ECOS • Buscar "${estado.q}"` : (estado.tag ? `ECOS • #${estado.tag}` : 'ECOS • Búsqueda');
  }

  async function cargarPagina() {
    const lista = $('busqueda-resultados');
    const botonMas = $('busqueda-ver-mas');
    if (cargando) return;
    cargando = true;
    const miPeticion = ++peticion;
    botonMas.disabled = true;
    botonMas.textContent = 'Cargando...';

    const p = new URLSearchParams({
      accion: 'buscar', tipo: estado.tipo, q: estado.q, limite: POR_PAGINA, offset: siguiente,
    });
    if (estado.tipo === 'publicaciones') {
      p.set('tag', estado.tag);
      p.set('orden', estado.orden);
      p.set('rango', estado.rango);
    }

    try {
      const resp = await fetch(url('api_publicaciones.php?' + p.toString()));
      const data = await resp.json();
      if (miPeticion !== peticion) return; // llegó una búsqueda más nueva
      if (!data.success) throw new Error(data.error || 'Error en la búsqueda');

      const items = estado.tipo === 'usuarios' ? data.usuarios : data.publicaciones;
      const render = estado.tipo === 'usuarios' ? usuario : publicacion;

      if (siguiente === 0) {
        lista.className = estado.tipo === 'usuarios' ? 'busqueda-lista-usuarios' : 'busqueda-lista-posts';
        lista.innerHTML = items.length ? '' : `
          <div class="empty-feed-notice">
            <h3>No encontramos ${estado.tipo === 'usuarios' ? 'usuarios' : 'publicaciones'}${estado.q ? ` para «${escapar(estado.q)}»` : ''}</h3>
            <p>Prueba con otras palabras${estado.tipo === 'publicaciones' ? ', quita filtros o busca en la pestaña Usuarios' : ' o busca en la pestaña Publicaciones'}.</p>
          </div>`;
      }

      lista.insertAdjacentHTML('beforeend', items.map(render).join(''));
      siguiente = data.siguiente;
      botonMas.style.display = data.hay_mas ? '' : 'none';
    } catch (err) {
      if (miPeticion !== peticion) return;
      console.error('Búsqueda:', err);
      if (siguiente === 0) {
        lista.innerHTML = `
          <div class="empty-feed-notice">
            <h3>No se pudo completar la búsqueda</h3>
            <p>Revisa que hayas iniciado sesión e intenta de nuevo.</p>
          </div>`;
      }
      botonMas.style.display = 'none';
    } finally {
      if (miPeticion === peticion) {
        cargando = false;
        botonMas.disabled = false;
        botonMas.textContent = 'Ver más';
      }
    }
  }

  // Empieza una búsqueda nueva con el estado actual
  function buscar() {
    siguiente = 0;
    cargando = false;
    $('busqueda-resultados').innerHTML = '<p style="opacity:.7;">Buscando...</p>';
    $('busqueda-ver-mas').style.display = 'none';
    actualizarUrl();
    pintarCabecera();
    cargarPagina();
  }

  function iniciar() {
    activar($('busqueda-resultados'));

    $('busqueda-form').addEventListener('submit', (e) => {
      e.preventDefault();
      const texto = $('busqueda-input').value.trim();
      // Escribir "#Rock" busca por ese hashtag exacto
      if (/^#[\p{L}\p{N}_]+$/u.test(texto) && estado.tipo === 'publicaciones') {
        estado.tag = texto.slice(1);
        estado.q = '';
      } else {
        estado.q = texto;
      }
      buscar();
    });

    document.querySelectorAll('[data-tipo]').forEach((tab) => {
      tab.addEventListener('click', () => {
        if (estado.tipo === tab.dataset.tipo) return;
        estado.tipo = tab.dataset.tipo;
        buscar();
      });
    });

    $('filtro-orden').addEventListener('change', (e) => { estado.orden = e.target.value; buscar(); });
    $('filtro-rango').addEventListener('change', (e) => { estado.rango = e.target.value; buscar(); });
    $('busqueda-ver-mas').addEventListener('click', cargarPagina);

    buscar();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
