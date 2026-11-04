// GitHub 在线更新源。禁止重定向、内网 DNS 结果和超大响应。
const dns = require('node:dns');
const nodeNet = require('node:net');

const GITHUB_OWNER = 'yuchen0x1';
const GITHUB_REPO = 'Short-Video-Analysis';
const GITHUB_LATEST_API = `https://api.github.com/repos/${GITHUB_OWNER}/${GITHUB_REPO}/releases/latest`;
const GITHUB_RELEASES_URL = `https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/releases/latest`;
const TIMEOUT_MS = 5000;
const MAX_RESPONSE_BYTES = 1024 * 1024;
const REQUEST_UA = 'qushuiyin-desktop-updater/2.3.2';
const VERSION_PATTERN = /^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/i;

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
    throw new Error('GitHub 更新主机解析到非公网地址');
  }
}

function assertGithubUrl(value) {
  let parsed;
  try { parsed = new URL(value); } catch { throw new Error('GitHub 更新地址无效'); }
  const host = parsed.hostname.toLowerCase();
  if (parsed.protocol !== 'https:' || (host !== 'api.github.com' && host !== 'github.com') || (parsed.port && parsed.port !== '443')) {
    throw new Error('在线更新仅允许 GitHub HTTPS 地址');
  }
  if (parsed.username || parsed.password) throw new Error('GitHub 更新地址不允许携带凭据');
  return parsed;
}

async function readBoundedText(response) {
  const reader = response.body?.getReader?.();
  if (!reader) {
    const text = await response.text();
    if (Buffer.byteLength(text) > MAX_RESPONSE_BYTES) throw new Error('GitHub 更新响应过大');
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
      throw new Error('GitHub 更新响应过大');
    }
    chunks.push(Buffer.from(value));
  }
  return Buffer.concat(chunks).toString('utf8');
}

async function fetchBody(rawUrl, accept) {
  const parsed = assertGithubUrl(rawUrl);
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
    if (response.status >= 300 && response.status < 400) throw new Error(`GitHub 更新地址不允许重定向 (HTTP ${response.status})`);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return await readBoundedText(response);
  } finally {
    clearTimeout(timer);
  }
}

async function fetchRedirectTag() {
  const parsed = assertGithubUrl(GITHUB_RELEASES_URL);
  await assertPublicHost(parsed.hostname);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    // /releases/latest 对正式版返回 302，Location 即 /releases/tag/vX.Y.Z
    const response = await fetch(parsed.href, {
      method: 'GET',
      redirect: 'manual',
      signal: controller.signal,
      headers: { Accept: 'text/html', 'User-Agent': REQUEST_UA },
    });
    if (response.status < 300 || response.status >= 400) throw new Error(`HTTP ${response.status}`);
    const location = response.headers.get('location') || '';
    const match = location.match(/\/releases\/tag\/(v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?)\/?$/i);
    if (!match) throw new Error('GitHub Release 跳转地址缺少版本号');
    return String(match[1]).trim();
  } finally {
    clearTimeout(timer);
  }
}

async function fetchGithubLatestTag() {
  try {
    const data = JSON.parse(await fetchBody(GITHUB_LATEST_API, 'application/vnd.github+json'));
    const tag = typeof data?.tag_name === 'string' ? data.tag_name.trim() : '';
    if (VERSION_PATTERN.test(tag)) return tag;
  } catch (error) {
    console.log('[update] GitHub API 不可用，改读 Release 跳转地址:', String(error?.message || error));
  }
  const tag = await fetchRedirectTag();
  if (!VERSION_PATTERN.test(tag)) throw new Error('GitHub Release 缺少有效版本号');
  return tag;
}

function githubFeedUrl(tag) {
  if (!VERSION_PATTERN.test(String(tag))) throw new Error('GitHub 更新版本号无效');
  return `https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/releases/download/${encodeURIComponent(tag)}/`;
}

module.exports = { fetchGithubLatestTag, githubFeedUrl, GITHUB_OWNER, GITHUB_REPO };
