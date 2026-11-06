const electron = require('electron');
const { app, BrowserWindow, dialog, shell, Menu, ipcMain, net, session } = electron;
const dns = require('node:dns').promises;
const fs = require('node:fs');
const http = require('node:http');
const nodeNet = require('node:net');
const path = require('node:path');
const { autoUpdater } = require('electron-updater');
const { fetchGithubLatestTag, githubFeedUrl } = require('./updateChannel.cjs');

const isDevelopment = Boolean(process.env.APP_DEV_SERVER_URL);
const WEIBO_PARTITION = 'persist:weibo-login-v1';
const WEIBO_LOGIN_URL = 'https://weibo.com/';
const WEIBO_ALLOWED_HOSTS = new Set(['weibo.com', 'www.weibo.com', 'passport.weibo.com', 'login.sina.com.cn']);
const MAX_WEIBO_RESPONSE_BYTES = 2 * 1024 * 1024;
const MAX_HTTP_REDIRECTS = 5;
const MEDIA_ALLOWED_DOMAINS = [
  'douyinvod.com', 'douyinvod.com.cn', 'douyinstatic.com', 'douyinpic.com', 'bytecdn.cn', 'ibytedtos.com',
  'bilivideo.com', 'hdslb.com', 'mcdn.bilivideo.cn', 'ixigua.com', 'ixiguavideo.com',
  'video.weishi.qq.com', 'ippzone.com', 'pipix.com', 'tiktokcdn.com', 'tiktokcdn-us.com',
  'huya.com', 'msstatic.com', 'douyucdn.cn', 'douyucdn2.cn',
];
let mainWindow = null;
let weiboLoginWindow = null;
let mediaProxyServer = null;
let mediaProxyPort = 0;
let lastUpdateStatus = { status: 'idle' };

function sendUpdate(payload) {
  lastUpdateStatus = payload;
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.send('app.update.status', payload);
  }
}

function changelogPath() {
  if (app.isPackaged) return path.join(process.resourcesPath, 'changelog', 'CHANGELOG.md');
  return path.join(__dirname, '..', '..', '..', 'CHANGELOG.md');
}

function readChangelog() {
  try {
    return fs.readFileSync(changelogPath(), 'utf8');
  } catch {
    return '暂无更新日志。';
  }
}

function getWeiboSession() {
  return session.fromPartition(WEIBO_PARTITION);
}

function isAllowedWeiboUrl(url) {
  try {
    const parsed = new URL(url);
    return parsed.protocol === 'https:' && WEIBO_ALLOWED_HOSTS.has(parsed.hostname.toLowerCase());
  } catch {
    return false;
  }
}

async function getWeiboCookieHeader() {
  const cookies = await getWeiboSession().cookies.get({ url: WEIBO_LOGIN_URL });
  return cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join('; ');
}

async function getWeiboLoginStatus() {
  const cookies = await getWeiboSession().cookies.get({ url: WEIBO_LOGIN_URL });
  const names = new Set(cookies.map((cookie) => cookie.name));
  const loggedIn = names.has('SUB') || names.has('SUBP') || names.has('SSOLoginState');
  return { loggedIn, cookieCount: cookies.length };
}

async function readBoundedText(response) {
  const reader = response.body?.getReader?.();
  if (!reader) return response.text();
  const chunks = [];
  let total = 0;
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    total += value.byteLength;
    if (total > MAX_WEIBO_RESPONSE_BYTES) {
      throw new Error('微博响应过大');
    }
    chunks.push(value);
  }
  return Buffer.concat(chunks.map((chunk) => Buffer.from(chunk))).toString('utf8');
}

function setupWeiboLoginIpc() {
  ipcMain.handle('weibo.login.status', () => getWeiboLoginStatus());

  ipcMain.handle('weibo.login.open', () => {
    if (weiboLoginWindow && !weiboLoginWindow.isDestroyed()) {
      weiboLoginWindow.focus();
      return getWeiboLoginStatus();
    }

    weiboLoginWindow = new BrowserWindow({
      width: 1080,
      height: 760,
      minWidth: 760,
      minHeight: 560,
      title: '登录微博',
      parent: mainWindow || undefined,
      webPreferences: {
        partition: WEIBO_PARTITION,
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
      },
    });

    weiboLoginWindow.webContents.setWindowOpenHandler(({ url }) => {
      if (isAllowedWeiboUrl(url)) return { action: 'allow' };
      if (/^https:\/\//i.test(url)) shell.openExternal(url);
      return { action: 'deny' };
    });
    weiboLoginWindow.webContents.on('will-navigate', (event, url) => {
      if (!isAllowedWeiboUrl(url)) {
        event.preventDefault();
        if (/^https:\/\//i.test(url)) shell.openExternal(url);
      }
    });
    weiboLoginWindow.webContents.on('will-redirect', (event, url) => {
      if (!isAllowedWeiboUrl(url)) {
        event.preventDefault();
      }
    });
    weiboLoginWindow.on('closed', () => {
      weiboLoginWindow = null;
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.webContents.send('weibo.login.changed');
      }
    });
    weiboLoginWindow.loadURL(WEIBO_LOGIN_URL);
    return getWeiboLoginStatus();
  });

  ipcMain.handle('weibo.login.logout', async () => {
    const weiboSession = getWeiboSession();
    for (const origin of ['https://weibo.com', 'https://www.weibo.com', 'https://passport.weibo.com', 'https://login.sina.com.cn']) {
      await weiboSession.clearStorageData({
        origin,
        storages: ['cookies', 'localstorage', 'indexdb'],
      });
    }
    try { await weiboSession.cookies.flushStore(); } catch {}
    if (mainWindow && !mainWindow.isDestroyed()) mainWindow.webContents.send('weibo.login.changed');
    return getWeiboLoginStatus();
  });

  ipcMain.handle('weibo.request', async (_event, payload) => {
    const url = String(payload?.url || '');
    const method = String(payload?.method || 'POST').toUpperCase();
    const timeoutMs = Math.max(1000, Math.min(Number(payload?.timeoutMs || 15000), 30000));
    let parsed;
    try {
      parsed = new URL(url);
    } catch {
      throw new Error('无效的微博请求地址');
    }
    if (method !== 'POST' || parsed.protocol !== 'https:' || parsed.hostname !== 'weibo.com' || !/^\/tv\/api\/component\/page\/tv\/show\/\d+:\d+$/.test(parsed.pathname)) {
      throw new Error('不允许的微博登录态请求');
    }

    const cookie = await getWeiboCookieHeader();
    if (!cookie) throw new Error('请先在关于页登录微博或设置微博凭据');

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await net.fetch(url, {
        method: 'POST',
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
          Referer: 'https://weibo.com/',
          Cookie: cookie,
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: String(payload?.body ?? ''),
        redirect: 'manual',
        credentials: 'omit',
        signal: controller.signal,
      });
      const headers = {};
      response.headers.forEach((value, key) => {
        if (key.toLowerCase() !== 'set-cookie') headers[key.toLowerCase()] = value;
      });
      const text = await readBoundedText(response);
      return { status: response.status, data: text, headers, url: response.url || url };
    } catch (error) {
      throw new Error('微博登录态请求失败: ' + String((error && error.message) || error));
    } finally {
      clearTimeout(timer);
    }
  });
}

function setupAutoUpdater() {
  autoUpdater.autoDownload = true;
  autoUpdater.autoInstallOnAppQuit = true;
  autoUpdater.allowPrerelease = false;

  autoUpdater.on('checking-for-update', () => sendUpdate({ status: 'checking' }));
  autoUpdater.on('update-available', (info) => sendUpdate({ status: 'available', version: info.version }));
  autoUpdater.on('update-not-available', (info) => sendUpdate({ status: 'none', version: info.version }));
  autoUpdater.on('download-progress', (progress) => sendUpdate({ status: 'downloading', percent: Math.round(progress.percent) }));
  autoUpdater.on('update-downloaded', async (info) => {
    sendUpdate({ status: 'downloaded', version: info.version });
    if (mainWindow && !mainWindow.isDestroyed()) {
      const { response } = await dialog.showMessageBox(mainWindow, {
        type: 'info',
        title: '更新已就绪',
        message: `新版本 ${info.version} 已下载完成。`,
        detail: '重启应用即可完成安装。',
        buttons: ['立即重启', '稍后'],
        defaultId: 0,
      });
      if (response === 0) autoUpdater.quitAndInstall();
    }
  });
  autoUpdater.on('error', (error) => sendUpdate({ status: 'error', message: String((error && error.message) || error) }));

  // 在线更新仅使用国内 Gitee。
  ipcMain.on('app.update.check', async () => {
    if (!app.isPackaged) {
      sendUpdate({ status: 'dev', message: '正常更新中' });
      return;
    }
    try {
      await checkForUpdatesWithChannel();
    } catch (error) {
      sendUpdate({ status: 'error', message: friendlyUpdateError(error) });
    }
  });
  ipcMain.on('app.update.install', () => {
    if (app.isPackaged) autoUpdater.quitAndInstall();
  });
  ipcMain.handle('app.version', () => app.getVersion());
  ipcMain.handle('app.update.status', () => lastUpdateStatus);
  ipcMain.handle('app.changelog', () => readChangelog());
}

async function checkForUpdatesWithChannel() {
  const tag = await fetchGithubLatestTag();
  console.log(`[update] channel=github tag=${tag}`);
  autoUpdater.setFeedURL({ provider: 'generic', url: githubFeedUrl(tag) });
  return autoUpdater.checkForUpdates();
}

// 把底层网络错误翻译成用户能读懂的提示，原始信息保留在日志里。
function friendlyUpdateError(error) {
  const raw = String((error && error.message) || error);
  console.log('[update] 检查失败:', raw);
  if (/ENOTFOUND|EAI_AGAIN|解析到非公网地址/i.test(raw)) return '无法连接 GitHub 更新服务：域名解析失败，请检查网络或 DNS 设置';
  if (/ETIMEDOUT|ECONNREFUSED|ECONNRESET|AbortError|abort/i.test(raw)) return '连接 GitHub 更新服务超时，请稍后重试';
  if (/HTTP 403|rate limit/i.test(raw)) return 'GitHub 更新服务请求受限，请稍后重试';
  if (/HTTP 404/i.test(raw)) return '更新源暂未发布新版本';
  if (/_proxy|tunnel|certificate|SSL|TLS/i.test(raw)) return 'GitHub 更新服务连接异常，请检查代理或网络环境';
  return raw;
}

// 本地解析的 HTTP 传输桥：renderer 经 IPC 发起请求，由主进程用 Electron net.fetch
// 执行（无浏览器 CORS 限制），返回状态、响应体、响应头与重定向后的最终地址。

// 渲染进程可请求的平台域名白名单（与 packages/parsers/src/domains.js 保持一致），
// 防止渲染进程被注入后把此 IPC 当作任意网络出口。
const PARSER_ALLOWED_DOMAINS = [
  'douyin.com', 'iesdouyin.com', 'kuaishou.com', 'kuaishou.cn', 'bilibili.com', 'b23.tv',
  'pipix.com', 'weibo.com', 'ixigua.com', 'channels.weixin.qq.com',
  'izuiyou.com', 'xiaochuankeji.cn', 'ippzone.com', 'pipigx.com', 'weishi.qq.com',
  'xiaohongshu.com', 'xhslink.com', 'tiktok.com', 'huya.com', 'douyu.com',
  // 抖音 ttwid 注册端点
  'bytedance.com',
];

function isAllowedParserUrl(url) {
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') return false;
    if (parsed.username || parsed.password || parsed.port) return false;
    const host = parsed.hostname.toLowerCase().replace(/^www\./, '').replace(/\.$/, '');
    return PARSER_ALLOWED_DOMAINS.some((domain) => host === domain || host.endsWith('.' + domain));
  } catch {
    return false;
  }
}

function isAllowedDomain(host, domains) {
  const normalized = String(host || '').toLowerCase().replace(/\.$/, '');
  if (!normalized || normalized === 'localhost' || normalized.endsWith('.localhost')) return false;
  return domains.some((domain) => normalized === domain || normalized.endsWith('.' + domain));
}

function parseIpv4(address) {
  const parts = String(address).split('.');
  if (parts.length !== 4 || parts.some((part) => !/^\d+$/.test(part))) return null;
  const octets = parts.map(Number);
  return octets.every((part) => part >= 0 && part <= 255) ? octets : null;
}

function isForbiddenIpv4(address) {
  const octets = parseIpv4(address);
  if (!octets) return true;
  const [first, second, third] = octets;
  if (first === 0 || first === 10 || first === 127 || first >= 224) return true;
  if (first === 100 && second >= 64 && second <= 127) return true;
  if (first === 169 && second === 254) return true;
  if (first === 172 && second >= 16 && second <= 31) return true;
  if (first === 192 && (second === 0 || second === 168)) return true;
  if (first === 192 && second === 88 && third === 99) return true;
  if (first === 198 && (second === 18 || second === 19)) return true;
  if (first === 198 && second === 51 && third === 100) return true;
  return first === 203 && second === 0 && third === 113;
}

function parseIpv6(address) {
  let value = String(address || '').toLowerCase().replace(/^\[|\]$/g, '').split('%')[0];
  if (!value || value.includes(':::')) return null;
  const halves = value.split('::');
  if (halves.length > 2) return null;
  const parsePart = (part) => {
    if (!part) return [];
    const pieces = part.split(':');
    const result = [];
    for (const piece of pieces) {
      if (piece.includes('.')) {
        const octets = parseIpv4(piece);
        if (!octets) return null;
        result.push((octets[0] << 8) | octets[1], (octets[2] << 8) | octets[3]);
      } else if (/^[0-9a-f]{1,4}$/.test(piece)) {
        result.push(parseInt(piece, 16));
      } else {
        return null;
      }
    }
    return result;
  };
  const left = parsePart(halves[0]);
  const right = parsePart(halves[1] || '');
  if (!left || !right || (halves.length === 1 && left.length !== 8) || (halves.length === 2 && left.length + right.length >= 8)) return null;
  return halves.length === 2 ? [...left, ...Array(8 - left.length - right.length).fill(0), ...right] : left;
}

function isForbiddenAddress(address) {
  if (nodeNet.isIPv4(address)) return isForbiddenIpv4(address);
  if (!nodeNet.isIPv6(address)) return true;
  const groups = parseIpv6(address);
  if (!groups) return true;
  const mapped = groups.slice(0, 5).every((value) => value === 0) && groups[5] === 0xffff;
  if (mapped) {
    const mappedIpv4 = `${groups[6] >> 8}.${groups[6] & 255}.${groups[7] >> 8}.${groups[7] & 255}`;
    return isForbiddenIpv4(mappedIpv4);
  }
  const first = groups[0];
  return groups.every((value) => value === 0) || (groups.slice(0, 7).every((value) => value === 0) && groups[7] === 1)
    || (first & 0xfe00) === 0xfc00 || (first & 0xffc0) === 0xfe80 || (first & 0xff00) === 0xff00
    || (groups[0] === 0x2001 && groups[1] === 0x0db8);
}

function parseMediaUrl(value) {
  try {
    const parsed = new URL(String(value || ''));
    if ((parsed.protocol !== 'http:' && parsed.protocol !== 'https:') || parsed.username || parsed.password) return null;
    if (parsed.port && ((parsed.protocol === 'http:' && parsed.port !== '80') || (parsed.protocol === 'https:' && parsed.port !== '443'))) return null;
    if (!isAllowedDomain(parsed.hostname, MEDIA_ALLOWED_DOMAINS)) return null;
    return parsed;
  } catch {
    return null;
  }
}

async function validateMediaTarget(value) {
  const parsed = parseMediaUrl(value);
  if (!parsed) return null;
  const addresses = await dns.lookup(parsed.hostname, { all: true, verbatim: true });
  if (!Array.isArray(addresses) || addresses.length === 0 || addresses.some((entry) => isForbiddenAddress(entry.address))) return null;
  return { parsed, address: addresses[0].address, family: addresses[0].family };
}

function resolveAllowedRedirect(baseUrl, location, urlValidator) {
  let nextUrl;
  try {
    nextUrl = new URL(String(location || ''), baseUrl).href;
  } catch {
    throw new Error('重定向地址无效');
  }
  if (!urlValidator(nextUrl)) throw new Error('重定向目标不在允许的域名内');
  return nextUrl;
}

function collectResponseHeaders(response) {
  const headers = {};
  response.headers.forEach((value, key) => {
    const lower = key.toLowerCase();
    headers[lower] = lower === 'set-cookie' && headers[lower] !== undefined
      ? `${headers[lower]}; ${value}`
      : value;
  });
  if (response.headers.getSetCookie) {
    try {
      const list = response.headers.getSetCookie();
      if (list && list.length) headers['set-cookie'] = list.join('; ');
    } catch {
      // 保持默认合并行为。
    }
  }
  return headers;
}

async function fetchParserResponse({ url, method, headers, body, fetchImpl = net?.fetch }) {
  if (typeof fetchImpl !== 'function') throw new Error('Electron 网络模块不可用');
  let currentUrl = String(url || '');
  const requestMethod = String(method || 'GET').toUpperCase();
  const requestBody = requestMethod === 'GET' || requestMethod === 'HEAD' ? undefined : String(body ?? '');

  for (let redirectCount = 0; redirectCount <= MAX_HTTP_REDIRECTS; redirectCount += 1) {
    if (!isAllowedParserUrl(currentUrl)) throw new Error('不允许的请求地址');
    const response = await fetchImpl(currentUrl, {
      method: requestMethod,
      headers: headers || undefined,
      body: requestBody,
      redirect: 'manual',
    });
    const location = response.headers.get('location');
    if (response.status >= 300 && response.status < 400 && location) {
      if (redirectCount >= MAX_HTTP_REDIRECTS) throw new Error('重定向次数过多');
      currentUrl = resolveAllowedRedirect(currentUrl, location, isAllowedParserUrl);
      continue;
    }
    return {
      status: response.status,
      data: await response.text(),
      headers: collectResponseHeaders(response),
      url: response.url || currentUrl,
    };
  }
  throw new Error('重定向次数过多');
}

function setupParserIpc() {
  ipcMain.handle('app.httpRequest', async (_event, payload) => {
    const url = String(payload?.url || '');
    if (!isAllowedParserUrl(url)) {
      throw new Error('不允许的请求地址');
    }
    try {
      return await fetchParserResponse({
        url,
        method: payload?.method,
        headers: payload?.headers,
        body: payload?.body,
      });
    } catch (error) {
      throw new Error('网络请求失败: ' + String((error && error.message) || error));
    }
  });
}

// 本地媒体代理：仅监听 127.0.0.1，为媒体请求注入 Referer/UA 绕过防盗链，
// 支持 Range 透传（边下边播）。仅转发 http/https 地址。
// 上游请求用 Node https/http 模块（完全控制长度与流式传输），避免
// net.fetch 对本地代理 content-length 校验的兼容性问题。
const PROXY_UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

const FORWARD_HEADERS = [
  'content-type', 'content-length', 'content-range', 'accept-ranges',
  'content-disposition', 'etag', 'last-modified', 'cache-control',
];

// 与服务端 MEDIA_PROXY_MAX_SIZE 默认值一致
const MAX_PROXY_BYTES = 524288000;

function startMediaProxy() {
  if (mediaProxyServer) return;
  mediaProxyServer = http.createServer((req, res) => {
    if (req.method !== 'GET') {
      res.writeHead(405, { 'Content-Type': 'text/plain' });
      res.end('Method Not Allowed');
      return;
    }
    const requestUrl = new URL(req.url, 'http://127.0.0.1');
    const target = requestUrl.searchParams.get('url');
    const filename = requestUrl.searchParams.get('filename');
    if (!target || !parseMediaUrl(target)) {
      res.writeHead(403, { 'Content-Type': 'text/plain' });
      res.end('invalid media url');
      return;
    }

    const forwardRequest = async (targetUrl, redirects = 0) => {
      const validated = await validateMediaTarget(targetUrl).catch(() => null);
      if (!validated) {
        if (!res.headersSent) {
          res.writeHead(403, { 'Content-Type': 'text/plain' });
          res.end('media target rejected');
        }
        return;
      }
      const upstream = validated.parsed;
      const isSecure = upstream.protocol === 'https:';
      const upstreamModule = isSecure ? require('node:https') : require('node:http');
      const headers = {
        'User-Agent': PROXY_UA,
        Accept: '*/*',
        Host: upstream.host,
        Referer: `${upstream.protocol}//${upstream.host}`,
        'Accept-Encoding': 'identity',
      };
      if (req.headers.range && /^bytes=\d*-\d*$/.test(String(req.headers.range))) {
        headers.Range = String(req.headers.range);
      }

      const proxy = upstreamModule.request(
        {
          protocol: upstream.protocol,
          hostname: validated.address,
          port: upstream.port || (isSecure ? 443 : 80),
          path: upstream.pathname + upstream.search,
          method: 'GET',
          headers,
          ...(isSecure ? { servername: upstream.hostname } : {}),
        },
        (upstreamRes) => {
          const status = upstreamRes.statusCode || 500;
          const location = upstreamRes.headers.location;
          if (status >= 300 && status < 400 && location) {
            upstreamRes.resume();
            if (redirects >= MAX_HTTP_REDIRECTS) {
              res.writeHead(508, { 'Content-Type': 'text/plain' });
              res.end('too many upstream redirects');
              return;
            }
            let nextUrl;
            try { nextUrl = new URL(location, upstream).href; } catch { nextUrl = ''; }
            if (!nextUrl) {
              res.writeHead(502, { 'Content-Type': 'text/plain' });
              res.end('invalid upstream redirect');
              return;
            }
            forwardRequest(nextUrl, redirects + 1).catch(() => {
              if (!res.headersSent) {
                res.writeHead(502, { 'Content-Type': 'text/plain' });
                res.end('upstream error');
              }
            });
            return;
          }

          const contentLength = Number.parseInt(String(upstreamRes.headers['content-length'] || ''), 10);
          if (Number.isSafeInteger(contentLength) && contentLength > MAX_PROXY_BYTES) {
            upstreamRes.resume();
            res.writeHead(413, { 'Content-Type': 'text/plain' });
            res.end('media file too large');
            return;
          }
          const forwardHeaders = {};
          for (const name of FORWARD_HEADERS) {
            if (name === 'content-length' && !Number.isSafeInteger(contentLength)) continue;
            const value = upstreamRes.headers[name];
            if (value !== undefined) forwardHeaders[name] = String(value);
          }
          if (forwardHeaders['accept-ranges'] === undefined) forwardHeaders['accept-ranges'] = 'bytes';
          if (filename) forwardHeaders['content-disposition'] = `attachment; filename*=UTF-8''${encodeURIComponent(filename)}`;
          res.writeHead(status, forwardHeaders);
          let bytes = 0;
          upstreamRes.on('data', (chunk) => {
            if (res.destroyed) return;
            const remaining = MAX_PROXY_BYTES - bytes;
            if (remaining <= 0 || chunk.length > remaining) {
              upstreamRes.destroy();
              res.destroy();
              return;
            }
            bytes += chunk.length;
            res.write(chunk);
          });
          upstreamRes.on('end', () => { if (!res.writableEnded && !res.destroyed) res.end(); });
          upstreamRes.on('error', () => { if (!res.destroyed) res.destroy(); });
        }
      );
      proxy.on('error', () => {
        if (!res.headersSent) {
          res.writeHead(502, { 'Content-Type': 'text/plain' });
          res.end('upstream error');
        } else if (!res.destroyed) {
          res.destroy();
        }
      });
      proxy.end();
    };

    forwardRequest(target).catch(() => {
      if (!res.headersSent) {
        res.writeHead(502, { 'Content-Type': 'text/plain' });
        res.end('upstream error');
      }
    });
  });

  mediaProxyServer.listen(0, '127.0.0.1', () => {
    mediaProxyPort = mediaProxyServer.address().port;
  });
  return new Promise((resolve) => {
    mediaProxyServer.once('listening', () => resolve(mediaProxyPort));
  });
}

// 让 preload 能拿到代理端口和地址
ipcMain.handle('app.mediaProxyPort', () => {
  if (!mediaProxyPort) {
    throw new Error('媒体代理尚未启动');
  }
  return mediaProxyPort;
});

ipcMain.handle('app.mediaProxyUrl', (_event, payload) => {
  const target = String(payload?.url || '');
  if (!parseMediaUrl(target)) {
    throw new Error('不允许的媒体地址');
  }
  if (!mediaProxyPort) {
    throw new Error('媒体代理尚未启动');
  }
  const url = new URL(`http://127.0.0.1:${mediaProxyPort}/`);
  url.searchParams.set('url', target);
  const filename = String(payload?.filename || '');
  if (filename) url.searchParams.set('filename', filename);
  return url.href;
});

function isTrustedMainFrameUrl(url) {
  try {
    const parsed = new URL(String(url || ''));
    if (isDevelopment) {
      const dev = new URL(process.env.APP_DEV_SERVER_URL);
      return parsed.origin === dev.origin;
    }
    return parsed.protocol === 'file:';
  } catch {
    return false;
  }
}

function createWindow() {
  const window = new BrowserWindow({
    width: 1360,
    height: 900,
    minWidth: 960,
    minHeight: 680,
    backgroundColor: '#0b1718',
    title: '去水印解析',
    icon: path.join(__dirname, '..', 'build', 'icon.png'),
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.cjs'),
      additionalArguments: [`--media-proxy-port=${mediaProxyPort}`],
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });
  mainWindow = window;

  window.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:\/\//i.test(url)) shell.openExternal(url);
    return { action: 'deny' };
  });
  window.webContents.on('will-navigate', (event, url) => {
    if (!isTrustedMainFrameUrl(url)) {
      event.preventDefault();
      if (/^https?:\/\//i.test(url)) shell.openExternal(url);
    }
  });
  window.webContents.on('will-redirect', (event, url) => {
    if (!isTrustedMainFrameUrl(url)) event.preventDefault();
  });

  if (isDevelopment) {
    window.loadURL(process.env.APP_DEV_SERVER_URL);
  } else {
    window.loadFile(path.join(__dirname, '..', 'dist', 'index.html'));
  }
}

app.whenReady().then(async () => {
  Menu.setApplicationMenu(null);
  setupAutoUpdater();
  setupWeiboLoginIpc();
  setupParserIpc();
  try {
    await startMediaProxy();
  } catch (error) {
    dialog.showErrorBox('去水印解析无法启动', error.message || '本地媒体代理启动失败');
    app.quit();
    return;
  }
  createWindow();
  if (app.isPackaged) {
    // 启动时静默检查国内 Gitee，失败时不打扰用户。
    checkForUpdatesWithChannel().catch(() => {});
  }
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('before-quit', () => {
  if (mediaProxyServer) mediaProxyServer.close();
});
