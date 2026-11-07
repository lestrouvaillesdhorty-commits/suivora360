/* Suivora360 — service worker minimal.
   - Ne met JAMAIS en cache les pages de l'application (données privées).
   - Garde en cache les images/styles/scripts pour un affichage plus rapide.
   - Hors connexion : affiche une page claire au lieu de l'erreur du navigateur. */
const VERSION = 'suivora-v1';
const STATIQUES = ['/hors-ligne.html', '/assets/img/icon-192.png', '/assets/img/logo-full.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(STATIQUES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((cles) => Promise.all(cles.filter((c) => c !== VERSION).map((c) => caches.delete(c)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/hors-ligne.html')));
    return;
  }
  if (url.pathname.startsWith('/assets/')) {
    e.respondWith(
      caches.open(VERSION).then((cache) =>
        cache.match(req).then((hit) => {
          const reseau = fetch(req).then((rep) => { if (rep.ok) cache.put(req, rep.clone()); return rep; }).catch(() => hit);
          return hit || reseau;
        })
      )
    );
  }
});
