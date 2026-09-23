/* Service worker. CONFIG is prepended by Pwa::worker(). */

const isApi = url => decodeURIComponent(url.pathname + url.search).includes(CONFIG.api);
const isApp = url => url.href.split('#')[0].split('?')[0] === CONFIG.app.split('?')[0];
const isAsset = url => url.href.startsWith(CONFIG.assets) || CONFIG.shell.includes(url.href);

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CONFIG.cache)
      .then(cache => Promise.all(CONFIG.shell.map(u => cache.add(u).catch(() => {}))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys
        .filter(k => k.startsWith('travel-risk-') && k !== CONFIG.cache)
        .map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// The app asks for this on sign-out so no personal data stays in the cache.
self.addEventListener('message', event => {
  if (event.data === 'clear-api-cache') {
    event.waitUntil(caches.open(CONFIG.cache).then(async cache => {
      for (const req of await cache.keys()) {
        if (isApi(new URL(req.url))) await cache.delete(req);
      }
    }));
  }
});

async function networkFirst(request) {
  const cache = await caches.open(CONFIG.cache);
  try {
    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
  } catch (e) {
    const hit = await cache.match(request, { ignoreSearch: false });
    if (hit) return hit;
    throw e;
  }
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(CONFIG.cache);
  const hit = await cache.match(request);
  const update = fetch(request).then(r => { if (r.ok) cache.put(request, r.clone()); return r; });
  return hit || update;
}

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (isApi(url)) {
    event.respondWith(networkFirst(request));
  } else if (request.mode === 'navigate' && isApp(url)) {
    event.respondWith(networkFirst(request).catch(() => caches.match(CONFIG.shell[0])));
  } else if (isAsset(url)) {
    event.respondWith(staleWhileRevalidate(request));
  }
  // Anything else on the site: not ours, let the browser handle it.
});
