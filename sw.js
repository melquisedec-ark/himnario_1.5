const CACHE_NAME = 'himnario-v1';
const ASSETS_TO_CACHE = [
  '/', 
  'index.php',
  'assets/fondos/pergamino.jpg', // Tus fondos clave
  'assets/icon.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' // Librerías externas
];

// 1. INSTALACIÓN: Guardamos lo básico (App Shell)
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(ASSETS_TO_CACHE))
  );
});

// 2. ACTIVACIÓN: Limpiar cachés viejas si actualizas la versión
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keyList => {
      return Promise.all(keyList.map(key => {
        if (key !== CACHE_NAME) return caches.delete(key);
      }));
    })
  );
});

// 3. INTERCEPTOR (FETCH): La magia offline
self.addEventListener('fetch', event => {
  // Estrategia: "Stale-While-Revalidate" para PHP (Ver rápido, actualizar fondo)
  // Estrategia: "Cache First" para imágenes/estáticos
  
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      // Si existe en caché, lo devolvemos
      const fetchPromise = fetch(event.request).then(networkResponse => {
        // Y actualizamos la caché en segundo plano para la próxima vez
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, networkResponse.clone());
        });
        return networkResponse;
      });
      return cachedResponse || fetchPromise;
    })
  );
});