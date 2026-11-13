import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const tag = process.argv[2] || process.env.RELEASE_TAG || '';
if (!/^v\d+\.\d+\.\d+$/.test(tag)) throw new Error('需要正式版本标签 vMAJOR.MINOR.PATCH');
const source = fs.readFileSync(path.join(root, 'docs/releases/release-notes.md'), 'utf8');
const sections = source.split(/^## /m).slice(1);
const section = sections.find(value => value.startsWith(`${tag} · `));
if (!section) throw new Error(`找不到 ${tag} 的发布说明`);
const output = path.resolve(process.argv[3] || path.join(root, '.evolution/release-notes.md'));
fs.mkdirSync(path.dirname(output), { recursive: true });
fs.writeFileSync(output, `## ${section.trim()}\n`, 'utf8');
console.log(`Release notes ready: ${tag}`);
