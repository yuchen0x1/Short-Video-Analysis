<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Config;
use App\Utils\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * 视频解析结果缓存服务
 * 使用 Symfony Cache 组件，支持多种驱动（文件、Redis、数组）
 */
class ParseCache
{
    private static ?\Symfony\Contracts\Cache\CacheInterface $cache = null;

    private static function getCache(): \Symfony\Contracts\Cache\CacheInterface
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $config = Config::get('cache.parse', []);
        $enabled = $config['enabled'] ?? true;
        $driver = $config['driver'] ?? 'file';
        $prefix = $config['prefix'] ?? 'video_spider_parse_';
        $defaultTtl = $config['ttl'] ?? 86400;

        if (!$enabled) {
            self::$cache = new ArrayAdapter();
            return self::$cache;
        }

        switch ($driver) {
            case 'redis':
                $redisConfig = $config['redis'] ?? [];
                $host = $redisConfig['host'] ?? '127.0.0.1';
                $port = $redisConfig['port'] ?? 6379;
                $password = $redisConfig['password'] ?? '';
                $database = $redisConfig['database'] ?? 0;

                $client = \Redis::class;
                if (!extension_loaded('redis')) {
                    Logger::error('Redis 扩展未安装，回退到文件缓存');
                    $driver = 'file';
                    break;
                }

                $redis = new \Redis();
                try {
                    $redis->connect($host, $port);
                    if ($password) {
                        $redis->auth($password);
                    }
                    $redis->select($database);
                    self::$cache = new RedisAdapter($redis, '', $defaultTtl);
                } catch (\Throwable $e) {
                    Logger::error('Redis 连接失败，回退到文件缓存', ['error' => $e->getMessage()]);
                    $driver = 'file';
                }
                break;

            case 'file':
            default:
                $fileConfig = $config['file'] ?? [];
                $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/parse/';
                self::$cache = new FilesystemAdapter('', $defaultTtl, $directory);
                break;
        }

        // 如果是回退到文件缓存
        if (self::$cache === null) {
            $fileConfig = $config['file'] ?? [];
            $directory = $fileConfig['directory'] ?? __DIR__ . '/../../storage/cache/parse/';
            self::$cache = new FilesystemAdapter('', $defaultTtl, $directory);
        }

        return self::$cache;
    }

    /**
     * 获取缓存
     */
    public static function get(string $key): ?array
    {
        $cache = self::getCache();
        $prefixedKey = Config::get('cache.parse.prefix', 'video_spider_parse_') . $key;

        // 不使用 callback-get：未命中时不能写入占位条目，
        // 否则每个请求过的 URL 都会在文件缓存中留下永不过期的 null 文件。
        $item = $cache->getItem($prefixedKey);
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();
        return is_array($value) ? $value : null;
    }

    /**
     * 设置缓存
     */
    public static function set(string $key, array $result, ?int $ttl = null): void
    {
        $cache = self::getCache();
        $prefixedKey = Config::get('cache.parse.prefix', 'video_spider_parse_') . $key;
        $defaultTtl = Config::get('cache.parse.ttl', 86400);
        $ttl = $ttl ?? $defaultTtl;

        $cache->save(
            $cache->getItem($prefixedKey)
                ->set($result)
                ->expiresAfter($ttl)
        );
    }

    /**
     * 生成缓存键
     */
    public static function makeKey(string $url): string
    {
        $parts = parse_url(trim($url));
        if (is_array($parts) && isset($parts['host'])) {
            $query = [];
            parse_str((string) ($parts['query'] ?? ''), $query);
            foreach (array_keys($query) as $key) {
                if (str_starts_with(strtolower((string) $key), 'utm_')) {
                    unset($query[$key]);
                }
            }
            ksort($query);
            $normalized = strtolower((string) ($parts['scheme'] ?? 'https')) . '://'
                . strtolower((string) $parts['host'])
                . (isset($parts['port']) ? ':' . $parts['port'] : '')
                . ((string) ($parts['path'] ?? '/'))
                . ($query !== [] ? '?' . http_build_query($query) : '');
            return md5($normalized);
        }

        return md5($url);
    }

    /**
     * 清除所有缓存
     */
    public static function clear(): bool
    {
        $cache = self::getCache();
        return $cache->clear();
    }

    /**
     * 删除指定键
     */
    public static function delete(string $key): bool
    {
        $cache = self::getCache();
        $prefixedKey = Config::get('cache.parse.prefix', 'video_spider_parse_') . $key;
        return $cache->delete($prefixedKey);
    }
}
