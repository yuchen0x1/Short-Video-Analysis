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
    /** 媒体 Cookie 的绝对寿命上限：滑动续期也不能无限延长同一个会话。 */
    private const MEDIA_COOKIE_MAX_LIFETIME = 43200;

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
     * 生成供测试与 Cookie 复用的签名值，格式为 `issuedAt.expiry.signature`。
     * 带上签发时间才能在滑动续期之上再加一道绝对寿命上限。
     */
    public static function mediaCookieValue(int $now, int $ttl = self::MEDIA_COOKIE_TTL): string
    {
        $expiry = $now + max(60, $ttl);
        return $now . '.' . $expiry . '.' . self::mediaSignature($now, $expiry);
    }

    public static function mediaCookieIsValid(string $value, ?int $now = null): bool
    {
        if (!preg_match('/^(\d{10,12})\.(\d{10,12})\.([a-f0-9]{64})$/', $value, $matches)) {
            return false;
        }
        $issuedAt = (int) $matches[1];
        $expiry = (int) $matches[2];
        $current = $now ?? time();
        if ($expiry < $current || $issuedAt > $current + 60) {
            return false;
        }
        if ($current - $issuedAt > self::MEDIA_COOKIE_MAX_LIFETIME) {
            return false;
        }
        $key = (string) Config::get('api.key', '');
        if ($key === '') {
            return false;
        }
        return hash_equals(self::mediaSignature($issuedAt, $expiry), $matches[3]);
    }

    private static function mediaSignature(int $issuedAt, int $expiry): string
    {
        $key = (string) Config::get('api.key', '');
        return hash_hmac('sha256', 'media|' . $issuedAt . '|' . $expiry, $key);
    }

    private static function issueMediaCookie(): void
    {
        $now = time();
        // 已携带仍有效的 Cookie 时保留原签发时间，避免无限制滑动续期。
        $issuedAt = $now;
        $existing = (string) ($_COOKIE[self::MEDIA_COOKIE] ?? '');
        if (preg_match('/^(\d{10,12})\.(\d{10,12})\.([a-f0-9]{64})$/', $existing, $matches)) {
            $existingIssuedAt = (int) $matches[1];
            if ($now - $existingIssuedAt <= self::MEDIA_COOKIE_MAX_LIFETIME) {
                $issuedAt = $existingIssuedAt;
            }
        }
        $value = self::mediaCookieValue($issuedAt);
        $remaining = max(60, $issuedAt + self::MEDIA_COOKIE_MAX_LIFETIME - $now);

        setcookie(self::MEDIA_COOKIE, $value, [
            'expires' => $now + min(self::MEDIA_COOKIE_TTL, $remaining),
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
