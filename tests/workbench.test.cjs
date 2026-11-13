const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createLibrary, matches, csvCell, runQueue, readLimited } = require('../public/workbench.js');
const record = (id, extra = {}) => ({ rawUrl: `https://www.douyin.com/video/${id}`, title: `作品 ${id}`, ...extra });
function storage(values = {}) {
  return { values, getItem(key) { return this.values[key] ?? null; }, setItem(key, value) { this.values[key] = value; } };
}
const backup = (history, favorites = []) => JSON.stringify({ app: 'short-video-analysis', version: 2, history, favorites });

test('legacy records migrate on first write; originals and credentials remain untouched', () => {
  const original = JSON.stringify([record(1)]);
  const store = storage({ 'watermark-parse-history-v1': original, 'watermark-weibo-cookie-v1': 'private' });
  const library = createLibrary(store);
  assert.equal(library.read().history.length, 1);
  assert.equal(store.getItem('watermark-library-v2'), null);
  library.save('favorites', [record(2, { cookie: 'secret' })]);
  assert.equal(store.getItem('watermark-parse-history-v1'), original);
  assert.equal(library.read().history.length, 1);
  assert.doesNotMatch(library.export(), /private|secret|cookie/i);
});
test('import merges without replacing existing records and is idempotent', () => {
  const library = createLibrary(storage());
  library.save('history', [record(1)]);
  const text = backup([record(1, { title: 'replacement' }), record(2)], [record(3)]);
  library.import(text);library.import(text);
  assert.deepEqual(library.read().history.map(x => x.title), ['作品 1', '作品 2']);
  assert.equal(library.read().favorites.length, 1);
  assert.equal(createLibrary(storage()).import(library.export()).history.length, 2);
});
test('invalid format, dangerous links and oversized backups cannot alter stored data', () => {
  const store = storage(), library = createLibrary(store);
  library.save('history', [record(1)]);
  const before = store.getItem('watermark-library-v2');
  for (const text of ['{', '{}', backup([{ rawUrl: 'javascript:alert(1)' }]), backup([{ rawUrl: 'https://user:pass@example.com' }]), ' '.repeat(4*1024*1024+1)]) {
    assert.throws(() => library.import(text));
    assert.equal(store.getItem('watermark-library-v2'), before);
  }
});
test('storage quota failure leaves both collections intact', () => {
  const store = storage(), library = createLibrary(store);
  library.save('history', [record(1)]);
  const before = store.getItem('watermark-library-v2');
  store.setItem = () => { throw new Error('QuotaExceededError'); };
  assert.throws(() => library.import(backup([record(2)], [record(3)])), /Quota/);
  assert.equal(store.getItem('watermark-library-v2'), before);
});
test('writes stay within the size that can be restored from a backup', () => {
  const store = storage(), library = createLibrary(store);
  library.save('history', [record(1)]);
  const before = store.getItem('watermark-library-v2');
  assert.throws(() => library.save('favorites', Array.from({length:600},(_,i)=>record(i,{title:'x'.repeat(8192)}))), /4 MiB/);
  assert.equal(store.getItem('watermark-library-v2'), before);
});
test('corrupt legacy data is preserved and blocks writes', () => {
  const store = storage({ 'watermark-parse-history-v1': '{broken' });
  assert.throws(() => createLibrary(store).save('favorites', []));
  assert.equal(store.getItem('watermark-parse-history-v1'), '{broken');
  assert.equal(store.getItem('watermark-library-v2'), null);
});
test('search covers title, author and URL without changing order', () => {
  const item = record(42, { title: '测试 Coffee', author: '作者甲' });
  for (const query of [' coffee ', '作者甲', '/42', '']) assert.ok(matches(item, query));
  assert.equal(matches(item, 'missing'), false);
});
test('CSV escapes quotes and neutralizes formula/control prefixes', () => {
  for (const value of ['=1+1', '+cmd', '-2', '@SUM(A1)', '  =1', '\ttext', '\n=1']) assert.ok(csvCell(value).startsWith('"\''));
  assert.equal(csvCell('a,"b"'), '"a,""b"""');
});
test('queue bounds concurrent requests and keeps input order across failures', async () => {
  const items = Array.from({length:8}, (_,i) => ({url:String(i),loading:true}));
  let active=0,peak=0;
  await runQueue(items,items.map((_,i)=>i),async url=>{
    peak=Math.max(peak,++active);await new Promise(resolve=>setTimeout(resolve,2));active--;
    if(url==='2')throw new Error('rate limited');return {url,data:{title:url}};
  });
  assert.equal(peak,3);assert.equal(items[2].error,'rate limited');
  assert.equal(items[7].data.title,'7');assert.ok(items.every(x=>!x.loading));
});
test('stopping queue prevents new requests and retry preserves successful entries', async () => {
  const items=Array.from({length:6},(_,i)=>({url:String(i),loading:true}));
  const controller=new AbortController();let calls=0;
  await runQueue(items,items.map((_,i)=>i),async url=>{calls++;if(url==='1')controller.abort();return {url,data:{title:url}};},{signal:controller.signal,concurrency:1});
  assert.equal(calls,2);assert.ok(items.slice(1).every(x=>x.error==='已停止处理'));
  const first=items[0];await runQueue(items,[1,2,3,4,5],async url=>({url,data:{title:url}}));
  assert.equal(items[0],first);assert.ok(items.every(x=>x.data));
});
test('media streaming enforces size without trusting Content-Length', async () => {
  assert.equal((await readLimited(new Response('abc'),3)).length,3);
  await assert.rejects(readLimited(new Response('abcd'),3),/128 MiB/);
  await assert.rejects(readLimited(new Response('x',{headers:{'content-length':'999'}}),3),/128 MiB/);
  await assert.rejects(readLimited(new Response('',{status:403}),3),/403/);
});
