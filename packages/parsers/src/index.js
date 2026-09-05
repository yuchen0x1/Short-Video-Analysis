// 解析器门面：接口契约与 PHP /api/v1 保持一致
//   parse(url) / parseProfile(url, cursor) / getSupportedPlatforms()
import { cacheGet, cacheSet, makeParseKey } from './cache.js';
import { detectPlatform, PLATFORMS } from './domains.js';
import { fixUrl } from './utils.js';
import * as douyin from './parsers/douyin.js';

const modules = {
  douyin,
  kuaishou: await import('./parsers/kuaishou.js'),
  bilibili: await import('./parsers/bilibili.js'),
  xiaohongshu: await import('./parsers/xiaohongshu.js'),
  weibo: await import('./parsers/weibo.js'),
  pipixia: await import('./parsers/pipixia.js'),
  izuiyou: await import('./parsers/izuiyou.js'),
  pipigx: await import('./parsers/pipigx.js'),
  weishi: await import('./parsers/weishi.js'),
  ixigua: await import('./parsers/xigua.js'),
  shipinhao: await import('./parsers/shipinhao.js'),
};

function normalizeResult(platform, result) {
  const value = result && typeof result === 'object' ? result : {};
  const mediaType = value.media_type === 'image' ? 'image' : 'video';
  const images = Array.isArray(value.images)
    ? value.images.map((url) => fixUrl(url)).filter(Boolean)
    : [];
  const url = fixUrl(value.url) || (mediaType === 'image' ? images[0] || '' : '');

  return {
    ...value,
    title: String(value.title ?? ''),
    author: String(value.author ?? ''),
    avatar: fixUrl(value.avatar),
    uid: value.uid ?? '',
    like: value.like ?? 0,
    time: value.time ?? 0,
    cover: fixUrl(value.cover),
    url,
    music: value.music && typeof value.music === 'object'
      ? value.music
      : { author: '', avatar: '' },
    media_type: mediaType,
    images,
    platform,
  };
}

export function getSupportedPlatforms() {
  return Object.keys(PLATFORMS);
}

export async function parse(url, options = {}) {
  const platform = detectPlatform(url);
  if (!platform) {
    throw new Error('不支持的视频平台');
  }

  const cacheKey = makeParseKey('parse', url);
  const cached = cacheGet(cacheKey);
  if (cached && !options.force) return cached;

  const parser = modules[platform];
  const result = normalizeResult(platform, await parser.parse(url));
  cacheSet(cacheKey, result, 86400);
  return result;
}

export async function parseProfile(url, cursor = 0) {
  const platform = detectPlatform(url);
  if (platform !== 'douyin') {
    throw new Error('目前仅支持抖音个人主页批量解析');
  }
  const result = await douyin.parseProfile(url, cursor);
  return {
    ...result,
    items: Array.isArray(result.items)
      ? result.items.map((item) => normalizeResult('douyin', item))
      : [],
  };
}

export { setTransport } from './transport.js';
export { setWeiboCookie, fixUrl } from './utils.js';
export { generateABogus } from './a-bogus.js';
export { detectPlatform };
