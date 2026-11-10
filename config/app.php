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

    // 在线更新源：桌面端由 electron-updater 使用，网站/手机版通过 API 检查（统一使用 GitHub Releases）
    'updates' => [
        'github_owner' => Config::env('UPDATE_GITHUB_OWNER', 'yuchen0x1'),
        'github_repo' => Config::env('UPDATE_GITHUB_REPO', 'Short-Video-Analysis'),
        'github_api_url' => Config::env('UPDATE_GITHUB_API_URL', 'https://api.github.com/repos/yuchen0x1/Short-Video-Analysis/releases/latest'),
        'github_releases_url' => Config::env('UPDATE_GITHUB_RELEASES_URL', 'https://github.com/yuchen0x1/Short-Video-Analysis/releases'),
        // /api/v1/update 的短 TTL 缓存秒数，避免反复消耗 GitHub 匿名配额
        'cache_ttl' => (int) Config::env('UPDATE_CACHE_TTL', '900'),
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
        'max_json_body_bytes' => max(1024, (int) Config::env('MAX_JSON_BODY_BYTES', 1048576)),
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
        // 默认白名单集中在 config/media_domains.php，避免与客户端运行时漂移；
        // 显式设置 MEDIA_PROXY_ALLOWED_DOMAINS 时以环境变量为准。
        'allowed_domains' => (static function (): array {
            $env = trim((string) Config::env('MEDIA_PROXY_ALLOWED_DOMAINS', ''));
            if ($env !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $env))));
            }
            $domains = require __DIR__ . '/media_domains.php';
            return is_array($domains) ? array_values($domains) : [];
        })(),
        'strict_mode' => Config::env('MEDIA_PROXY_STRICT_MODE', 'true') === 'true',
    ],

    // 日志配置
    'logging' => [
        'level' => Config::env('LOG_LEVEL', 'error'), // debug, info, warning, error
        'file' => __DIR__ . '/../../storage/logs/app.log',
        'max_files' => (int)Config::env('LOG_MAX_FILES', '30'),
    ],
];

