<?php

namespace App\Utils;

/**
 * Cookie 管理工具
 */
class CookieManager
{
    private static ?array $cookies = null;
    private static array $runtimeCookies = [];

    /**
     * 设置运行期 Cookie 覆盖
     *
     * @param string $platform 平台名称
     * @param string $cookie Cookie 字符串
     */
    public static function set(string $platform, string $cookie): void
    {
        self::$runtimeCookies[$platform] = trim($cookie);
    }

    /**
     * 清除运行期 Cookie 覆盖
     *
     * @param string $platform 平台名称
     */
    public static function clear(string $platform): void
    {
        unset(self::$runtimeCookies[$platform]);
    }

    /**
     * 获取指定平台的 Cookie
     *
     * @param string $platform 平台名称
     * @return string Cookie 字符串，不存在时返回空字符串
     */
    public static function get(string $platform): string
    {
        if (isset(self::$runtimeCookies[$platform])) {
            return self::$runtimeCookies[$platform];
        }

        if (self::$cookies === null) {
            self::$cookies = Config::load('cookies');
        }

        if (!isset(self::$cookies[$platform]) || !is_array(self::$cookies[$platform])) {
            return '';
        }

        return self::$cookies[$platform]['cookie'] ?? '';
    }

    /**
     * 检查平台 Cookie 是否存在
     *
     * @param string $platform 平台名称
     * @return bool 是否存在
     */
    public static function has(string $platform): bool
    {
        return !empty(self::get($platform));
    }
}

