// 微博解析器：需要用户在设置里填写微博 Cookie
import { fetchOk, parseJson } from '../http.js';
import { desktop } from '../ua.js';
import { fixUrl, getWeiboCookie } from '../utils.js';

function headers(cookie = '') {
  const result = {
    'User-Agent': desktop(),
    Referer: 'https://weibo.com/',
    'Content-Type': 'application/x-www-form-urlencoded',
  };
  if (cookie) result.Cookie = cookie;
  return result;
}

export async function parse(url) {
  let normalizedUrl = url;
  try {
    normalizedUrl = decodeURIComponent(url);
  } catch {
    // 保留原始 URL，继续使用未编码格式尝试解析
  }

  const cid = normalizedUrl.includes('show?fid=')
    ? normalizedUrl.match(/fid=([^&#]+)/)?.[1]
    : normalizedUrl.match(/\d+:\d+/)?.[0];
  if (!cid) {
    throw new Error('无法解析微博视频 ID');
  }

  const endpoint = new URL('https://weibo.com/tv/api/component');
  endpoint.pathname += '/page/tv/show/' + cid;
  const body = 'data=' + JSON.stringify({ Component_Play_Playinfo: { oid: cid } });

  const cookie = getWeiboCookie();
  const options = cookie ? {} : { credentialMode: 'weibo-session' };
  const response = await fetchOk(endpoint.href, body, headers(cookie), options);
  const data = response ? parseJson(response.data) : null;
  const item = data?.data?.Component_Play_Playinfo;
  if (!item) {
    throw new Error('获取微博视频信息失败（Cookie 可能已失效）');
  }

  const urls = item.urls;
  const firstKey = urls && typeof urls === 'object' ? Object.keys(urls)[0] : null;
  const videoUrl = firstKey ? urls[firstKey] : urls;
  if (!videoUrl) {
    throw new Error('未找到微博视频地址');
  }

  return {
    author: item.author || '',
    avatar: fixUrl(item.avatar || ''),
    time: item.real_date || '',
    title: item.title || '',
    cover: fixUrl(item.cover_image || ''),
    url: fixUrl(videoUrl),
  };
}
