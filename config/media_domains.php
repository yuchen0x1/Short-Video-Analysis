<?php

declare(strict_types=1);

/**
 * 媒体代理默认域名白名单（单一来源）
 *
 * 覆盖 12 个点播平台与 4 个直播平台实际返回的媒体 CDN。
 * 修改此文件后，必须同步以下两个运行时（有 PHPUnit 用例校验一致性）：
 * - apps/app-shell/electron/main.cjs  MEDIA_ALLOWED_DOMAINS
 * - apps/mobile/android/app/src/main/java/com/yuchen/qushuiyin/MediaProxyPlugin.java
 *   MediaProxySecurity.ALLOWED_MEDIA_DOMAINS
 *
 * 匹配规则见 MediaProxy::matchesDomain：完全相等或 ".域名" 子域名后缀。
 */
return [
    // 抖音
    'douyin.com',
    'douyinvod.com',
    'douyinvod.com.cn',
    'douyinstatic.com',
    'douyinpic.com',
    'bytecdn.cn',
    'ibytedtos.com',
    // B站
    'bilivideo.com',
    'hdslb.com',
    'mcdn.bilivideo.cn',
    // 西瓜视频
    'ixigua.com',
    'ixiguavideo.com',
    // 微视
    'video.weishi.qq.com',
    // 皮皮搞笑 / 皮皮虾
    'ippzone.com',
    'pipix.com',
    // TikTok
    'tiktok.com',
    'tiktokcdn.com',
    'tiktokcdn-us.com',
    // 虎牙 / 斗鱼
    'huya.com',
    'msstatic.com',
    'douyucdn.cn',
    'douyucdn2.cn',
    // 小红书（sns-img-*/sns-video-* 图片与视频）
    'xhscdn.com',
    // 快手（视频 kwaicdn / 图片 kwimgs、yximgs）
    'kwaicdn.com',
    'kwimgs.com',
    'yximgs.com',
    // 微博（视频 weibocdn / 图片 sinaimg）
    'weibocdn.com',
    'sinaimg.cn',
    // 视频号（视频 finder.video.qq.com / 图片 qpic.cn）
    'finder.video.qq.com',
    'qpic.cn',
    // 最右
    'izuiyou.com',
    'xiaochuankeji.cn',
];
