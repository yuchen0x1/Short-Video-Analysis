/* Shared, dependency-free workbench logic; also loaded by Node regression tests. */
(function (root) {
  'use strict';
  const LIBRARY_KEY = 'watermark-library-v2';
  const MAX_RECORDS = 2000;
  const MAX_BACKUP_BYTES = 4 * 1024 * 1024;

  function httpUrl(value) {
    try {
      const url = new URL(value);
      return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password;
    } catch { return false; }
  }

  function records(value) {
    if (!Array.isArray(value) || value.length > MAX_RECORDS) throw new Error('记录格式错误或超过 2000 条上限');
    const seen = new Set();
    return value.map(item => {
      if (!item || typeof item !== 'object' || typeof item.rawUrl !== 'string' || item.rawUrl.length > 8192 || !httpUrl(item.rawUrl)) {
        throw new Error('备份包含无效的分享链接');
      }
      // Explicit fields prevent credentials or unknown settings entering a backup.
      const result = { rawUrl: item.rawUrl };
      for (const field of ['title', 'author', 'cover', 'videoUrl']) {
        result[field] = typeof item[field] === 'string' ? item[field].slice(0, 8192) : '';
      }
      for (const field of ['cover', 'videoUrl']) if (result[field] && !httpUrl(result[field])) result[field] = '';
      for (const field of ['like', 'time', 'savedAt']) {
        result[field] = typeof item[field] === 'number' && Number.isFinite(item[field]) ? item[field]
          : (typeof item[field] === 'string' ? item[field].slice(0, 100) : 0);
      }
      return result;
    }).filter(item => !seen.has(item.rawUrl) && seen.add(item.rawUrl));
  }

  function createLibrary(storage) {
    const serializeBackup = data => JSON.stringify({ app: 'short-video-analysis', ...data, exportedAt: new Date().toISOString() }, null, 2);
    function read() {
      const raw = storage.getItem(LIBRARY_KEY);
      if (raw !== null) {
        const data = JSON.parse(raw);
        if (data.version !== 2) throw new Error('本地资料版本不受支持，请先导出原始数据');
        return { version: 2, history: records(data.history), favorites: records(data.favorites) };
      }
      return { version: 2,
        history: records(JSON.parse(storage.getItem('watermark-parse-history-v1') || '[]')),
        favorites: records(JSON.parse(storage.getItem('watermark-parse-favorites-v1') || '[]')) };
    }
    function write(data) {
      if (new TextEncoder().encode(serializeBackup(data)).length > MAX_BACKUP_BYTES) {
        throw new Error('资料超过可恢复备份的 4 MiB 上限，请先导出并整理记录');
      }
      // One atomic localStorage write. Keep legacy keys untouched for rollback.
      storage.setItem(LIBRARY_KEY, JSON.stringify(data));
      return data;
    }
    return {
      read,
      save(kind, items) {
        if (!['history', 'favorites'].includes(kind)) throw new Error('未知资料类型');
        const data = read();
        data[kind] = records(items);
        return write(data);
      },
      export() {
        return serializeBackup(read());
      },
      import(text) {
        if (new TextEncoder().encode(text).length > MAX_BACKUP_BYTES) throw new Error('备份文件不能超过 4 MiB');
        const data = JSON.parse(text);
        if (!data || data.app !== 'short-video-analysis' || data.version !== 2) throw new Error('请选择本工具导出的 v2 JSON 备份');
        const incoming = { history: records(data.history), favorites: records(data.favorites) };
        const current = read();
        const merge = (old, next) => {
          const urls = new Set(old.map(item => item.rawUrl));
          return records([...old, ...next.filter(item => !urls.has(item.rawUrl))]);
        };
        return write({ version: 2, history: merge(current.history, incoming.history), favorites: merge(current.favorites, incoming.favorites) });
      },
    };
  }

  function matches(item, query) {
    return [item.title, item.author, item.rawUrl].join(' ').toLocaleLowerCase().includes(query.trim().toLocaleLowerCase());
  }

  function csvCell(value) {
    let text = String(value ?? '');
    // Quoting alone does not prevent spreadsheet formula execution.
    if (/^[\s\u0000-\u001f]*[=+@-]/u.test(text) || /^[\t\r\n]/u.test(text)) text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
  }

  async function runQueue(items, indices, request, { signal, onChange = () => {}, concurrency = 3 } = {}) {
    let next = 0;
    async function worker() {
      while (!signal?.aborted) {
        const index = indices[next++];
        if (index === undefined) return;
        const url = items[index].url;
        items[index] = { url, loading: true };
        onChange(index);
        try {
          const result = await request(url, signal);
          items[index] = signal?.aborted ? { url, error: '已停止处理' } : result;
        } catch (error) {
          items[index] = { url, error: signal?.aborted ? '已停止处理' : (error.message || '解析失败') };
        }
        onChange(index);
      }
    }
    await Promise.all(Array.from({ length: Math.min(Math.max(1, concurrency), indices.length) }, worker));
    for (const index of indices) {
      if (items[index].loading) items[index] = { url: items[index].url, error: '已停止处理' };
    }
  }

  async function readLimited(response, maxBytes) {
    if (!response.ok) throw new Error('媒体下载失败（HTTP ' + response.status + '）');
    if (Number(response.headers.get('content-length')) > maxBytes) throw new Error('打包总大小超过 128 MiB，请分批或逐个下载');
    if (!response.body?.getReader) throw new Error('当前浏览器不支持安全打包，请逐个下载');
    const reader = response.body.getReader(), parts = [];
    let size = 0;
    try {
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        size += value.byteLength;
        if (size > maxBytes) throw new Error('打包总大小超过 128 MiB，请分批或逐个下载');
        parts.push(value);
      }
    } catch (error) { await reader.cancel().catch(() => {}); throw error; }
    finally { reader.releaseLock(); }
    const result = new Uint8Array(size);
    let offset = 0;
    for (const part of parts) { result.set(part, offset); offset += part.byteLength; }
    return result;
  }

  const api = { createLibrary, matches, csvCell, runQueue, readLimited, MAX_BACKUP_BYTES };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.Workbench = api;
})(typeof window !== 'undefined' ? window : this);
