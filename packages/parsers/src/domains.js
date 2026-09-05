// 平台域名表，与 PHP app/Services/VideoParser.php 的 PLATFORMS 一致
export const PLATFORMS = {
  douyin: { domains: ['douyin.com'] },
  kuaishou: { domains: ['kuaishou.com', 'kuaishou.cn'] },
  bilibili: { domains: ['bilibili.com', 'b23.tv'] },
  pipixia: { domains: ['pipix.com'] },
  weibo: { domains: ['weibo.com'] },
  ixigua: { domains: ['ixigua.com'] },
  shipinhao: { domains: ['channels.weixin.qq.com'] },
  izuiyou: { domains: ['izuiyou.com'] },
  pipigx: { domains: ['ippzone.com', 'pipigx.com'] },
  weishi: { domains: ['weishi.qq.com'] },
  xiaohongshu: { domains: ['xiaohongshu.com', 'xhslink.com'] },
};

export function detectPlatform(url) {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    return null;
  }
  host = host.toLowerCase().replace(/^www\./, '');

  for (const [platform, config] of Object.entries(PLATFORMS)) {
    for (const domain of config.domains) {
      if (host === domain || host.endsWith('.' + domain)) {
        return platform;
      }
    }
  }
  return null;
}
