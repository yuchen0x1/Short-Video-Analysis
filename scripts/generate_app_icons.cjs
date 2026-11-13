const fs = require('node:fs');
const path = require('node:path');
const { createRequire } = require('node:module');

const repoRoot = path.resolve(__dirname, '..');
const desktopRoot = path.join(repoRoot, 'apps', 'app-shell');
const brandRoot = path.join(repoRoot, 'assets', 'brand');
if (process.argv.slice(2).some(arg => arg !== '--desktop')) throw new Error('本项目只生成电脑版图标');
const projectRequire = createRequire(path.join(desktopRoot, 'package.json'));
const sharp = projectRequire('sharp');

// 位图主图（app-icon.png）存在时优先使用，否则回退到 SVG 矢量主图。
const pngMaster = path.join(brandRoot, 'app-icon.png');
const svgMaster = path.join(brandRoot, 'app-icon.svg');
const hasPngMaster = fs.existsSync(pngMaster);
const master = hasPngMaster ? pngMaster : svgMaster;

async function masterMetadata() {
  return sharp(master).metadata();
}

async function render(source, destination, size = 1024) {
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  await sharp(source).resize(size, size).png().toFile(destination);
}

async function buildDesktopIcons() {
  const desktopRequire = createRequire(path.join(desktopRoot, 'package.json'));
  const pngToIcoModule = desktopRequire('png-to-ico');
  const pngToIco = pngToIcoModule.default || pngToIcoModule;
  const buildDir = path.join(desktopRoot, 'build');
  const tempDir = path.join(buildDir, '.icon-sizes');
  const icoInputs = [];
  const natural = await masterMetadata();
  const masterSize = Math.min(1024, natural.width || 1024);

  fs.mkdirSync(tempDir, { recursive: true });
  await render(master, path.join(buildDir, 'icon.png'), masterSize);
  await render(master, path.join(desktopRoot, 'public', 'app-icon.png'), 256);

  for (const size of [16, 24, 32, 48, 64, 128, 256]) {
    const target = path.join(tempDir, `icon-${size}.png`);
    await render(master, target, size);
    icoInputs.push(target);
  }

  fs.writeFileSync(path.join(buildDir, 'icon.ico'), await pngToIco(icoInputs));
  fs.rmSync(tempDir, { recursive: true, force: true });
}

async function main() {
  await buildDesktopIcons();
  console.log('App icons generated.');
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
