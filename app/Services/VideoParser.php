<?php

namespace App\Services;

/**
 * 视频解析服务
 */
class VideoParser
{
    private const PLATFORMS = [
        "douyin" => [
            "class" => \App\Parsers\DouyinParser::class,
            "domains" => ["douyin.com"],
        ],
        "kuaishou" => [
            "class" => \App\Parsers\KuaishouParser::class,
            "domains" => ["kuaishou.com", "kuaishou.cn"],
        ],
        "bilibili" => [
            "class" => \App\Parsers\BilibiliParser::class,
            "domains" => ["bilibili.com", "b23.tv"],
        ],
        "pipixia" => [
            "class" => \App\Parsers\PipixiaParser::class,
            "domains" => ["pipix.com"],
        ],
        "weibo" => [
            "class" => \App\Parsers\WeiboParser::class,
            "domains" => ["weibo.com"],
        ],
        "ixigua" => [
            "class" => \App\Parsers\XiguaParser::class,
            "domains" => ["ixigua.com"],
        ],
        "shipinhao" => [
            "class" => \App\Parsers\ShipinhaoParser::class,
            "domains" => ["channels.weixin.qq.com"],
        ],
        "izuiyou" => [
            "class" => \App\Parsers\IzuiyouParser::class,
            "domains" => ["izuiyou.com"],
        ],
        "pipigx" => [
            "class" => \App\Parsers\PipigxParser::class,
            "domains" => ["ippzone.com", "pipigx.com"],
        ],
        "weishi" => [
            "class" => \App\Parsers\WeishiParser::class,
            "domains" => ["weishi.qq.com"],
        ],
        "xiaohongshu" => [
            "class" => \App\Parsers\XiaohongshuParser::class,
            "domains" => ["xiaohongshu.com", "xhslink.com"],
        ],
        "tiktok" => [
            "class" => \App\Parsers\TiktokParser::class,
            "domains" => ["tiktok.com"],
        ],
    ];

    /**
     * 解析视频 URL（带缓存）
     */
    public function parse(string $url): array
    {
        // 直播间链接走直播解析入口
        if ((new LiveParser())->isLiveUrl($url)) {
            throw new \InvalidArgumentException('直播间链接请使用直播解析入口');
        }

        // Check cache first
        $cacheKey = ParseCache::makeKey($url);
        $cached = ParseCache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $platform = $this->getPlatform($url);
        if (!$platform) {
            throw new \InvalidArgumentException("不支持的视频平台");
        }

        $result = self::PLATFORMS[$platform]["class"]::parse($url);

        // Cache the result
        ParseCache::set($cacheKey, $result);

        return $result;
    }

    /**
     * 解析公开个人主页中的作品列表。
     * 目前仅抖音支持主页分页，其他平台仍使用单条解析流程。
     */
    public function parseValidatedProfile(string $url, int $cursor = 0): array
    {
        if (!$this->isExplicitDouyinProfileUrl($url)) {
            throw new \InvalidArgumentException('目前仅支持明确的抖音个人主页链接');
        }
        return $this->parseProfile($url, $cursor);
    }

    public function parseProfile(string $url, int $cursor = 0): array
    {
        $platform = $this->getPlatform($url);
        if ($platform !== 'douyin' || !$this->isExplicitDouyinProfileUrl($url)) {
            throw new \InvalidArgumentException('目前仅支持明确的抖音个人主页链接');
        }

        $validatedUrl = $url;
        return self::PLATFORMS['douyin']['class']::parseProfile($validatedUrl, $cursor);
    }

    /**
     * 解析抖音合集（collection）中的作品列表。
     */
    public function parseValidatedCollection(string $url, int $cursor = 0): array
    {
        if (!$this->isExplicitDouyinCollectionUrl($url)) {
            throw new \InvalidArgumentException('目前仅支持明确的抖音合集链接');
        }
        return $this->parseCollection($url, $cursor);
    }

    public function parseCollection(string $url, int $cursor = 0): array
    {
        $platform = $this->getPlatform($url);
        if ($platform !== 'douyin' || !$this->isExplicitDouyinCollectionUrl($url)) {
            throw new \InvalidArgumentException('目前仅支持明确的抖音合集链接');
        }

        return self::PLATFORMS['douyin']['class']::parseCollection($url, $cursor);
    }

    private function isExplicitDouyinProfileUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        return ($host === 'douyin.com' || str_ends_with($host, '.douyin.com'))
            && (preg_match('~^/user/[^/?#]+~i', $path) === 1
                || preg_match('/(?:^|&)(?:sec_user_id|sec_uid)=[^&]+/i', $query) === 1);
    }

    private function isExplicitDouyinCollectionUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        return ($host === 'douyin.com' || str_ends_with($host, '.douyin.com'))
            && (preg_match('#^/collection/\d+#i', $path) === 1
                || preg_match('/(?:^|&)mix_id=\d+/i', $query) === 1);
    }

    private function getPlatform(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        $host = strtolower(preg_replace("/^www\./", "", $host));

        foreach (self::PLATFORMS as $platform => $config) {
            foreach ($config["domains"] as $domain) {
                if ($host === $domain || substr($host, -strlen("." . $domain)) === "." . $domain) {
                    return $platform;
                }
            }
        }

        return null;
    }

    public function getSupportedPlatforms(): array
    {
        return array_keys(self::PLATFORMS);
    }
}
