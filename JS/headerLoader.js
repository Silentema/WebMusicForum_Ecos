
const projectRootUrl = new URL('../', document.currentScript.src);

window.projectUrl = function(path) {
    return new URL(path, projectRootUrl).href;
};

window.resolveProjectPaths = function(container) {
    container.querySelectorAll('[href], [src], [action]').forEach(element => {
        ['href', 'src', 'action'].forEach(attribute => {
            const value = element.getAttribute(attribute);
            if (
                value &&
                !/^(?:[a-z][a-z\d+.-]*:|\/\/|#)/i.test(value)
            ) {
                element.setAttribute(attribute, window.projectUrl(value));
            }
        });
    });
};

function cargarHeader() {
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        headerContainer.style.opacity = '0';
        headerContainer.style.transition = 'opacity 0.2s ease';
    }
    
    fetch(window.projectUrl('header1.html'))
        .then(res => res.text())
        .then(data => {
            if (headerContainer) {
                const template = document.createElement('template');
                template.innerHTML = data;
                window.resolveProjectPaths(template.content);
                headerContainer.replaceChildren(template.content);
                
                const scripts = headerContainer.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.textContent = script.textContent;
                    document.head.appendChild(newScript);
                });
                
                setTimeout(() => {
                    const menuIcon = document.querySelector('.menu-icon');
                    if (menuIcon) {
                        menuIcon.onclick = window.abrirMenu;
                        menuIcon.style.cursor = 'pointer';
                    }
                    
                    headerContainer.style.opacity = '1';
                }, 30);
            }
        })
        .catch(error => {
            console.error('Error cargando el header:', error);
            if (headerContainer) {
                headerContainer.style.opacity = '1';
            }
        });
}

function inicializarModoOscuro() {
    const savedTheme = localStorage.getItem('darkMode');
    if (savedTheme === 'true') {
        document.body.classList.add('dark-theme');
    }
    window.addEventListener('storage', function(e) {
        if (e.key === 'darkMode') {
            if (e.newValue === 'true') {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    cargarHeader();
    inicializarModoOscuro();
});