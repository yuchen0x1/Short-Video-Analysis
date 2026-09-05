// 抖音解析分步诊断：ttwid 获取 → 签名请求 → 响应内容
const ROOT = new URL('..', import.meta.url).href;
const nodeFetchTransport = {
  async request({ url, method, headers, body, timeoutMs = 15000 }) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await fetch(url, { method, headers, body: body ?? undefined, redirect: 'follow', signal: controller.signal });
      const text = await response.text();
      const headerObj = {};
      response.headers.forEach((value, key) => { headerObj[key.toLowerCase()] = value; });
      return { status: response.status, data: text, headers: headerObj, url: response.url };
    } finally { clearTimeout(timer); }
  },
};

const ttwidResult = await nodeFetchTransport.request({
  url: 'https://ttwid.bytedance.com/ttwid/union/register/',
  method: 'POST',
  headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36', 'Content-Type': 'application/json' },
  body: JSON.stringify({ region: 'cn', aid: 6383, need_t: 1, service: 'www.douyin.com', migrate_priority: 0, cb_url_protocol: 'https', domain: '.douyin.com' }),
});
console.log('== ttwid 注册 ==');
console.log('status:', ttwidResult.status);
console.log('set-cookie:', String(ttwidResult.headers['set-cookie'] || '').slice(0, 120));
const ttwid = String(ttwidResult.headers['set-cookie'] || '').match(/ttwid=([^;\s]+)/)?.[1];
console.log('ttwid:', ttwid ? decodeURIComponent(ttwid).slice(0, 40) + '…' : 'NULL');

// 用 PHP 侧同样的参数跑一次详情接口（签名用我们移植的算法）
const { generateABogus } = await import(new URL('src/a-bogus.js', ROOT).href);
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';
// msToken 可通过环境变量注入真实值；默认占位符仅供本地签名诊断
const msToken = process.env.DOUYIN_MS_TOKEN || '00000000000000000000000000000000';
const search = new URLSearchParams({ device_platform: 'webapp', aid: '6383', channel: 'channel_pc_web', aweme_id: '7344998635323907337', msToken });
const query = search.toString();
const sig = generateABogus(query, UA);
console.log('\n== 详情接口 ==');
console.log('query:', query);
console.log('a_bogus len:', sig.length);
const detailUrl = new URL('https://www.douyin.com/aweme/v1/web/aweme/detail/');
detailUrl.search = query + '&a_bogus=' + encodeURIComponent(sig);
const detail = await nodeFetchTransport.request({
  url: detailUrl.href,
  method: 'GET',
  headers: {
    'User-Agent': UA,
    Accept: 'application/json, text/plain, */*',
    'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8',
    Referer: 'https://www.douyin.com/video/7344998635323907337',
    Cookie: 'ttwid=' + ttwid,
    'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'same-origin',
  },
});
console.log('status:', detail.status);
console.log('body head:', String(detail.data).slice(0, 300));
