<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 直播解析服务：按域名路由到对应平台的直播解析器。
 * 与 VideoParser 的路由方式保持一致；解析结果返回直播间信息与 flv/hls 拉流地址列表。
 */
class LiveParser
{
    private const PLATFORMS = [
        'douyin' => [
            'class' => \App\Parsers\Live\DouyinLiveParser::class,
            'domains' => ['live.douyin.com'],
        ],
        'bilibili' => [
            'class' => \App\Parsers\Live\BilibiliLiveParser::class,
            'domains' => ['live.bilibili.com'],
        ],
        'huya' => [
            'class' => \App\Parsers\Live\HuyaLiveParser::class,
            'domains' => ['huya.com'],
        ],
        'douyu' => [
            'class' => \App\Parsers\Live\DouyuLiveParser::class,
            'domains' => ['douyu.com'],
        ],
    ];

    /**
     * 解析直播间 URL（不带缓存：直播流地址时效短，缓存意义有限）
     */
    public function parse(string $url): array
    {
        $platform = $this->getPlatform($url);
        if ($platform === null) {
            throw new \InvalidArgumentException('不支持的直播平台');
        }

        return self::PLATFORMS[$platform]['class']::parse($url);
    }

    public function isLiveUrl(string $url): bool
    {
        return $this->getPlatform($url) !== null;
    }

    public function getSupportedPlatforms(): array
    {
        return array_keys(self::PLATFORMS);
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
}
