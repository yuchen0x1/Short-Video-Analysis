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
  const master = path.join(brandRoot, 'app-icon.svg');
  const icoInputs = [];

  fs.mkdirSync(tempDir, { recursive: true });
  await render(master, path.join(buildDir, 'icon.png'));
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
  const master = path.join(brandRoot, 'app-icon.svg');
  const round = path.join(brandRoot, 'app-icon-round.svg');
  const foreground = path.join(brandRoot, 'app-icon-foreground.svg');
  const background = path.join(brandRoot, 'app-icon-background.svg');
  const assetsDir = path.join(mobileRoot, 'assets');
  const androidRes = path.join(mobileRoot, 'android', 'app', 'src', 'main', 'res');

  await render(master, path.join(assetsDir, 'icon-only.png'));
  await render(foreground, path.join(assetsDir, 'icon-foreground.png'));
  await render(background, path.join(assetsDir, 'icon-background.png'));
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
    await render(round, path.join(mipmapDir, 'ic_launcher_round.png'), legacySize);
    await render(foreground, path.join(mipmapDir, 'ic_launcher_foreground.png'), foregroundSize);
  }

  fs.writeFileSync(
    path.join(androidRes, 'values', 'ic_launcher_background.xml'),
    '<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <color name="ic_launcher_background">#071114</color>\n</resources>\n',
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
