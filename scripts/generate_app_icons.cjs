const fs = require('node:fs');
const path = require('node:path');
const { createRequire } = require('node:module');

const repoRoot = path.resolve(__dirname, '..');
const desktopRoot = path.join(repoRoot, 'apps', 'app-shell');
const mobileRoot = path.join(repoRoot, 'apps', 'mobile');
const brandRoot = path.join(repoRoot, 'assets', 'brand');
const args = new Set(process.argv.slice(2));
const generateDesktop = args.size === 0 || args.has('--desktop');
const generateMobile = args.size === 0 || args.has('--mobile');
const dependencyRoot = generateDesktop ? desktopRoot : mobileRoot;
const projectRequire = createRequire(path.join(dependencyRoot, 'package.json'));
const sharp = projectRequire('sharp');

// 位图主图（app-icon.png）存在时优先使用，否则回退到 SVG 矢量主图。
const pngMaster = path.join(brandRoot, 'app-icon.png');
const svgMaster = path.join(brandRoot, 'app-icon.svg');
const hasPngMaster = fs.existsSync(pngMaster);
const master = hasPngMaster ? pngMaster : svgMaster;
const adaptiveBackground = hasPngMaster ? '#ffffff' : '#071114';

async function masterMetadata() {
  return sharp(master).metadata();
}

async function render(source, destination, size = 1024) {
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  await sharp(source).resize(size, size).png().toFile(destination);
}

// 圆形蒙版：内容按整圆裁切（旧式圆角启动器使用）
async function renderRound(source, destination, size) {
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  const mask = Buffer.from(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}"><circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="#fff"/></svg>`,
  );
  const content = await sharp(source).resize(size, size).png().toBuffer();
  await sharp(content).composite([{ input: mask, blend: 'dest-in' }]).png().toFile(destination);
}

// 自适应图标前景：内容缩到安全区（约 62%）置于中央，白色铺满 108dp 网格
async function renderAdaptiveForeground(source, destination, size) {
  fs.mkdirSync(path.dirname(destination), { recursive: true });
  const inner = Math.round(size * 0.62);
  const content = await sharp(source).resize(inner, inner).png().toBuffer();
  const offset = Math.round((size - inner) / 2);
  await sharp({ create: { width: size, height: size, channels: 4, background: { r: 255, g: 255, b: 255, alpha: 1 } } })
    .composite([{ input: content, left: offset, top: offset }])
    .png()
    .toFile(destination);
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

async function buildMobileIcons() {
  const round = path.join(brandRoot, 'app-icon-round.svg');
  const foreground = path.join(brandRoot, 'app-icon-foreground.svg');
  const background = path.join(brandRoot, 'app-icon-background.svg');
  const assetsDir = path.join(mobileRoot, 'assets');
  const androidRes = path.join(mobileRoot, 'android', 'app', 'src', 'main', 'res');
  const natural = await masterMetadata();
  const masterSize = Math.min(1024, natural.width || 1024);

  await render(master, path.join(assetsDir, 'icon-only.png'), masterSize);
  if (hasPngMaster) {
    await renderAdaptiveForeground(master, path.join(assetsDir, 'icon-foreground.png'), masterSize);
    await render({ create: { width: masterSize, height: masterSize, channels: 4, background: { r: 255, g: 255, b: 255, alpha: 1 } } }, path.join(assetsDir, 'icon-background.png'), masterSize);
  } else {
    await render(foreground, path.join(assetsDir, 'icon-foreground.png'));
    await render(background, path.join(assetsDir, 'icon-background.png'));
  }
  await render(master, path.join(mobileRoot, 'public', 'app-icon.png'), 256);

  if (!fs.existsSync(androidRes)) return;

  const densities = {
    mdpi: [48, 108],
    hdpi: [72, 162],
    xhdpi: [96, 216],
    xxhdpi: [144, 324],
    xxxhdpi: [192, 432],
  };

  for (const [density, [legacySize, foregroundSize]] of Object.entries(densities)) {
    const mipmapDir = path.join(androidRes, `mipmap-${density}`);
    await render(master, path.join(mipmapDir, 'ic_launcher.png'), legacySize);
    if (hasPngMaster) {
      await renderRound(master, path.join(mipmapDir, 'ic_launcher_round.png'), legacySize);
      await renderAdaptiveForeground(master, path.join(mipmapDir, 'ic_launcher_foreground.png'), foregroundSize);
    } else {
      await render(round, path.join(mipmapDir, 'ic_launcher_round.png'), legacySize);
      await render(foreground, path.join(mipmapDir, 'ic_launcher_foreground.png'), foregroundSize);
    }
  }

  fs.writeFileSync(
    path.join(androidRes, 'values', 'ic_launcher_background.xml'),
    `<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <color name="ic_launcher_background">${adaptiveBackground}</color>\n</resources>\n`,
  );
}

async function main() {
  if (generateDesktop) await buildDesktopIcons();
  if (generateMobile) await buildMobileIcons();
  console.log('App icons generated.');
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
