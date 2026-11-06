<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\Logger;
use App\Utils\UserAgent;

/**
 * TikTok 国际版解析器
 *
 * 三级回退：embed 页 → 视频页水合数据 → 移动端 feed 接口，均不依赖签名算法。
 * 数据形状两类：web itemStruct（camelCase）与 app aweme（snake_case）。
 */
class TiktokParser extends BaseParser
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    public static function getHeaders(): array
    {
        return [
            'User-Agent' => self::DESKTOP_UA,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    public static function parse(string $url): array
    {
        $videoId = self::extractVideoId($url);
        if ($videoId === null) {
            throw new \InvalidArgumentException('无法识别 TikTok 视频链接');
        }

        // 路径 1：embed 页（体积小、风控弱）
        $item = self::fetchWebItem('https://www.tiktok.com/embed/v2/' . $videoId, $videoId);

        // 路径 2：完整视频页水合数据
        if ($item === null) {
            $item = self::fetchWebItem('https://www.tiktok.com/@tiktok/video/' . $videoId, $videoId);
        }

        // 路径 3：移动端 feed 接口（历史上无需签名）
        if ($item === null) {
            $item = self::fetchFeedItem($videoId);
        }

        if ($item === null) {
            throw new \RuntimeException('解析 TikTok 视频信息失败，视频可能已删除或接口暂时受限');
        }

        return self::isAweme($item) ? self::awemeToResult($item) : self::webItemToResult($item);
    }

    public static function isShortLink(string $url): bool
    {
        return preg_match('#https?://v[mt]\.tiktok\.com/#i', $url) === 1;
    }

    public static function extractVideoId(string $url): ?string
    {
        // 仅识别 TikTok 域名，其他平台的 /video/<数字> 不归本解析器
        if (!self::isTiktokUrl($url)) {
            return null;
        }

        if (preg_match('#/(?:video|photo|v)/(\d{10,25})#i', $url, $match)) {
            return $match[1];
        }

        if (self::isShortLink($url)) {
            $location = HttpClient::getLocation($url);
            if ($location && preg_match('#/(?:video|photo|v)/(\d{10,25})#i', $location, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    public static function isTiktokUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        return $host === 'tiktok.com' || str_ends_with($host, '.tiktok.com');
    }

    private static function fetchWebItem(string $pageUrl, string $videoId): ?array
    {
        // 每条路径只请求一次且限制总超时：三条路径叠加必须低于 PHP 默认执行上限，不可达网络时快速失败
        $rawHeaders = '';
        $result = HttpClient::request($pageUrl, null, self::getHeaders(), 0, $rawHeaders, 8);
        if (!$result['success']) {
            return null;
        }

        $data = self::extractUniversalData((string) $result['data']);
        if ($data === null) {
            return null;
        }

        $item = self::findItemStruct($data, $videoId);
        return is_array($item) ? $item : null;
    }

    /**
     * 提取 TikTok 水合数据：__UNIVERSAL_DATA_FOR_REHYDRATION__ 或 window.__DEFAULT_SCOPE__
     */
    private static function extractUniversalData(string $html): ?array
    {
        if (preg_match('/<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>([\s\S]*?)<\/script>/i', $html, $match)) {
            $data = self::parseJson(trim($match[1]));
            if (is_array($data)) {
                return $data;
            }
        }

        if (preg_match('/window\.__DEFAULT_SCOPE__\s*=\s*(\{[\s\S]*?\});?\s*<\/script>/i', $html, $match)) {
            $data = self::parseJson(trim($match[1]));
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * 递归查找 id 匹配且带 video/imagePost 的作品对象（web itemStruct），限深防卡。
     */
    private static function findItemStruct($node, string $videoId, int $depth = 0)
    {
        if (!is_array($node) || $depth > 12) {
            return null;
        }

        if ((string) ($node['id'] ?? '') === $videoId && (isset($node['video']) || isset($node['imagePost']))) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = self::findItemStruct($value, $videoId, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private static function isAweme(array $item): bool
    {
        return isset($item['aweme_id']) || isset($item['play_addr']) || isset($item['image_post_info'])
            || isset($item['statistics']);
    }

    private static function fetchFeedItem(string $videoId): ?array
    {
        $query = http_build_query([
            'aweme_id' => $videoId,
            'version_code' => '262.1.0',
            'app_name' => 'musical_ly',
            'channel' => 'App',
            'device_id' => 'null',
            'os_version' => '14.4.2',
            'device_platform' => 'iphone',
            'device_type' => 'iPhone9,3',
        ]);
        $rawHeaders = '';
        $result = HttpClient::request(
            'https://api16-normal-c-useast1a.tiktokv.com/aweme/v1/feed/?' . $query,
            null,
            ['User-Agent' => UserAgent::mobile()],
            0,
            $rawHeaders,
            8
        );
        if (!$result['success']) {
            Logger::error('TikTok feed 接口请求失败', ['video_id' => $videoId, 'http_code' => $result['http_code'] ?? 0]);
            return null;
        }

        $json = self::parseJson($result['data']);
        $item = $json['aweme_list'][0] ?? null;
        return is_array($item) ? $item : null;
    }

    // ---- web itemStruct（camelCase）----

    private static function firstOf($urlList): string
    {
        if (!is_array($urlList)) {
            return '';
        }
        foreach ($urlList as $value) {
            if (is_string($value) && $value !== '') {
                return self::fixUrl($value);
            }
        }

        return '';
    }

    private static function pickWebVideoUrl(array $item): string
    {
        $bitRates = is_array($item['video']['bitrateInfo'] ?? null) ? $item['video']['bitrateInfo'] : [];
        usort($bitRates, static fn (array $a, array $b): int => (int) ($b['Bitrate'] ?? 0) <=> (int) ($a['Bitrate'] ?? 0));
        foreach ($bitRates as $entry) {
            $url = self::firstOf($entry['PlayAddr']['UrlList'] ?? null);
            if ($url !== '') {
                return $url;
            }
        }

        return self::firstOf([$item['video']['playAddr'] ?? null]);
    }

    private static function webItemToResult(array $item): array
    {
        $images = [];
        foreach (is_array($item['imagePost']['images'] ?? null) ? $item['imagePost']['images'] : [] as $image) {
            $url = self::firstOf($image['imageURL']['urlList'] ?? null);
            if ($url !== '' && !in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
        $isImage = $images !== [];
        $mediaUrl = $isImage ? $images[0] : self::pickWebVideoUrl($item);
        if ($mediaUrl === '') {
            throw new \RuntimeException('未找到 TikTok 媒体地址');
        }

        $cover = $isImage
            ? $images[0]
            : (self::firstOf([$item['video']['cover'] ?? null])
                ?: self::firstOf([$item['video']['originCover'] ?? null])
                ?: self::firstOf([$item['video']['dynamicCover'] ?? null]));

        return [
            'author' => (string) ($item['author']['nickname'] ?? ''),
            'uid' => (string) ($item['author']['uniqueId'] ?? ''),
            'avatar' => self::firstOf([$item['author']['avatarLarger'] ?? null])
                ?: self::firstOf([$item['author']['avatarMedium'] ?? null])
                ?: self::firstOf([$item['author']['avatarThumb'] ?? null]),
            'like' => (int) ($item['statsV2']['diggCount'] ?? $item['stats']['diggCount'] ?? 0),
            'time' => (int) ($item['createTime'] ?? 0),
            'title' => (string) ($item['desc'] ?? ''),
            'cover' => $cover,
            'url' => $mediaUrl,
            'media_type' => $isImage ? 'image' : 'video',
            'images' => $images,
            'music' => [
                'author' => (string) ($item['music']['authorName'] ?? ''),
                'title' => (string) ($item['music']['title'] ?? ''),
                'avatar' => self::firstOf([$item['music']['coverLarger'] ?? null])
                    ?: self::firstOf([$item['music']['coverThumb'] ?? null]),
                'url' => self::firstOf([$item['music']['playUrl'] ?? null]),
            ],
        ];
    }

    // ---- app aweme（snake_case，与抖音同构）----

    private static function awemeToResult(array $item): array
    {
        $images = [];
        foreach (is_array($item['image_post_info']['images'] ?? null) ? $item['image_post_info']['images'] : [] as $image) {
            $url = self::firstOf($image['display_image']['url_list'] ?? null)
                ?: self::firstOf($image['url_list'] ?? null);
            if ($url !== '' && !in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
        $isImage = $images !== [];

        $mediaUrl = '';
        if (!$isImage) {
            $mediaUrl = self::firstOf($item['video']['play_addr']['url_list'] ?? null);
            if ($mediaUrl === '') {
                $bitRates = is_array($item['video']['bit_rate'] ?? null) ? $item['video']['bit_rate'] : [];
                usort($bitRates, static fn (array $a, array $b): int => (int) ($b['bit_rate'] ?? 0) <=> (int) ($a['bit_rate'] ?? 0));
                foreach ($bitRates as $entry) {
                    $mediaUrl = self::firstOf($entry['play_addr']['url_list'] ?? null);
                    if ($mediaUrl !== '') {
                        break;
                    }
                }
            }
        }
        if ($mediaUrl === '') {
            throw new \RuntimeException('未找到 TikTok 媒体地址');
        }

        $cover = $isImage
            ? $images[0]
            : (self::firstOf($item['video']['cover']['url_list'] ?? null)
                ?: self::firstOf($item['video']['origin_cover']['url_list'] ?? null));

        return [
            'author' => (string) ($item['author']['nickname'] ?? ''),
            'uid' => (string) ($item['author']['uid'] ?? $item['author']['unique_id'] ?? ''),
            'avatar' => self::firstOf($item['author']['avatar_medium']['url_list'] ?? null)
                ?: self::firstOf($item['author']['avatar_thumb']['url_list'] ?? null),
            'like' => (int) ($item['statistics']['digg_count'] ?? 0),
            'time' => (int) ($item['create_time'] ?? 0),
            'title' => (string) ($item['desc'] ?? ''),
            'cover' => $cover,
            'url' => $mediaUrl,
            'media_type' => $isImage ? 'image' : 'video',
            'images' => $images,
            'music' => [
                'author' => (string) ($item['music']['author'] ?? ''),
                'title' => (string) ($item['music']['title'] ?? ''),
                'avatar' => self::firstOf($item['music']['cover_large']['url_list'] ?? null)
                    ?: self::firstOf($item['music']['cover_thumb']['url_list'] ?? null),
                'url' => self::firstOf($item['music']['play_url']['url_list'] ?? null)
                    ?: self::firstOf($item['music']['download_url']['url_list'] ?? null),
            ],
        ];
    }
}
