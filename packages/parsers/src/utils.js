// 与 PHP AbstractParser 的 fixUrl 行为一致：补全协议相对地址 //xxx -> https://xxx
export function fixUrl(url) {
  const value = String(url ?? '');
  if (value === '') return '';
  return value.startsWith('http') ? value : 'https:' + value;
}

export function firstUrl(urlList) {
  if (!Array.isArray(urlList) || typeof urlList[0] !== 'string' || urlList[0] === '') {
    return null;
  }
  return fixUrl(urlList[0]);
}

// 微博 Cookie 等运行期设置，由宿主 App 注入（localStorage / 设置页）
const runtimeSettings = { weiboCookie: '' };

export function setWeiboCookie(cookie) {
  runtimeSettings.weiboCookie = String(cookie || '').trim();
}

export function getWeiboCookie() {
  return runtimeSettings.weiboCookie;
}

// CRC32 计算表（用于零依赖生成合法的客户端 ZIP 压缩包）
const CRC_TABLE = new Uint32Array(256);
for (let i = 0; i < 256; i += 1) {
  let c = i;
  for (let k = 0; k < 8; k += 1) {
    c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
  }
  CRC_TABLE[i] = c >>> 0;
}

export function crc32(buffer) {
  let crc = 0xFFFFFFFF;
  for (let i = 0; i < buffer.length; i += 1) {
    crc = CRC_TABLE[(crc ^ buffer[i]) & 0xFF] ^ (crc >>> 8);
  }
  return (crc ^ 0xFFFFFFFF) >>> 0;
}

/**
 * 纯前端零依赖生成合规 ZIP 包（Store 模式，不消耗服务器任何带宽与 CPU）
 * @param {Array<{ name: string, data: Uint8Array|ArrayBuffer }>} entries
 * @returns {Blob}
 */
export function createZipBlob(entries) {
  const enc = new TextEncoder();
  const fileParts = [];
  const centralParts = [];
  let offset = 0;

  const now = new Date();
  const dosTime = (now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1);
  const dosDate = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();

  for (const entry of entries) {
    const rawData = entry.data;
    const bytes = rawData instanceof Uint8Array
      ? rawData
      : new Uint8Array(rawData instanceof ArrayBuffer ? rawData : []);
    const nameBytes = enc.encode(String(entry.name || 'file'));
    const crc = crc32(bytes);
    const size = bytes.length;

    // 本地文件头 (30 + nameBytes.length)
    const localHeader = new Uint8Array(30 + nameBytes.length);
    const lv = new DataView(localHeader.buffer);
    lv.setUint32(0, 0x04034b50, true); // Local file header signature
    lv.setUint16(4, 20, true);         // Version needed to extract (2.0)
    lv.setUint16(6, 0x0800, true);      // General purpose bit flag (UTF-8 filename)
    lv.setUint16(8, 0, true);           // Compression method (0 = Store)
    lv.setUint16(10, dosTime, true);
    lv.setUint16(12, dosDate, true);
    lv.setUint32(14, crc, true);
    lv.setUint32(18, size, true);       // Compressed size
    lv.setUint32(22, size, true);       // Uncompressed size
    lv.setUint16(26, nameBytes.length, true);
    lv.setUint16(28, 0, true);          // Extra field length
    localHeader.set(nameBytes, 30);

    fileParts.push(localHeader, bytes);

    // 核心目录头 (46 + nameBytes.length)
    const centralHeader = new Uint8Array(46 + nameBytes.length);
    const cv = new DataView(centralHeader.buffer);
    cv.setUint32(0, 0x02014b50, true); // Central file header signature
    cv.setUint16(4, 20, true);         // Version made by
    cv.setUint16(6, 20, true);         // Version needed to extract
    cv.setUint16(8, 0x0800, true);      // UTF-8
    cv.setUint16(10, 0, true);          // Store
    cv.setUint16(12, dosTime, true);
    cv.setUint16(14, dosDate, true);
    cv.setUint32(16, crc, true);
    cv.setUint32(20, size, true);
    cv.setUint32(24, size, true);
    cv.setUint16(28, nameBytes.length, true);
    cv.setUint16(30, 0, true);          // Extra length
    cv.setUint16(32, 0, true);          // Comment length
    cv.setUint16(34, 0, true);          // Disk start
    cv.setUint16(36, 0, true);          // Internal attrs
    cv.setUint32(38, 0, true);          // External attrs
    cv.setUint32(42, offset, true);     // Local header offset
    centralHeader.set(nameBytes, 46);

    centralParts.push(centralHeader);
    offset += localHeader.length + size;
  }

  const centralOffset = offset;
  let centralSize = 0;
  for (const part of centralParts) {
    centralSize += part.length;
  }

  // 目录结束记录 (22 bytes)
  const eocd = new Uint8Array(22);
  const ev = new DataView(eocd.buffer);
  ev.setUint32(0, 0x06054b50, true);   // End of central dir signature
  ev.setUint16(4, 0, true);            // Disk number
  ev.setUint16(6, 0, true);            // Disk with central dir
  ev.setUint16(8, entries.length, true);  // Number of entries on disk
  ev.setUint16(10, entries.length, true); // Total number of entries
  ev.setUint32(12, centralSize, true); // Central dir size
  ev.setUint32(16, centralOffset, true); // Central dir offset
  ev.setUint16(20, 0, true);           // Comment length

  return new Blob([...fileParts, ...centralParts, eocd], { type: 'application/zip' });
}
