const DEFAULT_OWNER = 'yuchen0x1';
const DEFAULT_REPO = 'Short-Video-Analysis';

const GITHUB_HOSTS = ['github.com', 'api.github.com', 'objects.githubusercontent.com'];

export const UPDATE_REPOSITORY = Object.freeze({
  owner: DEFAULT_OWNER,
  repo: DEFAULT_REPO,
  provider: 'github',
  apiUrl: `https://api.github.com/repos/${DEFAULT_OWNER}/${DEFAULT_REPO}/releases/latest`,
  releasesUrl: `https://github.com/${DEFAULT_OWNER}/${DEFAULT_REPO}/releases`,
});

function parseVersion(value) {
  const match = String(value ?? '').trim().replace(/^v/i, '').match(/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([0-9A-Za-z.-]+))?$/);
  if (!match) return null;
  return {
    major: Number(match[1]),
    minor: Number(match[2] || 0),
    patch: Number(match[3] || 0),
    prerelease: match[4] ? match[4].split('.') : [],
  };
}

export function normalizeVersion(value) {
  const parsed = parseVersion(value);
  return parsed ? `${parsed.major}.${parsed.minor}.${parsed.patch}${parsed.prerelease.length ? `-${parsed.prerelease.join('.')}` : ''}` : '';
}

export function compareVersions(left, right) {
  const a = parseVersion(left);
  const b = parseVersion(right);
  if (!a || !b) return 0;
  for (const key of ['major', 'minor', 'patch']) {
    if (a[key] !== b[key]) return a[key] > b[key] ? 1 : -1;
  }
  if (!a.prerelease.length && b.prerelease.length) return 1;
  if (a.prerelease.length && !b.prerelease.length) return -1;
  const length = Math.max(a.prerelease.length, b.prerelease.length);
  for (let index = 0; index < length; index += 1) {
    const av = a.prerelease[index];
    const bv = b.prerelease[index];
    if (av === bv) continue;
    if (av === undefined) return -1;
    if (bv === undefined) return 1;
    const an = /^\d+$/.test(av);
    const bn = /^\d+$/.test(bv);
    if (an && bn) return Number(av) > Number(bv) ? 1 : -1;
    if (an !== bn) return an ? -1 : 1;
    return av > bv ? 1 : -1;
  }
  return 0;
}

async function browserRequest({ url, headers, timeoutMs = 12000 }) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), Math.max(1000, Math.min(Number(timeoutMs) || 12000, 30000)));
  try {
    const response = await fetch(url, { headers, cache: 'no-store', signal: controller.signal });
    return { status: response.status, data: await response.text() };
  } catch (error) {
    if (error?.name === 'AbortError') throw new Error('版本检查超时');
    throw error;
  } finally {
    clearTimeout(timer);
  }
}

function parseResponseData(data) {
  if (data && typeof data === 'object') return data;
  try {
    return JSON.parse(String(data || ''));
  } catch {
    throw new Error('版本服务返回了无效数据');
  }
}

function decodeXmlEntities(value) {
  const entities = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'" };
  return String(value || '').replace(/&(amp|lt|gt|quot|apos);/g, (_, name) => entities[name]);
}

function parseGithubAtomRelease(data, owner, repo) {
  const xml = String(data || '');
  const entryMatch = xml.match(/<entry\b[^>]*>([\s\S]*?)<\/entry>/i);
  if (!entryMatch) return null;
  const entry = entryMatch[1];
  const title = decodeXmlEntities(entry.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i)?.[1] || '').trim();
  const tag = title.match(/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?/i)?.[0] || '';
  if (!tag) return null;
  const updated = entry.match(/<updated\b[^>]*>([\s\S]*?)<\/updated>/i)?.[1]?.trim() || '';
  const content = decodeXmlEntities(entry.match(/<content\b[^>]*>([\s\S]*?)<\/content>/i)?.[1] || '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return {
    tag_name: tag,
    html_url: `https://github.com/${owner}/${repo}/releases/tag/${encodeURIComponent(tag)}`,
    published_at: updated,
    body: content,
  };
}

function parseGithubExpandedAssets(data, owner, repo, tag) {
  const html = String(data || '');
  const assets = [];
  const seen = new Set();
  const linkPattern = /<a\b[^>]*href="([^"]*\/releases\/download\/[^"?#]+)"[^>]*>([\s\S]*?)<\/a>/gi;
  for (const match of html.matchAll(linkPattern)) {
    let url = match[1].replace(/&/g, String.fromCharCode(38));
    if (url.startsWith('/')) url = `https://github.com${url}`;
    const name = decodeXmlEntities(match[2].replace(/<[^>]+>/g, '')).trim();
    if (!name || seen.has(url)) continue;
    try {
      const parsed = new URL(assertUpdateUrl(url, { githubOnly: true }));
      if (parsed.hostname !== 'github.com') continue;
      const expectedPrefix = `/${owner}/${repo}/releases/download/${encodeURIComponent(tag)}/`;
      if (!parsed.pathname.startsWith(expectedPrefix)) continue;
    } catch {
      continue;
    }
    seen.add(url);
    assets.push({ name, browser_download_url: url, size: 0, digest: '' });
  }
  return assets;
}

function isAllowedUpdateHost(host) {
  const normalized = String(host || '').toLowerCase().replace(/\.$/, '');
  return GITHUB_HOSTS.some((domain) => normalized === domain || normalized.endsWith('.' + domain));
}

// provider 为 github 时锁定 GitHub 域名；自定义站点 API（VITE_UPDATE_API_URL）只要求 HTTPS 与无凭据。
function assertUpdateUrl(value, { githubOnly = true } = {}) {
  let parsed;
  try {
    parsed = new URL(String(value || ''));
  } catch {
    throw new Error('更新源地址无效');
  }
  if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error('更新源仅支持 HTTP/HTTPS');
  if (parsed.username || parsed.password) throw new Error('更新源地址不允许携带用户凭据');
  if (githubOnly && !isAllowedUpdateHost(parsed.hostname)) throw new Error('更新源仅允许 GitHub 域名');
  return parsed.href;
}

function normalizeAssets(rawAssets, { githubOnly = true } = {}) {
  return Array.isArray(rawAssets) ? rawAssets.map((asset) => ({
    name: String(asset.name || ''),
    url: String(asset.browser_download_url || asset.url || ''),
    size: Number(asset.size || 0),
    digest: String(asset.digest || ''),
  })).filter((asset) => {
    if (!asset.name || !asset.url) return false;
    try { assertUpdateUrl(asset.url, { githubOnly }); return true; } catch { return false; }
  }) : [];
}

function preferredAndroidAsset(assets) {
  const apkAssets = assets.filter((asset) => /\.apk$/i.test(asset.name || ''));
  return apkAssets.find((asset) => !/(debug|unsigned)/i.test(asset.name))
    || apkAssets.find((asset) => /debug/i.test(asset.name))
    || apkAssets.find((asset) => !/unsigned/i.test(asset.name))
    || apkAssets[0]
    || null;
}

function preferredWindowsAsset(assets) {
  return assets.find((asset) => /\.exe$/i.test(asset.name || '') && /setup/i.test(asset.name))
    || assets.find((asset) => /\.exe$/i.test(asset.name || ''))
    || null;
}

async function fetchGithubReleaseFallback({ request, owner, repo }) {
  const atomUrl = assertUpdateUrl(`https://github.com/${owner}/${repo}/releases.atom`, { githubOnly: true });
  const atomResponse = await request({
    url: atomUrl,
    timeoutMs: 12000,
    headers: {
      Accept: 'application/atom+xml',
      'User-Agent': 'qushuiyin-update-checker/2.3.2',
    },
  });
  if (!atomResponse || atomResponse.status < 200 || atomResponse.status >= 300) {
    throw new Error(`无法获取最新版本（HTTP ${atomResponse?.status || 0}）`);
  }
  const release = parseGithubAtomRelease(atomResponse.data, owner, repo);
  if (!release) throw new Error('最新 Release 缺少有效版本号');

  const assetsUrl = assertUpdateUrl(`https://github.com/${owner}/${repo}/releases/expanded_assets/${encodeURIComponent(release.tag_name)}`, { githubOnly: true });
  const assetsResponse = await request({
    url: assetsUrl,
    timeoutMs: 12000,
    headers: {
      Accept: 'text/html',
      'User-Agent': 'qushuiyin-update-checker/2.3.2',
    },
  });
  release.assets = assetsResponse && assetsResponse.status >= 200 && assetsResponse.status < 300
    ? parseGithubExpandedAssets(assetsResponse.data, owner, repo, release.tag_name)
    : [];
  return release;
}

export async function checkForUpdate({ currentVersion, request = browserRequest, repository = UPDATE_REPOSITORY } = {}) {
  const current = normalizeVersion(currentVersion);
  if (!current) throw new Error('当前版本号无效');
  const githubOnly = repository.provider === 'github';
  const apiUrl = assertUpdateUrl(repository.apiUrl, { githubOnly });
  const releasesUrl = assertUpdateUrl(repository.releasesUrl, { githubOnly });
  repository = { ...repository, apiUrl, releasesUrl };
  let payload;
  try {
    const response = await request({
      url: apiUrl,
      timeoutMs: 12000,
      headers: {
        Accept: githubOnly ? 'application/vnd.github+json' : 'application/json',
        'User-Agent': 'qushuiyin-update-checker/2.3.2',
      },
    });
    if (!response || response.status < 200 || response.status >= 300) {
      throw new Error(`无法获取最新版本（HTTP ${response?.status || 0}）`);
    }
    payload = parseResponseData(response.data);
  } catch (error) {
    if (!githubOnly) throw error;
    payload = await fetchGithubReleaseFallback({ request, owner: repository.owner, repo: repository.repo });
  }
  // 网站 API 返回已整理的数据；允许移动端通过 VITE_UPDATE_API_URL 复用同一接口。
  if (payload.success && payload.data && payload.data.latest_version) {
    const data = payload.data;
    const latest = normalizeVersion(data.latest_version);
    if (!latest) throw new Error('最新版本号无效');
    const assets = normalizeAssets(data.assets, { githubOnly });
    return {
      currentVersion: current,
      latestVersion: latest,
      updateAvailable: compareVersions(latest, current) > 0,
      releaseUrl: assertUpdateUrl(data.release_url || repository.releasesUrl, { githubOnly }),
      publishedAt: data.published_at || '',
      notes: String(data.notes || ''),
      assets,
      androidAsset: data.android_asset ? normalizeAssets([data.android_asset], { githubOnly })[0] || preferredAndroidAsset(assets) : preferredAndroidAsset(assets),
      windowsAsset: data.windows_asset ? normalizeAssets([data.windows_asset], { githubOnly })[0] || preferredWindowsAsset(assets) : preferredWindowsAsset(assets),
    };
  }
  if (!payload.tag_name) throw new Error('更新服务返回了不支持的数据');
  const latest = normalizeVersion(payload.tag_name);
  if (!latest) throw new Error('最新 Release 缺少有效版本号');
  const assets = normalizeAssets(payload.assets, { githubOnly });
  return {
    currentVersion: current,
    latestVersion: latest,
    updateAvailable: compareVersions(latest, current) > 0,
    releaseUrl: assertUpdateUrl(payload.html_url || `${repository.releasesUrl}/tag/${payload.tag_name}`, { githubOnly }),
    publishedAt: payload.published_at || '',
    notes: String(payload.body || ''),
    assets,
    androidAsset: preferredAndroidAsset(assets),
    windowsAsset: preferredWindowsAsset(assets),
  };
}
