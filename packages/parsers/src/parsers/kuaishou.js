// 快手解析器：短链解析取 photoId，visionVideoDetail 接口取视频信息
import { fetchOk, parseJson, resolveShortLink } from '../http.js';
import { mobile } from '../ua.js';
import { fixUrl } from '../utils.js';

function headers() {
  return {
    'User-Agent': mobile(),
    Referer: 'https://www.kuaishou.com/',
    'Content-Type': 'application/json',
  };
}

export async function parse(url) {
  const target = (await resolveShortLink(url, headers())) || url;

  const photoId =
    target.match(/(?:photo|short-video)\/(\w+)/i)?.[1] ||
    target.match(/\/(?:v|video)\/(\w+)/i)?.[1];
  if (!photoId) {
    throw new Error('无法解析快手视频 ID');
  }

  const body = JSON.stringify({
    operationName: 'visionVideoDetail',
    query: 'query visionVideoDetail($photoId: String, $type: String, $page: String, $webPageArea: String) {\n  visionVideoDetail(photoId: $photoId, type: $type, page: $page, webPageArea: $webPageArea) {\n    status\n    type\n    author { id name headerUrl }\n    photo {\n      id\n      caption\n      coverUrl\n      duration\n      likeCount\n      viewCount\n      photoUrl\n      timestamp\n    }\n  }\n}',
    variables: { photoId, page: 'selected' },
  });

  const response = await fetchOk('https://www.kuaishou.com/graphql', body, headers());
  const data = response ? parseJson(response.data) : null;
  const errorText = JSON.stringify(data?.errors || data?.error_msg || '');
  if (data?.captcha || data?.result === 2 || /captcha|验证码|风控/i.test(errorText)) {
    throw new Error('快手接口需要验证码或登录态');
  }
  const detail = data?.data?.visionVideoDetail;
  const photo = detail?.photo;
  if (!photo || !photo.photoUrl) {
    throw new Error('获取快手视频信息失败');
  }

  return {
    author: detail.author?.name || '',
    uid: detail.author?.id ?? '',
    avatar: detail.author?.headerUrl || '',
    like: photo.likeCount ?? 0,
    time: photo.timestamp ?? 0,
    title: photo.caption || '',
    cover: photo.coverUrl || '',
    url: fixUrl(photo.photoUrl),
    music: {
      author: '',
      avatar: '',
    },
  };
}
