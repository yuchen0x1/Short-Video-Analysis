<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * 可选 API Key 鉴权（X-API-Key 或 Authorization: Bearer）
 *
 * index.php 的媒体代理与 api/v1.php 共用同一套校验逻辑。
 */
final class ApiAuth
{
    private const MEDIA_COOKIE = 'qushuiyin_media';
    private const MEDIA_COOKIE_TTL = 7200;

    /**
     * 校验请求携带的 API Key；未开启或校验通过时静默返回，否则输出 401 并退出。
     */
    public static function verify(): void
    {
        if (!Config::get('api.key_enabled', false)) {
            return;
        }

        if (!self::headerKeyIsValid()) {
            Response::error('无效或缺失 API Key', 401);
        }

        // 浏览器无法给 <video>/<img> 请求附加自定义请求头；校验通过后
        // 下发短期、仅限媒体代理使用的签名 Cookie，避免把 Key 写进 URL。
        self::issueMediaCookie();
    }

    /**
     * 媒体代理鉴权：允许 API Key 请求头，或由 API 请求换取的短期媒体 Cookie。
     */
    public static function verifyMediaRequest(): void
    {
        if (!Config::get('api.key_enabled', false)) {
            return;
        }
        if (self::headerKeyIsValid()) {
            self::issueMediaCookie();
            return;
        }
        if (self::mediaCookieIsValid((string) ($_COOKIE[self::MEDIA_COOKIE] ?? ''))) {
            return;
        }
        Response::error('无效或缺失 API Key', 401, null, 'unauthorized', false);
    }

    private static function headerKeyIsValid(): bool
    {
        $apiKey = (string) Config::get('api.key', '');
        if ($apiKey === '') {
            return false;
        }

        $provided = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
        if ($provided === '') {
            if (preg_match('/^Bearer\s+(.+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
                $provided = trim($m[1]);
            }
        }
        return hash_equals($apiKey, $provided);
    }

    /**
     * 生成供测试与 Cookie 复用的签名值，格式为 `expiry.signature`。
     */
    public static function mediaCookieValue(int $now, int $ttl = self::MEDIA_COOKIE_TTL): string
    {
        $expiry = $now + max(60, $ttl);
        $key = (string) Config::get('api.key', '');
        $signature = hash_hmac('sha256', 'media|' . $expiry, $key);
        return $expiry . '.' . $signature;
    }

    public static function mediaCookieIsValid(string $value, ?int $now = null): bool
    {
        if (!preg_match('/^(\d{10,12})\.([a-f0-9]{64})$/', $value, $matches)) {
            return false;
        }
        $expiry = (int) $matches[1];
        if ($expiry < ($now ?? time())) {
            return false;
        }
        $key = (string) Config::get('api.key', '');
        if ($key === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', 'media|' . $expiry, $key), $matches[2]);
    }

    private static function issueMediaCookie(): void
    {
        $value = self::mediaCookieValue(time());
        setcookie(self::MEDIA_COOKIE, $value, [
            'expires' => time() + self::MEDIA_COOKIE_TTL,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
