const ROOT = new URL('..', import.meta.url);

if (!process.argv.includes('--live')) {
  await import(new URL('contract.mjs', import.meta.url).href);
} else {
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
        response.headers.forEach((value, key) => { headerObj[key.toLowerCase()] = value; });
        return { status: response.status, data: text, headers: headerObj, url: response.url };
      } finally {
        clearTimeout(timer);
      }
    },
  };

  const parsers = await import(new URL('src/index.js', ROOT).href);
  parsers.setTransport(nodeFetchTransport);
  parsers.setWeiboCookie(process.env.PARSER_WEIBO_COOKIE || '');

  const defaults = {
    bilibili: 'https://www.bilibili.com/video/BV1xx411c7mD',
    douyin: 'https://www.douyin.com/video/7253815894357363979',
  };
  const platforms = parsers.getSupportedPlatforms();
  const cases = platforms.map((platform) => ({
    platform,
    url: process.env[`PARSER_SAMPLE_${platform.toUpperCase()}`] || defaults[platform] || '',
  }));
  const results = [];

  function externalFailure(message) {
    return /Cookie|登录|风控|core_dep|429|403|502|503|网络|超时|暂时失效|需要验证/i.test(message);
  }

  function validateResult(result) {
    if (!result || typeof result !== 'object') return '结果不是对象';
    if (typeof result.url !== 'string' || !/^https?:\/\//i.test(result.url)) return '未返回可下载媒体 URL';
    if (result.media_type === 'image' && (!Array.isArray(result.images) || result.images.length === 0)) {
      return '图集结果缺少 images';
    }
    return '';
  }

  for (const { platform, url } of cases) {
    if (!url) {
      const result = { platform, status: 'skip', reason: '未设置有效公开样本；使用 PARSER_SAMPLE_<PLATFORM> 提供' };
      results.push(result);
      console.log(`[SKIP] ${platform}: ${result.reason}`);
      continue;
    }

    const started = Date.now();
    try {
      const value = await parsers.parse(url);
      const validationError = validateResult(value);
      if (validationError) {
        const result = { platform, status: 'fail', elapsedMs: Date.now() - started, reason: validationError };
        results.push(result);
        console.log(`[FAIL] ${platform}: ${validationError}`);
        continue;
      }
      const result = {
        platform,
        status: 'pass',
        elapsedMs: Date.now() - started,
        title: String(value.title || '').slice(0, 40),
        mediaType: value.media_type,
      };
      results.push(result);
      console.log(`[PASS] ${platform}: ${result.title || '(无标题)'} | ${result.mediaType}`);
    } catch (error) {
      const message = String(error?.message || error);
      const status = externalFailure(message) ? 'skip' : 'fail';
      const result = { platform, status, elapsedMs: Date.now() - started, reason: message.slice(0, 120) };
      results.push(result);
      console.log(`[${status.toUpperCase()}] ${platform}: ${result.reason}`);
    }
  }

  const passed = results.filter((result) => result.status === 'pass').length;
  const skipped = results.filter((result) => result.status === 'skip').length;
  const failed = results.filter((result) => result.status === 'fail').length;
  console.log(`\n== 实时审计汇总 ==`);
  console.log(`通过: ${passed} / ${results.length}，跳过: ${skipped}，失败: ${failed}`);
  if (skipped) console.log('提示：SKIP 需要有效公开样本、微博 Cookie 或平台允许当前网络环境后重新验证。');
  process.exitCode = failed ? 1 : 0;
}
