// 抖音解析器：ttwid 获取 + a_bogus 签名 + 视频详情/主页分页
// 自 PHP app/Parsers/DouyinParser.php 移植，请求流程与字段提取保持一致
import { generateABogus } from '../a-bogus.js';
import { cacheDelete, cacheGet, cacheSet, makeParseKey } from '../cache.js';
import { fetchOk, httpGet, parseJson, resolveShortLink } from '../http.js';
import { mobile } from '../ua.js';
import { fixUrl, firstUrl } from '../utils.js';

const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

const session = { ttwid: null, msToken: null, verifyFp: null, whaleCutToken: null, webId: null };

function profileHeaders(secUserId, ttwid) {
  return {
    'User-Agent': DESKTOP_UA,
    Accept: 'application/json, text/plain, */*',
    'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8',
    Referer: 'https://www.douyin.com/user/' + encodeURIComponent(secUserId),
    Cookie: `ttwid=${ttwid}; s_v_web_id=${session.verifyFp}; msToken=${session.msToken}`,
    'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'same-origin',
  };
}

function detailHeaders(videoId, ttwid) {
  return {
    'User-Agent': DESKTOP_UA,
    Accept: 'application/json, text/plain, */*',
    'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8',
    Referer: 'https://www.douyin.com/video/' + videoId,
    Cookie: `ttwid=${ttwid}`,
    'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'same-origin',
  };
}

async function getTtwid() {
  const body = JSON.stringify({
    region: 'cn',
    aid: 6383,
    need_t: 1,
    service: 'www.douyin.com',
    migrate_priority: 0,
    cb_url_protocol: 'https',
    domain: '.douyin.com',
  });
  const response = await fetchOk('https://ttwid.bytedance.com/ttwid/union/register/', body, {
    'User-Agent': DESKTOP_UA,
    'Content-Type': 'application/json',
  }, { maxRetries: 1 });
  if (!response) return null;

  const setCookie = response.headers['set-cookie'] || '';
  const match = setCookie.match(/ttwid=([^;\s]+)/i);
  return match ? decodeURIComponent(match[1]) : null;
}

function randomToken(length) {
  const chars = 'ABCDEFGHIGKLMNOPQRSTUVWXYZabcdefghigklmnopqrstuvwxyz0123456789=';
  let result = '';
  const cryptoObj = globalThis.crypto;
  if (cryptoObj?.getRandomValues) {
    const bytes = new Uint8Array(length);
    cryptoObj.getRandomValues(bytes);
    for (let i = 0; i < length; i += 1) result += chars[bytes[i] % chars.length];
  } else {
    for (let i = 0; i < length; i += 1) result += chars[Math.floor(Math.random() * chars.length)];
  }
  return result;
}

function randomVerifyFp() {
  const alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  const timestamp = Math.floor(Date.now()).toString(36);
  let uuid = '';
  for (let i = 0; i < 36; i += 1) {
    if ([8, 13, 18, 23].includes(i)) {
      uuid += '_';
      continue;
    }
    if (i === 14) {
      uuid += '4';
      continue;
    }
    let value = Math.floor(Math.random() * alphabet.length);
    if (i === 19) {
      value = (3 & value) | 8;
    }
    uuid += alphabet[value];
  }
  return `verify_${timestamp}_${uuid}`;
}

function signedUrl(base, params) {
  const search = new URLSearchParams(params);
  const query = search.toString();
  const aBogus = generateABogus(query, DESKTOP_UA);
  if (!aBogus) {
    throw new Error('抖音签名生成失败');
  }
  const url = new URL(base);
  url.search = query + '&a_bogus=' + encodeURIComponent(aBogus);
  return url.href;
}

function headerValue(headers, name) {
  const key = name.toLowerCase();
  const value = headers[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function isAudioUrl(url) {
  const path = (() => {
    try {
      return new URL(url, 'https://example.com').pathname.toLowerCase();
    } catch {
      return String(url).toLowerCase();
    }
  })();
  return /\.(?:mp3|m4a|aac|wav|ogg|flac)$/.test(path);
}

// download_addr / v1/play 中转地址带 watermark=1；只有 douyinvod 等直链才是无水印源
function isWatermarkedUrl(url) {
  return url.includes('watermark=1') || url.includes('/aweme/v1/play/');
}

function pickVideoUrl(item) {
  // 优先使用视频 uri 走无水印播放接口；play_addr 在部分响应中本身就是带水印流
  const uri = item.video?.play_addr?.uri || '';
  if (uri !== '' && !isAudioUrl(uri)) {
    const playUrl = new URL('https://www.douyin.com/aweme/v1/play/');
    playUrl.searchParams.set('video_id', uri);
    playUrl.searchParams.set('ratio', '1080p');
    playUrl.searchParams.set('line', '0');
    playUrl.searchParams.set('watermark', '0');
    return playUrl.href;
  }

  const candidates = [];
  const playUrls = item.video?.play_addr?.url_list;
  if (Array.isArray(playUrls) && playUrls[0]) {
    candidates.push(playUrls[0]);
  }

  const bitRates = Array.isArray(item.video?.bit_rate) ? [...item.video.bit_rate] : [];
  bitRates.sort((a, b) => (b.bit_rate ?? 0) - (a.bit_rate ?? 0));
  for (const bitRate of bitRates) {
    const urls = bitRate?.play_addr?.url_list;
    if (Array.isArray(urls) && urls[0]) {
      candidates.push(urls[0]);
    }
  }

  for (const candidate of candidates.map(fixUrl)) {
    if (candidate !== '' && !isAudioUrl(candidate) && !isWatermarkedUrl(candidate)) {
      return candidate;
    }
  }

  throw new Error('未找到视频 URL');
}

// 图集作品没有可播放的视频地址；download_url_list 可能是带水印地址，不能用于无水印结果
function pickImageUrls(item) {
  const groups = [item.images, item.image_post_info?.images];
  const images = [];
  for (const group of groups) {
    if (!Array.isArray(group)) continue;
    for (const image of group) {
      if (!image || typeof image !== 'object') continue;
      const url = firstUrl(image.url_list) ?? firstUrl(image.display_image?.url_list);
      if (url && !images.includes(url)) {
        images.push(url);
      }
    }
  }
  return images;
}

function buildResult(item) {
  const images = pickImageUrls(item);
  const isImagePost = images.length > 0;
  const mediaUrlValue = isImagePost ? images[0] : pickVideoUrl(item);

  return {
    author: item.author?.nickname ?? '',
    uid: item.author?.uid ?? item.author?.unique_id ?? '',
    avatar:
      firstUrl(item.author?.avatar_medium?.url_list) ??
      firstUrl(item.author?.avatar_thumb?.url_list) ??
      firstUrl(item.author?.avatar_larger?.url_list) ??
      '',
    like: item.statistics?.digg_count ?? 0,
    time: item.create_time ?? 0,
    title: item.desc ?? '',
    cover: isImagePost
      ? images[0]
      : firstUrl(item.video?.cover?.url_list) ?? firstUrl(item.video?.origin_cover?.url_list) ?? '',
    url: mediaUrlValue,
    media_type: isImagePost ? 'image' : 'video',
    images,
    music: {
      author: item.music?.author ?? '',
      title: item.music?.title ?? '',
      avatar:
        firstUrl(item.music?.cover_large?.url_list) ??
        firstUrl(item.music?.cover_thumb?.url_list) ??
        '',
      url:
        firstUrl(item.music?.play_url?.url_list) ??
        firstUrl(item.music?.download_url?.url_list) ??
        '',
    },
  };
}

async function fetchAwemeDetail(videoId) {
  const ttwid = await getTtwid();
  if (!ttwid) return null;

  const url = signedUrl('https://www.douyin.com/aweme/v1/web/aweme/detail/', {
    device_platform: 'webapp',
    aid: '6383',
    channel: 'channel_pc_web',
    aweme_id: videoId,
    msToken: randomToken(107),
  });

  const response = await fetchOk(url, null, detailHeaders(videoId, ttwid), { maxRetries: 1 });
  const json = response ? parseJson(response.data) : null;
  return json?.aweme_detail ?? null;
}

async function fetchLegacy(videoId) {
  const response = await fetchOk(
    'https://www.iesdouyin.com/share/video/' + videoId,
    null,
    { 'User-Agent': mobile() },
    { maxRetries: 1 }
  );
  if (!response || typeof response.data !== 'string') return null;
  const match = response.data.match(/window\._ROUTER_DATA\s*=\s*([\s\S]*?)<\/script>/);
  if (!match) return null;
  const data = parseJson(match[1].trim());
  return data?.loaderData?.['video_(id)/page']?.videoInfoRes?.item_list?.[0] ?? null;
}

async function extractVideoId(url) {
  const direct = url.match(/\/(?:video|note|share\/video)\/(\d+)/i)?.[1];
  if (direct) return direct;

  if (/https?:\/\/v\.douyin\.com\//i.test(url)) {
    const location = await resolveShortLink(url, { 'User-Agent': mobile() });
    if (location) {
      const fromLocation = location.match(/\/(?:video|note|share\/video)\/(\d+)/i)?.[1];
      if (fromLocation) return fromLocation;
    }
    const response = await fetchOk(url, null, { 'User-Agent': mobile() }, { maxRetries: 1 });
    if (response && typeof response.data === 'string') {
      const fromBody = response.data.match(/\/(?:video|note|share\/video)\/(\d+)/i)?.[1];
      if (fromBody) return fromBody;
    }
  }

  return url.match(/\b\d{15,25}\b/)?.[0] || null;
}

async function extractSecUserId(url) {
  const fromPath = url.match(/\/user\/([^/?#]+)/i)?.[1];
  if (fromPath) return decodeURIComponent(fromPath);

  if (/https?:\/\/v\.douyin\.com\//i.test(url)) {
    const location = await resolveShortLink(url, { 'User-Agent': mobile() });
    if (location && location !== url) {
      return extractSecUserId(location);
    }
  }

  try {
    const parsed = new URL(url);
    const candidate = parsed.searchParams.get('sec_user_id') || parsed.searchParams.get('sec_uid');
    if (candidate && candidate.trim() !== '') {
      return candidate.trim();
    }
  } catch {
    // 忽略 URL 解析失败，继续兜底
  }

  return url.match(/\bMS4w[^\s/?#]+/i)?.[0] || null;
}

function loadSession(secUserId) {
  const cached = cacheGet(makeParseKey('douyin-profile-session', secUserId)) || {};
  if (!session.ttwid && typeof cached.ttwid === 'string') session.ttwid = cached.ttwid;
  if (!session.msToken && typeof cached.ms_token === 'string') session.msToken = cached.ms_token;
  if (!session.verifyFp && typeof cached.verify_fp === 'string') session.verifyFp = cached.verify_fp;
  if (!session.whaleCutToken && typeof cached.whale_cut_token === 'string') session.whaleCutToken = cached.whale_cut_token;
  if (!session.webId && typeof cached.web_id === 'string') session.webId = cached.web_id;
}

function persistSession(secUserId, ttwid) {
  cacheSet(makeParseKey('douyin-profile-session', secUserId), {
    ttwid,
    ms_token: session.msToken,
    verify_fp: session.verifyFp,
    whale_cut_token: session.whaleCutToken,
    web_id: session.webId,
  }, 1800);
}

function resetSession() {
  session.ttwid = null;
  session.msToken = null;
  session.verifyFp = null;
  session.whaleCutToken = null;
  session.webId = null;
}

async function fetchProfilePage(secUserId, cursor) {
  loadSession(secUserId);
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  for (let attempt = 0; attempt < 3; attempt += 1) {
    if (!session.ttwid) {
      session.ttwid = await getTtwid();
    }
    const ttwid = session.ttwid;
    if (!ttwid) {
      await sleep(150 * (attempt + 1));
      continue;
    }

    if (!session.msToken) session.msToken = randomToken(107);
    if (!session.verifyFp) session.verifyFp = randomVerifyFp();
    persistSession(secUserId, ttwid);

    const url = signedUrl('https://www.douyin.com/aweme/v1/web/aweme/post/', {
      device_platform: 'webapp',
      aid: '6383',
      channel: 'channel_pc_web',
      verifyFp: session.verifyFp,
      fp: session.verifyFp,
      webid: session.webId || '',
      sec_user_id: secUserId,
      count: '18',
      max_cursor: cursor > 0 ? String(cursor) : '',
      locate_query: 'false',
      show_live_replay_strategy: '1',
      need_time_list: '1',
      time_list_query: '0',
      whale_cut_token: session.whaleCutToken || '',
      cut_version: '1',
      publish_video_strategy_type: '2',
      msToken: session.msToken,
    });

    let response = null;
    try {
      response = await httpGet(url, profileHeaders(secUserId, ttwid), { maxRetries: 1 });
    } catch {
      response = null;
    }

    if (response?.headers) {
      const whaleCutToken = headerValue(response.headers, 'whale_cut_token');
      if (whaleCutToken) session.whaleCutToken = whaleCutToken;
      const webId = headerValue(response.headers, 'cookie_ttwidinfo_webid');
      if (webId) session.webId = webId;
    }
    persistSession(secUserId, ttwid);

    const json = response && response.status === 200 ? parseJson(response.data) : null;
    if (json && Array.isArray(json.aweme_list)) {
      return json;
    }

    if (cursor > 0 && attempt === 0) {
      cacheDelete(makeParseKey('douyin-profile-session', secUserId));
      resetSession();
    }
    await sleep(250 * (attempt + 1));
  }

  return null;
}

export async function parse(url) {
  const videoId = await extractVideoId(url);
  if (!videoId) {
    throw new Error('无法解析视频 ID');
  }

  let item = null;
  let error = '';
  try {
    item = await fetchAwemeDetail(videoId);
  } catch (e) {
    error = e.message || String(e);
  }
  if (item === null) {
    item = await fetchLegacy(videoId);
  }
  if (item === null) {
    throw new Error(error !== '' ? error : '解析视频信息失败，视频可能已删除或接口暂时失效');
  }

  return buildResult(item);
}

export async function parseProfile(url, cursor = 0) {
  const secUserId = await extractSecUserId(url);
  if (!secUserId) {
    throw new Error('无法识别抖音个人主页链接');
  }

  const pageCursor = Math.max(0, cursor);
  const pageData = await fetchProfilePage(secUserId, pageCursor);
  if (!pageData) {
    throw new Error('获取抖音个人主页作品失败，主页可能需要登录或接口暂时失效');
  }

  const items = [];
  const awemeList = Array.isArray(pageData.aweme_list) ? pageData.aweme_list : [];
  let profile = pageData.user ?? pageData.user_info ?? {};

  for (const item of awemeList) {
    if (!item || typeof item !== 'object') continue;
    if (Object.keys(profile).length === 0 && item.author) {
      profile = item.author;
    }
    const result = buildResult(item);
    const awemeId = String(item.aweme_id ?? item.group_id ?? '');
    result.aweme_id = awemeId;
    result.source_url = awemeId !== '' ? 'https://www.douyin.com/video/' + awemeId : '';
    items.push(result);
  }

  return {
    platform: 'douyin',
    profile: {
      uid: profile.uid ?? profile.sec_uid ?? secUserId,
      sec_user_id: profile.sec_uid ?? secUserId,
      author: profile.nickname ?? profile.unique_id ?? '',
      avatar:
        firstUrl(profile.avatar_larger?.url_list) ??
        firstUrl(profile.avatar_medium?.url_list) ??
        firstUrl(profile.avatar_thumb?.url_list) ??
        '',
      signature: profile.signature ?? '',
    },
    items,
    total: items.length,
    cursor: pageCursor,
    next_cursor: Number(pageData.max_cursor ?? pageCursor) || 0,
    has_more: Number(pageData.has_more ?? 0) === 1,
    page_size: items.length,
  };
}
