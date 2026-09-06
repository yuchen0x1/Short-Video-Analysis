// B站直播解析：Room/get_info + xlive getRoomPlayInfo，返回直播间信息与 hls/flv 拉流地址
// 请求流程与 PHP app/Parsers/Live/BilibiliLiveParser.php 保持一致
import { fetchOk } from '../http.js';
import { fixUrl } from '../utils.js';

export function isBilibiliLiveUrl(url) {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    return false;
  }
  host = host.toLowerCase().replace(/^www\./, '');
  return host === 'live.bilibili.com' || host.endsWith('.live.bilibili.com');
}

export function extractRoomId(url) {
  try {
    const path = new URL(url).pathname;
    return path.match(/\/(\d+)(?:[/?#]|$)/i)?.[1] ?? null;
  } catch {
    return null;
  }
}

function headers() {
  return {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
    Referer: 'https://live.bilibili.com/',
  };
}

async function fetchJson(url) {
  const response = await fetchOk(url, null, headers(), { maxRetries: 1 });
  return response ? parseJsonData(response.data) : null;
}

function parseJsonData(data) {
  if (data != null && typeof data === 'object') return data;
  try {
    const text = String(data ?? '').trim();
    return text ? JSON.parse(text) : null;
  } catch {
    return null;
  }
}

// 展平 playurl.stream[].format[].codec[] 为拉流地址列表；
// url_info 是 CDN 候选数组（每 codec 取第一条），画质取 codec.current_qn 对照 quality_description / g_qn_desc
function collectCodecStreams(playurl, streams, onlyHls = false) {
  const qualityDesc = {};
  for (const descList of [playurl.quality_description, playurl.g_qn_desc]) {
    for (const desc of Array.isArray(descList) ? descList : []) {
      if (desc && typeof desc === 'object') {
        const key = String(desc.qn ?? '');
        if (key !== '' && !qualityDesc[key]) qualityDesc[key] = String(desc.desc ?? '');
      }
    }
  }
  for (const stream of Array.isArray(playurl.stream) ? playurl.stream : []) {
    if (!stream || typeof stream !== 'object') continue;
    if (onlyHls && stream.protocol_name !== 'http_hls') continue;
    const format = stream.protocol_name === 'http_hls' ? 'hls' : 'flv';
    for (const fmt of Array.isArray(stream.format) ? stream.format : []) {
      for (const codec of Array.isArray(fmt?.codec) ? fmt.codec : []) {
        const base = String(codec?.base_url ?? '');
        if (base === '') continue;
        const qn = String(codec?.current_qn ?? '');
        const quality = qualityDesc[qn] || (qn !== '' ? qn : '原画');
        for (const info of Array.isArray(codec?.url_info) ? codec.url_info : []) {
          const host = String(info?.host ?? '');
          const extra = String(info?.extra ?? '');
          if (host === '') continue;
          const url = host + base + extra;
          if (!streams.has(url)) {
            streams.set(url, { format, quality, url });
          }
          break; // 每个 codec 只取第一条 CDN
        }
      }
    }
  }
}

async function pickStreams(roomId) {
  const streams = new Map();
  const payload = await fetchJson(
    'https://api.live.bilibili.com/xlive/web-room/v2/index/getRoomPlayInfo?room_id=' + roomId
    + '&protocol=0,1&format=0,1,2&codec=0,1&qn=10000&platform=web&ptype=8'
  );
  const playurl = payload?.data?.playurl_info?.playurl;

  if (playurl && typeof playurl === 'object') {
    collectCodecStreams(playurl, streams);
  }

  // 未登录或部分 CDN 下 platform=web 只回 flv；用 h5 平台补拉 hls
  if (![...streams.values()].some((s) => s.format === 'hls')) {
    const hlsPayload = await fetchJson(
      'https://api.live.bilibili.com/xlive/web-room/v2/index/getRoomPlayInfo?room_id=' + roomId
      + '&protocol=1&format=0,2&codec=0&qn=10000&platform=h5&ptype=8'
    );
    const hlsPlayurl = hlsPayload?.data?.playurl_info?.playurl;
    if (hlsPlayurl && typeof hlsPlayurl === 'object') {
      collectCodecStreams(hlsPlayurl, streams, true);
    }
  }

  if (streams.size === 0) {
    // 兜底：旧版 playUrl 接口（flv）
    const legacy = await fetchJson(
      'https://api.live.bilibili.com/room/v1/Room/playUrl?cid=' + roomId + '&qn=10000&platform=web'
    );
    for (const item of Array.isArray(legacy?.data?.durl) ? legacy.data.durl : []) {
      const url = String(item?.url ?? '');
      if (url !== '' && !streams.has(url)) {
        streams.set(url, { format: 'flv', quality: '原画', url });
      }
    }
  }

  return [...streams.values()];
}

export async function parse(url) {
  const roomId = extractRoomId(url);
  if (!roomId) {
    throw new Error('无法识别 B 站直播间链接');
  }

  const info = await fetchJson('https://api.live.bilibili.com/room/v1/Room/get_info?room_id=' + roomId);
  if (!info || Number(info.code) !== 0) {
    throw new Error('获取 B 站直播间信息失败，直播间可能不存在');
  }

  const data = info.data && typeof info.data === 'object' ? info.data : {};
  const realRoomId = String(data.room_id ?? roomId);
  const isLive = Number(data.live_status ?? 0) === 1;

  let uname = '';
  let face = '';
  if (data.uid) {
    const master = await fetchJson('https://api.live.bilibili.com/live_user/v1/Master/info?uid=' + data.uid);
    const anchor = master?.data?.info && typeof master.data.info === 'object' ? master.data.info : {};
    uname = String(anchor.uname ?? '');
    face = String(anchor.face ?? '');
  }

  return {
    platform: 'bilibili',
    live: {
      room_id: realRoomId,
      title: String(data.title ?? ''),
      anchor: uname,
      avatar: fixUrl(face),
      cover: fixUrl(String(data.keyframe ?? data.user_cover ?? data.cover ?? '')),
      viewers: data.online != null ? Number(data.online) : null,
      status: isLive ? 'live' : 'offline',
    },
    streams: isLive ? await pickStreams(realRoomId) : [],
  };
}
