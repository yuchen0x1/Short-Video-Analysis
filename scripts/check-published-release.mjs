import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';

const [jsonPath, metadataDir] = process.argv.slice(2);
const release = JSON.parse(await readFile(jsonPath, 'utf8'));
assert(!release.draft && !release.prerelease && /^v\d+\.\d+\.\d+$/.test(release.tag_name));
const version = release.tag_name.slice(1);
const setup = `qushuiyin-desktop-client-setup-${version}.exe`;
const portable = `qushuiyin-desktop-client-portable-${version}.exe`;
const zip = `short-video-analysis-web-${version}.zip`;
const assets = new Map(release.assets.map(asset => [asset.name, asset]));
for (const name of ['latest.yml', 'update-manifest.json', 'SHA256SUMS.txt']) {
  const data = await readFile(join(metadataDir, name));
  assert.equal(assets.get(name)?.digest, 'sha256:' + createHash('sha256').update(data).digest('hex'), name);
}
const manifest = JSON.parse(await readFile(join(metadataDir, 'update-manifest.json'), 'utf8'));
assert.equal(manifest.tag, release.tag_name);
assert.equal(manifest.version, version);
assert.deepEqual(manifest.assets.map(asset => asset.name).sort(), [setup, portable, zip, `${setup}.blockmap`, 'latest.yml'].sort());
const sums = (await readFile(join(metadataDir, 'SHA256SUMS.txt'), 'utf8')).trim().split(/\r?\n/).sort();
assert.deepEqual(sums, manifest.assets.map(asset => `${asset.sha256}  ${asset.name}`).sort());
for (const asset of manifest.assets) {
  const published = assets.get(asset.name);
  assert.equal(published.state, 'uploaded', asset.name);
  assert.equal(published.size, asset.size, asset.name);
  assert.equal(published.digest, 'sha256:' + asset.sha256, asset.name);
  assert.equal(published.browser_download_url, asset.download_url, asset.name);
}
const feed = (await readFile(join(metadataDir, 'latest.yml'), 'utf8')).split(/\r?\n/);
assert(feed.includes('version: ' + version));
assert(feed.includes('path: ' + setup));
assert(feed.includes('    size: ' + assets.get(setup).size));
assert(feed.some(line => /^sha512: [A-Za-z0-9+/]{86}==$/.test(line)));
console.log(`Published release and update metadata verified for v${version}`);
