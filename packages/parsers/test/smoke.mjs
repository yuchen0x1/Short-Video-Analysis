// Node 冒烟验证：域名识别、a_bogus 签名、真实平台解析、契约字段
// 这里的 Node fetch 传输适配器也是将来电脑版复用的原型。
// 用法：node packages/parsers/test/smoke.mjs

const ROOT = new URL('..', import.meta.url).href;

const nodeFetchTransport = {
  async request({ url, method, headers, body, timeoutMs = 15000 }) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await fetch(url, {
        method,
        headers,
        body: body ?? undefined,
        redirect: 'follow',
        signal: controller.signal,
      });
      const text = await response.text();
      const headerObj = {};
      response.headers.forEach((value, key) => {
        headerObj[key.toLowerCase()] = value;
      });
      return { status: response.status, data: text, headers: headerObj, url: response.url };
    } finally {
      clearTimeout(timer);
    }
  },
};

const parsers = await import(new URL('src/index.js', ROOT).href);
parsers.setTransport(nodeFetchTransport);

let failed = 0;
const check = (name, condition, detail = '') => {
  const mark = condition ? 'PASS' : 'FAIL';
  if (!condition) failed += 1;
  console.log(`[${mark}] ${name}${detail ? ' — ' + detail : ''}`);
};

// 1. 平台列表
const platforms = parsers.getSupportedPlatforms();
check('平台数量 = 11', platforms.length === 11, platforms.join(','));

// 2. 域名识别
const domainCases = [
  ['https://v.douyin.com/AbCdEf/', 'douyin'],
  ['https://www.douyin.com/video/1234567890', 'douyin'],
  ['https://www.bilibili.com/video/BV1xx411c7mD', 'bilibili'],
  ['https://b23.tv/AbCdEf', 'bilibili'],
  ['https://v.kuaishou.com/AbCdEf', 'kuaishou'],
  ['https://www.xiaohongshu.com/explore/abc123', 'xiaohongshu'],
  ['https://xhslink.com/AbCdEf', 'xiaohongshu'],
  ['https://weishi.qq.com/xxx', 'weishi'],
  ['https://channels.weixin.qq.com/xxx', 'shipinhao'],
  ['https://example.com/video/1', null],
];
for (const [url, expected] of domainCases) {
  const actual = parsers.detectPlatform(url);
  check(`域名识别 ${new URL(url).hostname} -> ${expected}`, actual === expected, `实际: ${actual}`);
}

// 3. a_bogus 签名可生成
const sig = parsers.generateABogus(
  'device_platform=webapp&aid=6383&channel=channel_pc_web&aweme_id=7123456789012345678&msToken=ABCabc123',
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'
);
check('a_bogus 签名生成', typeof sig === 'string' && sig.length > 20, `长度 ${sig.length}`);

// 4. 真实解析：B 站
try {
  const result = await parsers.parse('https://www.bilibili.com/video/BV1xx411c7mD');
  check('B 站解析 title', typeof result.title === 'string' && result.title !== '', result.title.slice(0, 30));
  check('B 站解析 url', typeof result.url === 'string' && result.url.startsWith('http'), result.url.slice(0, 60));
  check('B 站解析 author', result.author !== '', result.author);
} catch (e) {
  check('B 站解析', false, e.message);
}

// 5. 真实解析：抖音（依赖 ttwid/签名，网络环境敏感，仅报告）
try {
  const result = await parsers.parse('https://www.douyin.com/video/7344998635323907337');
  check('抖音解析 title', typeof result.title === 'string', result.title?.slice(0, 30));
  check('抖音解析 url', typeof result.url === 'string' && result.url.startsWith('http'));
} catch (e) {
  console.log(`[WARN] 抖音解析失败（网络敏感项，仅报告）: ${e.message}`);
}

console.log(failed === 0 ? '\n全部通过' : `\n${failed} 项失败`);
process.exit(failed === 0 ? 0 : 1);
