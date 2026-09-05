import assert from 'node:assert/strict';
import { checkForUpdate, compareVersions, normalizeVersion } from '../src/index.js';

assert.equal(normalizeVersion('v2.3'), '2.3.0');
assert.equal(normalizeVersion('v2.3.2-invalid tag'), '');
assert.equal(compareVersions('2.4.0', '2.3.1'), 1);
assert.equal(compareVersions('2.3.1', '2.3.1'), 0);
assert.equal(compareVersions('2.3.1-beta.1', '2.3.1'), -1);

const apiEnvelope = await checkForUpdate({
  currentVersion: '2.3.1',
  repository: {
    owner: 'yuchen0x1', repo: 'short-video-watermark-remover', provider: 'api',
    apiUrl: 'https://api.gitee.com/yuchen0x1/short-video-watermark-remover/update', releasesUrl: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases',
  },
  request: async () => ({ status: 200, data: JSON.stringify({ success: true, data: {
    current_version: '2.3.1', latest_version: '2.4.0', update_available: true,
    release_url: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases/tag/v2.4.0',
    assets: [{ name: 'qushuiyin-mobile-android-2.4.0-release-unsigned.apk', url: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases/download/v2.4.0/update.apk' }],
  } }) }),
});
assert.equal(apiEnvelope.updateAvailable, true);
assert.equal(apiEnvelope.androidAsset.name, 'qushuiyin-mobile-android-2.4.0-release-unsigned.apk');

const giteeHtml = `
  <a class="releases-sidebar-item" data-tag="v2.4.0" href="#release-v2.4.0"></a>
  <a href="/yuchen0x1/short-video-watermark-remover/releases/download/v2.4.0/latest.yml">latest.yml</a>
  <a href="/yuchen0x1/short-video-watermark-remover/releases/download/v2.4.0/qushuiyin-mobile-android-2.4.0.apk">apk</a>
`;
const giteeResult = await checkForUpdate({
  currentVersion: '2.3.2',
  repository: {
    owner: 'yuchen0x1',
    repo: 'short-video-watermark-remover',
    provider: 'gitee',
    apiUrl: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases',
    releasesUrl: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases',
  },
  request: async () => ({ status: 200, data: giteeHtml }),
});
assert.equal(giteeResult.latestVersion, '2.4.0');
assert.equal(giteeResult.androidAsset.name, 'qushuiyin-mobile-android-2.4.0.apk');

let requestOptions = null;
await checkForUpdate({
  currentVersion: '2.3.1',
  request: async (options) => {
    requestOptions = options;
    return { status: 200, data: giteeHtml };
  },
});
assert.equal(requestOptions.timeoutMs, 12000);
assert.equal(requestOptions.url, 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases');

let giteeRequestOptions = null;
await checkForUpdate({
  currentVersion: '2.3.2',
  repository: {
    owner: 'yuchen0x1',
    repo: 'short-video-watermark-remover',
    provider: 'gitee',
    apiUrl: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases',
    releasesUrl: 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases',
  },
  request: async (options) => {
    giteeRequestOptions = options;
    return { status: 200, data: giteeHtml };
  },
});
assert.equal(giteeRequestOptions.headers.Accept, 'text/html');

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '2.3.2',
    repository: { owner: 'x', repo: 'y', provider: 'api', apiUrl: 'https://updates.example.cn/api/v1/update', releasesUrl: 'https://gitee.com/x/y/releases' },
    request: async () => ({ status: 200, data: '{}' }),
  }),
  /更新源必须使用 Gitee 域名/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '2.3.2',
    repository: { owner: 'x', repo: 'y', provider: 'api', apiUrl: 'https://gitee.com.attacker.example/api/v1/update', releasesUrl: 'https://gitee.com/x/y/releases' },
    request: async () => ({ status: 200, data: '{}' }),
  }),
  /更新源必须使用 Gitee 域名/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '2.3.2',
    repository: { owner: 'x', repo: 'y', provider: 'api', apiUrl: 'https://user:pass@gitee.com/api/v1/update', releasesUrl: 'https://gitee.com/x/y/releases' },
    request: async () => ({ status: 200, data: '{}' }),
  }),
  /更新源地址不允许携带用户凭据/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '2.3.2',
    repository: { owner: 'x', repo: 'y', provider: 'api', apiUrl: 'ftp://gitee.com/api/v1/update', releasesUrl: 'https://gitee.com/x/y/releases' },
    request: async () => ({ status: 200, data: '{}' }),
  }),
  /更新源仅支持 HTTP\/HTTPS/
);

await assert.rejects(
  () => checkForUpdate({
    currentVersion: '2.3.2',
    repository: { owner: 'x', repo: 'y', provider: 'api', apiUrl: 'https://api.github.com/repos/x/y/releases/latest', releasesUrl: 'https://github.com/x/y/releases' },
    request: async () => ({ status: 200, data: '{}' }),
  }),
  /更新源必须使用 Gitee 域名/
);

console.log('update-checker contract OK');
