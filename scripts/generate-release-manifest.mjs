import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const releaseDir = path.resolve(process.argv[2] || path.join(root, 'apps', 'app-shell', 'release'));
const tag = String(process.env.RELEASE_TAG || process.argv[3] || '').trim();
const version = tag.replace(/^v/i, '');
const owner = process.env.GITHUB_OWNER || 'yuchen0x1';
const repo = process.env.GITHUB_REPO || 'Short-Video-Analysis';
const semver = /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/;

if (!fs.existsSync(releaseDir)) throw new Error(`发布目录不存在：${releaseDir}`);
if (!semver.test(version)) throw new Error(`无效的发布版本：${tag || '(空)'}`);

const excluded = new Set(['SHA256SUMS.txt', 'update-manifest.json', 'builder-debug.yml']);
const files = fs.readdirSync(releaseDir, { withFileTypes: true })
  .filter((entry) => entry.isFile() && !excluded.has(entry.name))
  .map((entry) => entry.name)
  .filter((name) => /\.(?:exe|yml|blockmap)$/i.test(name))
  .filter((name) => /\.yml$/i.test(name) ? /^(?:latest|alpha|beta)\.yml$/i.test(name) : name.includes(version))
  .sort((a, b) => a.localeCompare(b));

if (!files.some((name) => /^latest\.yml$/i.test(name))) throw new Error('发布目录缺少 latest.yml');
if (!files.some((name) => /setup.*\.exe$/i.test(name))) throw new Error('发布目录缺少 Windows 安装包');

const assets = files.map((name) => {
  const filePath = path.join(releaseDir, name);
  const buffer = fs.readFileSync(filePath);
  return {
    name,
    size: buffer.length,
    sha256: crypto.createHash('sha256').update(buffer).digest('hex'),
    download_url: `https://github.com/${owner}/${repo}/releases/download/${encodeURIComponent(tag)}/${encodeURIComponent(name)}`,
  };
});

const sums = assets.map((asset) => `${asset.sha256}  ${asset.name}`).join('\n') + '\n';
fs.writeFileSync(path.join(releaseDir, 'SHA256SUMS.txt'), sums, 'utf8');
fs.writeFileSync(path.join(releaseDir, 'update-manifest.json'), JSON.stringify({
  version,
  tag,
  channel: 'stable',
  releaseDate: new Date().toISOString(),
  assets,
}, null, 2) + '\n', 'utf8');

console.log(`release manifest written: ${assets.length} assets`);
