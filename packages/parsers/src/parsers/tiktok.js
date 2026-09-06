// TikTok 国际版解析：embed 页 → 视频页水合数据 → 移动端 feed 接口 三级回退
// 均不依赖签名算法；数据形状有两类：web itemStruct（camelCase）与 app aweme（snake_case）
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { fixUrl } from '../utils.js';
import { mobile } from '../ua.js';

const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

const PAGE_HEADERS = {
  'User-Agent': DESKTOP_UA,
  Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
  'Accept-Language': 'en-US,en;q=0.9',
};

export function isShortLink(url) {
  return /https?:\/\/v[mt]\.tiktok\.com\//i.test(url);
}

export function isTiktokUrl(url) {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    return false;
  }
  host = host.toLowerCase().replace(/^www\./, '');
  return host === 'tiktok.com' || host.endsWith('.tiktok.com');
}

// 短链需要一次重定向解析；其余链接直接匹配 /video/、/photo/ 或 /v/ 段
export async function extractVideoId(url) {
  // 仅识别 TikTok 域名，其他平台的 /video/<数字> 不归本解析器
  if (!isTiktokUrl(url) && !isShortLink(url)) return null;

  const direct = url.match(/\/(?:video|photo|v)\/(\d{10,25})/i)?.[1];
  if (direct) return direct;

  if (isShortLink(url)) {
    const location = await resolveShortLink(url, { 'User-Agent': mobile() });
    if (location) {
      return location.match(/\/(?:video|photo|v)\/(\d{10,25})/i)?.[1] ?? null;
    }
  }

  return null;
}

// 从页面 HTML 提取 TikTok 水合数据：__UNIVERSAL_DATA_FOR_REHYDRATION__ 或 window.__DEFAULT_SCOPE__
function extractUniversalData(html) {
  const universal = html.match(/<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>([\s\S]*?)<\/script>/);
  if (universal) {
    const data = parseJson(universal[1].trim());
    if (data) return data;
  }
  const scope = html.match(/window\.__DEFAULT_SCOPE__\s*=\s*(\{[\s\S]*?\});?\s*<\/script>/);
  if (scope) {
    return parseJson(scope[1].trim());
  }
  return null;
}

// 递归查找 id 匹配且带 video/imagePost 的作品对象（web itemStruct），限深防卡
function findItemStruct(node, videoId, depth = 0) {
  if (!node || typeof node !== 'object' || depth > 12) return null;
  if (Array.isArray(node)) {
    for (const child of node) {
      const found = findItemStruct(child, videoId, depth + 1);
      if (found) return found;
    }
    return null;
  }
  if (String(node.id ?? '') === videoId && (node.video || node.imagePost)) {
    return node;
  }
  for (const value of Object.values(node)) {
    if (value && typeof value === 'object') {
      const found = findItemStruct(value, videoId, depth + 1);
      if (found) return found;
    }
  }
  return null;
}

function firstOf(urlList) {
  const value = Array.isArray(urlList) ? urlList.find((u) => typeof u === 'string' && u !== '') : null;
  return value ? fixUrl(value) : '';
}

// web itemStruct：playAddr 无水印；bitrateInfo 按码率取最高
function pickWebVideoUrl(item) {
  const bitrates = Array.isArray(item.video?.bitrateInfo) ? [...item.video.bitrateInfo] : [];
  bitrates.sort((a, b) => (b.Bitrate ?? 0) - (a.Bitrate ?? 0));
  for (const entry of bitrates) {
    const url = firstOf(entry.PlayAddr?.UrlList);
    if (url) return url;
  }
  const playAddr = typeof item.video?.playAddr === 'string' ? item.video.playAddr : '';
  return playAddr ? fixUrl(playAddr) : '';
}

function webItemToResult(item) {
  const images = Array.isArray(item.imagePost?.images)
    ? item.imagePost.images.map((img) => firstOf(img?.imageURL?.urlList)).filter(Boolean)
    : [];
  const isImage = images.length > 0;
  const url = isImage ? images[0] : pickWebVideoUrl(item);
  if (!url) {
    throw new Error('未找到 TikTok 媒体地址');
  }

  return {
    author: item.author?.nickname ?? '',
    uid: item.author?.uniqueId ?? '',
    avatar:
      firstOf([item.author?.avatarLarger]) ||
      firstOf([item.author?.avatarMedium]) ||
      firstOf([item.author?.avatarThumb]),
    like: item.stats?.diggCount ?? 0,
    time: Number(item.createTime ?? 0) || 0,
    title: item.desc ?? '',
    cover: isImage
      ? images[0]
      : firstOf([item.video?.cover, item.video?.originCover, item.video?.dynamicCover]),
    url,
    media_type: isImage ? 'image' : 'video',
    images,
    music: {
      author: item.music?.authorName ?? '',
      title: item.music?.title ?? '',
      avatar: firstOf([item.music?.coverLarger, item.music?.coverThumb]),
      url: typeof item.music?.playUrl === 'string' && item.music.playUrl !== '' ? fixUrl(item.music.playUrl) : '',
    },
  };
}

// app aweme（snake_case，与抖音同构）
function awemeToResult(item) {
  const images = Array.isArray(item.image_post_info?.images)
    ? item.image_post_info.images
        .map((img) => firstOf(img?.display_image?.url_list) || firstOf(img?.url_list))
        .filter(Boolean)
    : [];
  const isImage = images.length > 0;

  let url = '';
  if (!isImage) {
    url = firstOf(item.video?.play_addr?.url_list);
    if (!url) {
      const bitRates = Array.isArray(item.video?.bit_rate) ? [...item.video.bit_rate] : [];
      bitRates.sort((a, b) => (b.bit_rate ?? 0) - (a.bit_rate ?? 0));
      for (const entry of bitRates) {
        url = firstOf(entry?.play_addr?.url_list);
        if (url) break;
      }
    }
  }
  if (!url) {
    throw new Error('未找到 TikTok 媒体地址');
  }

  return {
    author: item.author?.nickname ?? '',
    uid: String(item.author?.uid ?? item.author?.unique_id ?? ''),
    avatar: firstOf(item.author?.avatar_medium?.url_list) || firstOf(item.author?.avatar_thumb?.url_list),
    like: item.statistics?.digg_count ?? 0,
    time: Number(item.create_time ?? 0) || 0,
    title: item.desc ?? '',
    cover: isImage ? images[0] : firstOf(item.video?.cover?.url_list) || firstOf(item.video?.origin_cover?.url_list),
    url,
    media_type: isImage ? 'image' : 'video',
    images,
    music: {
      author: item.music?.author ?? '',
      title: item.music?.title ?? '',
      avatar: firstOf(item.music?.cover_large?.url_list) || firstOf(item.music?.cover_thumb?.url_list),
      url: firstOf(item.music?.play_url?.url_list) || firstOf(item.music?.download_url?.url_list),
    },
  };
}

async function fetchJsonPage(pageUrl) {
  const response = await fetchOk(pageUrl, null, PAGE_HEADERS, { maxRetries: 1 });
  if (!response || typeof response.data !== 'string') return null;
  return extractUniversalData(response.data);
}

// 移动端 feed 接口：历史上无需签名，作为前两条路径的兜底
async function fetchFeedApi(videoId) {
  const query = new URLSearchParams({
    aweme_id: videoId,
    version_code: '262.1.0',
    app_name: 'musical_ly',
    channel: 'App',
    device_id: 'null',
    os_version: '14.4.2',
    device_platform: 'iphone',
    device_type: 'iPhone9,3',
  });
  const response = await fetchOk(
    'https://api16-normal-c-useast1a.tiktokv.com/aweme/v1/feed/?' + query.toString(),
    null,
    { 'User-Agent': mobile() },
    { maxRetries: 1 }
  );
  if (!response) return null;
  const json = parseJson(response.data);
  const item = json?.aweme_list?.[0];
  return item && typeof item === 'object' ? awemeToResult(item) : null;
}

export async function parse(url) {
  const videoId = await extractVideoId(url);
  if (!videoId) {
    throw new Error('无法识别 TikTok 视频链接');
  }

  // 路径 1：embed 页（体积小、风控弱）
  try {
    const embedData = await fetchJsonPage('https://www.tiktok.com/embed/v2/' + videoId);
    const embedItem = embedData ? findItemStruct(embedData, videoId) : null;
    if (embedItem) {
      return webItemToResult(embedItem);
    }
  } catch {
    // 继续下一条路径
  }

  // 路径 2：完整视频页水合数据
  try {
    const pageData = await fetchJsonPage('https://www.tiktok.com/@tiktok/video/' + videoId);
    const pageItem = pageData ? findItemStruct(pageData, videoId) : null;
    if (pageItem) {
      return webItemToResult(pageItem);
    }
  } catch {
    // 继续下一条路径
  }

  // 路径 3：移动端 feed 接口
  const fromFeed = await fetchFeedApi(videoId);
  if (fromFeed) {
    return fromFeed;
  }

  throw new Error('解析 TikTok 视频信息失败，视频可能已删除或接口暂时受限');
}
