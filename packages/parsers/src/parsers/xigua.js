// 西瓜视频解析器：video/info 接口，视频地址 base64 编码存放于 video_list
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { desktop } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return { 'User-Agent': desktop(), Referer: 'https://www.ixigua.com/' };
}

function decodeMainUrl(mainUrl) {
  if (!mainUrl) return '';
  try {
    const decoded = atob(mainUrl);
    return /^https?:\/\//i.test(decoded) ? decoded : mainUrl;
  } catch {
    return mainUrl;
  }
}

function extractVideoItem(payload) {
  const data = payload?.data;
  return payload?.video_info || (data?.video_info || data) || null;
}

function extractMainUrl(data) {
  const videoList = data?.video?.video_list || {};
  const qualityOrder = ['video_list_4k', 'video_list_3', 'video_list_2', 'video_list_1', 'video_list_0'];
  for (const key of qualityOrder) {
    if (videoList[key]?.main_url) return videoList[key].main_url;
  }
  const entries = Object.values(videoList);
  for (let index = entries.length - 1; index >= 0; index -= 1) {
    if (entries[index]?.main_url) return entries[index].main_url;
  }
  return '';
}

function parseRouterItem(html) {
  const match = String(html || '').match(/window\._ROUTER_DATA\s*=\s*({[\s\S]*?});\s*</i);
  if (!match) return null;
  const state = parseJson(match[1]);
  return state?.loaderData?.['video_(id)/page']?.videoInfoRes?.item_list?.[0] || null;
}

function buildResult(item) {
  const videoUrl = item?.video?.play_addr?.url_list?.[0] || '';
  if (!videoUrl) return null;
  return {
    author: item.author?.nickname || '',
    uid: item.author?.user_id ?? item.author?.unique_id ?? '',
    avatar: fixUrl(item.author?.avatar_thumb?.url_list?.[0] || ''),
    like: item.statistics?.digg_count ?? 0,
    time: item.create_time ?? 0,
    title: item.desc || '',
    cover: fixUrl(item.video?.cover?.url_list?.[0] || item.video?.origin_cover?.url_list?.[0] || ''),
    url: fixUrl(videoUrl.replace(/playwm/g, 'play')),
  };
}

function parseHydratedVideo(html) {
  const match = String(html || '').match(/window\._SSR_HYDRATED_DATA\s*=\s*({[^<]*})[^<]*<\/script>/i);
  if (!match) return null;
  const state = parseJson(match[1]);
  const videoList = state?.anyVideo?.gidInformation?.packerData?.video?.videoResource?.normal?.video_list || {};
  const entries = Object.values(videoList).filter((entry) => entry?.main_url);
  entries.sort((left, right) => Number(right.vheight || 0) - Number(left.vheight || 0));
  const selected = entries[0];
  if (!selected?.main_url) return null;
  const videoUrl = decodeMainUrl(selected.main_url).replace(/^http:\/\//i, 'https://');
  if (!/^https?:\/\//i.test(videoUrl)) return null;
  const info = state?.anyVideo?.gidInformation || {};
  return {
    title: info.title || '',
    author: info.author?.name || '',
    uid: info.author?.id ?? '',
    avatar: fixUrl(info.author?.avatar || ''),
    cover: fixUrl(selected.poster_url || info.cover_url || ''),
    url: videoUrl,
    media_type: 'video',
    images: [],
  };
}

function concatBytes(...parts) {
  const size = parts.reduce((total, part) => total + part.length, 0);
  const result = new Uint8Array(size);
  let offset = 0;
  for (const part of parts) {
    result.set(part, offset);
    offset += part.length;
  }
  return result;
}

// CryptoJS 的 passphrase AES 使用 OpenSSL EVP_BytesToKey（MD5）派生 AES-256-CBC。
// 这里仅实现解码公开页面中的媒体地址，不执行页面返回的任何脚本。
function md5(input) {
  const data = input instanceof Uint8Array ? input : new Uint8Array(input);
  const bitLength = data.length * 8;
  const paddedLength = Math.ceil((data.length + 9) / 64) * 64;
  const padded = new Uint8Array(paddedLength);
  padded.set(data);
  padded[data.length] = 0x80;
  const view = new DataView(padded.buffer);
  view.setUint32(paddedLength - 8, bitLength >>> 0, true);
  view.setUint32(paddedLength - 4, Math.floor(bitLength / 0x100000000) >>> 0, true);

  const shifts = [
    7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22,
    5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20,
    4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23,
    6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21,
  ];
  const constants = Array.from({ length: 64 }, (_, index) => Math.floor(Math.abs(Math.sin(index + 1)) * 0x100000000) >>> 0);
  const rotate = (value, amount) => (value << amount) | (value >>> (32 - amount));
  let a0 = 0x67452301;
  let b0 = 0xefcdab89;
  let c0 = 0x98badcfe;
  let d0 = 0x10325476;

  for (let offset = 0; offset < padded.length; offset += 64) {
    const words = new Uint32Array(16);
    for (let index = 0; index < 16; index += 1) words[index] = view.getUint32(offset + index * 4, true);
    let a = a0;
    let b = b0;
    let c = c0;
    let d = d0;
    for (let index = 0; index < 64; index += 1) {
      let f;
      let g;
      if (index < 16) { f = (b & c) | (~b & d); g = index; }
      else if (index < 32) { f = (d & b) | (~d & c); g = (5 * index + 1) % 16; }
      else if (index < 48) { f = b ^ c ^ d; g = (3 * index + 5) % 16; }
      else { f = c ^ (b | ~d); g = (7 * index) % 16; }
      const next = (a + f + constants[index] + words[g]) >>> 0;
      const rotated = rotate(next, shifts[index]);
      a = d;
      d = c;
      c = b;
      b = (b + rotated) >>> 0;
    }
    a0 = (a0 + a) >>> 0;
    b0 = (b0 + b) >>> 0;
    c0 = (c0 + c) >>> 0;
    d0 = (d0 + d) >>> 0;
  }

  const digest = new Uint8Array(16);
  const result = new DataView(digest.buffer);
  result.setUint32(0, a0, true);
  result.setUint32(4, b0, true);
  result.setUint32(8, c0, true);
  result.setUint32(12, d0, true);
  return digest;
}

async function decodeEncryptedUrl(value) {
  if (!value || !globalThis.crypto?.subtle || typeof atob !== 'function') return '';
  try {
    const reversed = [...String(value)].reverse().join('');
    const binary = atob(reversed);
    const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));
    if (bytes.length <= 16 || new TextDecoder().decode(bytes.slice(0, 8)) !== 'Salted__') return '';
    const password = new TextEncoder().encode('xigua.fe.web_mobile');
    const salt = bytes.slice(8, 16);
    const cipher = bytes.slice(16);
    const blocks = [];
    let previous = new Uint8Array(0);
    while (blocks.reduce((total, block) => total + block.length, 0) < 48) {
      previous = md5(concatBytes(previous, password, salt));
      blocks.push(previous);
    }
    const keyIv = concatBytes(...blocks).slice(0, 48);
    const key = await crypto.subtle.importKey('raw', keyIv.slice(0, 32), { name: 'AES-CBC' }, false, ['decrypt']);
    const plain = await crypto.subtle.decrypt({ name: 'AES-CBC', iv: keyIv.slice(32, 48) }, key, cipher);
    const url = new TextDecoder().decode(plain).trim();
    return /^https?:\/\//i.test(url) ? url : '';
  } catch {
    return '';
  }
}

async function parseSsrVideo(html) {
  const match = String(html || '').match(/window\._SSR_DATA\s*=\s*({[\s\S]*?});?\s*</i);
  if (!match) return null;
  const state = parseJson(match[1]);
  const info = state?.data?.storeState?.detail?.videoData?.result;
  const url = await decodeEncryptedUrl(info?.url);
  if (!url) return null;
  return {
    author: info.media_user?.screen_name || '',
    uid: info.media_user?.id ?? '',
    avatar: fixUrl(info.media_user?.avatar_url || ''),
    title: info.title || info.abstract || '',
    cover: fixUrl(info.cover_image_url || ''),
    url,
    media_type: 'video',
    images: [],
  };
}

export async function parse(url) {
  const target = (await resolveShortLink(url, headers())) || url;

  const videoId =
    target.match(/\/(?:video|item)\/(\d+)/i)?.[1] ||
    target.match(/\b(\d{10,25})\b/)?.[1];
  if (!videoId) {
    throw new Error('无法解析西瓜视频 ID');
  }

  let data = null;
  for (const [key, value] of [['vid', videoId], ['video_id', videoId]]) {
    const endpoint = new URL('https://www.ixigua.com/api/video/info');
    endpoint.searchParams.set(key, value);
    const response = await fetchOk(endpoint.href, null, headers(), { maxRetries: 0 });
    const json = response ? parseJson(response.data) : null;
    const candidate = extractVideoItem(json);
    if (candidate) {
      data = candidate;
      const mainUrl = extractMainUrl(candidate);
      if (mainUrl) {
        return {
          author: candidate.user_info?.name || candidate.author?.screen_name || '',
          uid: candidate.user_info?.user_id ?? candidate.author?.id ?? '',
          avatar: fixUrl(candidate.avatar_url || ''),
          like: candidate.statistics?.digg_count ?? candidate.digg_count ?? 0,
          time: candidate.create_time ?? candidate.createTime ?? 0,
          title: candidate.title || candidate.video?.title || '',
          cover: fixUrl(candidate.video?.poster_url || candidate.cover_url || ''),
          url: fixUrl(decodeMainUrl(mainUrl)),
        };
      }
    }
  }

  const pageResponse = await fetchOk(target, null, headers(), { maxRetries: 0 });
  const ssrResult = await parseSsrVideo(pageResponse?.data);
  if (ssrResult) return ssrResult;

  // 旧接口下线时，西瓜分享页仍可能内嵌抖音兼容的 ROUTER_DATA。
  const shareUrl = new URL(`https://m.ixigua.com/douyin/share/video/${videoId}`);
  shareUrl.search = 'aweme_type=107&schema_type=1&utm_source=copy&utm_campaign=client_share&utm_medium=android&app=aweme';
  const shareResponse = await fetchOk(shareUrl.href, null, headers(), { maxRetries: 0 });
  const fallback = buildResult(parseRouterItem(shareResponse?.data));
  if (!fallback) {
    const hydrated = parseHydratedVideo(shareResponse?.data);
    if (hydrated) return hydrated;
    throw new Error('获取西瓜视频信息失败');
  }
  return fallback;
}
