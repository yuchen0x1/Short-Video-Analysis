// 内存缓存：解析结果 24h（对齐 PHP ParseCache 默认 TTL），抖音主页会话 30min。
// 限制条目数，避免长时间运行的桌面客户端因不断解析新链接而无界增长。
const store = new Map();
const MAX_ENTRIES = 256;

function prune(now = Date.now()) {
  for (const [key, entry] of store) {
    if (entry.expiresAt <= now) store.delete(key);
  }
  while (store.size > MAX_ENTRIES) {
    const oldest = store.keys().next().value;
    if (oldest === undefined) break;
    store.delete(oldest);
  }
}

export function cacheGet(key) {
  const entry = store.get(key);
  if (!entry) return null;
  if (entry.expiresAt <= Date.now()) {
    store.delete(key);
    return null;
  }
  // Map insertion order doubles as a small LRU list.
  store.delete(key);
  store.set(key, entry);
  return entry.value;
}

export function cacheSet(key, value, ttlSeconds = 86400) {
  prune();
  store.delete(key);
  store.set(key, { value, expiresAt: Date.now() + ttlSeconds * 1000 });
  prune();
}

export function cacheDelete(key) {
  store.delete(key);
}

export function cacheClear() {
  store.clear();
}

// 与 PHP ParseCache::makeKey 的归一化逻辑一致：去掉 utm_* 参数、参数排序、scheme/host 小写
export function makeParseKey(prefix, url) {
  try {
    const parsed = new URL(String(url));
    const params = new URLSearchParams();
    const sorted = [...parsed.searchParams.entries()]
      .filter(([name]) => !name.startsWith('utm_'))
      .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));
    for (const [name, value] of sorted) {
      params.append(name, value);
    }
    const query = params.toString();
    const normalized = `${parsed.protocol.toLowerCase()}//${parsed.host.toLowerCase()}${parsed.pathname}${query ? '?' + query : ''}`;
    return `${prefix}|${normalized}`;
  } catch {
    return `${prefix}|${String(url)}`;
  }
}
