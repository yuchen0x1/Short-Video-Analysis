import assert from 'node:assert/strict';
import crypto from 'node:crypto';

const ROOT = new URL('..', import.meta.url);
const parsers = await import(new URL('src/index.js', ROOT));

const calls = [];
const counts = new Map();
const json = (value) => JSON.stringify(value);
const xiguaProtectedUrl = (value) => {
  const salt = Buffer.from('12345678');
  let previous = Buffer.alloc(0);
  let derived = Buffer.alloc(0);
  while (derived.length < 48) {
    previous = crypto.createHash('md5').update(Buffer.concat([previous, Buffer.from('xigua.fe.web_mobile'), salt])).digest();
    derived = Buffer.concat([derived, previous]);
  }
  const cipher = crypto.createCipheriv('aes-256-cbc', derived.subarray(0, 32), derived.subarray(32, 48));
  const encrypted = Buffer.concat([cipher.update(Buffer.from(value)), cipher.final()]);
  return Buffer.concat([Buffer.from('Salted__'), salt, encrypted]).toString('base64').split('').reverse().join('');
};
const response = (data, url, status = 200, headers = {}) => ({
  status,
  data: typeof data === 'string' ? data : json(data),
  headers,
  url,
});

const douyinVideo = {
  aweme_id: '7123456789012345678',
  desc: 'fixture douyin video',
  create_time: 1700000000,
  author: {
    nickname: '抖音作者',
    uid: 'douyin-user',
    avatar_medium: { url_list: ['//img.example/douyin.jpg'] },
  },
  statistics: { digg_count: 12 },
  video: {
    play_addr: { uri: 'v0300fixtureuri', url_list: ['https://v26.douyinvod.com/no-watermark/douyin.mp4'] },
    bit_rate: [
      { bit_rate: 2000, play_addr: { url_list: ['https://www.douyin.com/aweme/v1/play/?video_id=x&watermark=1'] } },
      { bit_rate: 1500, play_addr: { url_list: ['https://v9.douyinvod.com/lower/douyin.mp4'] } },
    ],
    cover: { url_list: ['https://cdn.example/douyin-cover.jpg'] },
  },
  music: {
    author: '音乐作者',
    title: 'fixture 背景',
    cover_large: { url_list: ['https://cdn.example/music.jpg'] },
    play_url: { url_list: ['https://cdn.example/music.mp3'] },
  },
};

const pipixiaItem = {
  content: 'fixture pipixia video',
  author: { id: 123, name: '皮皮虾作者', avatar: { download_list: [{ url: '//img.example/pipixia-avatar.jpg' }] } },
  cover: { url_list: [{ url: 'https://cdn.example/pipixia-cover.jpg' }] },
  video: { video_high: { url_list: [{ url: 'https://cdn.example/pipixia.mp4' }] } },
};

function pipixiaPayload(item) {
  return { data: { display_time: 1700000000, cell_comments: [{ comment_info: { item, comments: [] } }] } };
}

const requestCounts = (url) => {
  const count = (counts.get(url) || 0) + 1;
  counts.set(url, count);
  return count;
};

const transport = {
  async request({ url, method = 'GET', body, headers = {}, credentialMode = null }) {
    calls.push({ url, method, body, headers, credentialMode });
    const count = requestCounts(url);
    const parsed = new URL(url);

    if (url.includes('ttwid.bytedance.com/ttwid/union/register')) {
      return response('{}', url, 200, { 'set-cookie': 'ttwid=fixture-ttwid; Path=/;' });
    }
    if (url.includes('/aweme/v1/web/aweme/detail/')) {
      return response({ aweme_detail: douyinVideo }, url);
    }
    if (url.includes('/aweme/v1/web/aweme/post/')) {
      return response({
        aweme_list: [douyinVideo],
        user: douyinVideo.author,
        max_cursor: 123,
        has_more: 1,
      }, url);
    }
    if (url.includes('api.bilibili.com/x/web-interface/view')) {
      return response({ data: {
        bvid: 'BV1fixture',
        cid: 99,
        title: 'fixture bilibili',
        pubdate: 1700000000,
        pic: 'https://cdn.example/bilibili-cover.jpg',
        owner: { mid: 7, name: 'B站作者', face: 'https://cdn.example/bilibili-avatar.jpg' },
        stat: { like: 8 },
      } }, url);
    }
    if (url.includes('api.bilibili.com/x/player/playurl')) {
      return response({ data: { durl: [{ url: 'https://cdn.example/bilibili.mp4' }] } }, url);
    }
    if (url === 'https://www.kuaishou.com/graphql') {
      return response({ data: { visionVideoDetail: {
        author: { id: 'ks-user', name: '快手作者', headerUrl: 'https://cdn.example/kuaishou-avatar.jpg' },
        photo: {
          caption: 'fixture kuaishou',
          coverUrl: 'https://cdn.example/kuaishou-cover.jpg',
          photoUrl: 'https://cdn.example/kuaishou.mp4',
          timestamp: 1700000000,
          likeCount: 5,
        },
      } } }, url);
    }
    if (url.startsWith('https://www.xiaohongshu.com/explore/')) {
      const noteId = (url.match(/explore\/([a-f0-9]+)/i) || [])[1] || 'abcdef123456';
      const isImageNote = noteId === 'abcdef999999';
      const note = isImageNote ? {
        title: 'fixture xiaohongshu images',
        time: 1700000000,
        likedCount: 6,
        user: { userId: 'xhs-user', nickname: '小红书作者', avatar: 'https://cdn.example/xhs-avatar.jpg' },
        imageList: [
          { urlDefault: 'https://cdn.example/xhs-img-1.jpg' },
          { urlDefault: 'https://cdn.example/xhs-img-2.jpg' },
        ],
      } : {
        title: 'fixture xiaohongshu',
        time: 1700000000,
        likedCount: 4,
        user: { userId: 'xhs-user', nickname: '小红书作者', avatar: 'https://cdn.example/xhs-avatar.jpg' },
        imageList: [{ urlDefault: 'https://cdn.example/xhs-cover.jpg' }],
        video: { media: { stream: { master_url: 'https://cdn.example/xhs.mp4' } } },
      };
      const state = { note: { noteDetailMap: { [noteId]: { note } } } };
      return response(`<script>window.__INITIAL_STATE__ = ${json(state)};</script><`, url);
    }
    if (url.includes('weibo.com/tv/api/component/page/tv/show/')) {
      return response({ data: { Component_Play_Playinfo: {
        title: 'fixture weibo', author: '微博作者', real_date: '2024-01-01',
        avatar: '//img.example/weibo-avatar.jpg', cover_image: '//img.example/weibo-cover.jpg',
        urls: { mp4: 'https://cdn.example/weibo.mp4' },
      } } }, url);
    }
    if (url.includes('api.pipix.com/bds/cell/cell_comment')) {
      const itemId = parsed.searchParams.get('cell_id');
      if (itemId === '1002') {
        return response(pipixiaPayload({
          content: 'fixture pipixia gallery',
          author: { id: '123', name: '图集作者' },
          note: { multi_image: [
            { url_list: [{ url: 'https://cdn.example/gallery-1.jpg' }] },
            { url_list: [{ url: '//cdn.example/gallery-2.jpg' }] },
          ] },
        }), url);
      }
      return response(pipixiaPayload(pipixiaItem), url);
    }
    if (url === 'https://share.xiaochuankeji.cn/planck/share/post/detail_h5') {
      return response({ data: { post: {
        content: 'fixture izuiyou',
        member: { name: '最右作者', avatar_urls: { origin: { urls: ['//img.example/izuiyou-avatar.jpg'] } } },
        imgs: [{ url: '//img.example/izuiyou-cover.jpg' }],
        videos: { main: { url: 'https://cdn.example/izuiyou.mp4' } },
      } } }, url);
    }
    if (url === 'https://share.ippzone.com/ppapi/share/fetch_content') {
      return response({ ret: 1, data: { post: {
        content: 'fixture pipigx',
        imgs: [{ id: 77 }],
        videos: { '77': { url: 'https://cdn.example/pipigx.mp4' } },
      } } }, url);
    }
    if (url.startsWith('https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage')) {
      if (url.includes('fixture-weishi-fallback')) return response('{}', url, 404);
      return response({ data: { feeds: [{
        feed_desc_withat: 'fixture weishi', video_url: 'https://cdn.example/weishi.mp4',
        images: [{ url: '//img.example/weishi-cover.jpg' }],
        poster: { nick: '微视作者', avatar: '//img.example/weishi-avatar.jpg', createtime: 1700000000 },
      }] } }, url);
    }
    if (url.startsWith('https://weishi.qq.com/feed/fixture-weishi-fallback')) {
      return response('<script>try { window.Vise.initState = ' + json({ feedsList: [{
        id: 'fixture-weishi-fallback',
        poster: { nick: '微视页面作者', avatar: '//img.example/page-avatar.jpg', createtime: 1700000001 },
        images: [{ url: '//img.example/page-cover.jpg' }],
        videoUrl: 'http://cdn.example/page-weishi.mp4',
        shareInfo: { bodyMap: { 0: { title: 'fixture page weishi' } } },
      }] }) + ';</script><', url);
    }
    if (url.startsWith('https://www.ixigua.com/api/video/info')) {
      if (url.includes('5005')) {
        return response({ video_info: {
          title: 'fixture ixigua top-level',
          video: { video_list: { video_list_1: { main_url: 'https://cdn.example/ixigua-top-level.mp4' } } },
        } }, url);
      }
      if (url.includes('5006')) {
        return response({ data: { video_info: {
          title: 'fixture ixigua nested',
          video: { video_list: { video_list_1: { main_url: Buffer.from('https://cdn.example/ixigua-nested.mp4').toString('base64') } } },
        } } }, url);
      }
      if (url.includes('5007')) {
        if (url.includes('vid=')) return response('{}', url, 404);
        return response({ data: {
          title: 'fixture ixigua second api',
          video: { video_list: { video_list_1: { main_url: Buffer.from('https://cdn.example/ixigua-second.mp4').toString('base64') } } },
        } }, url);
      }
      if (url.includes('5008')) return response({ data: { title: 'metadata only' } }, url);
      if (url.includes('5002') || url.includes('5003') || url.includes('5004')) return response({ data: {} }, url);
      return response({ data: {
        title: 'fixture ixigua', create_time: 1700000000, cover_url: 'https://cdn.example/ixigua-cover.jpg',
        user_info: { user_id: 'xigua-user', name: '西瓜作者' },
        video: { video_list: { video_list_4k: { main_url: Buffer.from('https://cdn.example/ixigua.mp4').toString('base64') } } },
      } }, url);
    }
    if (url === 'https://channels.weixin.qq.com/cgi-bin/mmfinder-bin/feeds/video') {
      return response({ data: { video_info: {
        title: 'fixture shipinhao', url: 'https://cdn.example/shipinhao.mp4',
        cover_url: 'https://cdn.example/shipinhao-cover.jpg', author: { id: 'wx-user', name: '视频号作者' },
      } } }, url);
    }
    if (url.startsWith('https://m.ixigua.com/douyin/share/video/5008')) {
      return response('<script>window._ROUTER_DATA = ' + json({ loaderData: { 'video_(id)/page': { videoInfoRes: { item_list: [{ desc: 'fixture xigua metadata fallback', video: { play_addr: { url_list: ['https://cdn.example/metadata-fallback.mp4'] } } }] } } } }) + ';</script><', url);
    }
    if (url.startsWith('https://m.ixigua.com/douyin/share/video/5002')) {
      const item = {
        desc: 'fixture xigua router',
        create_time: 1700000002,
        author: { nickname: '西瓜页面作者', user_id: 'xigua-router-user', avatar_thumb: { url_list: ['//img.example/router-avatar.jpg'] } },
        video: { play_addr: { url_list: ['https://cdn.example/playwm-router.mp4'] }, cover: { url_list: ['//img.example/router-cover.jpg'] } },
      };
      return response('<script>window._ROUTER_DATA = ' + json({ loaderData: { 'video_(id)/page': { videoInfoRes: { item_list: [item] } } } }) + ';</script><', url);
    }
    if (url.startsWith('https://m.ixigua.com/douyin/share/video/5003')) {
      return response('<script>window._SSR_HYDRATED_DATA = ' + json({ anyVideo: { gidInformation: {
        title: 'fixture xigua hydrated',
        packerData: { video: { videoResource: { normal: { video_list: {
          low: { vheight: 360, main_url: Buffer.from('http://cdn.example/xigua-low.mp4').toString('base64') },
          high: { vheight: 1080, main_url: Buffer.from('http://cdn.example/xigua-high.mp4').toString('base64') },
        } } } } },
      } } }) + ';</script><', url);
    }
    if (url === 'https://www.ixigua.com/video/5004') {
      return response('<script>window._SSR_DATA = ' + json({ data: { storeState: { detail: { videoData: { result: {
        title: 'fixture xigua encrypted',
        media_user: { id: 'xigua-encrypted-user', screen_name: '加密页面作者' },
        cover_image_url: 'https://cdn.example/encrypted-cover.jpg',
        url: xiguaProtectedUrl('https://cdn.example/encrypted-xigua.mp4'),
      } } } } } }) + '</script><', url);
    }

    throw new Error(`Unexpected fixture request: ${method} ${url} ${body || ''}`);
  },
};

parsers.setTransport(transport);
parsers.setWeiboCookie('fixture=weibo');

const checks = [];
async function check(name, callback) {
  try {
    await callback();
    checks.push({ name, ok: true });
    console.log(`[PASS] ${name}`);
  } catch (error) {
    checks.push({ name, ok: false, error });
    console.log(`[FAIL] ${name}: ${error.message}`);
  }
}

function assertMedia(result, platform) {
  assert.equal(result.platform, platform);
  assert.equal(typeof result.url, 'string');
  assert.match(result.url, /^https?:\/\//);
  assert.equal(typeof result.title, 'string');
  assert.equal(typeof result.author, 'string');
  assert.equal(typeof result.cover, 'string');
  assert.equal(typeof result.media_type, 'string');
  assert.ok(Array.isArray(result.images));
  assert.ok(result.music && typeof result.music === 'object');
}

await check('平台注册表包含 11 个平台', () => {
  assert.deepEqual(parsers.getSupportedPlatforms().sort(), [
    'bilibili', 'douyin', 'izuiyou', 'kuaishou', 'pipigx', 'pipixia', 'shipinhao', 'weibo', 'weishi', 'xiaohongshu', 'ixigua',
  ].sort());
});

await check('空 URL 不生成伪协议', () => {
  assert.equal(parsers.fixUrl(''), '');
  assert.equal(parsers.fixUrl(null), '');
  assert.equal(parsers.fixUrl('//cdn.example/file.mp4'), 'https://cdn.example/file.mp4');
});

const cases = [
  ['douyin', 'https://www.douyin.com/video/7123456789012345678'],
  ['kuaishou', 'https://www.kuaishou.com/short-video/fixture-ks'],
  ['bilibili', 'https://www.bilibili.com/video/BV1fixture'],
  ['xiaohongshu', 'https://www.xiaohongshu.com/explore/abcdef123456?source=fixture'],
  ['weibo', 'https://weibo.com/tv/show?fid=1034:4899473006910556'],
  ['pipixia', 'https://www.pipix.com/item/1001'],
  ['izuiyou', 'https://share.izuiyou.com/?pid=2001'],
  ['pipigx', 'https://share.ippzone.com/post/3001'],
  ['weishi', 'https://weishi.qq.com/feed/fixture-weishi'],
  ['ixigua', 'https://www.ixigua.com/item/4001'],
  ['shipinhao', 'https://channels.weixin.qq.com/web/pages/feed?vid=fixture-wx'],
];

for (const [platform, url] of cases) {
  await check(`${platform} 成功解析契约`, async () => {
    const result = await parsers.parse(url);
    assertMedia(result, platform);
  });
}

await check('抖音优先无水印播放接口并排除水印地址', async () => {
  const result = await parsers.parse('https://www.douyin.com/video/7123456789012345678');
  assert.match(result.url, /\/aweme\/v1\/play\/\?/);
  assert.match(result.url, /watermark=0/);
  assert.ok(!result.url.includes('watermark=1'));
});

await check('抖音解析结果包含背景音乐播放地址', async () => {
  const result = await parsers.parse('https://www.douyin.com/video/7123456789012345678');
  assert.equal(result.music.url, 'https://cdn.example/music.mp3');
  assert.equal(result.music.author, '音乐作者');
  assert.equal(result.music.title, 'fixture 背景');
});

await check('小红书图文笔记返回图集', async () => {
  const result = await parsers.parse('https://www.xiaohongshu.com/explore/abcdef999999?source=fixture');
  assert.equal(result.platform, 'xiaohongshu');
  assert.equal(result.media_type, 'image');
  assert.deepEqual(result.images, ['https://cdn.example/xhs-img-1.jpg', 'https://cdn.example/xhs-img-2.jpg']);
  assert.equal(result.url, result.images[0]);
  assert.equal(result.cover, 'https://cdn.example/xhs-img-1.jpg');
});

await check('皮皮虾图集无视频地址仍可解析', async () => {
  const result = await parsers.parse('https://www.pipix.com/item/1002');
  assert.equal(result.platform, 'pipixia');
  assert.equal(result.media_type, 'image');
  assert.deepEqual(result.images, ['https://cdn.example/gallery-1.jpg', 'https://cdn.example/gallery-2.jpg']);
  assert.equal(result.url, result.images[0]);
});

await check('微视分享页 initState 后备解析', async () => {
  const result = await parsers.parse('https://weishi.qq.com/feed/fixture-weishi-fallback');
  assert.equal(result.platform, 'weishi');
  assert.equal(result.title, 'fixture page weishi');
  assert.equal(result.url, 'http://cdn.example/page-weishi.mp4');
});

await check('西瓜顶层 video_info 响应解析', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5005');
  assert.equal(result.title, 'fixture ixigua top-level');
  assert.equal(result.url, 'https://cdn.example/ixigua-top-level.mp4');
});

await check('西瓜 data.video_info 响应解析', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5006');
  assert.equal(result.title, 'fixture ixigua nested');
  assert.equal(result.url, 'https://cdn.example/ixigua-nested.mp4');
});

await check('西瓜首个接口失败后使用第二接口', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5007');
  assert.equal(result.title, 'fixture ixigua second api');
  assert.equal(result.url, 'https://cdn.example/ixigua-second.mp4');
});

await check('西瓜 metadata 无媒体地址时继续页面后备', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5008');
  assert.equal(result.title, 'fixture xigua metadata fallback');
  assert.equal(result.url, 'https://cdn.example/metadata-fallback.mp4');
});

await check('西瓜分享页 ROUTER_DATA 后备解析', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5002');
  assert.equal(result.platform, 'ixigua');
  assert.equal(result.title, 'fixture xigua router');
  assert.equal(result.url, 'https://cdn.example/play-router.mp4');
});

await check('西瓜 SSR_HYDRATED_DATA 后备解析', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5003');
  assert.equal(result.platform, 'ixigua');
  assert.equal(result.title, 'fixture xigua hydrated');
  assert.equal(result.url, 'https://cdn.example/xigua-high.mp4');
});

await check('西瓜 SSR_DATA 加密媒体地址解析', async () => {
  const result = await parsers.parse('https://www.ixigua.com/video/5004');
  assert.equal(result.platform, 'ixigua');
  assert.equal(result.title, 'fixture xigua encrypted');
  assert.equal(result.url, 'https://cdn.example/encrypted-xigua.mp4');
});

await check('抖音主页分页契约', async () => {
  const result = await parsers.parseProfile('https://www.douyin.com/user/fixture-profile', 0);
  assert.equal(result.platform, 'douyin');
  assert.equal(result.cursor, 0);
  assert.equal(result.next_cursor, 123);
  assert.equal(result.has_more, true);
  assert.equal(result.items.length, 1);
  assert.equal(result.items[0].source_url, 'https://www.douyin.com/video/7123456789012345678');
});

await check('POST 请求带 JSON Content-Type', () => {
  const jsonPosts = calls.filter((call) => call.method === 'POST');
  assert.ok(jsonPosts.length >= 5);
  for (const call of jsonPosts) {
    if (call.url.includes('weibo.com')) {
      assert.equal(call.headers['Content-Type'], 'application/x-www-form-urlencoded');
    } else {
      assert.equal(call.headers['Content-Type'], 'application/json');
    }
  }
});

await check('微博无手工 Cookie 时使用网页登录态模式', async () => {
  parsers.setWeiboCookie('');
  const before = calls.length;
  const result = await parsers.parse('https://weibo.com/tv/show?fid=1034:4899473006910557');
  assertMedia(result, 'weibo');
  const call = calls.slice(before).find((item) => item.url.includes('weibo.com/tv/api/component/page/tv/show/'));
  assert.equal(call?.credentialMode, 'weibo-session');
  assert.equal(call?.headers.Cookie, undefined);
  parsers.setWeiboCookie('fixture=weibo');
});

const failed = checks.filter((checkResult) => !checkResult.ok);
console.log(`\n离线契约审计：${checks.length - failed.length} 通过，${failed.length} 失败`);
process.exitCode = failed.length ? 1 : 0;
