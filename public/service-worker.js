// 缓存名带版本：发布新版本时改这里即可让旧缓存整体失效，避免新旧外壳混用。
const CACHE_NAME = 'qushuiyin-shell-v1.3.2';
const SHELL_ASSETS = ['./workbench.js', './web-update-preference.js', './manifest.webmanifest', './icon.svg', './icon-192.png', './icon-512.png'];
// 离线兜底入口：/ 与 /index.php 都映射到同一份导航缓存
const NAV_FALLBACK_KEY = './';

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL_ASSETS))
  );
});

self.addEventListener('message', event => {
  if (event.data?.type === 'ACTIVATE_UPDATE') event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key.startsWith('qushuiyin-shell-') && key !== CACHE_NAME).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || url.pathname.includes('/api/') || url.searchParams.has('action')) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then(response => {
          // 只缓存成功响应，错误页不应成为离线外壳
          if (response.ok) {
            const copy = response.clone();
            event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.put(NAV_FALLBACK_KEY, copy)).catch(() => null));
          }
          return response;
        })
        .catch(async () => {
          const cache = await caches.open(CACHE_NAME);
          return await cache.match(NAV_FALLBACK_KEY) || Response.error();
        })
    );
    return;
  }

  if (SHELL_ASSETS.some(asset => new URL(asset, self.registration.scope).href === url.href)) {
    event.respondWith(caches.open(CACHE_NAME).then(async cache => await cache.match(request) || fetch(request)));
  }
});
