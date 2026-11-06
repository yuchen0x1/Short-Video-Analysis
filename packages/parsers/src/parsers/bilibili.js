// B 站解析器：view 接口取 cid + playurl 接口取视频地址（durl 优先，其次 DASH）
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { desktop } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return { 'User-Agent': desktop(), Referer: 'https://www.bilibili.com/' };
}

export async function parse(url) {
  const target = (await resolveShortLink(url, headers())) || url;

  const bvid = target.match(/BV\w+/i)?.[0];
  const avid = bvid ? null : target.match(/av(\d+)/i)?.[1];
  if (!bvid && !avid) {
    throw new Error('无法解析 B 站视频 ID');
  }

  const viewUrl = new URL('https://api.bilibili.com/x/web-interface/view');
  viewUrl.searchParams.set(bvid ? 'bvid' : 'aid', bvid || avid);

  const viewRes = await fetchOk(viewUrl.href, null, headers());
  const view = viewRes ? parseJson(viewRes.data) : null;
  const item = view?.data;
  if (!item?.cid) {
    throw new Error('获取 B 站视频信息失败');
  }

  const playUrl = new URL('https://api.bilibili.com/x/player/playurl');
  playUrl.searchParams.set('bvid', item.bvid || bvid);
  playUrl.searchParams.set('cid', String(item.cid));
  playUrl.searchParams.set('qn', '80');
  playUrl.searchParams.set('fnver', '0');
  playUrl.searchParams.set('fnval', '4048');
  playUrl.searchParams.set('fourk', '1');

  const playRes = await fetchOk(playUrl.href, null, headers());
  const play = playRes ? parseJson(playRes.data) : null;
  const data = play?.data;

  const videoUrl =
    data?.durl?.[0]?.url ||
    data?.dash?.video?.[0]?.baseUrl ||
    data?.dash?.video?.[0]?.backupUrl?.[0] ||
    '';
  if (!videoUrl) {
    throw new Error('未找到 B 站视频地址');
  }

  const audioUrl =
    data?.dash?.audio?.[0]?.baseUrl ||
    data?.dash?.audio?.[0]?.backupUrl?.[0] ||
    '';

  return {
    author: item.owner?.name || '',
    uid: item.owner?.mid ?? '',
    avatar: item.owner?.face || '',
    like: item.stat?.like ?? 0,
    time: item.pubdate ?? 0,
    title: item.title || '',
    cover: item.pic || '',
    url: fixUrl(videoUrl),
    music: {
      author: item.owner?.name || '',
      title: item.title || '',
      avatar: item.owner?.face || '',
      url: audioUrl ? fixUrl(audioUrl) : '',
    },
  };
}
