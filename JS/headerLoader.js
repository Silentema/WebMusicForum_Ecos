
function cargarHeader() {
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        headerContainer.style.opacity = '0';
        headerContainer.style.transition = 'opacity 0.2s ease';
    }
    
    fetch('header1.html')
        .then(res => res.text())
        .then(data => {
            if (headerContainer) {
                headerContainer.innerHTML = data;
                
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