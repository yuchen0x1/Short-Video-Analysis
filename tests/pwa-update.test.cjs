const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { test } = require('node:test');
const { join } = require('node:path');

const source = fs.readFileSync(join(__dirname, '..', 'public', 'service-worker.js'), 'utf8');

function worker({ installError, offline } = {}) {
  const handlers = {};
  const shell = new Map();
  const cache = {
    async addAll(assets) {
      if (installError) throw installError;
      for (const asset of assets) shell.set(asset, new Response(asset));
    },
    async put(key, response) { shell.set(key, response); },
    async match(key) { return shell.get(key); },
  };
  let skips = 0;
  vm.runInNewContext(source, {
    URL, Response,
    self: {
      location: { origin: 'https://example.test' },
      registration: { scope: 'https://example.test/app/' },
      addEventListener(name, handle) { handlers[name] = handle; },
      async skipWaiting() { skips++; },
    },
    caches: { async open() { return cache; } },
    async fetch() {
      if (offline) throw Error('offline');
      return new Response('live HTML');
    },
  });
  const call = (name, event = {}) => {
    let response;
    const handled = [];
    handlers[name]({
      ...event,
      waitUntil(value) { handled.push(value); },
      respondWith(value) { response = value; },
    });
    return { response, async wait() { await Promise.all(handled); } };
  };
  return { call, shell, skips: () => skips };
}

test('precache succeeds before installation and waits for an explicit update request', async () => {
  const app = worker();
  await app.call('install').wait();
  assert(app.shell.has('./workbench.js'));
  assert(app.shell.has('./web-update-preference.js'));
  assert.equal(app.skips(), 0);
  await app.call('message', { data: { type: 'OTHER' } }).wait();
  assert.equal(app.skips(), 0);
  await app.call('message', { data: { type: 'ACTIVATE_UPDATE' } }).wait();
  assert.equal(app.skips(), 1);
});

test('a partial precache fails the installation', async () => {
  const app = worker({ installError: Error('asset unavailable') });
  await assert.rejects(app.call('install').wait(), /asset unavailable/);
  assert.equal(app.skips(), 0);
});

test('navigation falls back to the active shell without intercepting API or media', async () => {
  const app = worker({ offline: true });
  const request = pathname => ({ request: { url: 'https://example.test/app/' + pathname, mode: 'navigate' } });
  assert.equal((await app.call('fetch', request('')).response).type, 'error');
  app.shell.set('./', new Response('offline shell'));
  assert.equal(await (await app.call('fetch', request('')).response).text(), 'offline shell');
  assert.equal(app.call('fetch', request('api/v1/health')).response, undefined);
  assert.equal(app.call('fetch', request('index.php?action=media')).response, undefined);
});
