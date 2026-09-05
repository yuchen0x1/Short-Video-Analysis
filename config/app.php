<?php

use App\Utils\Config;

// 确保环境变量已加载
Config::env('APP_NAME');

/**
 * 应用配置文件
 */
return [
    // 应用基础配置
    'app' => [
        'name' => Config::env('APP_NAME', 'Short Video No-Watermark Downloader'),
        'debug' => Config::env('APP_DEBUG', 'false') === 'true',
        'cors_allow_origin' => Config::env('APP_CORS_ALLOW_ORIGIN', '*'),
        'env' => Config::env('APP_ENV', 'production'), // production, staging, local
    ],

    // 在线更新源：桌面端由 electron-updater 使用，网站/手机版通过 API 检查
    'updates' => [
        'gitee_owner' => Config::env('UPDATE_GITEE_OWNER', 'yuchen0x1'),
        'gitee_repo' => Config::env('UPDATE_GITEE_REPO', 'short-video-watermark-remover'),
        'gitee_url' => Config::env('UPDATE_GITEE_URL', 'https://gitee.com/yuchen0x1/short-video-watermark-remover/releases'),
    ],

    // API 速率限制配置
    'rate_limit' => [
        'enabled' => Config::env('RATE_LIMIT_ENABLED', 'true') !== 'false',
        'max_requests' => (int)Config::env('RATE_LIMIT_MAX_REQUESTS', 60),
        'time_window' => (int)Config::env('RATE_LIMIT_TIME_WINDOW', 60),
        'trust_proxy_headers' => Config::env('RATE_LIMIT_TRUST_PROXY_HEADERS', 'false') === 'true',
        'fail_open' => Config::env('RATE_LIMIT_FAIL_OPEN', 'false') === 'true',
        // 媒体转发独立限额（复用 rate_limit.time_window 时间窗口）
        'media_max_requests' => (int)Config::env('RATE_LIMIT_MEDIA_MAX_REQUESTS', 120),
    ],

    // API Key 鉴权（可选开启）
    'api' => [
        'key_enabled' => Config::env('API_KEY_ENABLED', 'false') === 'true',
        'key' => Config::env('API_KEY', ''),
    ],

    // HTTP 客户端配置
    'curl' => [
        'connect_timeout' => (int)Config::env('CURL_CONNECT_TIMEOUT', 5),
        'timeout' => (int)Config::env('CURL_TIMEOUT', 10),
        'max_retries' => (int)Config::env('CURL_MAX_RETRIES', 3),
        'cafile' => Config::env('CURL_CA_BUNDLE', __DIR__ . '/../storage/certs/cacert.pem'),
    ],

    // 媒体代理配置
    'media_proxy' => [
        'enabled' => Config::env('MEDIA_PROXY_ENABLED', 'true') !== 'false',
        'max_file_size' => (int)Config::env('MEDIA_PROXY_MAX_SIZE', '524288000'), // 500MB
        'allowed_domains' => array_filter(array_map('trim', explode(',', Config::env(
            'MEDIA_PROXY_ALLOWED_DOMAINS',
            'douyinvod.com,douyinvod.com.cn,douyinstatic.com,bytecdn.cn,ibytedtos.com,bilivideo.com,mcdn.bilivideo.cn,ixigua.com,ixiguavideo.com,video.weishi.qq.com,ippzone.com,pipix.com'
        )))),
        'strict_mode' => Config::env('MEDIA_PROXY_STRICT_MODE', 'true') === 'true',
    ],

    // 日志配置
    'logging' => [
        'level' => Config::env('LOG_LEVEL', 'error'), // debug, info, warning, error
        'file' => __DIR__ . '/../../storage/logs/app.log',
        'max_files' => (int)Config::env('LOG_MAX_FILES', '30'),
    ],
];

