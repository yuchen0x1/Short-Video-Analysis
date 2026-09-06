<?php

declare(strict_types=1);

namespace App\Parsers\Live;

use App\Parsers\BaseParser;
use App\Utils\HttpClient;
use App\Utils\Logger;

/**
 * 虎牙直播解析器：直播间页面 hyPlayerConfig.stream 内嵌 JSON 直取拉流地址，无签名。
 * 实测虎牙 FLV 可直接播放（302 跳边缘节点）；HLS 接口对非页面请求受限，只下发 FLV。
 */
class HuyaLiveParser extends BaseParser
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    protected static function getHeaders(): array
    {
        return ['User-Agent' => self::DESKTOP_UA];
    }

    public static function parse(string $url): array
    {
        $roomId = self::extractRoomId($url);
        if ($roomId === null) {
            throw new \InvalidArgumentException('无法识别虎牙直播间链接');
        }

        $result = HttpClient::request('https://www.huya.com/' . $roomId, null, [
            'User-Agent' => self::DESKTOP_UA,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ], 0);

        if (!$result['success']) {
            Logger::error('虎牙直播间页面请求失败', ['room_id' => $roomId, 'http_code' => $result['http_code'] ?? 0]);
            throw new \RuntimeException('获取虎牙直播间信息失败，请稍后再试');
        }

        $stream = self::extractStreamPayload((string) $result['data']);
        if ($stream === null) {
            throw new \RuntimeException('获取虎牙直播间信息失败，直播间可能不存在或未开播');
        }

        $records = is_array($stream['data'] ?? null) ? $stream['data'] : [];
        $info = is_array($records[0]['gameLiveInfo'] ?? null) ? $records[0]['gameLiveInfo'] : [];
        $list = is_array($records[0]['gameStreamInfoList'] ?? null) ? $records[0]['gameStreamInfoList'] : [];

        if (trim((string) ($info['nick'] ?? '')) === '' && trim((string) ($info['roomName'] ?? '')) === '' && $list === []) {
            // 不存在的房间页面会返回全零值的 gameLiveInfo
            throw new \RuntimeException('虎牙直播间不存在或已关闭');
        }

        if ($list === []) {
            // 有房间信息但无拉流数据：未开播
            return [
                'platform' => 'huya',
                'live' => [
                    'room_id' => $roomId,
                    'title' => (string) ($info['roomName'] ?? ''),
                    'anchor' => (string) ($info['nick'] ?? ''),
                    'avatar' => self::fixUrl((string) ($info['avatar180'] ?? '')),
                    'cover' => self::fixUrl((string) ($info['screenshot'] ?? '')),
                    'viewers' => isset($info['totalCount']) ? (int) $info['totalCount'] : null,
                    'status' => 'offline',
                ],
                'streams' => [],
            ];
        }

        return [
            'platform' => 'huya',
            'live' => [
                'room_id' => (string) ($info['profileRoom'] ?? $roomId),
                'title' => (string) ($info['roomName'] ?? ''),
                'anchor' => (string) ($info['nick'] ?? ''),
                'avatar' => self::fixUrl((string) ($info['avatar180'] ?? '')),
                'cover' => self::fixUrl((string) ($info['screenshot'] ?? '')),
                'viewers' => isset($info['totalCount']) ? (int) $info['totalCount'] : null,
                'status' => 'live',
            ],
            // 每 CDN 线一条 FLV 原画；虎牙 HLS 对非页面请求受限，不下发
            'streams' => self::pickStreams($list),
        ];
    }

    public static function isHuyaLiveUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';
        return $host === 'huya.com' || str_ends_with($host, '.huya.com');
    }

    public static function extractRoomId(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/(\d+)(?:[/?#]|$)~i', $path, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * 从页面脚本中提取 hyPlayerConfig 的 stream 值（内嵌 JSON 对象，需括号配平扫描）。
     */
    private static function extractStreamPayload(string $html): ?array
    {
        $marker = strpos($html, 'stream:');
        if ($marker === false) {
            return null;
        }

        $start = strpos($html, '{', $marker);
        if ($start === false) {
            return null;
        }

        // 括号配平扫描（跳过字符串字面量），机器生成的 JSON 不含转义陷阱之外的结构
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);
        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($html, $start, $i - $start + 1);
                    $data = self::parseJson($json);
                    return is_array($data) ? $data : null;
                }
            }
        }

        return null;
    }

    /**
     * 按 CDN 线去重取 FLV 原画地址（AL/TX/HS 等），最多 3 条。
     */
    private static function pickStreams(array $list): array
    {
        $streams = [];
        $seen = [];
        foreach ($list as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $cdn = (string) ($entry['sCdnType'] ?? '');
            if ($cdn === '' || isset($seen[$cdn])) {
                continue;
            }
            $url = self::fixUrl(
                (string) ($entry['sFlvUrl'] ?? '') . '/' . ($entry['sStreamName'] ?? '') . '.' . ($entry['sFlvUrlSuffix'] ?? 'flv')
                . '?' . ($entry['sFlvAntiCode'] ?? '')
            );
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $seen[$cdn] = true;
            $streams[] = [
                'format' => 'flv',
                'quality' => '原画 · ' . strtoupper($cdn),
                'url' => $url,
            ];
            if (count($streams) >= 3) {
                break;
            }
        }

        return $streams;
    }
}
