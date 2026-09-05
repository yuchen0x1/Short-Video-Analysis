// 皮皮搞笑解析器：post id + fetch_content 接口
import { fetchOk, parseJson } from '../http.js';
import { desktop } from '../ua.js';
import { fixUrl } from '../utils.js';

const API_URL = 'https://share.ippzone.com/ppapi/share/fetch_content';

function headers() {
  return { 'User-Agent': desktop(), Referer: API_URL, 'Content-Type': 'application/json' };
}

export async function parse(url) {
  const pid = url.match(/post\/(\d+)/)?.[1];
  if (!pid) {
    throw new Error('无法解析皮皮搞笑 ID');
  }

  const body = JSON.stringify({ pid: Number(pid), type: 'post', mid: null });
  const response = await fetchOk(API_URL, body, headers());
  const data = response ? parseJson(response.data) : null;
  if (!data || data.ret !== 1) {
    throw new Error('获取皮皮搞笑内容失败');
  }

  const item = data.data?.post;
  if (!item) {
    throw new Error('获取皮皮搞笑内容失败');
  }

  const imgId = item.imgs?.[0]?.id;
  const videoUrl = imgId ? item.videos?.[String(imgId)]?.url : '';
  if (!videoUrl) {
    throw new Error('未找到皮皮搞笑视频地址');
  }

  const cover = new URL('https://file.ippzone.com/img/view/id/' + imgId);
  return {
    title: item.content || '',
    cover: fixUrl(cover.href),
    url: fixUrl(videoUrl),
  };
}
