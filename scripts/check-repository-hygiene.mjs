import fs from 'node:fs';
import { execFileSync } from 'node:child_process';

// Check the index: files already tracked by Git are not protected by .gitignore.
const paths = execFileSync('git', ['ls-files', '-z'], { encoding: 'utf8' }).split('\0').filter(Boolean);
const forbidden = [
  /(^|\/)(node_modules|vendor|dist|coverage|__pycache__|\.gradle|\.idea|\.evolution)(\/|$)/,
  /(^|\/)release(?:-[^/]*)?\//,
  /(^|\/)\.env(?:\.(?!example$)[^/]+)?$/,
  /(^|\/)(keystore\.properties|local\.properties|\.phpunit\.result\.cache)$/,
  /\.(?:exe|apk|aab|ipa|jks|keystore|p12|pfx|log|pyc|bak|tmp)$/i,
  /^apps\/(?!app-shell\/)/,
  /(^|\/)(android|ios)\//,
  /^packages\/update-checker\//,
  /^\.workflow\//,
];
const secret = /-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----|\bgh[pousr]_[A-Za-z0-9]{30,}\b|\bgithub_pat_[A-Za-z0-9_]{50,}\b/;
const problems = [];
for (const file of paths) {
  if (!fs.existsSync(file)) continue; // A tracked deletion will disappear after git add.
  if (forbidden.some(pattern => pattern.test(file))) problems.push(`${file}: generated file or local configuration`);
  const bytes = fs.readFileSync(file);
  if (bytes.length < 2 * 1024 * 1024 && !bytes.includes(0) && secret.test(bytes.toString('utf8'))) {
    problems.push(`${file}: private key or token pattern (value redacted)`);
  }
}
if (problems.length) {
  console.error(problems.join('\n'));
  process.exitCode = 1;
} else {
  console.log(`Repository hygiene PASS: ${paths.length} tracked paths; no generated packages, local credentials or known token patterns`);
}
