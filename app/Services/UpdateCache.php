<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\Logger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * 在线更新检查结果缓存
 *
 * /api/v1/update 每次访问都会代理 GitHub API；GitHub 匿名配额按出口 IP 计算，
 * 公共实例很容易被反复刷新打满。这里用独立的文件缓存做短 TTL 合并，
 * 与解析缓存（ParseCache）分开，避免清理解析缓存时连带清掉版本信息。
 */
final class UpdateCache
{
    private const PREFIX = 'video_spider_update_';

    private static ?\Symfony\Contracts\Cache\CacheInterface $cache = null;

    private static function getCache(): \Symfony\Contracts\Cache\CacheInterface
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // 解析缓存整体关闭时仍保留更新缓存，但用内存缓存避免写盘
        if (Config::get('cache.parse.enabled', true) === false) {
            self::$cache = new ArrayAdapter();
            return self::$cache;
        }

        $directory = (string) Config::get(
            'cache.parse.file.directory',
            __DIR__ . '/../../storage/cache/parse/'
        );

        try {
            self::$cache = new FilesystemAdapter('update', 900, $directory);
        } catch (\Throwable $e) {
            Logger::warning('更新缓存初始化失败，降级为内存缓存', ['error' => $e->getMessage()]);
            self::$cache = new ArrayAdapter();
        }

        return self::$cache;
    }

    /**
     * 读取缓存的更新检查结果。
     *
     * @param string $key 缓存键（通常是 current_version，保证版本升级后立即失效）
     * @return array|null
     */
    public static function get(string $key): ?array
    {
        try {
            $item = self::getCache()->getItem(self::PREFIX . md5($key));
            if (!$item->isHit()) {
                return null;
            }
            $value = $item->get();
            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            Logger::warning('读取更新缓存失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 写入更新检查结果。
     */
    public static function set(string $key, array $value): void
    {
        $ttl = max(60, (int) Config::get('updates.cache_ttl', 900));
        try {
            self::getCache()->save(
                self::getCache()->getItem(self::PREFIX . md5($key))
                    ->set($value)
                    ->expiresAfter($ttl)
            );
        } catch (\Throwable $e) {
            Logger::warning('写入更新缓存失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 清除全部更新缓存（测试与手动排障用）。
     */
    public static function clear(): bool
    {
        try {
            return self::getCache()->clear();
        } catch (\Throwable $e) {
            Logger::warning('清除更新缓存失败', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
