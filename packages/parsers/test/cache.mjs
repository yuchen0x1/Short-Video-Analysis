import assert from 'node:assert/strict';
import test from 'node:test';
import { cacheClear, cacheGet, cacheSet } from '../src/cache.js';

test('cacheClear removes entries and expired entries are ignored', () => {
  cacheClear();
  cacheSet('live', { ok: true }, 60);
  assert.deepEqual(cacheGet('live'), { ok: true });
  cacheSet('expired', { ok: false }, -1);
  assert.equal(cacheGet('expired'), null);
  cacheClear();
  assert.equal(cacheGet('live'), null);
});

test('cache remains bounded and keeps recently read entries', () => {
  cacheClear();
  for (let i = 0; i < 256; i += 1) cacheSet(`key-${i}`, i);
  assert.equal(cacheGet('key-0'), 0);
  cacheSet('key-256', 256);
  assert.equal(cacheGet('key-0'), 0);
  assert.equal(cacheGet('key-1'), null);
  cacheClear();
});
