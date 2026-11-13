// 解析器门面：接口契约与 PHP /api/v1 保持一致
//   parse(url) / parseProfile(url, cursor) / getSupportedPlatforms()
import { cacheGet, cacheSet, makeParseKey } from './cache.js';
import { detectPlatform, PLATFORMS } from './domains.js';
import { fixUrl } from './utils.js';
import * as douyin from './parsers/douyin.js';
import * as douyinLive from './parsers/douyin-live.js';
import * as bilibiliLive from './parsers/bilibili-live.js';
import * as huyaLive from './parsers/huya-live.js';
import * as douyuLive from './parsers/douyu-live.js';

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
  tiktok: await import('./parsers/tiktok.js'),
};

const liveModules = {
  douyin: douyinLive,
  bilibili: bilibiliLive,
  huya: huyaLive,
  douyu: douyuLive,
};

function detectLivePlatform(url) {
  if (douyinLive.isDouyinLiveUrl(url)) return 'douyin';
  if (bilibiliLive.isBilibiliLiveUrl(url)) return 'bilibili';
  if (huyaLive.isHuyaLiveUrl(url)) return 'huya';
  if (douyuLive.isDouyuLiveUrl(url)) return 'douyu';
  return null;
}

export function isLiveUrl(url) {
  return detectLivePlatform(url) !== null;
}

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
  if (detectLivePlatform(url)) {
    throw new Error('直播间链接请使用直播解析入口');
  }

  const platform = detectPlatform(url);
  if (!platform) {
    throw new Error('不支持的视频平台');
  }

  if (douyin.isCollectionUrl(url)) {
    throw new Error('抖音合集链接请使用合集解析入口');
  }

  const cacheKey = makeParseKey('parse', url);
  // 微博可以随时切换 Cookie 或宿主登录态，不能复用上一账号的结果。
  const cached = platform === 'weibo' ? null : cacheGet(cacheKey);
  if (cached && !options.force) return cached;

  const parser = modules[platform];
  const result = normalizeResult(platform, await parser.parse(url));
  if (platform !== 'weibo') cacheSet(cacheKey, result, 86400);
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

export async function parseCollection(url, cursor = 0) {
  const platform = detectPlatform(url);
  if (platform !== 'douyin') {
    throw new Error('目前仅支持抖音合集解析');
  }
  const result = await douyin.parseCollection(url, cursor);
  return {
    ...result,
    items: Array.isArray(result.items)
      ? result.items.map((item) => normalizeResult('douyin', item))
      : [],
  };
}

// 直播解析：直播间地址 → 直播间信息 + flv/hls 拉流地址列表（不做录制）
export async function parseLive(url) {
  const platform = detectLivePlatform(url);
  if (!platform) {
    throw new Error('不支持的直播平台');
  }
  return liveModules[platform].parse(url);
}

// 发现：平台公开热榜、关键词搜索与单视频公开评论（仅用户主动查询，按需分页，不落库）
export { hotList, search, comments } from './discovery.js';

export { setTransport } from './transport.js';
export { setWeiboCookie, fixUrl, crc32, createZipBlob } from './utils.js';
export { generateABogus } from './a-bogus.js';
export { detectPlatform };
