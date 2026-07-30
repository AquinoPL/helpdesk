let deferredPrompt;

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        // Usar variable global PWA_SW_URL definida en footer.php
        const swUrl = typeof PWA_SW_URL !== 'undefined' ? PWA_SW_URL : '/service-worker.js';
        
        navigator.serviceWorker.register(swUrl)
            .then(registration => {
                console.log('[PWA] Service Worker registrado exitosamente con scope:', registration.scope);
            })
            .catch(error => {
                console.error('[PWA] Error al registrar el Service Worker:', error);
            });
    });
}

// Escuchar el evento antes de instalar
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevenir la notificación automática por defecto en algunos navegadores
    e.preventDefault();
    // Guardar el evento para dispararlo luego
    deferredPrompt = e;
    
    // Mostrar el botón de instalación si existe en el DOM
    const installBtn = document.getElementById('btnInstallPwa');
    if (installBtn) {
        installBtn.style.display = 'block';
        
        installBtn.addEventListener('click', async () => {
            // Ocultar el botón
            installBtn.style.display = 'none';
            // Mostrar el prompt nativo
            deferredPrompt.prompt();
            // Esperar a la respuesta del usuario
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`[PWA] Decisión de instalación: ${outcome}`);
            // Limpiar la variable
            deferredPrompt = null;
        });
    }
});

// Escuchar cuando la app ya ha sido instalada
window.addEventListener('appinstalled', () => {
    // Ocultar el botón si sigue visible
    const installBtn = document.getElementById('btnInstallPwa');
    if (installBtn) installBtn.style.display = 'none';
    
    // Limpiar el prompt
    deferredPrompt = null;
    console.log('[PWA] La aplicación fue instalada exitosamente');
});
