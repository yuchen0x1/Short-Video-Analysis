export const FAVORITES_STORAGE_KEY = 'qushuiyin-app-favorites-v1';
export const MAX_FAVORITES = 200;

const MAX_URL_LENGTH = 8192;
const MAX_TEXT_LENGTH = 2048;
const MAX_STORAGE_BYTES = 4 * 1024 * 1024;

function isHttpUrl(value) {
  try {
    const url = new URL(value);
    return (url.protocol === 'http:' || url.protocol === 'https:') && !url.username && !url.password;
  } catch {
    return false;
  }
}

function normalizeRecord(value, savedAt = Date.now()) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
  const rawUrl = typeof value.rawUrl === 'string' ? value.rawUrl.trim() : '';
  if (!rawUrl || rawUrl.length > MAX_URL_LENGTH || !isHttpUrl(rawUrl)) return null;

  const cover = typeof value.cover === 'string' && value.cover.length <= MAX_URL_LENGTH && isHttpUrl(value.cover)
    ? value.cover
    : '';
  const time = Number(value.time);
  const storedAt = Number(value.savedAt);

  return {
    rawUrl,
    title: typeof value.title === 'string' ? value.title.slice(0, MAX_TEXT_LENGTH) : '',
    author: typeof value.author === 'string' ? value.author.slice(0, MAX_TEXT_LENGTH) : '',
    time: Number.isFinite(time) ? time : 0,
    cover,
    mediaType: value.mediaType === 'image' ? 'image' : 'video',
    savedAt: Number.isFinite(storedAt) && storedAt > 0 ? storedAt : savedAt,
  };
}

function normalizeList(value) {
  if (!Array.isArray(value) || value.length > MAX_FAVORITES) {
    throw new Error(`收藏数据无效或超过 ${MAX_FAVORITES} 条上限`);
  }
  const seen = new Set();
  return value
    .map((item) => normalizeRecord(item))
    .filter((item) => item && !seen.has(item.rawUrl) && seen.add(item.rawUrl));
}

export function createFavoritesStore(storage) {
  if (!storage || typeof storage.getItem !== 'function' || typeof storage.setItem !== 'function') {
    throw new TypeError('收藏存储需要提供 getItem 和 setItem');
  }

  function list() {
    const raw = storage.getItem(FAVORITES_STORAGE_KEY);
    if (raw === null) return [];
    try {
      return normalizeList(JSON.parse(raw));
    } catch (error) {
      throw new Error(`无法读取本地收藏：${error.message}`);
    }
  }

  function write(items) {
    const normalized = normalizeList(items);
    const serialized = JSON.stringify(normalized);
    if (new TextEncoder().encode(serialized).length > MAX_STORAGE_BYTES) {
      throw new Error('收藏数据超过 4 MiB 上限');
    }
    storage.setItem(FAVORITES_STORAGE_KEY, serialized);
    return normalized;
  }

  function remove(rawUrl) {
    const current = list();
    const next = current.filter((item) => item.rawUrl !== rawUrl);
    if (next.length === current.length) return false;
    write(next);
    return true;
  }

  function add(value) {
    const item = normalizeRecord(value);
    if (!item) throw new Error('收藏需要有效的 HTTP(S) 分享链接');
    const current = list();
    if (current.some((saved) => saved.rawUrl === item.rawUrl)) return false;
    if (current.length >= MAX_FAVORITES) {
      throw new Error(`收藏已达到 ${MAX_FAVORITES} 条上限，请先移除已有收藏`);
    }
    write([item, ...current]);
    return true;
  }

  return {
    list,
    has(rawUrl) {
      return list().some((item) => item.rawUrl === rawUrl);
    },
    add,
    toggle(value) {
      const item = normalizeRecord(value);
      if (!item) throw new Error('收藏需要有效的 HTTP(S) 分享链接');
      return remove(item.rawUrl) ? false : add(item);
    },
    remove,
    clear() {
      const current = list();
      if (!current.length) return 0;
      write([]);
      return current.length;
    },
  };
}
