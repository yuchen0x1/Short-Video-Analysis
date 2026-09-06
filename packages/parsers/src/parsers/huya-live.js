// 虎牙直播解析：直播间页面 hyPlayerConfig.stream 内嵌 JSON 直取拉流地址，无签名
// 与 PHP app/Parsers/Live/HuyaLiveParser.php 保持一致；FLV 可直接播放（302 跳边缘），HLS 受限不下发
import { fetchOk } from '../http.js';

const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

export function isHuyaLiveUrl(url) {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    return false;
  }
  host = host.toLowerCase().replace(/^www\./, '');
  return host === 'huya.com' || host.endsWith('.huya.com');
}

export function extractRoomId(url) {
  try {
    const path = new URL(url).pathname;
    return path.match(/\/(\d+)(?:[/?#]|$)/i)?.[1] ?? null;
  } catch {
    return null;
  }
}

// 从页面脚本提取 hyPlayerConfig 的 stream 值：定位后做括号配平扫描（跳过字符串字面量）
function extractStreamPayload(html) {
  const marker = html.indexOf('stream:');
  if (marker === -1) return null;
  const start = html.indexOf('{', marker);
  if (start === -1) return null;

  let depth = 0;
  let inString = false;
  let escaped = false;
  for (let i = start; i < html.length; i += 1) {
    const char = html[i];
    if (inString) {
      if (escaped) escaped = false;
      else if (char === '\\') escaped = true;
      else if (char === '"') inString = false;
      continue;
    }
    if (char === '"') inString = true;
    else if (char === '{') depth += 1;
    else if (char === '}') {
      depth -= 1;
      if (depth === 0) {
        try {
          return JSON.parse(html.slice(start, i + 1));
        } catch {
          return null;
        }
      }
    }
  }
  return null;
}

function fixUrl(url) {
  const value = String(url ?? '');
  if (!value) return '';
  return value.startsWith('http') ? value : 'https:' + value;
}

function pickStreams(list) {
  const streams = [];
  const seen = new Set();
  for (const entry of Array.isArray(list) ? list : []) {
    if (!entry || typeof entry !== 'object') continue;
    const cdn = String(entry.sCdnType ?? '');
    if (!cdn || seen.has(cdn)) continue;
    const url = fixUrl(
      `${entry.sFlvUrl ?? ''}/${entry.sStreamName ?? ''}.${entry.sFlvUrlSuffix ?? 'flv'}?${entry.sFlvAntiCode ?? ''}`
    );
    if (!/^https?:\/\//i.test(url)) continue;
    seen.add(cdn);
    streams.push({ format: 'flv', quality: '原画 · ' + cdn.toUpperCase(), url });
    if (streams.length >= 3) break;
  }
  return streams;
}

export async function parse(url) {
  const roomId = extractRoomId(url);
  if (!roomId) {
    throw new Error('无法识别虎牙直播间链接');
  }

  const response = await fetchOk('https://www.huya.com/' + roomId, null, {
    'User-Agent': DESKTOP_UA,
    Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
  }, { maxRetries: 1 });
  if (!response) {
    throw new Error('获取虎牙直播间信息失败，请稍后再试');
  }

  const stream = extractStreamPayload(typeof response.data === 'string' ? response.data : String(response.data ?? ''));
  if (!stream) {
    throw new Error('获取虎牙直播间信息失败，直播间可能不存在或未开播');
  }

  const records = Array.isArray(stream.data) ? stream.data : [];
  const info = records[0]?.gameLiveInfo && typeof records[0].gameLiveInfo === 'object' ? records[0].gameLiveInfo : {};
  const list = records[0]?.gameStreamInfoList && typeof records[0].gameStreamInfoList === 'object' ? records[0].gameStreamInfoList : [];

  // 不存在的房间页面会返回全零值的 gameLiveInfo
  if (!String(info.nick ?? '').trim() && !String(info.roomName ?? '').trim() && list.length === 0) {
    throw new Error('虎牙直播间不存在或已关闭');
  }

  const isLive = list.length > 0;

  return {
    platform: 'huya',
    live: {
      room_id: String(info.profileRoom ?? roomId),
      title: String(info.roomName ?? ''),
      anchor: String(info.nick ?? ''),
      avatar: fixUrl(info.avatar180),
      cover: fixUrl(info.screenshot),
      viewers: info.totalCount != null ? Number(info.totalCount) : null,
      status: isLive ? 'live' : 'offline',
    },
    streams: isLive ? pickStreams(list) : [],
  };
}
