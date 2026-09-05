// 皮皮虾解析器：短链解析取 item id，cell_comment 接口取视频信息
// 2026 平台改版：旧 is.snssdk.com/bds/cell/detail 已失效，改用 api.pipix.com/bds/cell/cell_comment
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { mobile } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return { 'User-Agent': mobile() };
}

export async function parse(url) {
  const target = (await resolveShortLink(url, headers())) || url;

  const itemId = target.match(/item\/(\d+)/)?.[1];
  if (!itemId) {
    throw new Error('无法解析皮皮虾视频 ID');
  }

  const endpoint = new URL('https://api.pipix.com/bds/cell/cell_comment/');
  endpoint.searchParams.set('offset', '0');
  endpoint.searchParams.set('cell_type', '1');
  endpoint.searchParams.set('api_version', '1');
  endpoint.searchParams.set('cell_id', itemId);
  endpoint.searchParams.set('ac', 'wifi');
  endpoint.searchParams.set('channel', 'huawei_1319_64');
  endpoint.searchParams.set('aid', '1319');
  endpoint.searchParams.set('app_name', 'super');

  const response = await fetchOk(endpoint.href, null, headers());
  const data = response ? parseJson(response.data) : null;
  const item = data?.data?.cell_comments?.[0]?.comment_info?.item;
  if (!item) {
    throw new Error('获取皮皮虾视频信息失败');
  }

  // 优先取作者本人评论里的无水印地址；video_high 为备用
  const authorId = item.author?.id;
  let videoUrl =
    item.origin_video_download?.url_list?.[0]?.url ||
    item.video?.video_high?.url_list?.[0]?.url ||
    '';
  if (!videoUrl) {
    videoUrl = item.video?.play_addr?.url_list?.[0]?.url || '';
  }
  for (const comment of data?.data?.cell_comments?.[0]?.comment_info?.comments || []) {
    const commentItem = comment.item;
    if (
      authorId != null &&
      String(commentItem?.author?.id) === String(authorId) &&
      commentItem?.video?.video_high?.url_list?.[0]?.url
    ) {
      videoUrl = commentItem.video.video_high.url_list[0].url;
      break;
    }
  }

  // 图集图片
  const images = [];
  for (const image of item.note?.multi_image || []) {
    if (image.url_list?.[0]?.url) {
      images.push(fixUrl(image.url_list[0].url));
    }
  }
  if (!videoUrl && images.length === 0) {
    throw new Error('未找到皮皮虾视频地址');
  }

  return {
    author: item.author?.name || '',
    avatar: fixUrl(item.author?.avatar?.download_list?.[0]?.url || ''),
    time: data?.data?.display_time ?? 0,
    title: item.content || '',
    cover: fixUrl(item.cover?.url_list?.[0]?.url || item.note?.multi_image?.[0]?.url_list?.[0]?.url || ''),
    url: fixUrl(videoUrl || images[0] || ''),
    media_type: images.length ? 'image' : 'video',
    images,
  };
}
