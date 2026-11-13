import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const tag = String(process.env.RELEASE_TAG || process.argv[2] || '').trim();
const version = tag.replace(/^v/i, '');
const semver = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/;

if (!semver.test(version)) {
  throw new Error(`发布版本必须是 vMAJOR.MINOR.PATCH，收到：${tag || '(空)'}`);
}
if (version.includes('-')) {
  throw new Error(`稳定发布标签不能包含预发布后缀：${tag}`);
}

const readJson = (file) => JSON.parse(fs.readFileSync(path.join(root, file), 'utf8'));
const sources = [
  ['composer.json', readJson('composer.json').version],
  ['apps/app-shell/package.json', readJson('apps/app-shell/package.json').version],
  ['apps/mobile/package.json', readJson('apps/mobile/package.json').version],
  ['packages/parsers/package.json', readJson('packages/parsers/package.json').version],
  ['packages/update-checker/package.json', readJson('packages/update-checker/package.json').version],
];
for (const client of ['app-shell', 'mobile']) {
  const file = `apps/${client}/package-lock.json`;
  const lock = readJson(file);
  sources.push([file, lock.version], [`${file} packages[""]`, lock.packages?.['']?.version]);
}
const api = fs.readFileSync(path.join(root, 'public/openapi.yaml'), 'utf8');
sources.push(['public/openapi.yaml', api.match(/^  version:\s*(\S+)/m)?.[1] || '']);
const gradle = fs.readFileSync(path.join(root, 'apps/mobile/android/app/build.gradle'), 'utf8');
const androidVersion = gradle.match(/versionName\s+"([^"]+)"/)?.[1] || '';
sources.push(['apps/mobile/android/app/build.gradle', androidVersion]);

const mismatches = sources.filter(([, value]) => String(value) !== version);
if (mismatches.length) {
  const detail = mismatches.map(([file, value]) => `${file}=${value || '(缺失)'}`).join(', ');
  throw new Error(`版本号与 ${tag} 不一致：${detail}`);
}

console.log(`release version OK: ${tag}`);
