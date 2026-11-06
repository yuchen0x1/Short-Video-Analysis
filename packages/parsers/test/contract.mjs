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

const douyinMixItem = {
  ...douyinVideo,
  aweme_id: '7123456789012345679',
  desc: 'fixture douyin mix item 2',
};

const douyinMixInfo = {
  mix_id: '7190000000000000000',
  mix_name: 'fixture douyin mix',
  desc: 'fixture mix desc',
  creator: {
    nickname: '合集作者',
    avatar_medium: { url_list: ['https://cdn.example/mix-avatar.jpg'] },
  },
  statis: { updated_to_eposide: 25 },
};

const douyinLiveRoom = {
  id_str: '123456789',
  title: 'fixture douyin live',
  user_count: 888,
  status: { status: 2 },
  owner: {
    nickname: '抖音主播',
    avatar_thumb: { url_list: ['https://cdn.example/douyin-live-avatar.jpg'] },
  },
  cover: { url_list: ['https://cdn.example/douyin-live-cover.jpg'] },
  stream_url: {
    flv_pull_url: {
      FULL_HD1: 'https://pull-flv.example.com/douyin.flv',
      SD1: 'https://pull-flv.example.com/douyin-sd.flv',
    },
    hls_pull_url_map: {
      FULL_HD1: 'https://pull-hls.example.com/douyin.m3u8',
    },
  },
};

const tiktokItemStruct = {
  id: '7100000000000000001',
  desc: 'fixture tiktok video',
  createTime: '1700000000',
  author: { uniqueId: 'tiktokuser', nickname: 'TikTok 作者', avatarLarger: 'https://cdn.example/tt-avatar.jpg' },
  stats: { diggCount: 99 },
  statsV2: { diggCount: 1234 },
  video: {
    playAddr: 'https://v16m.example.com/tt-play.mp4',
    cover: 'https://cdn.example/tt-cover.jpg',
    bitrateInfo: [
      { Bitrate: 800, PlayAddr: { UrlList: ['https://v16m.example.com/tt-lq.mp4'] } },
      { Bitrate: 2000, PlayAddr: { UrlList: ['https://v16m.example.com/tt-hq.mp4'] } },
    ],
  },
  music: { authorName: '音乐人', title: 'tt 音乐', playUrl: 'https://cdn.example/tt-music.mp3' },
};

const tiktokPhotoItem = {
  id: '7200000000000000002',
  desc: 'fixture tiktok photo',
  createTime: '1700000001',
  author: { uniqueId: 'tiktokuser', nickname: 'TikTok 图集作者', avatarLarger: 'https://cdn.example/tt-avatar.jpg' },
  stats: { diggCount: 31 },
  imagePost: {
    images: [
      { imageURL: { urlList: ['https://cdn.example/tt-img-1.jpg', 'https://cdn.example/tt-img-1b.jpg'] } },
      { imageURL: { urlList: ['https://cdn.example/tt-img-2.jpg'] } },
      { imageURL: { urlList: ['https://cdn.example/tt-img-3.jpg'] } },
    ],
  },
  music: { authorName: '音乐人', title: 'tt 图集音乐', playUrl: 'https://cdn.example/tt-music2.mp3' },
};

const tiktokAweme = {
  aweme_id: '7300000000000000003',
  desc: 'fixture tiktok feed',
  create_time: 1700000002,
  author: { nickname: 'Feed 作者', uid: 'tt-feed', avatar_thumb: { url_list: ['//cdn.example/feed-avatar.jpg'] } },
  statistics: { digg_count: 7 },
  video: { play_addr: { url_list: ['https://cdn.example/feed.mp4'] }, cover: { url_list: ['https://cdn.example/feed-cover.jpg'] } },
  music: { play_url: { url_list: ['https://cdn.example/feed-music.mp3'] } },
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
    if (url.includes('/aweme/v1/web/mix/aweme/')) {
      if (parsed.searchParams.get('cursor') === '456') {
        return response({ aweme_list: [douyinMixItem], cursor: 0, has_more: 0 }, url);
      }
      return response({ aweme_list: [douyinVideo, douyinMixItem], cursor: 456, has_more: 1 }, url);
    }
    if (url.includes('/aweme/v1/web/mix/detail/')) {
      return response({ mix_info: douyinMixInfo }, url);
    }
    if (url.includes('live.douyin.com/webcast/room/web/enter/')) {
      return response({ status_code: 0, data: { data: [douyinLiveRoom] } }, url);
    }
    if (url.includes('api.live.bilibili.com/room/v1/Room/get_info')) {
      const roomId = parsed.searchParams.get('room_id');
      return response({ code: 0, data: {
        room_id: Number(roomId || 6),
        title: 'fixture bilibili live',
        live_status: roomId === '7' ? 0 : 1,
        uid: 7,
        online: 500,
        keyframe: 'https://cdn.example/live-kf.jpg',
      } }, url);
    }
    if (url.includes('api.live.bilibili.com/live_user/v1/Master/info')) {
      return response({ data: { info: {
        uname: 'B站主播',
        face: 'https://cdn.example/live-anchor.jpg',
      } } }, url);
    }
    if (url.includes('api.live.bilibili.com/xlive/web-room/v2/index/getRoomPlayInfo')) {
      return response({ code: 0, data: { playurl_info: { playurl: {
        quality_description: [
          { qn: 10000, desc: '原画' },
          { qn: 250, desc: '流畅' },
        ],
        stream: [
          { protocol_name: 'http_hls', format: [{ format_name: 'fmp4', codec: [
            { base_url: '/live-bvc/hls.m3u8', current_qn: 10000, url_info: [{ host: 'https://cn-hbxy-cm-01-16.bilivideo.com', extra: '?expires=1' }] },
          ] }] },
          { protocol_name: 'http_stream', format: [{ format_name: 'flv', codec: [
            { base_url: '/live-bvc/stream.flv', current_qn: 250, url_info: [{ host: 'https://cn-hbxy-cm-01-16.bilivideo.com', extra: '?expires=1' }] },
          ] }] },
        ],
      } } } }, url);
    }
    if (url.includes('api.live.bilibili.com/room/v1/Room/playUrl')) {
      return response({ code: 0, data: { durl: [{ url: 'https://cdn.example/legacy-live.flv' }] } }, url);
    }
    if (url.startsWith('https://www.tiktok.com/embed/v2/7100000000000000001')) {
      return response(
        `<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__">${json({ __DEFAULT_SCOPE__: { 'webapp.video-detail': { itemInfo: { itemStruct: tiktokItemStruct } } } })}</script>`,
        url
      );
    }
    if (url.startsWith('https://www.tiktok.com/embed/v2/')) {
      // 其余 id 无 embed 数据，触发下一条回退路径
      return response('<html><body>embed</body></html>', url);
    }
    if (url.startsWith('https://www.tiktok.com/@tiktok/video/7200000000000000002')) {
      return response(
        `<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__">${json({ __DEFAULT_SCOPE__: { 'webapp.video-detail': { itemInfo: { itemStruct: tiktokPhotoItem } } } })}</script>`,
        url
      );
    }
    if (url.includes('tiktokv.com/aweme/v1/feed/')) {
      return response({ aweme_list: [tiktokAweme] }, url);
    }
    if (/https?:\/\/v[mt]\.tiktok\.com\//i.test(url)) {
      return response('', 'https://www.tiktok.com/@user/video/7100000000000000001', 301);
    }
    if (url === 'https://www.kuaishou.com/f/fixture-share-token') {
      return count === 1
        ? response('', url, 200)
        : response('', 'https://www.kuaishou.com/short-video/fixture-ks', 302);
    }
    if (url.includes('api.bilibili.com/x/frontend/finger/spi')) {
      return response({ data: { b_3: 'fixture-buvid3', b_4: 'fixture-buvid4' } }, url);
    }
    if (url.includes('api.bilibili.com/x/web-interface/popular')) {
      return response({ code: 0, data: { list: [
        { bvid: 'BVhot001', title: '<em class="kw">热门</em>视频一', pic: '//cdn.example/hot-1.jpg',
          owner: { name: '热门作者' }, stat: { view: 123456 }, duration: '3:21' },
        { bvid: 'BVhot002', title: '热门视频二', pic: 'https://cdn.example/hot-2.jpg',
          owner: { name: '热门作者' }, stat: { view: 789 }, duration: '1:02' },
      ] } }, url);
    }
    if (url.includes('api.bilibili.com/x/web-interface/search/type')) {
      return response({ code: 0, data: {
        numResults: 42,
        result: [
          { bvid: 'BVsrch01', title: '<em class="keyword">搜索</em>结果一', pic: '//cdn.example/s-1.jpg',
            author: '搜索作者', play: 999, duration: '0:30' },
        ],
      } }, url);
    }
    if (url.includes('api.bilibili.com/x/v2/reply')) {
      return response({ code: 0, data: {
        page: { count: 60, size: 20, num: 1 },
        replies: [
          { member: { uname: '评论用户A', avatar: '//cdn.example/u-a.jpg' },
            content: { message: 'fixture 评论一' }, like: 12, ctime: 1700000000 },
          { member: { uname: '评论用户B', avatar: 'https://cdn.example/u-b.jpg' },
            content: { message: 'fixture 评论二' }, like: 3, ctime: 1700000001 },
        ],
      } }, url);
    }
    if (url.includes('douyin.com/aweme/v1/web/hot/search/list/')) {
      return response({ data: { word_list: [
        { word: '热点词一', hot_value: 556677, word_cover: { url_list: ['//cdn.example/w1.jpg'] } },
        { word: '热点词二', hot_value: 112233, word_cover: { url_list: [] } },
      ] } }, url);
    }
    if (/^https?:\/\/www\.huya\.com\/\d+/.test(url)) {
      const roomId = url.match(/www\.huya\.com\/(\d+)/)[1];
      const streamPayload = {
        data: [{
          gameLiveInfo: {
            profileRoom: Number(roomId), roomName: 'fixture huya room ' + roomId, nick: '虎牙主播',
            avatar180: 'https://cdn.example/huya-avatar.jpg', screenshot: 'https://cdn.example/huya-cover.jpg',
            totalCount: 4321,
          },
          gameStreamInfoList: roomId === '9' ? [] : [
            { sCdnType: 'AL', sStreamName: '1134-flv', sFlvUrl: 'http://al.flv.huya.com/src', sFlvUrlSuffix: 'flv', sFlvAntiCode: 'wsSecret=abc&wsTime=1' },
            { sCdnType: 'TX', sStreamName: '1134-flv', sFlvUrl: 'http://tx.flv.huya.com/src', sFlvUrlSuffix: 'flv', sFlvAntiCode: 'wsSecret=def&wsTime=1' },
            { sCdnType: 'AL', sStreamName: 'dup', sFlvUrl: 'http://al.flv.huya.com/src', sFlvUrlSuffix: 'flv', sFlvAntiCode: 'dup' },
          ],
        }],
      };
      return response(`<script>var hyPlayerConfig = {html5: 1, stream: ${json(streamPayload)}};</script>`, url);
    }
    if (url.includes('open.douyucdn.cn/api/RoomApi/room/')) {
      const roomId = url.match(/RoomApi\/room\/(\d+)/)[1];
      return response({ error: 0, data: {
        room_id: roomId,
        room_name: 'fixture douyu room ' + roomId,
        owner_name: '斗鱼主播',
        avatar: 'https://cdn.example/douyu-avatar.jpg',
        room_thumb: 'https://cdn.example/douyu-cover.jpg',
        room_status: roomId === '7' ? '2' : '1',
        online: 1234,
      } }, url);
    }
    if (url.includes('www.douyu.com/swf_api/homeH5Enc')) {
      // fixture 签名函数使用 CryptoJS.MD5('abc')（公开测试向量 900150983cd24fb0d6963f7d28e17f72）校验内嵌 MD5 实现
      return response({ error: 0, data: { room8: 'function ub98484234(rid, did, tt){return "v=test&did=" + did + "&tt=" + tt + "&sign=md5check_" + CryptoJS.MD5("abc").toString();}' } }, url);
    }
    if (url.includes('www.douyu.com/lapi/live/getH5Play/')) {
      const query = new URL(url).searchParams;
      const rate = query.get('rate') || '0';
      const flv = rate === '8'
        ? 'https://douyucdn2.example/live/fixture8.flv?wsAuth=b'
        : 'https://douyucdn2.example/live/fixture0.flv?wsAuth=a';
      return response({ error: 0, msg: 'ok', data: {
        rtmp_url: 'https://douyucdn2.example/live',
        rtmp_live: rate === '8' ? 'fixture8.flv?wsAuth=b' : 'fixture0.flv?wsAuth=a',
        multirates: [
          { name: '原画', rate: 0 },
          { name: '蓝光8M', rate: 8 },
        ],
      } }, url);
    }
    if (url.includes('api.bilibili.com/x/web-interface/view')) {
      return response({ code: 0, data: {
        bvid: 'BV1fixture',
        aid: 9001,
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
      const isEfVideoNote = noteId === 'abcdef777777';
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
        title: isEfVideoNote ? 'fixture xiaohongshu EF streams' : 'fixture xiaohongshu',
        time: 1700000000,
        likedCount: 4,
        user: { userId: 'xhs-user', nickname: '小红书作者', avatar: 'https://cdn.example/xhs-avatar.jpg' },
        imageList: [{ urlDefault: 'https://cdn.example/xhs-cover.jpg' }],
        video: {
          media: {
            stream: isEfVideoNote
              ? {
                  EF4: [{ masterUrl: 'https://cdn.example/xhs-ef4.mp4', height: 720, avgBitrate: 1200 }],
                  EF6: [{ master_url: 'https://cdn.example/xhs-ef6.mp4', height: 1080, avg_bitrate: 1800 }],
                  EF7: [{ masterUrl: 'https://cdn.example/xhs-ef7.mp4', height: 1080, avgBitrate: 2200 }],
                }
              : { master_url: 'https://cdn.example/xhs.mp4' },
          },
        },
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

await check('平台注册表包含 12 个平台', () => {
  assert.deepEqual(parsers.getSupportedPlatforms().sort(), [
    'bilibili', 'douyin', 'izuiyou', 'kuaishou', 'pipigx', 'pipixia', 'shipinhao', 'weibo', 'weishi', 'xiaohongshu', 'ixigua', 'tiktok',
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
  ['tiktok', 'https://www.tiktok.com/@user/video/7100000000000000001'],
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

await check('抖音请求携带 Argus 网关头且详情指纹参数同源', async () => {
  await parsers.parse('https://www.douyin.com/video/7123456789012345678');
  const detailCall = calls.findLast((item) => item.url.includes('/aweme/v1/web/aweme/detail/'));
  assert.ok(detailCall, '应发起抖音详情请求');
  assert.equal(detailCall.headers['x-tt-argus'], '1');
  const query = new URL(detailCall.url).searchParams;
  assert.equal(query.get('verifyFp'), query.get('fp'));
  assert.ok(detailCall.headers.Cookie.includes(`s_v_web_id=${query.get('verifyFp')}`));
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

await check('小红书 EF 分档按分辨率和码率选择最高质量视频', async () => {
  const result = await parsers.parse('https://www.xiaohongshu.com/explore/abcdef777777?source=fixture');
  assert.equal(result.platform, 'xiaohongshu');
  assert.equal(result.media_type, 'video');
  assert.equal(result.url, 'https://cdn.example/xhs-ef7.mp4');
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

await check('抖音合集分页契约', async () => {
  const result = await parsers.parseCollection('https://www.douyin.com/collection/7190000000000000000', 0);
  assert.equal(result.platform, 'douyin');
  assert.equal(result.collection.mix_id, '7190000000000000000');
  assert.equal(result.collection.title, 'fixture douyin mix');
  assert.equal(result.collection.author, '合集作者');
  assert.equal(result.collection.total, 25);
  assert.equal(result.cursor, 0);
  assert.equal(result.next_cursor, 456);
  assert.equal(result.has_more, true);
  assert.equal(result.items.length, 2);
  assert.equal(result.items[0].source_url, 'https://www.douyin.com/video/7123456789012345678');
});

await check('抖音合集第二页使用 cursor 参数', async () => {
  const result = await parsers.parseCollection('https://www.douyin.com/collection/7190000000000000000', 456);
  assert.equal(result.has_more, false);
  assert.equal(result.items.length, 1);
  assert.equal(result.items[0].source_url, 'https://www.douyin.com/video/7123456789012345679');
  const call = calls.find((item) => {
    if (!item.url.includes('/aweme/v1/web/mix/aweme/')) return false;
    return new URL(item.url).searchParams.get('cursor') === '456';
  });
  assert.ok(call, '第二页请求应携带 cursor=456');
});

await check('合集链接不会被当作单条视频解析', async () => {
  await assert.rejects(
    () => parsers.parse('https://www.douyin.com/collection/7190000000000000000'),
    /合集/
  );
});

await check('B站直播解析契约（v2 拉流 + 主播信息）', async () => {
  const result = await parsers.parseLive('https://live.bilibili.com/6');
  assert.equal(result.platform, 'bilibili');
  assert.equal(result.live.status, 'live');
  assert.equal(result.live.room_id, '6');
  assert.equal(result.live.anchor, 'B站主播');
  assert.equal(result.live.viewers, 500);
  assert.ok(result.streams.length >= 2);
  assert.ok(result.streams.some((s) => s.format === 'hls' && s.quality === '原画'));
  assert.ok(result.streams.some((s) => s.format === 'flv' && s.quality === '流畅'));
  assert.ok(result.streams.every((s) => /^https?:\/\//.test(s.url)));
});

await check('B站未开播直播间返回空拉流列表', async () => {
  const result = await parsers.parseLive('https://live.bilibili.com/7');
  assert.equal(result.live.status, 'offline');
  assert.deepEqual(result.streams, []);
});

await check('抖音直播解析契约（flv/hls 画质映射）', async () => {
  const result = await parsers.parseLive('https://live.douyin.com/123456789');
  assert.equal(result.platform, 'douyin');
  assert.equal(result.live.status, 'live');
  assert.equal(result.live.room_id, '123456789');
  assert.equal(result.live.anchor, '抖音主播');
  assert.equal(result.live.viewers, 888);
  assert.ok(result.streams.some((s) => s.format === 'flv' && s.quality === '蓝光'));
  assert.ok(result.streams.some((s) => s.format === 'flv' && s.quality === '标清'));
  assert.ok(result.streams.some((s) => s.format === 'hls' && s.quality === '蓝光'));
});

await check('直播链接不会被当作单条视频解析', async () => {
  await assert.rejects(
    () => parsers.parse('https://live.douyin.com/123456789'),
    /直播/
  );
  await assert.rejects(
    () => parsers.parse('https://live.bilibili.com/6'),
    /直播/
  );
});

await check('TikTok 视频解析契约（embed 水合数据 + 最高码率 playAddr）', async () => {
  const result = await parsers.parse('https://www.tiktok.com/@user/video/7100000000000000001');
  assertMedia(result, 'tiktok');
  assert.equal(result.url, 'https://v16m.example.com/tt-hq.mp4');
  assert.equal(result.author, 'TikTok 作者');
  assert.equal(result.like, 1234);
  assert.equal(result.music.url, 'https://cdn.example/tt-music.mp3');
  assert.equal(result.time, 1700000000);
});

await check('TikTok statsV2 优先于旧 stats', async () => {
  const result = await parsers.parse('https://www.tiktok.com/@user/video/7100000000000000001');
  assert.equal(result.like, 1234);
});

await check('TikTok 短链解析', async () => {
  const result = await parsers.parse('https://vm.tiktok.com/abc/');
  assert.equal(result.platform, 'tiktok');
  assert.equal(result.url, 'https://v16m.example.com/tt-hq.mp4');
});

await check('快手 /f/ 短链经二次重定向解析', async () => {
  const result = await parsers.parse('https://www.kuaishou.com/f/fixture-share-token');
  assert.equal(result.platform, 'kuaishou');
  assert.equal(result.url, 'https://cdn.example/kuaishou.mp4');
});

await check('TikTok 图集解析契约', async () => {
  const result = await parsers.parse('https://www.tiktok.com/@user/photo/7200000000000000002');
  assert.equal(result.platform, 'tiktok');
  assert.equal(result.media_type, 'image');
  assert.deepEqual(result.images, [
    'https://cdn.example/tt-img-1.jpg',
    'https://cdn.example/tt-img-2.jpg',
    'https://cdn.example/tt-img-3.jpg',
  ]);
  assert.equal(result.url, result.images[0]);
  assert.equal(result.music.url, 'https://cdn.example/tt-music2.mp3');
});

await check('TikTok feed 接口兜底路径', async () => {
  const result = await parsers.parse('https://www.tiktok.com/@user/video/7300000000000000003');
  assert.equal(result.platform, 'tiktok');
  assert.equal(result.url, 'https://cdn.example/feed.mp4');
  assert.equal(result.author, 'Feed 作者');
  assert.equal(result.avatar, 'https://cdn.example/feed-avatar.jpg');
});

await check('发现：B站热榜契约（去标签 + 协议补全）', async () => {
  const result = await parsers.hotList('bilibili', 1);
  assert.equal(result.platform, 'bilibili');
  assert.equal(result.items.length, 2);
  assert.equal(result.items[0].title, '热门视频一');
  assert.equal(result.items[0].cover, 'https://cdn.example/hot-1.jpg');
  assert.equal(result.items[0].hot, 123456);
  assert.equal(result.has_more, true);
  assert.equal(result.next_page, 2);
});

await check('发现：抖音热榜契约（签名请求 + 热点词）', async () => {
  const result = await parsers.hotList('douyin');
  assert.equal(result.platform, 'douyin');
  assert.equal(result.items.length, 2);
  assert.equal(result.items[0].title, '热点词一');
  assert.equal(result.items[0].hot, 556677);
  assert.equal(result.items[1].cover, '');
  assert.equal(result.has_more, false);
});

await check('发现：B站搜索契约（buvid3 Cookie 随请求下发）', async () => {
  const result = await parsers.search('bilibili', '搜索词', 1);
  assert.equal(result.platform, 'bilibili');
  assert.equal(result.total, 42);
  assert.equal(result.items[0].title, '搜索结果一');
  assert.equal(result.has_more, true);
  const searchCall = calls.find((item) => item.url.includes('/x/web-interface/search/type'));
  assert.ok(searchCall?.headers.Cookie?.includes('buvid3=fixture-buvid3'), '搜索请求应携带 buvid3 Cookie');
});

await check('发现：B站评论契约（BV 换 aid + 分页）', async () => {
  const result = await parsers.comments('https://www.bilibili.com/video/BV1fixture', 1);
  assert.equal(result.platform, 'bilibili');
  assert.equal(result.total, 60);
  assert.equal(result.comments.length, 2);
  assert.equal(result.comments[0].user, '评论用户A');
  assert.equal(result.comments[0].avatar, 'https://cdn.example/u-a.jpg');
  assert.equal(result.has_more, true);
  assert.equal(result.next_page, 2);
});

await check('发现：合规拒绝路径', async () => {
  await assert.rejects(() => parsers.hotList('kuaishou'), /不支持的热榜平台/);
  await assert.rejects(() => parsers.search('bilibili', '  '), /关键词不能为空/);
  await assert.rejects(() => parsers.search('douyin', '词'), /暂不开放/);
  await assert.rejects(() => parsers.comments('https://www.douyin.com/video/7123456789012345678'), /仅支持 B 站/);
});

await check('虎牙直播解析契约（页面直取 + CDN 去重）', async () => {
  const result = await parsers.parseLive('https://www.huya.com/11342412');
  assert.equal(result.platform, 'huya');
  assert.equal(result.live.status, 'live');
  assert.equal(result.live.room_id, '11342412');
  assert.equal(result.live.anchor, '虎牙主播');
  assert.equal(result.live.viewers, 4321);
  assert.ok(result.streams.length === 2, '同 CDN 应去重');
  assert.ok(result.streams.every((s) => s.format === 'flv'));
  assert.match(result.streams[0].url, /^http:\/\/al\.flv\.huya\.com\/src\/1134-flv\.flv\?wsSecret=abc/);
  assert.match(result.streams[1].quality, /原画 · TX/);
});

await check('虎牙未开播返回空拉流列表', async () => {
  const result = await parsers.parseLive('https://www.huya.com/9');
  assert.equal(result.live.status, 'offline');
  assert.deepEqual(result.streams, []);
});

await check('虎牙链接不会被当作单条视频解析', async () => {
  await assert.rejects(
    () => parsers.parse('https://www.huya.com/11342412'),
    /直播/
  );
});

await check('斗鱼直播解析契约（签名 eval + 多画质）', async () => {
  const result = await parsers.parseLive('https://www.douyu.com/8');
  assert.equal(result.platform, 'douyu');
  assert.equal(result.live.status, 'live');
  assert.equal(result.live.room_id, '8');
  assert.equal(result.live.anchor, '斗鱼主播');
  assert.equal(result.live.viewers, 1234);
  assert.deepEqual(result.streams.map((s) => s.quality), ['原画', '蓝光8M']);
  assert.equal(result.streams[1].url, 'https://douyucdn2.example/live/fixture8.flv?wsAuth=b');
  // 内嵌纯 JS MD5 用公开测试向量校验：MD5('abc') = 900150983cd24fb0d6963f7d28e17f72
  const signCall = calls.find((item) => item.url.includes('/lapi/live/getH5Play/8'));
  assert.match(new URL(signCall.url).search, /sign=md5check_900150983cd24fb0d6963f7d28e17f72/);
});

await check('斗鱼未开播返回空拉流列表', async () => {
  const result = await parsers.parseLive('https://www.douyu.com/7');
  assert.equal(result.live.status, 'offline');
  assert.deepEqual(result.streams, []);
});

await check('斗鱼链接不会被当作单条视频解析', async () => {
  await assert.rejects(
    () => parsers.parse('https://www.douyu.com/8'),
    /直播/
  );
});

await check('POST 请求带 JSON Content-Type', () => {
  const jsonPosts = calls.filter((call) => call.method === 'POST');
  assert.ok(jsonPosts.length >= 5);
  for (const call of jsonPosts) {
    if (call.url.includes('weibo.com') || call.url.includes('douyu.com/lapi/live/')) {
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
