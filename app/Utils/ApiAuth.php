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
    /**
     * 校验请求携带的 API Key；未开启或校验通过时静默返回，否则输出 401 并退出。
     */
    public static function verify(): void
    {
        if (!Config::get('api.key_enabled', false)) {
            return;
        }

        $apiKey = (string) Config::get('api.key', '');
        $provided = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
        if ($provided === '') {
            if (preg_match('/^Bearer\s+(.+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
                $provided = trim($m[1]);
            }
        }

        if ($apiKey === '' || !hash_equals($apiKey, $provided)) {
            Response::error('无效或缺失 API Key', 401);
        }
    }
}
