// 国内 Gitee 在线更新源。禁止重定向、内网 DNS 结果和超大响应。
const dns = require('node:dns');
const nodeNet = require('node:net');

const GITEE_OWNER = 'yuchen0x1';
const GITEE_REPO = 'short-video-watermark-remover';
const GITEE_LATEST_API = `https://gitee.com/api/v5/repos/${GITEE_OWNER}/${GITEE_REPO}/releases/latest`;
const GITEE_RELEASES_URL = `https://gitee.com/${GITEE_OWNER}/${GITEE_REPO}/releases`;
const TIMEOUT_MS = 5000;
const MAX_RESPONSE_BYTES = 1024 * 1024;
const REQUEST_UA = 'qushuiyin-desktop-updater/2.3.2';

function isForbiddenIpv4(ip) {
  const parts = ip.split('.').map(Number);
  if (parts.length !== 4 || parts.some((part) => !Number.isInteger(part) || part < 0 || part > 255)) return true;
  const [a, b, c] = parts;
  return a === 0 || a === 10 || a === 127 || a >= 224
    || (a === 100 && b >= 64 && b <= 127)
    || (a === 169 && b === 254)
    || (a === 172 && b >= 16 && b <= 31)
    || (a === 192 && b === 168)
    || (a === 192 && b === 0)
    || (a === 198 && (b === 18 || b === 19))
    || (a === 198 && b === 51 && c === 100)
    || (a === 203 && b === 0 && c === 113);
}

function isForbiddenIp(ip) {
  const version = nodeNet.isIP(ip);
  if (version === 4) return isForbiddenIpv4(ip);
  if (version !== 6) return true;
  const normalized = ip.toLowerCase().split('%')[0];
  if (normalized === '::' || normalized === '::1' || normalized.startsWith('fc') || normalized.startsWith('fd') || normalized.startsWith('ff')) return true;
  if (/^fe[89ab]/.test(normalized) || normalized.startsWith('2001:db8:')) return true;
  const mapped = normalized.match(/::ffff:(\d+\.\d+\.\d+\.\d+)$/);
  return mapped ? isForbiddenIpv4(mapped[1]) : false;
}

async function assertPublicHost(hostname) {
  const records = await dns.promises.lookup(hostname, { all: true });
  if (!records.length || records.some((record) => isForbiddenIp(record.address))) {
    throw new Error('Gitee 更新主机解析到非公网地址');
  }
}

function assertGiteeUrl(value) {
  let parsed;
  try { parsed = new URL(value); } catch { throw new Error('Gitee 更新地址无效'); }
  if (parsed.protocol !== 'https:' || parsed.hostname.toLowerCase() !== 'gitee.com' || (parsed.port && parsed.port !== '443')) {
    throw new Error('在线更新仅允许国内 Gitee HTTPS 地址');
  }
  if (parsed.username || parsed.password) throw new Error('Gitee 更新地址不允许携带凭据');
  return parsed;
}

async function readBoundedText(response) {
  const reader = response.body?.getReader?.();
  if (!reader) {
    const text = await response.text();
    if (Buffer.byteLength(text) > MAX_RESPONSE_BYTES) throw new Error('Gitee 更新响应过大');
    return text;
  }
  const chunks = [];
  let total = 0;
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    total += value.byteLength;
    if (total > MAX_RESPONSE_BYTES) {
      try { await reader.cancel(); } catch {}
      throw new Error('Gitee 更新响应过大');
    }
    chunks.push(Buffer.from(value));
  }
  return Buffer.concat(chunks).toString('utf8');
}

async function fetchBody(rawUrl, accept) {
  const parsed = assertGiteeUrl(rawUrl);
  await assertPublicHost(parsed.hostname);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    const response = await fetch(parsed.href, {
      method: 'GET',
      redirect: 'manual',
      signal: controller.signal,
      headers: { Accept: accept, 'User-Agent': REQUEST_UA },
    });
    if (response.status >= 300 && response.status < 400) throw new Error(`Gitee 更新地址不允许重定向 (HTTP ${response.status})`);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return await readBoundedText(response);
  } finally {
    clearTimeout(timer);
  }
}

async function fetchGiteeLatestTag() {
  try {
    const data = JSON.parse(await fetchBody(GITEE_LATEST_API, 'application/json'));
    const tag = typeof data?.tag_name === 'string' ? data.tag_name.trim() : '';
    if (/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/i.test(tag)) return tag;
  } catch (error) {
    console.log('[update] Gitee API 不可用，改读公开 Release 列表:', String(error?.message || error));
  }
  const html = await fetchBody(GITEE_RELEASES_URL, 'text/html');
  const match = html.match(/class=["'][^"']*releases-sidebar-item[^"']*["'][^>]*data-tag=["']([^"']+)["']/i);
  const tag = match ? String(match[1]).trim() : '';
  if (!/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/i.test(tag)) throw new Error('Gitee Release 列表缺少有效版本号');
  return tag;
}

function giteeFeedUrl(tag) {
  if (!/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/i.test(String(tag))) throw new Error('Gitee 更新版本号无效');
  return `https://gitee.com/${GITEE_OWNER}/${GITEE_REPO}/releases/download/${encodeURIComponent(tag)}/`;
}

module.exports = { fetchGiteeLatestTag, giteeFeedUrl, GITEE_OWNER, GITEE_REPO };
