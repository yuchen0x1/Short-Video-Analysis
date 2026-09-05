// 微视解析器：短链解析取 feedId + WSH5GetPlayPage 接口
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { desktop } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return { 'User-Agent': desktop(), Referer: 'https://weishi.qq.com/' };
}

function parseSharePage(html) {
  const match = String(html || '').match(/window\.Vise\.initState\s*=\s*({[\s\S]*?});\s*(?:}|<\/)/i);
  if (!match) return null;
  try {
    const state = JSON.parse(match[1]);
    return state?.feedsList?.[0] || null;
  } catch {
    return null;
  }
}

function buildResult(item) {
  const videoUrl = item?.video_url || item?.videoUrl || '';
  if (!videoUrl) return null;
  const shareTitle = item?.shareInfo?.bodyMap?.['0']?.title || item?.materialDesc || '';
  return {
    author: item?.poster?.nick || '',
    avatar: fixUrl(item?.poster?.avatar || ''),
    time: item?.poster?.createtime ?? 0,
    title: item?.feed_desc_withat || shareTitle,
    cover: fixUrl(item?.images?.[0]?.url || item?.videoCover || ''),
    url: fixUrl(videoUrl),
  };
}

export async function parse(url) {
  const target = (await resolveShortLink(url, headers())) || url;

  const feedId =
    target.match(/feed\/([A-Za-z0-9_-]+)/)?.[1] ||
    target.match(/feedid=([A-Za-z0-9_-]+)/)?.[1] ||
    target.match(/id=([A-Za-z0-9_-]+)/)?.[1];
  if (!feedId) {
    throw new Error('无法解析微视视频 ID');
  }

  const endpoint = new URL('https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage');
  endpoint.searchParams.set('feedid', feedId);

  const response = await fetchOk(endpoint.href, null, headers());
  const data = response ? parseJson(response.data) : null;
  const apiResult = buildResult(data?.data?.feeds?.[0]);
  if (apiResult) return apiResult;

  // 微视旧 JSON 接口逐步下线，公开分享页仍保留可用的 initState。
  const page = await fetchOk(target, null, headers());
  const pageResult = buildResult(parseSharePage(page?.data));
  if (pageResult) return pageResult;

  throw new Error('获取微视视频信息失败');
}
