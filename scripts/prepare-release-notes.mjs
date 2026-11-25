import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const tag = process.argv[2] || process.env.RELEASE_TAG || '';
if (!/^v\d+\.\d+\.\d+$/.test(tag)) throw new Error('需要正式版本标签 vMAJOR.MINOR.PATCH');
const source = fs.readFileSync(path.join(root, 'docs/releases/release-notes.md'), 'utf8');
const lines = source.split(/\r?\n/);
const start = lines.findIndex(line => line.startsWith(`## ${tag} · `));
if (start < 0) throw new Error(`找不到 ${tag} 的发布说明`);
const next = lines.findIndex((line, index) => index > start && /^## v\d+\.\d+\.\d+ · /.test(line));
const body = lines.slice(start + 1, next < 0 ? undefined : next).join('\n').trim();
const output = path.resolve(process.argv[3] || path.join(root, '.evolution/release-notes.md'));
fs.mkdirSync(path.dirname(output), { recursive: true });
if (!body.startsWith('## 📢 版本概述')) throw new Error(`${tag} 发布说明必须以「## 📢 版本概述」开始`);
fs.writeFileSync(output, `${body}\n`, 'utf8');
console.log(`Release notes ready: ${tag}`);
