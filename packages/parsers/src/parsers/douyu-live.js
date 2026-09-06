// 斗鱼直播解析：房间信息走公开 API，拉流地址需 ub98484234 签名
// 签名函数文本来自 swf_api 接口，本地 eval 求值（生成的代码只用 CryptoJS.MD5，
// 用内嵌纯 JS MD5 等价实现，避免依赖 crypto-js）；与 PHP DouyuLiveParser 保持一致
import { fetchOk } from '../http.js';

const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';
const DEFAULT_DID = '10000000000000000000000000001501';

export function isDouyuLiveUrl(url) {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    return false;
  }
  host = host.toLowerCase().replace(/^www\./, '');
  return host === 'douyu.com' || host.endsWith('.douyu.com');
}

export function extractRoomId(url) {
  try {
    const parsed = new URL(url);
    const fromPath = parsed.pathname.match(/\/(\d+)(?:[/?#]|$)/i)?.[1];
    if (fromPath) return fromPath;
    return parsed.searchParams.get('rid') && /^\d+$/.test(parsed.searchParams.get('rid'))
      ? parsed.searchParams.get('rid')
      : null;
  } catch {
    return null;
  }
}

// 紧凑 MD5（RFC 1321，UTF-8 输入，hex 输出）
function md5Hex(input) {
  const bytes = [];
  for (const ch of String(input)) {
    const code = ch.codePointAt(0);
    if (code < 0x80) bytes.push(code);
    else if (code < 0x800) bytes.push(0xc0 | (code >> 6), 0x80 | (code & 63));
    else if (code < 0x10000) bytes.push(0xe0 | (code >> 12), 0x80 | ((code >> 6) & 63), 0x80 | (code & 63));
    else bytes.push(0xf0 | (code >> 18), 0x80 | ((code >> 12) & 63), 0x80 | ((code >> 6) & 63), 0x80 | (code & 63));
  }
  const origLen = bytes.length;
  // 消息 + 0x80 + 长度（8 字节）对齐到 64 字节块
  const padded = new Uint8Array((((origLen + 8) >> 6) + 1) << 6);
  padded.set(bytes);
  padded[origLen] = 0x80;
  const view = new DataView(padded.buffer);
  view.setUint32(padded.length - 8, (origLen * 8) >>> 0, true);
  view.setUint32(padded.length - 4, Math.floor((origLen * 8) / 4294967296), true);

  const S = [7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22,
    5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20,
    4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11, 16, 23,
    6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21];
  const K = new Int32Array(64);
  for (let i = 0; i < 64; i += 1) K[i] = (Math.abs(Math.sin(i + 1)) * 4294967296) | 0;

  let a0 = 0x67452301, b0 = 0xefcdab89, c0 = 0x98badcfe, d0 = 0x10325476;
  for (let off = 0; off < padded.length; off += 64) {
    const M = [];
    for (let i = 0; i < 16; i += 1) M[i] = view.getUint32(off + i * 4, true);
    let A = a0, B = b0, C = c0, D = d0;
    for (let i = 0; i < 64; i += 1) {
      let F, g;
      if (i < 16) { F = (B & C) | (~B & D); g = i; }
      else if (i < 32) { F = (D & B) | (~D & C); g = (5 * i + 1) % 16; }
      else if (i < 48) { F = B ^ C ^ D; g = (3 * i + 5) % 16; }
      else { F = C ^ (B | ~D); g = (7 * i) % 16; }
      F = (F + A + K[i] + M[g]) | 0;
      A = D; D = C; C = B;
      B = (B + ((F << S[i]) | (F >>> (32 - S[i])))) | 0;
    }
    a0 = (a0 + A) | 0; b0 = (b0 + B) | 0; c0 = (c0 + C) | 0; d0 = (d0 + D) | 0;
  }
  return [a0, b0, c0, d0]
    .flatMap((word) => [word & 255, (word >>> 8) & 255, (word >>> 16) & 255, (word >>> 24) & 255])
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}

const CryptoJsShim = {
  MD5(value) {
    return { toString: () => md5Hex(String(value)) };
  },
};

// eval 房间页下发的签名函数（含 ub98484234 标识校验）
function evalSign(fnText, rid, did, tt) {
  if (typeof fnText !== 'string' || !fnText.includes('ub98484234')) {
    throw new Error('获取斗鱼签名参数失败，请稍后再试');
  }
  const result = new Function(
    'CryptoJS',
    'rid',
    'did',
    'tt',
    fnText + '\n;return ub98484234(rid, did, tt);'
  )(CryptoJsShim, rid, did, tt);
  if (typeof result !== 'string' || result === '') {
    throw new Error('获取斗鱼签名参数失败，请稍后再试');
  }
  return result;
}

const baseHeaders = () => ({
  'User-Agent': DESKTOP_UA,
  Referer: 'https://www.douyu.com/',
});

async function playStream(rid, sign, extra) {
  const url = 'https://www.douyu.com/lapi/live/getH5Play/' + rid + '?' + sign
    + (extra ? '&' + extra : '');
  // 空 body 走 POST（与 PHP 端一致）
  const response = await fetchOk(url, '', {
    ...baseHeaders(),
    'Content-Type': 'application/x-www-form-urlencoded',
  }, { maxRetries: 1 });
  const json = response ? parseJsonData(response.data) : null;
  if (!json || Number(json.error) !== 0 || !json.data || typeof json.data !== 'object') return null;
  return json.data;
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

function buildFlvUrl(playInfo) {
  const base = String(playInfo?.rtmp_url ?? '');
  const live = String(playInfo?.rtmp_live ?? '');
  return base && live ? base.replace(/\/+$/, '') + '/' + live : '';
}

function pickStreams(rid, sign) {
  return (async () => {
    const first = await playStream(rid, sign, 'rate=0');
    if (!first) throw new Error('获取斗鱼拉流地址失败，直播间可能未开播');

    const streams = [];
    const seen = new Set();
    const rates = Array.isArray(first.multirates) ? first.multirates : [];
    for (const rate of rates) {
      if (!rate || typeof rate !== 'object' || seen.has(rate.rate) || streams.length >= 5) continue;
      seen.add(rate.rate);
      const variant = await playStream(rid, sign, 'rate=' + rate.rate);
      const url = variant ? buildFlvUrl(variant) : '';
      if (url) {
        streams.push({ format: 'flv', quality: String(rate.name ?? ('rate' + rate.rate)), url });
      }
    }
    if (streams.length === 0) {
      const url = buildFlvUrl(first);
      if (url) streams.push({ format: 'flv', quality: '原画', url });
    }
    return streams;
  })();
}

export async function parse(url) {
  const rid = extractRoomId(url);
  if (!rid) {
    throw new Error('无法识别斗鱼直播间链接');
  }

  const infoRes = await fetchOk('https://open.douyucdn.cn/api/RoomApi/room/' + rid, null, {
    'User-Agent': DESKTOP_UA,
  }, { maxRetries: 1 });
  const info = infoRes ? (parseJsonData(infoRes.data)?.data ?? null) : null;
  if (!info || typeof info !== 'object') {
    throw new Error('获取斗鱼直播间信息失败，直播间可能不存在');
  }

  const isLive = String(info.room_status ?? '') === '1';

  const live = {
    room_id: String(info.room_id ?? rid),
    title: String(info.room_name ?? ''),
    anchor: String(info.owner_name ?? ''),
    avatar: info.avatar ? String(info.avatar) : '',
    cover: info.room_thumb ? String(info.room_thumb) : '',
    viewers: info.online != null ? Number(info.online) : null,
    status: isLive ? 'live' : 'offline',
  };

  if (!isLive) {
    return { platform: 'douyu', live, streams: [] };
  }

  const signRes = await fetchOk('https://www.douyu.com/swf_api/homeH5Enc?rids=' + rid, null, baseHeaders(), { maxRetries: 1 });
  const signJson = signRes ? parseJsonData(signRes.data) : null;
  const fnText = signJson?.data?.['room' + rid];
  if (typeof fnText !== 'string' || fnText === '') {
    throw new Error('获取斗鱼签名参数失败，请稍后再试');
  }

  const did = DEFAULT_DID;
  const tt = Math.floor(Date.now() / 1000);
  const sign = evalSign(fnText, rid, did, tt);

  return {
    platform: 'douyu',
    live,
    streams: await pickStreams(rid, sign),
  };
}
