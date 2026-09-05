// 原生桥接：CapacitorHttp 传输适配器（原生网络层，无 CORS）+ 本地媒体代理封装
import { Capacitor, registerPlugin } from '@capacitor/core';

const MediaProxyPlugin = registerPlugin('MediaProxyPlugin');

const isNative = Capacitor.isNativePlatform();

// CapacitorHttp 适配器：供 packages/parsers 的传输层注入。
// - 自动跟随重定向，res.url 为最终地址（短链解析依赖）
// - 响应头键统一小写（ttwid 等 Set-Cookie 读取）
const capacitorHttpTransport = {
  async request({ url, method, headers, body, timeoutMs = 15000 }) {
    const { CapacitorHttp } = await import('@capacitor/core');
    const response = await CapacitorHttp.request({
      url,
      method,
      headers,
      data: body ?? undefined,
      connectTimeout: timeoutMs,
      readTimeout: timeoutMs,
      responseType: 'text',
    });
    const headerObj = {};
    for (const [key, value] of Object.entries(response.headers || {})) {
      headerObj[String(key).toLowerCase()] = Array.isArray(value) ? value.join('; ') : value;
    }
    // 非 JSON 响应返回字符串；JSON 响应 CapacitorHttp 可能已解析为对象（parseJson 两者都兼容）
    return {
      status: response.status,
      data: response.data,
      headers: headerObj,
      url: response.url || url,
    };
  },
};

const proxy = { port: 0, ready: false, error: null, starting: null };

function initMediaProxy() {
  if (!isNative || proxy.ready) return Promise.resolve(true);
  if (proxy.starting) return proxy.starting;

  proxy.starting = MediaProxyPlugin.start()
    .then((result) => {
      proxy.port = Number(result?.port || 0);
      if (!proxy.port) throw new Error('本地媒体代理未返回端口');
      proxy.ready = true;
      proxy.error = null;
      return true;
    })
    .catch((error) => {
      proxy.error = error;
      proxy.ready = false;
      throw error;
    })
    .finally(() => {
      proxy.starting = null;
    });
  return proxy.starting;
}

async function ensureMediaProxy() {
  if (!isNative) return true;
  if (proxy.ready) return true;
  await initMediaProxy();
  return proxy.ready;
}

function mediaUrl(url, filename) {
  if (!isNative) return url;
  if (!proxy.ready) return '';
  const proxyUrl = new URL('http://127.0.0.1:' + proxy.port + '/');
  proxyUrl.searchParams.set('url', url);
  if (filename) {
    proxyUrl.searchParams.set('filename', filename);
  }
  return proxyUrl.href;
}

export { capacitorHttpTransport, initMediaProxy, ensureMediaProxy, mediaUrl, isNative, MediaProxyPlugin };
