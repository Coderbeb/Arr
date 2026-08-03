// Minimal service worker for PWA installability
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', () => self.clients.claim());
self.addEventListener('fetch', (event) => {
  // Pass through all requests (no caching for now)
  event.respondWith(fetch(event.request));
});
