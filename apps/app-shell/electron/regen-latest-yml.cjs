/**
 * 外部签名（如 SignPath）会改变安装包内容，导致 electron-builder 构建时
 * 生成的 latest.yml sha512/size 与 blockmap 失效。本脚本在签名完成后重算
 * latest.yml，并删除过期的 blockmap（更新器将回退为全量下载，校验不受影响）。
 *
 * 用法：node regen-latest-yml.cjs <目录（默认 ../release）>
 */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const dir = process.argv[2] ? path.resolve(process.argv[2]) : path.join(__dirname, '..', 'release');
const ymlPath = path.join(dir, 'latest.yml');

if (!fs.existsSync(ymlPath)) {
  console.error(`未找到 ${ymlPath}`);
  process.exit(1);
}

const lines = fs.readFileSync(ymlPath, 'utf8').split(/\r?\n/);
const versionLine = lines.find((l) => /^version:/.test(l));
const pathLine = lines.find((l) => /^path:/.test(l));
const exeName = pathLine ? pathLine.replace(/^path:\s*/, '').trim() : '';
const exeFile = path.join(dir, exeName);

if (!versionLine || !exeName || !fs.existsSync(exeFile)) {
  console.error(`latest.yml 引用的安装包不存在: ${exeFile}`);
  process.exit(1);
}

const buf = fs.readFileSync(exeFile);
const sha512 = crypto.createHash('sha512').update(buf).digest('base64');
const version = versionLine.replace(/^version:\s*/, '').trim();
const releaseDate = new Date().toISOString().replace(/\.\d+Z$/, '.000Z');

fs.writeFileSync(
  ymlPath,
  [
    `version: ${version}`,
    'files:',
    `  - url: ${exeName}`,
    `    sha512: ${sha512}`,
    `    size: ${buf.length}`,
    `path: ${exeName}`,
    `sha512: ${sha512}`,
    `releaseDate: '${releaseDate}'`,
    '',
  ].join('\n'),
  'utf8'
);

let removed = 0;
for (const f of fs.readdirSync(dir)) {
  if (f.endsWith('.blockmap')) {
    fs.rmSync(path.join(dir, f));
    removed++;
  }
}

console.log(`latest.yml 已按签名后的安装包重算（sha512=${sha512.slice(0, 12)}…, size=${buf.length}），删除过期 blockmap ${removed} 个`);
