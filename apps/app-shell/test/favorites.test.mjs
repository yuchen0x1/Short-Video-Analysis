import assert from 'node:assert/strict';
import test from 'node:test';
import { createFavoritesStore, FAVORITES_STORAGE_KEY, MAX_FAVORITES } from '../src/favorites.js';

function createStorage(values = {}) {
  return {
    values: { ...values },
    getItem(key) { return Object.hasOwn(this.values, key) ? this.values[key] : null; },
    setItem(key, value) { this.values[key] = String(value); },
  };
}

const record = (id, extra = {}) => ({
  rawUrl: `https://www.douyin.com/video/${id}`,
  title: `作品 ${id}`,
  author: '作者',
  time: 1_700_000_000,
  cover: `https://example.com/${id}.jpg`,
  mediaType: 'video',
  ...extra,
});

test('favorites are persisted locally, returned newest first, and deduplicated by source URL', () => {
  const storage = createStorage();
  const favorites = createFavoritesStore(storage);
  assert.deepEqual(favorites.list(), []);
  assert.equal(favorites.add(record('1', { savedAt: 1 })), true);
  assert.equal(favorites.add(record('2', { savedAt: 2 })), true);
  assert.equal(favorites.add(record('1', { title: 'replacement' })), false);
  assert.equal(favorites.has(record('1').rawUrl), true);
  assert.deepEqual(favorites.list().map((item) => item.rawUrl), [record('2').rawUrl, record('1').rawUrl]);
  assert.equal(JSON.parse(storage.getItem(FAVORITES_STORAGE_KEY))[0].title, '作品 2');
});

test('records contain only supported display fields and reject unsafe source URLs', () => {
  const favorites = createFavoritesStore(createStorage());
  assert.throws(() => favorites.add(record('1', { rawUrl: 'javascript:alert(1)' })), /HTTP\(S\)/);
  assert.throws(() => favorites.add(record('1', { rawUrl: 'https://user:secret@example.com/video/1' })), /HTTP\(S\)/);
  favorites.add(record('1', {
    cover: 'file:///private/cover.jpg',
    cookie: 'must not persist',
    token: 'must not persist',
  }));
  const [saved] = favorites.list();
  assert.equal(saved.cover, '');
  assert.equal('cookie' in saved, false);
  assert.equal('token' in saved, false);
});

test('toggle removes an existing favorite and adds it again with fresh metadata', () => {
  const favorites = createFavoritesStore(createStorage());
  assert.equal(favorites.toggle(record('1')), true);
  assert.equal(favorites.toggle(record('1')), false);
  assert.equal(favorites.has(record('1').rawUrl), false);
  assert.equal(favorites.toggle(record('1', { title: '再次收藏' })), true);
  assert.equal(favorites.list()[0].title, '再次收藏');
});

test('remove and clear report changes and leave an empty collection', () => {
  const favorites = createFavoritesStore(createStorage());
  favorites.add(record('1'));
  favorites.add(record('2'));
  assert.equal(favorites.remove(record('1').rawUrl), true);
  assert.equal(favorites.remove(record('1').rawUrl), false);
  assert.equal(favorites.clear(), 1);
  assert.equal(favorites.clear(), 0);
  assert.deepEqual(favorites.list(), []);
});

test('corrupt data is preserved and storage errors do not report success', () => {
  const storage = createStorage({ [FAVORITES_STORAGE_KEY]: '{broken' });
  const favorites = createFavoritesStore(storage);
  assert.throws(() => favorites.list(), /无法读取本地收藏/);
  assert.throws(() => favorites.add(record('1')), /无法读取本地收藏/);
  assert.equal(storage.getItem(FAVORITES_STORAGE_KEY), '{broken');

  const failingStorage = createStorage();
  failingStorage.setItem = () => { throw new Error('QuotaExceededError'); };
  assert.throws(() => createFavoritesStore(failingStorage).add(record('1')), /QuotaExceededError/);
});

test('stored data is validated, deduplicated, bounded, and unknown fields are dropped', () => {
  const storage = createStorage({
    [FAVORITES_STORAGE_KEY]: JSON.stringify([
      record('1', { cookie: 'secret' }),
      record('1', { title: 'duplicate' }),
      { rawUrl: 'javascript:alert(1)', title: 'unsafe' },
    ]),
  });
  const favorites = createFavoritesStore(storage);
  assert.equal(favorites.list().length, 1);
  assert.equal(favorites.list()[0].title, '作品 1');
  assert.equal('cookie' in favorites.list()[0], false);
  storage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(Array.from({ length: MAX_FAVORITES + 1 }, (_, index) => record(String(index)))));
  assert.throws(() => favorites.list(), /上限/);
});

test('adding beyond the favorites limit fails without evicting saved records', () => {
  const storage = createStorage({
    [FAVORITES_STORAGE_KEY]: JSON.stringify(Array.from({ length: MAX_FAVORITES }, (_, index) => record(String(index)))),
  });
  const favorites = createFavoritesStore(storage);
  const before = storage.getItem(FAVORITES_STORAGE_KEY);
  assert.throws(() => favorites.add(record('new')), /先移除已有收藏/);
  assert.equal(storage.getItem(FAVORITES_STORAGE_KEY), before);
  assert.equal(favorites.list().length, MAX_FAVORITES);
});
