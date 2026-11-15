/**
 * Sistema integral de Publicaciones, Comentarios, Reacciones y Emojis para ECOS
 */

// Funciones auxiliares de resolución de rutas relativas
function getPublicacionesApiEndpoint(params = '') {
  let base = 'api_publicaciones.php';
  if (typeof window.projectUrl === 'function') {
    base = window.projectUrl('api_publicaciones.php');
  } else if (window.location.pathname.toLowerCase().includes('/php/')) {
    base = '../api_publicaciones.php';
  }
  return params ? `${base}?${params}` : base;
}

function resolveAssetPath(path) {
  if (!path) return '';
  if (/^(?:[a-z][a-z\d+.-]*:|\/\/|\/)/i.test(path)) return path;
  if (path.startsWith('../')) return path;
  if (typeof window.projectUrl === 'function') {
    return window.projectUrl(path);
  }
  if (window.location.pathname.toLowerCase().includes('/php/')) {
    return '../' + path.replace(/^\/+/, '');
  }
  return path;
}

async function parseJsonResponse(resp) {
  const text = await resp.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    const cleanMsg = text.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
    throw new Error(cleanMsg.substring(0, 200) || 'Error inesperado del servidor');
  }
}

const EMOJI_LIST = [
  '😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰',
  '😘','😗','😙','😚','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨',
  '😐','😑','😶','😏','😒','🙄','😬','🤥','😔','😪','🤤','😴','😷','🤒','🤕','🤢',
  '🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓','🧐','😕','😟','🙁','😮',
  '😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓',
  '😩','😫','🥱','😤','😡','😠','🤬','😈','👿','💀','☠️','💩','🤡','👻','👽','👾',
  // Música
  '🎵','🎶','🎼','🎹','🥁','🎷','🎺','🎸','🪕','🎻','🎤','🎧','📻','🎙️','🎚️','🎛️',
  // Manos y gestos
  '👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤏','👈','👉','👆','👇',
  '✋','🤚','🖐️','🖖','👋','🤙','💪','👏','🙌','👐','🤝','🙏','✍️',
  // Símbolos y corazones
  '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖',
  '💘','💝','💟','🔥','✨','⭐','🌟','⚡','💥','💯','💢','💬','👁️‍🗨️','🎉','🍻','🥂'
];

let activeEmojiTargetInput = null;

// Mostrar notificación flotante
function mostrarToast(mensaje) {
  let toast = document.getElementById('ecos-toast-msg');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'ecos-toast-msg';
    toast.className = 'ecos-toast';
    document.body.appendChild(toast);
  }
  toast.textContent = mensaje;
  toast.classList.add('show');
  setTimeout(() => {
    toast.classList.remove('show');
  }, 3200);
}

// Inicializar Selector de Emojis
function initEmojiPicker() {
  let picker = document.getElementById('ecos-emoji-picker');
  if (!picker) {
    picker = document.createElement('div');
    picker.id = 'ecos-emoji-picker';
    picker.className = 'ecos-emoji-picker';

    const header = document.createElement('div');
    header.className = 'emoji-picker-header';
    header.innerHTML = `
      <span class="emoji-picker-title">Seleccionar emoji</span>
      <button type="button" class="emoji-picker-close" onclick="closeEmojiPicker()">&times;</button>
    `;
    picker.appendChild(header);

    const grid = document.createElement('div');
    grid.className = 'emoji-grid-container';

    EMOJI_LIST.forEach(emoji => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'emoji-cell-btn';
      btn.textContent = emoji;
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        insertEmoji(emoji);
      });
      grid.appendChild(btn);
    });

    picker.appendChild(grid);
    document.body.appendChild(picker);

    // Cerrar al hacer clic fuera
    document.addEventListener('click', (e) => {
      if (picker.classList.contains('show') && !picker.contains(e.target)) {
        closeEmojiPicker();
      }
    });
  }
}

function openEmojiPicker(e, targetInputId) {
  e.stopPropagation();
  initEmojiPicker();
  const picker = document.getElementById('ecos-emoji-picker');
  activeEmojiTargetInput = document.getElementById(targetInputId);

  const rect = e.currentTarget.getBoundingClientRect();
  const pickerWidth = 320;
  const pickerHeight = 260;

  let left = rect.left + window.scrollX;
  let top = rect.bottom + window.scrollY + 8;

  // Ajustar si sale de pantalla
  if (left + pickerWidth > window.innerWidth) {
    left = window.innerWidth - pickerWidth - 15;
  }
  if (top + pickerHeight > window.innerHeight + window.scrollY) {
    top = rect.top + window.scrollY - pickerHeight - 8;
  }

  picker.style.left = `${Math.max(10, left)}px`;
  picker.style.top = `${Math.max(10, top)}px`;
  picker.classList.add('show');
}

function closeEmojiPicker() {
  const picker = document.getElementById('ecos-emoji-picker');
  if (picker) {
    picker.classList.remove('show');
  }
}

function insertEmoji(emoji) {
  if (!activeEmojiTargetInput) return;
  const el = activeEmojiTargetInput;
  const start = el.selectionStart || el.value.length;
  const end = el.selectionEnd || el.value.length;
  const text = el.value;
  el.value = text.substring(0, start) + emoji + text.substring(end);
  el.focus();
  const newPos = start + emoji.length;
  el.setSelectionRange(newPos, newPos);
}

/* ==========================================================
   MODAL DE CREAR / EDITAR PUBLICACIÓN
   ========================================================== */

function abrirModalCrearPublicacion() {
  const modal = document.getElementById('modal-publicacion');
  const modalTitle = document.getElementById('modal-post-header-title');
  const inputId = document.getElementById('modal-post-id');
  const inputTitle = document.getElementById('modal-post-title');
  const inputDesc = document.getElementById('modal-post-desc');
  const previewWrap = document.getElementById('modal-post-preview-wrap');
  const previewImg = document.getElementById('modal-post-preview-img');
  const fileInput = document.getElementById('modal-post-file');
  const submitBtn = document.getElementById('modal-post-submit-btn');

  // Limpiar formulario para creación
  inputId.value = '';
  inputTitle.value = '';
  inputDesc.value = '';
  fileInput.value = '';
  previewImg.src = '';
  previewWrap.classList.remove('has-image');
  document.getElementById('modal-post-delete-img').value = '0';

  modalTitle.textContent = 'Crear Publicación';
  submitBtn.textContent = 'Publicar';

  modal.classList.add('active');
  inputTitle.focus();
}

function abrirModalEditarPublicacion(id, titulo, texto, imagen) {
  const modal = document.getElementById('modal-publicacion');
  const modalTitle = document.getElementById('modal-post-header-title');
  const inputId = document.getElementById('modal-post-id');
  const inputTitle = document.getElementById('modal-post-title');
  const inputDesc = document.getElementById('modal-post-desc');
  const previewWrap = document.getElementById('modal-post-preview-wrap');
  const previewImg = document.getElementById('modal-post-preview-img');
  const fileInput = document.getElementById('modal-post-file');
  const deleteImgInput = document.getElementById('modal-post-delete-img');
  const submitBtn = document.getElementById('modal-post-submit-btn');

  inputId.value = id;
  inputTitle.value = titulo || '';
  inputDesc.value = texto || '';
  fileInput.value = '';
  deleteImgInput.value = '0';

  if (imagen && imagen.trim() !== '') {
    previewImg.src = resolveAssetPath(imagen);
    previewWrap.classList.add('has-image');
  } else {
    previewImg.src = '';
    previewWrap.classList.remove('has-image');
  }

  modalTitle.textContent = 'Editar Publicación';
  submitBtn.textContent = 'Guardar cambios';

  modal.classList.add('active');
  inputTitle.focus();
}

function cerrarModalPublicacion() {
  const modal = document.getElementById('modal-publicacion');
  if (modal) {
    modal.classList.remove('active');
  }
}

// Previsualizar imagen seleccionada de la computadora en el modal
function handleModalFileSelect(input) {
  const previewWrap = document.getElementById('modal-post-preview-wrap');
  const previewImg = document.getElementById('modal-post-preview-img');
  const deleteImgInput = document.getElementById('modal-post-delete-img');

  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 10 * 1024 * 1024) {
      alert('La imagen excede el límite de 10 MB.');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      previewImg.src = e.target.result;
      previewWrap.classList.add('has-image');
      deleteImgInput.value = '0';
    };
    reader.readAsDataURL(file);
  }
}

function removerImagenModal() {
  const previewWrap = document.getElementById('modal-post-preview-wrap');
  const previewImg = document.getElementById('modal-post-preview-img');
  const fileInput = document.getElementById('modal-post-file');
  const deleteImgInput = document.getElementById('modal-post-delete-img');

  fileInput.value = '';
  previewImg.src = '';
  previewWrap.classList.remove('has-image');
  deleteImgInput.value = '1';
}

// Guardar publicación (Crear o Editar)
async function guardarPublicacion(e) {
  if (e) e.preventDefault();

  const id = document.getElementById('modal-post-id').value;
  const titulo = document.getElementById('modal-post-title').value.trim();
  const desc = document.getElementById('modal-post-desc').value.trim();
  const fileInput = document.getElementById('modal-post-file');
  const deleteImg = document.getElementById('modal-post-delete-img').value;
  const previewWrap = document.getElementById('modal-post-preview-wrap');
  const submitBtn = document.getElementById('modal-post-submit-btn');

  const tieneImagen = previewWrap.classList.contains('has-image');

  if (!titulo && !desc && !tieneImagen) {
    alert('Por favor escribe un título, descripción o sube una imagen para tu publicación.');
    return;
  }

  const formData = new FormData();
  formData.append('titulo', titulo);
  formData.append('texto', desc);

  if (fileInput.files && fileInput.files[0]) {
    formData.append('imagen', fileInput.files[0]);
  }

  const esEdicion = !!id;
  const accion = esEdicion ? 'editar_publicacion' : 'crear_publicacion';
  if (esEdicion) {
    formData.append('id_publicacion', id);
    if (deleteImg === '1') {
      formData.append('eliminar_imagen', '1');
    }
  }

  try {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Guardando...';

    const resp = await fetch(getPublicacionesApiEndpoint(`accion=${accion}`), {
      method: 'POST',
      body: formData
    });

    const data = await parseJsonResponse(resp);

    if (!data.success) {
      throw new Error(data.error || 'Error al guardar la publicación.');
    }

    cerrarModalPublicacion();
    // El servidor dice si quedó publicada o pendiente de aprobación por un administrador
    mostrarToast(data.mensaje || (esEdicion ? 'Publicación actualizada con éxito' : '¡Publicación creada con éxito!'));

    // Recargar la página (con tiempo para leer el aviso si quedó pendiente)
    setTimeout(() => {
      window.location.reload();
    }, data.estado === 'pendiente' ? 2600 : 700);

  } catch (err) {
    alert(err.message);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = esEdicion ? 'Guardar cambios' : 'Publicar';
  }
}

// Eliminar Publicación
async function eliminarPublicacion(id) {
  if (!confirm('¿Estás seguro de que deseas eliminar esta publicación? No podrás deshacer esta acción.')) {
    return;
  }

  try {
    const formData = new FormData();
    formData.append('id_publicacion', id);

    const resp = await fetch(getPublicacionesApiEndpoint('accion=eliminar_publicacion'), {
      method: 'POST',
      body: formData
    });

    const data = await parseJsonResponse(resp);
    if (!data.success) {
      throw new Error(data.error || 'No se pudo eliminar la publicación.');
    }

    const postCard = document.getElementById(`post-card-${id}`);
    if (postCard) {
      postCard.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      postCard.style.opacity = '0';
      postCard.style.transform = 'scale(0.95)';
      setTimeout(() => {
        postCard.remove();
        // Si no quedan publicaciones, mostrar mensaje vacío
        const feed = document.querySelector('.feed');
        if (feed && !feed.querySelector('.user-post-card')) {
          feed.innerHTML += `
            <div class="empty-feed-notice">
              <svg viewBox="0 0 24 24"><path d="M19 5v14H5V5h14m0-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
              <h3>No tienes publicaciones aún</h3>
              <p>Haz clic en el botón '+' para compartir algo con la comunidad.</p>
            </div>
          `;
        }
      }, 400);
    }

    mostrarToast('Publicación eliminada correctamente');
  } catch (err) {
    alert(err.message);
  }
}

// Reacción Like / Unlike
async function toggleLikePost(btn, idPublicacion) {
  const heartPath = btn.querySelector('svg path');
  const countSpan = btn.querySelector('.post-stat-number');

  try {
    const formData = new FormData();
    formData.append('id_publicacion', idPublicacion);

    const resp = await fetch(getPublicacionesApiEndpoint('accion=toggle_like'), {
      method: 'POST',
      body: formData
    });

    const data = await parseJsonResponse(resp);
    if (!data.success) {
      throw new Error(data.error || 'Error al procesar el like.');
    }

    if (data.liked) {
      btn.classList.add('liked');
      if (heartPath) heartPath.setAttribute('fill', '#ff3040');
    } else {
      btn.classList.remove('liked');
      if (heartPath) heartPath.setAttribute('fill', '#777777');
    }

    countSpan.textContent = data.total_likes;

  } catch (err) {
    console.error('Error like:', err);
  }
}

/* ==========================================================
   COMENTARIOS
   ========================================================== */

function toggleSeccionComentarios(idPublicacion) {
  const section = document.getElementById(`comments-section-${idPublicacion}`);
  if (!section) return;

  const isVisible = section.classList.contains('show');
  if (isVisible) {
    section.classList.remove('show');
  } else {
    section.classList.add('show');
    cargarComentarios(idPublicacion);
  }
}

async function cargarComentarios(idPublicacion) {
  const listContainer = document.getElementById(`comments-list-${idPublicacion}`);
  if (!listContainer) return;

  try {
    const resp = await fetch(getPublicacionesApiEndpoint(`accion=obtener_comentarios&id_publicacion=${idPublicacion}`));
    const data = await parseJsonResponse(resp);

    if (!data.success) {
      throw new Error(data.error || 'Error al cargar comentarios.');
    }

    renderizarComentarios(idPublicacion, data.comentarios);
  } catch (err) {
    console.error(err);
  }
}

function renderizarComentarios(idPublicacion, comentarios) {
  const listContainer = document.getElementById(`comments-list-${idPublicacion}`);
  if (!listContainer) return;

  if (comentarios.length === 0) {
    listContainer.innerHTML = '<p style="color:var(--post-sub); font-size:0.9rem; margin:10px 0;">Sé el primero en comentar esta publicación.</p>';
    return;
  }

  listContainer.innerHTML = comentarios.map(c => {
    const urlPerfil = resolveAssetPath('PHP/6perfilPropio.php?id=' + encodeURIComponent(c.id_usuario));
    return `
    <div class="comment-item" id="comment-item-${c.id_comentario}">
      <a href="${urlPerfil}"><img src="${resolveAssetPath(c.avatar || 'Image/cuenta.png')}" class="comment-author-avatar" alt="Avatar"></a>
      <div class="comment-content-wrap">
        <div class="comment-author-header">
          <a href="${urlPerfil}" style="text-decoration:none;color:inherit;"><span class="comment-author-title">${escapeHTML(c.nombre)} ${c.alias ? `<small style="font-weight:normal;opacity:0.75;">${escapeHTML(c.alias)}</small>` : ''}</span></a>
          <span class="comment-date-text">${c.fecha_formateada}</span>
        </div>
        <div class="comment-body-text" id="comment-text-display-${c.id_comentario}">${escapeHTML(c.contenido || '')}</div>
        ${c.imagen ? `
          <img src="${resolveAssetPath(c.imagen)}" class="comment-attached-img" alt="Imagen adjunta" onclick="window.open(this.src, '_blank')">
        ` : ''}
        ${c.es_propio ? `
          <div class="comment-action-links">
            <button type="button" class="comment-link-btn" onclick="iniciarEdicionComentario(${c.id_comentario})">Editar</button>
            <button type="button" class="comment-link-btn delete" onclick="eliminarComentario(${c.id_comentario}, ${idPublicacion})">Borrar</button>
          </div>
        ` : ''}
      </div>
    </div>
  `;
  }).join('');
}

// Previsualizar imagen del comentario
function previewCommentImage(input, idPublicacion) {
  const box = document.getElementById(`comment-preview-box-${idPublicacion}`);
  if (!box) return;

  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 10 * 1024 * 1024) {
      alert('La imagen excede el límite de 10 MB.');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      box.innerHTML = `
        <img src="${e.target.result}" alt="Preview">
        <button type="button" class="comment-img-remove-btn" onclick="cancelarImagenComentario(${idPublicacion})">&times;</button>
      `;
      box.style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
  }
}

function cancelarImagenComentario(idPublicacion) {
  const box = document.getElementById(`comment-preview-box-${idPublicacion}`);
  const fileInput = document.getElementById(`comment-file-${idPublicacion}`);
  if (fileInput) fileInput.value = '';
  if (box) {
    box.innerHTML = '';
    box.style.display = 'none';
  }
}

// Enviar nuevo comentario
async function enviarComentario(idPublicacion) {
  const textarea = document.getElementById(`comment-text-${idPublicacion}`);
  const fileInput = document.getElementById(`comment-file-${idPublicacion}`);
  const countSpan = document.getElementById(`comment-count-${idPublicacion}`);

  const contenido = textarea.value.trim();
  const tieneArchivo = fileInput.files && fileInput.files.length > 0;

  if (!contenido && !tieneArchivo) {
    alert('Por favor escribe un comentario o adjunta una imagen.');
    return;
  }

  const formData = new FormData();
  formData.append('id_publicacion', idPublicacion);
  formData.append('contenido', contenido);
  if (tieneArchivo) {
    formData.append('imagen', fileInput.files[0]);
  }

  try {
    const resp = await fetch(getPublicacionesApiEndpoint('accion=crear_comentario'), {
      method: 'POST',
      body: formData
    });

    const data = await parseJsonResponse(resp);
    if (!data.success) {
      throw new Error(data.error || 'No se pudo publicar el comentario.');
    }

    textarea.value = '';
    cancelarImagenComentario(idPublicacion);
    if (countSpan) countSpan.textContent = data.total_comentarios;

    cargarComentarios(idPublicacion);
    mostrarToast('Comentario publicado');
  } catch (err) {
    alert(err.message);
  }
}

// Edición de comentario en línea
function iniciarEdicionComentario(idComentario) {
  const display = document.getElementById(`comment-text-display-${idComentario}`);
  if (!display) return;

  const textoActual = display.textContent.trim();
  display.innerHTML = `
    <div style="margin-top:6px;">
      <textarea id="edit-com-input-${idComentario}" style="width:100%; border:1px solid var(--post-border); border-radius:12px; padding:8px; font-family:inherit; font-size:0.92rem; background:var(--post-bg); color:var(--post-text); min-height:55px;">${escapeHTML(textoActual)}</textarea>
      <div style="display:flex; gap:8px; margin-top:8px; align-items:center;">
        <button type="button" class="comment-tool-btn" onclick="openEmojiPicker(event, 'edit-com-input-${idComentario}')" style="padding:4px 8px;">😀 Emojis</button>
        <button type="button" class="btn-send-comment" style="padding:4px 12px; font-size:0.8rem;" onclick="guardarEdicionComentario(${idComentario})">Guardar</button>
        <button type="button" class="comment-link-btn" onclick="cancelarEdicionComentario(${idComentario}, '${escapeQuotes(textoActual)}')">Cancelar</button>
      </div>
    </div>
  `;
}

function cancelarEdicionComentario(idComentario, textoOriginal) {
  const display = document.getElementById(`comment-text-display-${idComentario}`);
  if (display) {
    display.textContent = textoOriginal;
  }
}

async function guardarEdicionComentario(idComentario) {
  const input = document.getElementById(`edit-com-input-${idComentario}`);
  if (!input) return;

  const contenido = input.value.trim();
  if (!contenido) {
    alert('El comentario no puede quedar vacío.');
    return;
  }

  const formData = new FormData();
  formData.append('id_comentario', idComentario);
  formData.append('contenido', contenido);

  try {
    const resp = await fetch(getPublicacionesApiEndpoint('accion=editar_comentario'), {
      method: 'POST',
      body: formData
    });

    const data = await parseJsonResponse(resp);
    if (!data.success) {
      throw new Error(data.error || 'Error al editar comentario.');
    }

    const display = document.getElementById(`comment-text-display-${idComentario}`);
    if (display) {
      display.textContent = contenido;
    }
    mostrarToast('Comentario editado');
  } catch (err) {
    alert(err.message);
  }
}

// Eliminar Comentario
async function eliminarComentario(idComentario, idPublicacion) {
  if (!confirm('¿Estás seguro de eliminar este comentario?')) {
    return;
  }

  const formData = new FormData();
  formData.append('id_comentario', idComentario);

  try {
    const resp = await fetch(getPublicacionesApiEndpoint('accion=eliminar_comentario'), {
      method: 'POST',
      body: formData
    });

    const data = await parseJsonResponse(resp);
    if (!data.success) {
      throw new Error(data.error || 'Error al eliminar comentario.');
    }

    const comItem = document.getElementById(`comment-item-${idComentario}`);
    if (comItem) {
      comItem.remove();
    }

    const countSpan = document.getElementById(`comment-count-${idPublicacion}`);
    if (countSpan) {
      countSpan.textContent = data.total_comentarios;
    }

    mostrarToast('Comentario eliminado');
  } catch (err) {
    alert(err.message);
  }
}

// Utilidades
function escapeHTML(str) {
  if (!str) return '';
  return str.replace(/[&<>'"]/g, 
    tag => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[tag] || tag)
  );
}

function escapeQuotes(str) {
  if (!str) return '';
  return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Inicialización general
document.addEventListener('DOMContentLoaded', () => {
  initEmojiPicker();

  const fabCrear = document.getElementById('abrir-popup');
  if (fabCrear) {
    fabCrear.addEventListener('click', (e) => {
      e.preventDefault();
      abrirModalCrearPublicacion();
    });
  }

  const modalOverlay = document.getElementById('modal-publicacion');
  if (modalOverlay) {
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) {
        cerrarModalPublicacion();
      }
    });
  }
});
