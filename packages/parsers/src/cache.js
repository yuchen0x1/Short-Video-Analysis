// 内存缓存：解析结果 24h（对齐 PHP ParseCache 默认 TTL），抖音主页会话 30min
const store = new Map();

export function cacheGet(key) {
  const entry = store.get(key);
  if (!entry) return null;
  if (entry.expiresAt <= Date.now()) {
    store.delete(key);
    return null;
  }
  return entry.value;
}

export function cacheSet(key, value, ttlSeconds = 86400) {
  store.set(key, { value, expiresAt: Date.now() + ttlSeconds * 1000 });
}

export function cacheDelete(key) {
  store.delete(key);
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
