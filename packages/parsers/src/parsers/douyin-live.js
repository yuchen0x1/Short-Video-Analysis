// 抖音直播解析：webcast/room/web/enter 接口，返回直播间信息与 flv/hls 拉流地址
// 请求流程与 PHP app/Parsers/Live/DouyinLiveParser.php 保持一致，复用抖音 ttwid 与 a_bogus 签名
import { generateABogus } from '../a-bogus.js';
import { fetchOk, parseJson } from '../http.js';
import { fixUrl } from '../utils.js';
import { getTtwid } from './douyin.js';

const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

const QUALITY_NAMES = {
  ORIGINAL: '原画',
  FULL_HD1: '蓝光',
  HD1: '高清',
  SD1: '标清',
  SD2: '流畅',
};

export function isDouyinLiveUrl(url) {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    return false;
  }
  host = host.toLowerCase().replace(/^www\./, '');
  return host === 'live.douyin.com' || host.endsWith('.live.douyin.com');
}

export function extractRoomId(url) {
  const fromPath = url.match(/\/(\d{6,20})(?:[/?#]|$)/i)?.[1];
  if (fromPath) return fromPath;
  return null;
}

function firstUrl(urlList) {
  const value = Array.isArray(urlList) ? urlList[0] : null;
  return typeof value === 'string' && value !== '' ? fixUrl(value) : '';
}

function pickStreams(streamUrl) {
  const streams = [];
  const flvMap = streamUrl?.flv_pull_url;
  if (flvMap && typeof flvMap === 'object') {
    for (const [key, url] of Object.entries(flvMap)) {
      if (typeof url === 'string' && url !== '') {
        streams.push({ format: 'flv', quality: QUALITY_NAMES[key] ?? key, url: fixUrl(url) });
      }
    }
  }
  const hlsMap = streamUrl?.hls_pull_url_map;
  if (hlsMap && typeof hlsMap === 'object') {
    for (const [key, url] of Object.entries(hlsMap)) {
      if (typeof url === 'string' && url !== '') {
        streams.push({ format: 'hls', quality: QUALITY_NAMES[key] ?? key, url: fixUrl(url) });
      }
    }
  }
  // 兜底：仅下发单一直链的老结构
  if (streams.length === 0) {
    const rtmp = typeof streamUrl?.rtmp_pull_url === 'string' ? streamUrl.rtmp_pull_url : '';
    if (rtmp) streams.push({ format: 'flv', quality: '原画', url: fixUrl(rtmp) });
    const hls = typeof streamUrl?.hls_pull_url === 'string' ? streamUrl.hls_pull_url : '';
    if (hls) streams.push({ format: 'hls', quality: '原画', url: fixUrl(hls) });
  }
  return streams;
}

async function fetchRoom(roomId) {
  const ttwid = await getTtwid();
  if (!ttwid) return null;

  const params = new URLSearchParams({
    aid: '6383',
    device_platform: 'webapp',
    browser_language: 'zh-CN',
    browser_platform: 'Win32',
    browser_name: 'Chrome',
    browser_version: '123.0.0.0',
    web_rid: roomId,
  });
  const query = params.toString();
  const aBogus = generateABogus(query, DESKTOP_UA);
  const url = 'https://live.douyin.com/webcast/room/web/enter/?' + query
    + '&a_bogus=' + encodeURIComponent(aBogus);

  const response = await fetchOk(url, null, {
    'User-Agent': DESKTOP_UA,
    Accept: 'application/json, text/plain, */*',
    Referer: 'https://live.douyin.com/' + roomId,
    Cookie: `ttwid=${ttwid}`,
  }, { maxRetries: 1 });

  if (!response) return null;
  const json = parseJson(response.data);
  if (!json || Number(json.status_code) !== 0) return null;

  const payload = json.data ?? {};
  const rooms = payload.data;
  if (Array.isArray(rooms) && rooms[0]) return rooms[0];
  if (rooms && typeof rooms === 'object' && rooms.id_str) return rooms;
  return null;
}

export async function parse(url) {
  const roomId = extractRoomId(url);
  if (!roomId) {
    throw new Error('无法识别抖音直播间链接');
  }

  const room = await fetchRoom(roomId);
  if (!room) {
    throw new Error('获取抖音直播间信息失败，直播间可能不存在或接口暂时受限');
  }

  const status = Number(room.status?.status ?? room.status ?? 0);
  const streamUrl = room.stream_url && typeof room.stream_url === 'object' ? room.stream_url : {};
  const isLive = status === 2 && Object.keys(streamUrl).length > 0;

  return {
    platform: 'douyin',
    live: {
      room_id: String(room.id_str ?? roomId),
      title: room.title ?? '',
      anchor: room.owner?.nickname ?? '',
      avatar: firstUrl(room.owner?.avatar_thumb?.url_list),
      cover: firstUrl(room.cover?.url_list) ?? firstUrl(room.cover_medium?.url_list),
      viewers: room.user_count != null ? Number(room.user_count) : null,
      status: isLive ? 'live' : 'offline',
    },
    streams: isLive ? pickStreams(streamUrl) : [],
  };
}
