// 最右解析器：pid 参数 + share 接口
import { fetchOk, parseJson } from '../http.js';
import { desktop } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return {
    'User-Agent': desktop(),
    Referer: 'https://share.izuiyou.com/',
    'Content-Type': 'application/json',
  };
}

export async function parse(url) {
  let pid = '';
  try {
    pid = new URL(url).searchParams.get('pid') || '';
  } catch {
    // 继续使用正则兜底处理非标准分享文本
  }
  pid = pid.match(/^\d+$/)?.[0] || url.match(/\/detail\/(\d+)/i)?.[1] || url.match(/[?&]pid=(\d+)\b/i)?.[1] || '';
  if (!pid) {
    throw new Error('无法解析最右视频 ID');
  }

  const body = JSON.stringify({ h_av: '5.2.13.011', pid: Number(pid) });
  const response = await fetchOk('https://share.xiaochuankeji.cn/planck/share/post/detail_h5', body, headers());
  const data = response ? parseJson(response.data) : null;
  const item = data?.data?.post;
  if (!item) {
    throw new Error('获取最右视频信息失败');
  }

  const videos = item.videos || {};
  const firstKey = Object.keys(videos)[0];
  const videoUrl = firstKey ? videos[firstKey]?.url : '';
  if (!videoUrl) {
    throw new Error('未找到最右视频地址');
  }

  return {
    title: item.content || '',
    author: item.member?.name || '',
    avatar: fixUrl(item.member?.avatar_urls?.origin?.urls?.[0] || ''),
    cover: fixUrl(item.imgs?.[0]?.url || ''),
    url: fixUrl(videoUrl),
  };
}
