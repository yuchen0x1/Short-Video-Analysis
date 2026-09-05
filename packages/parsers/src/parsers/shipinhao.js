// 微信视频号解析器：vid 参数 + feeds/video 接口
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { mobile } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return {
    'User-Agent': mobile(),
    Referer: 'https://channels.weixin.qq.com/',
    'Content-Type': 'application/json',
  };
}

function extractVid(url) {
  try {
    const parsed = new URL(url);
    return parsed.searchParams.get('vid');
  } catch {
    return null;
  }
}

export async function parse(url) {
  let vid = extractVid(url);
  if (!vid) {
    const target = (await resolveShortLink(url, headers())) || url;
    vid = extractVid(target);
  }
  if (!vid) {
    throw new Error('无法解析视频号 vid');
  }

  const body = JSON.stringify({ vid });
  const response = await fetchOk(
    'https://channels.weixin.qq.com/cgi-bin/mmfinder-bin/feeds/video',
    body,
    headers()
  );
  const data = response ? parseJson(response.data) : null;
  const item = data?.video_info ?? data?.data?.video_info ?? data?.data?.feed;
  if (!item) {
    throw new Error('获取视频号信息失败');
  }

  const media = item.media ?? item.video ?? {};
  const videoUrl =
    [item.url, item.hd_url, item.sd_url, item.play_url, item.video_url, item.url_1, item.url_0].find(Boolean) ||
    [media.url, media.hd_url, media.sd_url, media.play_url].find(Boolean) ||
    '';
  if (!videoUrl) {
    throw new Error('未找到视频号视频地址');
  }

  const author = item.author ?? item.user ?? item.publisher ?? {};
  return {
    author: author.name ?? author.nickname ?? item.nickname ?? '',
    uid: author.id ?? author.uin ?? '',
    avatar: author.avatar ?? author.headimg_url ?? '',
    like: item.like_count ?? item.statistics?.like_count ?? 0,
    time: item.create_time ?? item.timestamp ?? item.publish_time ?? 0,
    title: item.desc ?? item.description ?? item.title ?? '',
    cover: item.cover_url ?? item.cover ?? item.thumbnail ?? '',
    url: fixUrl(videoUrl),
  };
}
