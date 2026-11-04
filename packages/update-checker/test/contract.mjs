import assert from 'node:assert/strict';
import { checkForUpdate, compareVersions, normalizeVersion, UPDATE_REPOSITORY } from '../src/index.js';

assert.equal(normalizeVersion('v2.3'), '2.3.0');
assert.equal(normalizeVersion('v2.3.2-invalid tag'), '');
assert.equal(compareVersions('2.4.0', '2.3.1'), 1);
assert.equal(compareVersions('2.3.1', '2.3.1'), 0);
assert.equal(compareVersions('2.3.1-beta.1', '2.3.1'), -1);

// GitHub Release JSON：默认仓库直接可用
const githubPayload = JSON.stringify({
  tag_name: 'v1.1.3',
  html_url: 'https://github.com/yuchen0x1/Short-Video-Analysis/releases/tag/v1.1.3',
  published_at: '2026-11-04T16:00:00Z',
  body: '更新说明',
  assets: [
    { name: 'qushuiyin-mobile-client-1.1.3-testsigned.apk', browser_download_url: 'https://github.com/yuchen0x1/Short-Video-Analysis/releases/download/v1.1.3/qushuiyin-mobile-client-1.1.3-testsigned.apk', size: 4111366 },
    { name: 'qushuiyin-desktop-client-setup-1.1.3.exe', browser_download_url: 'https://github.com/yuchen0x1/Short-Video-Analysis/releases/download/v1.1.3/qushuiyin-desktop-client-setup-1.1.3.exe', size: 103581585 },
    { name: 'latest.yml', browser_download_url: 'https://github.com/yuchen0x1/Short-Video-Analysis/releases/download/v1.1.3/latest.yml', size: 377 },
  ],
});
let requestOptions = null;
const githubResult = await checkForUpdate({
  currentVersion: '1.1.2',
  request: async (options) => {
    requestOptions = options;
    return { status: 200, data: githubPayload };
  },
});
assert.equal(requestOptions.url, 'https://api.github.com/repos/yuchen0x1/Short-Video-Analysis/releases/latest');
assert.equal(requestOptions.headers.Accept, 'application/vnd.github+json');
assert.equal(requestOptions.timeoutMs, 12000);
assert.equal(githubResult.latestVersion, '1.1.3');
assert.equal(githubResult.updateAvailable, true);
assert.equal(githubResult.releaseUrl, 'https://github.com/yuchen0x1/Short-Video-Analysis/releases/tag/v1.1.3');
assert.equal(githubResult.androidAsset.name, 'qushuiyin-mobile-client-1.1.3-testsigned.apk');
assert.equal(githubResult.windowsAsset.name, 'qushuiyin-desktop-client-setup-1.1.3.exe');
assert.equal(githubResult.assets.length, 3);

// 默认仓库（GitHub）拒绝非 GitHub 域名
await assert.rejects(
  () => checkForUpdate({
    currentVersion: '1.1.2',
    repository: { ...UPDATE_REPOSITORY, apiUrl: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases' },
    request: async () => ({ status: 200, data: githubPayload }),
  }),
  /更新源仅允许 GitHub 域名/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '1.1.2',
    repository: { ...UPDATE_REPOSITORY, apiUrl: 'https://github.com.attacker.example/repos/x/y/releases/latest' },
    request: async () => ({ status: 200, data: githubPayload }),
  }),
  /更新源仅允许 GitHub 域名/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '1.1.2',
    repository: { ...UPDATE_REPOSITORY, apiUrl: 'https://user:pass@api.github.com/repos/x/y/releases/latest' },
    request: async () => ({ status: 200, data: githubPayload }),
  }),
  /更新源地址不允许携带用户凭据/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '1.1.2',
    repository: { ...UPDATE_REPOSITORY, apiUrl: 'ftp://api.github.com/repos/x/y/releases/latest' },
    request: async () => ({ status: 200, data: githubPayload }),
  }),
  /更新源仅支持 HTTP\/HTTPS/
);

// 自定义站点 API（VITE_UPDATE_API_URL 场景）：允许非 GitHub 域名，只要求 HTTPS 与无凭据
const siteEnvelope = JSON.stringify({ success: true, data: {
  current_version: '1.1.2', latest_version: '1.2.0', update_available: true,
  release_url: 'https://updates.example.cn/releases/1.2.0',
  assets: [{ name: 'update.apk', url: 'https://updates.example.cn/files/update.apk' }],
} });
const siteResult = await checkForUpdate({
  currentVersion: '1.1.2',
  repository: {
    owner: 'yuchen0x1', repo: 'Short-Video-Analysis', provider: 'api',
    apiUrl: 'https://updates.example.cn/api/v1/update',
    releasesUrl: 'https://updates.example.cn/releases',
  },
  request: async () => ({ status: 200, data: siteEnvelope }),
});
assert.equal(siteResult.latestVersion, '1.2.0');
assert.equal(siteResult.updateAvailable, true);
assert.equal(siteResult.androidAsset.name, 'update.apk');

// 自定义站点 API 的资产 URL 仍不允许携带凭据
await assert.rejects(
  () => checkForUpdate({
    currentVersion: '1.1.2',
    repository: {
      owner: 'yuchen0x1', repo: 'Short-Video-Analysis', provider: 'api',
      apiUrl: 'https://user:pass@updates.example.cn/api/v1/update',
      releasesUrl: 'https://updates.example.cn/releases',
    },
    request: async () => ({ status: 200, data: siteEnvelope }),
  }),
  /更新源地址不允许携带用户凭据/
);

// GitHub 数据缺 tag_name 时报不支持的数据
await assert.rejects(
  () => checkForUpdate({
    currentVersion: '1.1.2',
    request: async () => ({ status: 200, data: '{}' }),
  }),
  /更新服务返回了不支持的数据/
);

console.log('update-checker contract OK');
