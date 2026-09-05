const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const projectRoot = path.resolve(__dirname, '..');
const runtimeDir = path.join(projectRoot, 'runtime', 'php');
const configuredDir = process.env.PHP_RUNTIME_DIR;
let sourceDir = configuredDir;

if (!sourceDir) {
  const command = process.platform === 'win32' ? 'where' : 'which';
  const executable = process.platform === 'win32' ? 'php.exe' : 'php';
  sourceDir = path.dirname(execFileSync(command, [executable], { encoding: 'utf8' }).split(/\r?\n/)[0].trim());
}

if (!sourceDir || !fs.existsSync(path.join(sourceDir, process.platform === 'win32' ? 'php.exe' : 'php'))) {
  throw new Error('找不到 PHP 运行时。请安装 PHP 8.1+，或设置 PHP_RUNTIME_DIR。');
}

fs.mkdirSync(path.dirname(runtimeDir), { recursive: true });
fs.cpSync(sourceDir, runtimeDir, { recursive: true, force: true });
console.log(`PHP runtime prepared: ${runtimeDir}`);
