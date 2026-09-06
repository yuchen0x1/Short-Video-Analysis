// 发现：平台公开热榜、关键词搜索与单视频公开评论
// 合规边界与 PHP App\Services\Discovery 一致：仅响应用户主动查询、按需分页、不落库
import { fetchOk, parseJson } from './http.js';
import { generateABogus } from './a-bogus.js';
import { getTtwid } from './parsers/douyin.js';

const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

const state = { bilibiliCookie: '' };

function stripTags(title) {
  return String(title ?? '').replace(/<[^>]*>/g, '').trim();
}

function fixImg(url) {
  const value = String(url ?? '');
  if (!value) return '';
  return value.startsWith('http') ? value : 'https:' + value;
}

async function bilibiliApi(url) {
  const headers = { 'User-Agent': DESKTOP_UA, Referer: 'https://www.bilibili.com/' };
  // 搜索/评论接口需要 buvid3；获取一次后复用
  if (!state.bilibiliCookie) {
    const spi = await fetchOk('https://api.bilibili.com/x/frontend/finger/spi', null, headers, { maxRetries: 1 });
    const spiJson = spi ? parseJson(spi.data) : null;
    const b3 = spiJson?.data?.b_3 ?? '';
    const b4 = spiJson?.data?.b_4 ?? '';
    if (b3) state.bilibiliCookie = `buvid3=${b3}` + (b4 ? `; buvid4=${b4}` : '');
  }
  if (state.bilibiliCookie) headers.Cookie = state.bilibiliCookie;

  const response = await fetchOk(url, null, headers, { maxRetries: 1 });
  const json = response ? parseJson(response.data) : null;
  if (!json || Number(json.code) !== 0) {
    throw new Error('B站接口返回异常，请稍后再试');
  }
  return json;
}

// ---- B站 ----

async function bilibiliHot(page) {
  const current = Math.max(1, page);
  const json = await bilibiliApi('https://api.bilibili.com/x/web-interface/popular?ps=20&pn=' + current);
  const list = Array.isArray(json.data?.list) ? json.data.list : [];

  const items = [];
  for (const item of list) {
    if (!item || typeof item !== 'object' || !item.bvid) continue;
    items.push({
      title: stripTags(item.title),
      url: 'https://www.bilibili.com/video/' + item.bvid,
      cover: fixImg(item.pic),
      author: item.owner?.name ?? '',
      hot: Number(item.stat?.view ?? 0),
      duration: String(item.duration ?? ''),
    });
  }

  return {
    platform: 'bilibili',
    title: 'B站综合热门',
    items,
    has_more: items.length > 0,
    next_page: current + 1,
  };
}

async function bilibiliSearch(keyword, page) {
  const current = Math.max(1, page);
  const json = await bilibiliApi(
    'https://api.bilibili.com/x/web-interface/search/type?search_type=video&keyword='
    + encodeURIComponent(keyword) + '&page=' + current
  );
  const data = json.data && typeof json.data === 'object' ? json.data : {};
  const results = Array.isArray(data.result) ? data.result : [];

  const items = [];
  for (const item of results) {
    if (!item || typeof item !== 'object' || !item.bvid) continue;
    items.push({
      title: stripTags(item.title),
      url: 'https://www.bilibili.com/video/' + item.bvid,
      cover: fixImg(item.pic),
      author: item.author ?? '',
      hot: Number(item.play ?? 0),
      duration: String(item.duration ?? ''),
    });
  }

  const total = Number(data.numResults ?? items.length);
  return {
    platform: 'bilibili',
    keyword,
    items,
    total,
    has_more: current * 20 < total,
    next_page: current + 1,
  };
}

async function bilibiliAid(url) {
  const direct = url.match(/av(\d+)/i)?.[1];
  if (direct) return direct;

  const bvid = url.match(/(BV\w+)/i)?.[1];
  if (!bvid) throw new Error('无法识别 B 站视频链接');

  const json = await bilibiliApi('https://api.bilibili.com/x/web-interface/view?bvid=' + bvid);
  const aid = String(json.data?.aid ?? '');
  if (!aid) throw new Error('无法识别 B 站视频链接');
  return aid;
}

async function bilibiliComments(url, page) {
  const current = Math.max(1, page);
  const aid = await bilibiliAid(url);
  const json = await bilibiliApi(
    'https://api.bilibili.com/x/v2/reply?type=1&oid=' + aid + '&pn=' + current + '&ps=20&sort=1'
  );
  const data = json.data && typeof json.data === 'object' ? json.data : {};
  const pageInfo = data.page && typeof data.page === 'object' ? data.page : {};

  const comments = [];
  for (const reply of Array.isArray(data.replies) ? data.replies : []) {
    if (!reply || typeof reply !== 'object') continue;
    comments.push({
      user: reply.member?.uname ?? '',
      avatar: fixImg(reply.member?.avatar),
      content: reply.content?.message ?? '',
      likes: Number(reply.like ?? 0),
      time: Number(reply.ctime ?? 0),
    });
  }

  const total = Number(pageInfo.count ?? 0);
  const size = Math.max(1, Number(pageInfo.size ?? 20));
  const num = Math.max(1, Number(pageInfo.num ?? current));

  return {
    platform: 'bilibili',
    total,
    page: current,
    has_more: num * size < total,
    next_page: current + 1,
    comments,
  };
}

// ---- 抖音热榜 ----

async function douyinHot() {
  const ttwid = await getTtwid();
  if (!ttwid) throw new Error('获取抖音会话失败，请稍后再试');

  const params = new URLSearchParams({
    device_platform: 'webapp',
    aid: '6383',
    channel: 'channel_pc_web',
    search_channel: 'aweme_hot',
    version_code: '170400',
    version_name: '17.4.0',
    browser_language: 'zh-CN',
    browser_platform: 'Win32',
    browser_name: 'Chrome',
    browser_version: '123.0.0.0',
  });
  const query = params.toString();
  const aBogus = generateABogus(query, DESKTOP_UA);
  const url = 'https://www.douyin.com/aweme/v1/web/hot/search/list/?' + query
    + '&a_bogus=' + encodeURIComponent(aBogus);

  const response = await fetchOk(url, null, {
    'User-Agent': DESKTOP_UA,
    Accept: 'application/json, text/plain, */*',
    Referer: 'https://www.douyin.com/hot',
    Cookie: `ttwid=${ttwid}`,
  }, { maxRetries: 1 });

  const json = response ? parseJson(response.data) : null;
  const wordList = Array.isArray(json?.data?.word_list) ? json.data.word_list : [];
  if (wordList.length === 0) {
    throw new Error('抖音热榜暂时受限，请稍后再试');
  }

  const items = [];
  for (const item of wordList) {
    if (!item || typeof item !== 'object') continue;
    const word = String(item.word ?? '');
    if (!word) continue;
    items.push({
      title: word,
      url: 'https://www.douyin.com/search/' + encodeURIComponent(word),
      cover: fixImg(item.word_cover?.url_list?.[0]),
      author: '',
      hot: Number(item.hot_value ?? 0),
      duration: '',
    });
  }

  return {
    platform: 'douyin',
    title: '抖音热点榜',
    items,
    has_more: false,
    next_page: 1,
  };
}

// ---- 门面 ----

export async function hotList(platform, page = 1) {
  if (platform === 'bilibili') return bilibiliHot(page);
  if (platform === 'douyin') return douyinHot();
  throw new Error('不支持的热榜平台');
}

export async function search(platform, keyword, page = 1) {
  const clean = String(keyword ?? '').trim();
  if (!clean) throw new Error('搜索关键词不能为空');
  if (clean.length > 100) throw new Error('搜索关键词过长');

  if (platform === 'bilibili') return bilibiliSearch(clean, page);
  if (platform === 'douyin') throw new Error('抖音搜索需要完整登录态，暂不开放');
  throw new Error('不支持的搜索平台');
}

export async function comments(url, page = 1) {
  let host = '';
  try {
    host = new URL(url).hostname.toLowerCase().replace(/^www\./, '');
  } catch {
    throw new Error('无效的视频链接');
  }
  if (host !== 'bilibili.com' && !host.endsWith('.bilibili.com') && host !== 'b23.tv') {
    throw new Error('目前仅支持 B 站视频评论查看');
  }
  return bilibiliComments(url, page);
}
