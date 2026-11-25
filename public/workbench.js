/* Shared, dependency-free workbench logic; also loaded by Node regression tests. */
(function (root) {
  'use strict';
  const LIBRARY_KEY = 'watermark-library-v2';
  const MAX_RECORDS = 2000;
  const MAX_BACKUP_BYTES = 4 * 1024 * 1024;
  const BATCH_STATE_KEY = 'watermark-batch-v1';
  const BATCH_CONCURRENCY_KEY = 'watermark-batch-concurrency-v1';
  const BATCH_LIMIT = 20;
  const MAX_CONCURRENCY = 6;

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

  function createRequestGuard() {
    let sequence = 0;
    return {
      start() { sequence += 1; return sequence; },
      invalidate() { sequence += 1; return sequence; },
      isCurrent(request) { return request === sequence; },
    };
  }

  function csvCell(value) {
    let text = String(value ?? '');
    // Quoting alone does not prevent spreadsheet formula execution.
    if (/^[\s\u0000-\u001f]*[=+@-]/u.test(text) || /^[\t\r\n]/u.test(text)) text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
  }

  function retryDelay(response, payload) {
    const header = response && response.headers && typeof response.headers.get === 'function'
      ? response.headers.get('retry-after') : null;
    const seconds = Number(header || (payload && payload.retry_after) || 0);
    return Number.isFinite(seconds) && seconds > 0 ? Math.min(Math.ceil(seconds), 300) : 0;
  }

  function apiError(response, payload) {
    const status = response && Number(response.status) || 0;
    const message = (payload && typeof payload.message === 'string' && payload.message) || '请求失败，请稍后再试';
    const error = new Error(message);
    error.name = 'ApiError';
    error.status = status;
    error.code = (payload && typeof payload.code === 'string' && payload.code) || 'http_' + status;
    error.retryable = payload && typeof payload.retryable === 'boolean'
      ? payload.retryable
      : status === 429 || status >= 500;
    error.retryAfter = retryDelay(response, payload);
    error.requestId = payload && payload.request_id || '';
    return error;
  }

  function wait(ms, signal) {
    if (!ms) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const timer = setTimeout(done, ms);
      function done() {
        if (signal) signal.removeEventListener('abort', abort);
        resolve();
      }
      function abort() {
        clearTimeout(timer);
        reject(new DOMException('Aborted', 'AbortError'));
      }
      if (signal) {
        if (signal.aborted) return abort();
        signal.addEventListener('abort', abort, { once: true });
      }
    });
  }

  /**
   * 并发数钳制。
   * 未设置时 localStorage.getItem 返回 null，Number(null) 是 0，会被误判成「整数 1 路」，
   * 因此空值必须先回落到默认 3 路，再做 1..MAX_CONCURRENCY 的钳制。
   */
  function clampConcurrency(value) {
    if (value === null || value === undefined || value === '') return 3;
    const parsed = Number(value);
    return Number.isInteger(parsed) ? Math.min(Math.max(parsed, 1), MAX_CONCURRENCY) : 3;
  }

  /**
   * 批量队列本地状态：只保存链接、结果与失败原因，不保存 Cookie/API Key。
   * 刷新后把未完成项标成可重试的“已中断”，由用户继续处理。
   */
  function createBatchStore(storage) {
    const clean = value => {
      if (!value || typeof value !== 'object' || typeof value.url !== 'string' || !httpUrl(value.url)) return null;
      const item = { url: value.url };
      if (value.data && typeof value.data === 'object') {
        const data = value.data;
        item.data = {};
        for (const field of ['title', 'author', 'cover', 'url', 'platform', 'media_type']) {
          item.data[field] = typeof data[field] === 'string' ? data[field].slice(0, 8192) : '';
        }
        for (const field of ['like', 'time']) item.data[field] = Number.isFinite(data[field]) ? data[field] : 0;
        item.data.images = Array.isArray(data.images) ? data.images.filter(httpUrl).slice(0, 200) : [];
        item.data.music = data.music && typeof data.music === 'object' ? {
          author: String(data.music.author || '').slice(0, 2048),
          avatar: httpUrl(data.music.avatar) ? data.music.avatar : '',
          url: httpUrl(data.music.url) ? data.music.url : '',
        } : { author: '', avatar: '' };
        if (!item.data.url && item.data.images.length) item.data.url = item.data.images[0];
      } else if (value.error) {
        item.error = String(value.error).slice(0, 500);
        item.retryable = value.retryable !== false;
        if (value.code) item.code = String(value.code).slice(0, 60);
        if (value.interrupted) item.interrupted = true;
      } else {
        item.error = '页面刷新后任务已停止';
        item.retryable = true;
        item.interrupted = true;
      }
      return item;
    };
    return {
      read() {
        const raw = storage.getItem(BATCH_STATE_KEY);
        if (!raw) return { items: [], savedAt: 0 };
        const parsed = JSON.parse(raw);
        if (!parsed || parsed.version !== 1 || !Array.isArray(parsed.items)) throw new Error('批量任务格式不受支持');
        const seen = new Set();
        return {
          items: parsed.items.map(clean).filter(item => item && !seen.has(item.url) && seen.add(item.url)).slice(0, BATCH_LIMIT),
          savedAt: Number(parsed.savedAt) || 0,
        };
      },
      write(items, options = {}) {
        const seen = new Set();
        const list = (Array.isArray(items) ? items : [])
          .map(clean)
          .filter(item => item && !seen.has(item.url) && seen.add(item.url))
          .slice(0, BATCH_LIMIT);
        storage.setItem(BATCH_STATE_KEY, JSON.stringify({
          version: 1,
          savedAt: options.savedAt ?? Date.now(),
          pendingCount: options.pendingCount ?? list.filter(item => !item.data && !item.error).length,
          items: list,
        }));
        return list;
      },
      clear() {
        storage.removeItem(BATCH_STATE_KEY);
      },
      readConcurrency() {
        return clampConcurrency(storage.getItem(BATCH_CONCURRENCY_KEY));
      },
      writeConcurrency(value) {
        const concurrency = clampConcurrency(value);
        storage.setItem(BATCH_CONCURRENCY_KEY, String(concurrency));
        return concurrency;
      },
    };
  }

  const API_KEY_STORAGE = 'watermark-api-key-v1';

  function apiKeyHeaders(key) {
    return key ? { 'X-API-Key': key } : {};
  }

  /**
   * 页面增强：API Key 登录卡片与批量恢复入口。返回元素引用，便于页面脚本绑定事件。
   */
  function mountEnhancements(doc, storage) {
    const sidebar = doc.querySelector('.sidebar');
    if (!sidebar) return {};

    let apiCard = doc.querySelector('#api-key-card');
    if (!apiCard) {
      apiCard = doc.createElement('section');
      apiCard.id = 'api-key-card';
      apiCard.className = 'panel side-panel';
      apiCard.hidden = true;
      apiCard.setAttribute('aria-label', 'API 访问密钥');
      apiCard.innerHTML = '<div class="side-heading"><div><h2>API 访问</h2><p>此实例启用了 API Key</p></div><span id="api-key-status" class="account-status"><i></i><span>未配置</span></span></div><div class="account-form"><label class="field-label" for="api-key-input">访问密钥</label><input id="api-key-input" type="password" autocomplete="off" spellcheck="false" placeholder="粘贴管理员提供的 API Key"><p class="input-hint">只保存在当前浏览器，经 X-API-Key 请求头发送，不写入链接或备份。</p><div class="account-actions"><button id="api-key-save" class="btn btn-teal" type="button">保存并验证</button><button id="api-key-clear" class="btn btn-quiet" type="button">清除</button></div></div>';
      sidebar.insertBefore(apiCard, sidebar.querySelector('#favorites') || sidebar.children[1] || null);
    }

    const batchPanel = doc.querySelector('#batch-panel');
    if (batchPanel && !doc.querySelector('#batch-concurrency')) {
      const actions = batchPanel.querySelector('.batch-actions');
      if (actions) {
        const label = doc.createElement('label');
        label.className = 'batch-concurrency-label';
        label.htmlFor = 'batch-concurrency';
        label.textContent = '并发';
        const select = doc.createElement('select');
        select.id = 'batch-concurrency';
        select.className = 'batch-concurrency';
        select.setAttribute('aria-label', '批量解析并发数');
        select.innerHTML = Array.from({ length: 6 }, (_, i) => `<option value="${i + 1}">${i + 1} 路</option>`).join('');
        actions.prepend(select);
        actions.prepend(label);
        const restore = doc.createElement('button');
        restore.id = 'batch-restore-btn';
        restore.className = 'btn btn-quiet';
        restore.type = 'button';
        restore.hidden = true;
        restore.textContent = '恢复上次任务';
        const clear = doc.createElement('button');
        clear.id = 'batch-clear-btn';
        clear.className = 'btn btn-quiet';
        clear.type = 'button';
        clear.hidden = true;
        clear.textContent = '清除本地任务';
        const stop = actions.querySelector('#batch-stop-btn');
        actions.insertBefore(restore, stop || null);
        actions.insertBefore(clear, stop || null);
      }
    }

    return {
      apiCard,
      apiStatus: doc.querySelector('#api-key-status'),
      apiInput: doc.querySelector('#api-key-input'),
      apiSave: doc.querySelector('#api-key-save'),
      apiClear: doc.querySelector('#api-key-clear'),
      batchRestore: doc.querySelector('#batch-restore-btn'),
      batchClear: doc.querySelector('#batch-clear-btn'),
      batchConcurrency: doc.querySelector('#batch-concurrency'),
    };
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
          items[index] = signal?.aborted
            ? { url, error: '已停止处理', retryable: true }
            : {
                url,
                error: error.message || '解析失败',
                code: error.code || '',
                retryable: error.retryable === true,
                retryAfter: Number(error.retryAfter) || 0,
              };
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

  const api = {
    createLibrary, createBatchStore, createRequestGuard, mountEnhancements, apiKeyHeaders, API_KEY_STORAGE,
    matches, csvCell, apiError, wait, clampConcurrency,
    runQueue, readLimited, MAX_BACKUP_BYTES, BATCH_LIMIT, MAX_CONCURRENCY,
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.Workbench = api;
})(typeof window !== 'undefined' ? window : this);
