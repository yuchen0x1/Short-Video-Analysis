import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { createReadStream } from 'node:fs';
import { readFile, stat } from 'node:fs/promises';
import { join } from 'node:path';

const [directory, tag, releaseJson] = process.argv.slice(2);
assert(directory && /^v\d+\.\d+\.\d+$/.test(tag), 'Usage: verify-release-artifacts.mjs <dir> <tag> [release-json]');
const version = tag.slice(1);
const repository = process.env.GITHUB_REPOSITORY || 'yuchen0x1/Short-Video-Analysis';
const installer = `qushuiyin-desktop-client-setup-${version}.exe`;
const required = [
  'latest.yml', installer, `${installer}.blockmap`,
  `qushuiyin-desktop-client-portable-${version}.exe`, `short-video-analysis-web-${version}.zip`,
].sort();
const hash = async (filename, algorithm, encoding = 'hex') => {
  const digest = createHash(algorithm);
  for await (const chunk of createReadStream(join(directory, filename))) digest.update(chunk);
  return digest.digest(encoding);
};
const read = (file) => readFile(join(directory, file), 'utf8');

const manifest = JSON.parse(await read('update-manifest.json'));
assert.equal(manifest.tag, tag);
assert.equal(manifest.version, version);
assert.equal(manifest.channel, 'stable');
assert.deepEqual(manifest.assets.map(asset => asset.name).sort(), required);
const sums = (await read('SHA256SUMS.txt')).trim().split(/\r?\n/).sort();
assert.deepEqual(sums, manifest.assets.map(asset => `${asset.sha256}  ${asset.name}`).sort());

for (const { name, size, sha256, download_url: downloadUrl } of manifest.assets) {
  assert.equal((await stat(join(directory, name))).size, size, name);
  assert.equal(await hash(name, 'sha256'), sha256, name);
  assert.equal(downloadUrl, `https://github.com/${repository}/releases/download/${tag}/${name}`);
}

const latest = (await read('latest.yml')).split(/\r?\n/);
const installerSize = manifest.assets.find(asset => asset.name === installer).size;
const sha512 = await hash(installer, 'sha512', 'base64');
for (const line of [
  `version: ${version}`, `path: ${installer}`, `sha512: ${sha512}`,
  `  - url: ${installer}`, `    sha512: ${sha512}`, `    size: ${installerSize}`,
]) assert(latest.includes(line), `latest.yml is missing metadata for ${installer}`);

if (releaseJson) {
  const release = JSON.parse(await readFile(releaseJson, 'utf8'));
  assert.equal(release.tag_name, tag);
  assert.equal(release.prerelease, false);
  const publishedFiles = [...required, 'SHA256SUMS.txt', 'update-manifest.json'].sort();
  assert.deepEqual(release.assets.map(asset => asset.name).sort(), publishedFiles);
  for (const asset of release.assets) {
    assert.equal(asset.state, 'uploaded', asset.name);
    assert.equal(asset.size, (await stat(join(directory, asset.name))).size, asset.name);
    assert.equal(asset.digest, 'sha256:' + await hash(asset.name, 'sha256'), asset.name);
  }
}
console.log(`Verified ${tag} with ${required.length} distribution files${releaseJson ? ' and matching GitHub digests' : ''}.`);
