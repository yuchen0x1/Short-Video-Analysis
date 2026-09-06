<?php

declare(strict_types=1);

namespace App\Parsers\Live;

use App\Parsers\BaseParser;
use App\Parsers\DouyinParser;
use App\Utils\HttpClient;
use App\Utils\Logger;
use App\Utils\UserAgent;

/**
 * 抖音直播解析器：webcast/room/web/enter 接口，返回 flv/hls 拉流地址
 */
class DouyinLiveParser extends BaseParser
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    private const QUALITY_NAMES = [
        'ORIGINAL' => '原画',
        'FULL_HD1' => '蓝光',
        'HD1' => '高清',
        'SD1' => '标清',
        'SD2' => '流畅',
    ];

    protected static function getHeaders(): array
    {
        return ['User-Agent' => UserAgent::desktop()];
    }

    public static function parse(string $url): array
    {
        $roomId = self::extractRoomId($url);
        if ($roomId === null) {
            throw new \InvalidArgumentException('无法识别抖音直播间链接');
        }

        $room = self::fetchRoom($roomId);
        if ($room === null) {
            throw new \RuntimeException('获取抖音直播间信息失败，直播间可能不存在或接口暂时受限');
        }

        $status = (int) ($room['status']['status'] ?? $room['status'] ?? 0);
        $streamUrl = is_array($room['stream_url'] ?? null) ? $room['stream_url'] : [];
        $isLive = $status === 2 && $streamUrl !== [];

        $live = [
            'room_id' => (string) ($room['id_str'] ?? $roomId),
            'title' => (string) ($room['title'] ?? ''),
            'anchor' => (string) ($room['owner']['nickname'] ?? ''),
            'avatar' => self::firstUrl($room['owner']['avatar_thumb']['url_list'] ?? null) ?? '',
            'cover' => self::firstUrl($room['cover']['url_list'] ?? null)
                ?? self::firstUrl($room['cover_medium']['url_list'] ?? null)
                ?? '',
            'viewers' => isset($room['user_count']) ? (int) $room['user_count'] : null,
            'status' => $isLive ? 'live' : 'offline',
        ];

        return [
            'platform' => 'douyin',
            'live' => $live,
            'streams' => $isLive ? self::pickStreams($streamUrl) : [],
        ];
    }

    public static function extractRoomId(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/(\d{6,20})(?:[/?#]|$)~i', $path, $match)) {
            return $match[1];
        }

        // 短链解析重定向后再取房间号
        if (preg_match('#https?://v\.douyin\.com/#i', $url)) {
            $location = HttpClient::getLocation($url);
            if ($location && $location !== $url && self::isDouyinLiveUrl($location)) {
                return self::extractRoomId($location);
            }
        }

        return null;
    }

    public static function isDouyinLiveUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        return $host === 'live.douyin.com' || str_ends_with($host, '.live.douyin.com');
    }

    private static function fetchRoom(string $roomId): ?array
    {
        $ua = self::DESKTOP_UA;
        $ttwid = DouyinParser::getTtwid($ua);
        if ($ttwid === null) {
            Logger::error('获取抖音 ttwid 失败', ['room_id' => $roomId]);
            return null;
        }

        $query = http_build_query([
            'aid' => '6383',
            'device_platform' => 'webapp',
            'browser_language' => 'zh-CN',
            'browser_platform' => 'Win32',
            'browser_name' => 'Chrome',
            'browser_version' => '123.0.0.0',
            'web_rid' => $roomId,
        ]);
        $aBogus = DouyinParser::generateABogus($query, $ua);
        $url = 'https://live.douyin.com/webcast/room/web/enter/?' . $query
            . '&a_bogus=' . rawurlencode($aBogus);
        $result = HttpClient::request($url, null, [
            'User-Agent' => $ua,
            'Accept' => 'application/json, text/plain, */*',
            'Referer' => 'https://live.douyin.com/' . $roomId,
            'Cookie' => 'ttwid=' . $ttwid,
        ]);

        if (!$result['success']) {
            Logger::error('抖音直播间接口请求失败', [
                'room_id' => $roomId,
                'http_code' => $result['http_code'] ?? 0,
                'error' => $result['error'] ?? '',
                'body' => mb_substr((string) ($result['data'] ?? ''), 0, 300),
            ]);
            return null;
        }

        $json = self::parseJson($result['data']);
        if (!is_array($json) || (int) ($json['status_code'] ?? -1) !== 0) {
            Logger::error('抖音直播间接口返回异常', [
                'room_id' => $roomId,
                'body' => mb_substr((string) ($result['data'] ?? ''), 0, 300),
            ]);
            return null;
        }

        $payload = is_array($json['data'] ?? null) ? $json['data'] : [];
        $rooms = $payload['data'] ?? null;
        if (is_array($rooms) && isset($rooms[0]) && is_array($rooms[0])) {
            return $rooms[0];
        }
        if (is_array($rooms) && isset($rooms['id_str'])) {
            return $rooms;
        }

        return null;
    }

    /**
     * 从 stream_url 提取 flv/hls 拉流地址；flv_pull_url / hls_pull_url_map 的键为画质档位。
     */
    private static function pickStreams(array $streamUrl): array
    {
        $streams = [];
        foreach (is_array($streamUrl['flv_pull_url'] ?? null) ? $streamUrl['flv_pull_url'] : [] as $key => $url) {
            if (is_string($url) && $url !== '') {
                $streams[] = [
                    'format' => 'flv',
                    'quality' => self::QUALITY_NAMES[(string) $key] ?? (string) $key,
                    'url' => self::fixUrl($url),
                ];
            }
        }
        foreach (is_array($streamUrl['hls_pull_url_map'] ?? null) ? $streamUrl['hls_pull_url_map'] : [] as $key => $url) {
            if (is_string($url) && $url !== '') {
                $streams[] = [
                    'format' => 'hls',
                    'quality' => self::QUALITY_NAMES[(string) $key] ?? (string) $key,
                    'url' => self::fixUrl($url),
                ];
            }
        }

        // 兜底：仅下发单一直链的老结构
        if ($streams === []) {
            foreach (['rtmp_pull_url' => 'flv', 'hls_pull_url' => 'hls'] as $key => $format) {
                $url = $streamUrl[$key] ?? '';
                if (is_string($url) && $url !== '') {
                    $streams[] = ['format' => $format, 'quality' => '原画', 'url' => self::fixUrl($url)];
                }
            }
        }

        return $streams;
    }

    private static function firstUrl($urlList): ?string
    {
        if (!is_array($urlList) || !isset($urlList[0]) || !is_string($urlList[0]) || $urlList[0] === '') {
            return null;
        }

        return self::fixUrl($urlList[0]);
    }
}
