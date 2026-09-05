// HTTP 层：重试（网络错误/5xx 指数退避，对齐 PHP HttpClient）、短链解析
import { getTransport } from './transport.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function request(url, { method = 'GET', headers = {}, body = null, maxRetries = 3, timeoutMs = 15000, credentialMode = null } = {}) {
  let lastError = null;
  for (let attempt = 0; attempt <= maxRetries; attempt += 1) {
    try {
      const response = await getTransport().request({ url, method, headers, body, timeoutMs, credentialMode });
      if (response.status >= 500 && attempt < maxRetries) {
        await sleep(2 ** attempt * 1000);
        continue;
      }
      return response;
    } catch (error) {
      lastError = error;
      if (attempt < maxRetries) {
        await sleep(2 ** attempt * 1000);
      }
    }
  }
  throw lastError || new Error('网络请求失败');
}

export async function httpGet(url, headers = {}, options = {}) {
  return request(url, { ...options, method: 'GET', headers });
}

export async function httpPost(url, body, headers = {}, options = {}) {
  return request(url, { ...options, method: 'POST', headers, body });
}

// 对齐 PHP BaseParser::fetch()：仅 HTTP 200 视为成功，否则返回 null
export async function fetchOk(url, body = null, headers = {}, options = {}) {
  try {
    const response = body === null
      ? await httpGet(url, headers, options)
      : await httpPost(url, body, headers, options);
    if (response.status !== 200 || response.data == null) return null;
    return response;
  } catch {
    return null;
  }
}

export function parseJson(data) {
  if (data != null && typeof data === 'object') return data;
  try {
    const text = String(data ?? '').trim();
    return text ? JSON.parse(text) : null;
  } catch {
    return null;
  }
}

// 短链解析：传输层自动跟随重定向后，用最终 URL 作为解析结果（等价于 PHP getLocation）
export async function resolveShortLink(url, headers = {}) {
  try {
    const response = await request(url, { method: 'GET', headers, maxRetries: 1, timeoutMs: 12000 });
    if (typeof response.url === 'string' && response.url && response.url !== url) {
      return response.url;
    }
    return url;
  } catch {
    return null;
  }
}
