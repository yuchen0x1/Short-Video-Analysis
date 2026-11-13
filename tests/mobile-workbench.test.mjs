import assert from 'node:assert/strict';
import test from 'node:test';
import { readStored, readHistory, loadDiscoveryPage, downloadGallery } from '../apps/mobile/src/workbench.js';

test('mobile can start with unavailable storage or corrupt history', () => {
  const blocked = { getItem() { throw new Error('denied'); } };
  assert.equal(readStored(blocked, 'cookie'), '');
  assert.deepEqual(readHistory(blocked, 'history'), []);
  assert.deepEqual(readHistory({ getItem: () => '{broken' }, 'history'), []);
});

test('history filters broken records, unsafe URLs and duplicates, retaining readable records', () => {
  const storage = { getItem: () => JSON.stringify([null, {}, { url: 'javascript:alert(1)' }, { url: 'https://user:pass@example.com' }, { url: 'https://www.douyin.com/video/1', title: 'one' }, { url: 'https://www.douyin.com/video/1', title: 'duplicate' }]) };
  const items = readHistory(storage, 'history');
  assert.equal(items.length, 1);
  assert.equal(items[0].title, 'one');
});

const discovery = () => ({ tab: 'hot', platform: 'bilibili', keyword: '', page: 1, items: [{ title: 'first' }], loading: false, hasMore: true });
test('failed discovery page is retried without skipping or losing previous results', async () => {
  const state = discovery(), pages = [];
  await assert.rejects(loadDiscoveryPage(state, async query => { pages.push(query.page); throw new Error('offline'); }), /offline/);
  assert.equal(state.page, 1);
  assert.equal(state.items.length, 1);
  await loadDiscoveryPage(state, async query => { pages.push(query.page); return { items: [{ title: 'second' }], has_more: false }; });
  assert.deepEqual(pages, [2, 2]);
  assert.equal(state.page, 2);
  assert.equal(state.items.length, 2);
});

test('discovery refuses duplicate requests and discards late responses for another platform', async () => {
  const state = discovery();
  let finish;
  const pending = loadDiscoveryPage(state, () => new Promise(resolve => { finish = resolve; }), true);
  assert.equal(await loadDiscoveryPage(state, () => { throw new Error('duplicate'); }), false);
  state.platform = 'douyin';
  finish({ items: [{ title: 'stale' }] });
  assert.equal(await pending, false);
  assert.equal(state.items[0].title, 'first');
  assert.equal(state.loading, false);
});

test('native gallery queues real URLs and never asks DownloadManager to save a blob', async () => {
  const queued = [];
  const result = await downloadGallery(['https://cdn/a.jpg', 'https://cdn/b.jpg'], 'gallery', {
    native: true, download: async (...args) => queued.push(args),
    fetchResponse: () => { throw new Error('native must not fetch into JS memory'); },
    createZip: () => { throw new Error('native must not create blob URLs'); },
  });
  assert.equal(result, null);
  assert.deepEqual(queued, [['https://cdn/a.jpg', 'gallery-01.jpg'], ['https://cdn/b.jpg', 'gallery-02.jpg']]);
});

test('gallery stops on a failed native enqueue instead of reporting full success', async () => {
  let calls = 0;
  await assert.rejects(downloadGallery(['https://cdn/a', 'https://cdn/b'], 'gallery', {
    native: true, download: async () => { calls += 1; throw new Error('permission denied'); },
  }), /permission denied/);
  assert.equal(calls, 1);
});

test('browser gallery checks total bytes across multiple streamed images', async () => {
  const entries = await downloadGallery(['https://cdn/a', 'https://cdn/b'], 'gallery', {
    native: false, limit: 4, fetchResponse: async () => new Response(new Uint8Array([1, 2])), createZip: entries => entries,
  });
  assert.equal(entries.length, 2);
  assert.equal(entries[1].data.length, 2);
  let cancelled = false;
  await assert.rejects(downloadGallery(['https://cdn/a'], 'gallery', {
    native: false, limit: 2,
    fetchResponse: async () => new Response(new ReadableStream({ start(controller) { controller.enqueue(new Uint8Array([1, 2, 3])); }, cancel() { cancelled = true; } })),
    createZip: () => { throw new Error('oversized media must not be zipped'); },
  }), /128 MiB/);
  assert.equal(cancelled, true);
});
