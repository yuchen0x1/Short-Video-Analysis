// 小红书解析器：SSR 页面 __INITIAL_STATE__ 提取（无签名依赖）
// 注意：真实页面的状态对象里含有 JS undefined 字面量，需清洗后 JSON.parse
import { fetchOk, resolveShortLink } from '../http.js';
import { mobile } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return { 'User-Agent': mobile(), Referer: 'https://www.xiaohongshu.com/' };
}

function parseInitialState(html) {
  // explore 页状态以 "};<" 结尾，discovery 分享页无分号，统一放宽为可选分号
  const match = html.match(/window\.__INITIAL_STATE__\s*=\s*({[\s\S]*?});?\s*</);
  if (!match) return null;
  try {
    // 页面状态里存在 undefined 值（PHP json_decode 同样无法处理），统一替换为 null
    return JSON.parse(match[1].replace(/undefined/g, 'null'));
  } catch {
    return null;
  }
}

function unwrapRedirect(url) {
  try {
    const parsed = new URL(url);
    // 部分 /404 链接把 redirectPath 放在 source 参数内部的第二个 ? 后面，
    // 标准 URLSearchParams 不会把它识别为顶层参数，因此同时做原始 URL 兜底。
    const rawUrl = String(url);
    const rawRedirectMatch = rawUrl.match(/[?&]redirectPath=([^&]+)/i);
    const redirectPath = parsed.searchParams.get('redirectPath') ||
      (rawRedirectMatch ? decodeURIComponent(rawRedirectMatch[1]) : '') ||
      parsed.searchParams.get('originalUrl') || '';
    if (redirectPath && /^https?:\/\//i.test(redirectPath)) {
      return redirectPath;
    }
  } catch {
    // 保留原始链接，交给后续 ID 提取逻辑处理
  }
  return url;
}

// /explore/ 页数据在 note.noteDetailMap，/discovery/item/ 分享页在 noteData.data.noteData，
// 且字段命名不同（nickName/masterUrl 为 camelCase），统一在此兼容。
function pickNoteData(state, noteId) {
  const wrapper = state?.note?.noteDetailMap?.[noteId];
  if (wrapper?.note || wrapper) return wrapper.note || wrapper;
  for (const value of Object.values(state?.note || {})) {
    if (value && typeof value === 'object' && value.note) return value.note;
  }
  const discoveryNote = state?.noteData?.data?.noteData;
  if (discoveryNote && typeof discoveryNote === 'object' && (discoveryNote.noteId || discoveryNote.imageList || discoveryNote.video)) {
    return discoveryNote;
  }
  return null;
}

function pickVideoUrl(noteData) {
  const video = noteData?.video;
  if (!video) return '';
  const stream = video.media?.stream;
  if (stream) {
    if (stream.master_url) return stream.master_url;
    for (const codec of [stream.h264, stream.h265, stream.h266, stream.av1]) {
      if (!Array.isArray(codec)) continue;
      for (const item of codec) {
        const url = item?.masterUrl || item?.master_url;
        if (url) return url;
      }
    }
  }
  const originKey = video.consumer?.origin_url_key || video.consumer?.originVideoKey;
  if (originKey && video[originKey]) return video[originKey];
  return video.url || '';
}

export async function parse(url) {
  const resolved = (await resolveShortLink(url, headers())) || url;
  // xhslink 短链在匿名访问时可能落到 /404，但会在 redirectPath 中保留带
  // xsec_token 的真实笔记链接；必须解包并保留查询参数，否则页面无法访问。
  const target = unwrapRedirect(resolved);

  const noteId =
    target.match(/explore\/([a-f0-9]+)/i)?.[1] ||
    target.match(/discovery\/item\/([a-f0-9]+)/i)?.[1] ||
    url.match(/explore\/([a-f0-9]+)/i)?.[1] ||
    url.match(/discovery\/item\/([a-f0-9]+)/i)?.[1];
  if (!noteId) {
    throw new Error('无法解析小红书笔记 ID');
  }

  const pageUrl = /(?:\/explore\/|\/discovery\/item\/)/i.test(target)
    ? new URL(target)
    : new URL(noteId, 'https://www.xiaohongshu.com/explore/');
  const response = await fetchOk(pageUrl.href, null, headers());
  if (!response) {
    throw new Error('获取小红书笔记页面失败');
  }

  const state = parseInitialState(typeof response.data === 'string' ? response.data : '');
  const noteData = state ? pickNoteData(state, noteId) : null;
  if (!noteData) {
    if (/\/404(?:\/|$)/i.test(response.url || '') || /(?:error_code|verifyMsg)=/i.test(response.url || '')) {
      throw new Error('小红书笔记当前无法浏览，可能需要登录或分享链接已失效');
    }
    throw new Error('未找到小红书笔记数据');
  }

  const videoUrl = pickVideoUrl(noteData);

  // 图文笔记没有视频流，从 imageList 提取全部图片
  const images = (Array.isArray(noteData.imageList) ? noteData.imageList : [])
    .map((image) => fixUrl(image?.urlDefault || image?.url || image?.liveImage || ''))
    .filter((url) => /^https?:\/\//i.test(url));
  if (!videoUrl && images.length === 0) {
    throw new Error('未能获取该笔记的视频或图片，笔记可能需要登录后查看');
  }

  const user = noteData.user || {};
  return {
    author: user.nickname || user.nickName || '',
    uid: user.userId ?? '',
    avatar: user.avatar || '',
    like: noteData.likedCount ?? noteData.interactInfo?.likedCount ?? 0,
    time: noteData.time ?? noteData.createTime ?? 0,
    title: noteData.title || noteData.desc || '',
    cover: noteData.imageList?.[0]?.urlDefault || noteData.cover?.urlDefault || images[0] || '',
    url: fixUrl(videoUrl || images[0]),
    media_type: videoUrl ? 'video' : 'image',
    images: videoUrl ? [] : images,
  };
}
