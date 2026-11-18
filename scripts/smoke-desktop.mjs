import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const [executable, version] = process.argv.slice(2);
assert(executable && version, 'Usage: smoke-desktop.mjs <executable> <version>');

async function waitFor(probe, name) {
  const deadline = Date.now() + 30_000;
  while (Date.now() < deadline) {
    const value = await probe().catch(() => null);
    if (value) return value;
    await delay(200);
  }
  throw new Error(`Timed out waiting for ${name}`);
}

const profile = await mkdtemp(join(tmpdir(), 'sv-desktop-smoke-'));
const child = spawn(resolve(executable), [
  `--user-data-dir=${profile}`, '--remote-debugging-port=0', '--disable-gpu',
], { stdio: ['ignore', 'ignore', 'inherit'], windowsHide: true });
let spawnError;
child.once('error', (error) => { spawnError = error; });
let socket;

try {
  const port = await waitFor(async () => {
    if (spawnError || child.exitCode !== null) return 'exited';
    const body = await readFile(join(profile, 'DevToolsActivePort'), 'utf8');
    return Number(body.split('\n')[0]);
  }, 'packaged desktop');
  if (port === 'exited') throw spawnError || new Error(`Desktop exited: ${child.exitCode}`);
  const debugUrl = await waitFor(async () => {
    const response = await fetch(`http://127.0.0.1:${port}/json/list`, { signal: AbortSignal.timeout(2_000) });
    return (await response.json()).find((target) => target.type === 'page' && target.url.startsWith('file:'))?.webSocketDebuggerUrl;
  }, 'desktop renderer');

  socket = new WebSocket(debugUrl);
  const result = await new Promise((resolveResult, reject) => {
    const timeout = setTimeout(() => reject(new Error('Renderer smoke test timed out')), 15_000);
    socket.addEventListener('error', () => {
      clearTimeout(timeout);
      reject(new Error('Could not connect to packaged desktop renderer'));
    }, { once: true });
    socket.addEventListener('open', () => {
      socket.send(JSON.stringify({
        id: 1,
        method: 'Runtime.evaluate',
        params: {
          expression: `(async () => {
            for (let attempt = 0; attempt < 80 && !document.querySelector('#parse-button'); attempt++) {
              await new Promise(resolve => setTimeout(resolve, 100));
            }
            document.querySelector('[data-view="profile"]').click();
            return {
              version: await window.appShell.getVersion(),
              profileInput: Boolean(document.querySelector('#link-input').getClientRects().length),
              history: Boolean(document.querySelector('[data-view="history"]')),
            };
          })()`,
          returnByValue: true,
          awaitPromise: true,
        },
      }));
    }, { once: true });
    socket.addEventListener('message', (event) => {
      const response = JSON.parse(event.data);
      if (response.id !== 1) return;
      clearTimeout(timeout);
      if (response.error || response.result?.exceptionDetails) {
        const details = response.result?.exceptionDetails;
        reject(new Error(response.error?.message || details.exception?.description || details.text));
        return;
      }
      resolveResult(response.result?.result?.value);
    });
  });
  assert.deepEqual(result, { version, profileInput: true, history: true });
  console.log(`Packaged desktop smoke passed for v${version}`);
} finally {
  socket?.close();
  child.kill();
}
