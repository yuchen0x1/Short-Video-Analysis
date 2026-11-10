// 缓存名带版本：发布新版本时改这里即可让旧缓存整体失效，避免新旧外壳混用。
const CACHE_NAME = 'qushuiyin-shell-v2';
const SHELL_ASSETS = ['./manifest.webmanifest', './icon.svg', './icon-192.png', './icon-512.png'];
// 离线兜底入口：/ 与 /index.php 都映射到同一份导航缓存
const NAV_FALLBACK_KEY = './';

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(SHELL_ASSETS).catch(() => null))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
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
            caches.open(CACHE_NAME).then(cache => cache.put(NAV_FALLBACK_KEY, copy)).catch(() => null);
          }
          return response;
        })
        .catch(() => caches.match(NAV_FALLBACK_KEY).then(cached => cached || caches.match(request)))
    );
    return;
  }

  // 静态资源使用 stale-while-revalidate：先给缓存保证秒开，后台回源更新。
  // 网页版资源未做内容哈希，纯 cache-first 会让旧样式/脚本长期驻留。
  if (['style', 'script', 'manifest', 'image', 'font'].includes(request.destination)) {
    event.respondWith(
      caches.match(request).then(cached => {
        const network = fetch(request)
          .then(response => {
            if (response.ok) {
              const copy = response.clone();
              caches.open(CACHE_NAME).then(cache => cache.put(request, copy)).catch(() => null);
            }
            return response;
          })
          .catch(() => cached);
        return cached || network;
      })
    );
  }
});
