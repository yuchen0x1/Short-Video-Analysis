// Mobile persistence, pagination and gallery downloads. No native plugin dependency.
export function readStored(storage, key) {
  try { return storage.getItem(key) || ''; } catch { return ''; }
}

export function readHistory(storage, key) {
  try {
    const items = JSON.parse(readStored(storage, key) || '[]');
    if (!Array.isArray(items)) return [];
    const seen = new Set();
    return items.filter(item => {
      if (!item || typeof item !== 'object' || typeof item.url !== 'string') return false;
      try {
        const url = new URL(item.url);
        if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || seen.has(item.url)) return false;
      } catch { return false; }
      seen.add(item.url);
      return true;
    }).slice(0, 20).map(item => ({
      url: item.url, title: String(item.title || '未命名内容').slice(0, 1000),
      author: String(item.author || '未知作者').slice(0, 200),
      time: Number.isFinite(Number(item.time)) ? Number(item.time) : 0,
      type: item.type === 'image' ? 'image' : 'video',
    }));
  } catch { return []; }
}

export async function loadDiscoveryPage(state, request, reset = false) {
  if (state.loading) return false;
  const query = { tab: state.tab, platform: state.platform, keyword: state.keyword, page: reset ? 1 : state.page + 1 };
  const key = JSON.stringify([query.tab, query.platform, query.keyword]);
  if (state.queryKey && state.queryKey !== key) {
    state.items = []; state.page = 0; state.hasMore = false; state.total = 0;
  }
  state.queryKey = key;
  state.loading = true;
  try {
    const data = await request(query);
    // A late response must not populate a different platform or query.
    if (['tab', 'platform', 'keyword'].some(key => state[key] !== query[key])) return false;
    const items = Array.isArray(data.items) ? data.items : [];
    state.items = reset ? items : state.items.concat(items);
    state.page = query.page;
    state.hasMore = Boolean(data.has_more);
    state.total = data.total ?? state.items.length;
    return true;
  } finally { state.loading = false; }
}

async function readLimited(response, limit) {
  if (!response.ok) throw new Error('图片下载失败');
  if (Number(response.headers.get('content-length')) > limit) {
    await response.body?.cancel();
    throw new Error('图集总大小不能超过 128 MiB');
  }
  if (!response.body?.getReader) throw new Error('浏览器不支持受限流式下载，请逐张保存');
  const reader = response.body.getReader(), chunks = [];
  let size = 0;
  try {
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      size += value.byteLength;
      if (size > limit) throw new Error('图集总大小不能超过 128 MiB');
      chunks.push(value);
    }
  } finally { await reader.cancel().catch(() => {}); reader.releaseLock(); }
  const bytes = new Uint8Array(size);
  let offset = 0;
  for (const chunk of chunks) { bytes.set(chunk, offset); offset += chunk.byteLength; }
  return bytes;
}

export async function downloadGallery(urls, name, { native, download, fetchResponse = fetch, createZip, progress = () => {}, limit = 128 * 1024 * 1024 }) {
  const entries = [];
  let total = 0;
  for (let index = 0; index < urls.length; index += 1) {
    const file = `${name}-${String(index + 1).padStart(2, '0')}.jpg`;
    progress(index + 1, urls.length);
    if (native) {
      // DownloadManager cannot read WebView blob: URLs. Queue real media URLs instead.
      await download(urls[index], file);
    } else {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 60000);
      try {
        const response = await fetchResponse(urls[index], { signal: controller.signal });
        const data = await readLimited(response, limit - total);
        total += data.byteLength;
        entries.push({ name: file, data });
      } finally { clearTimeout(timer); }
    }
  }
  return native ? null : createZip(entries);
}
