<?php

declare(strict_types=1);

namespace App\Parsers\Live;

use App\Parsers\BaseParser;
use App\Utils\Config;
use App\Utils\HttpClient;
use App\Utils\Logger;

/**
 * 斗鱼直播解析器
 *
 * 房间信息走公开 API；拉流地址需 ub98484234 签名——签名函数文本来自
 * swf_api 接口，eval 执行经本地 Node 签名边车（scripts/a_bogus_server.js 的
 * /douyu 端点，内置 CryptoJS.MD5 等价实现）完成。
 */
class DouyuLiveParser extends BaseParser
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    private const DEFAULT_DID = '10000000000000000000000000001501';

    protected static function getHeaders(): array
    {
        return ['User-Agent' => self::DESKTOP_UA];
    }

    public static function parse(string $url): array
    {
        $roomId = self::extractRoomId($url);
        if ($roomId === null) {
            throw new \InvalidArgumentException('无法识别斗鱼直播间链接');
        }

        $info = self::fetchRoomInfo($roomId);
        if ($info === null) {
            throw new \RuntimeException('获取斗鱼直播间信息失败，直播间可能不存在');
        }

        $isLive = (string) ($info['room_status'] ?? '') === '1';

        $live = [
            'room_id' => (string) ($info['room_id'] ?? $roomId),
            'title' => (string) ($info['room_name'] ?? ''),
            'anchor' => (string) ($info['owner_name'] ?? ''),
            'avatar' => self::fixUrl((string) ($info['avatar'] ?? '')),
            'cover' => self::fixUrl((string) ($info['room_thumb'] ?? '')),
            'viewers' => isset($info['online']) ? (int) $info['online'] : null,
            'status' => $isLive ? 'live' : 'offline',
        ];

        return [
            'platform' => 'douyu',
            'live' => $live,
            'streams' => $isLive ? self::pickStreams($roomId) : [],
        ];
    }

    public static function isDouyuLiveUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';
        return $host === 'douyu.com' || str_ends_with($host, '.douyu.com');
    }

    public static function extractRoomId(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/(\d+)(?:[/?#]|$)~i', $path, $match)) {
            return $match[1];
        }

        $query = (string) parse_url($url, PHP_URL_QUERY);
        if (preg_match('/(?:^|&)rid=(\d+)/i', $query, $match)) {
            return $match[1];
        }

        return null;
    }

    private static function fetchRoomInfo(string $roomId): ?array
    {
        $result = self::fetch('https://open.douyucdn.cn/api/RoomApi/room/' . $roomId);
        if (!$result) {
            Logger::error('斗鱼房间信息接口请求失败', ['room_id' => $roomId]);
            return null;
        }
        $json = self::parseJson($result['data']);
        if (!is_array($json) || (int) ($json['error'] ?? -1) !== 0 || !is_array($json['data'] ?? null)) {
            return null;
        }

        return $json['data'];
    }

    /**
     * 拉取签名函数文本并经本地签名边车求值，再按画质档位拉取 FLV 地址。
     */
    private static function pickStreams(string $roomId): array
    {
        $swf = self::fetch('https://www.douyu.com/swf_api/homeH5Enc?rids=' . $roomId);
        if (!$swf) {
            throw new \RuntimeException('获取斗鱼签名参数失败，请稍后再试');
        }
        $swfJson = self::parseJson($swf['data']);
        $fnText = $swfJson['data']['room' . $roomId] ?? null;
        if (!is_string($fnText) || $fnText === '') {
            throw new \RuntimeException('获取斗鱼签名参数失败，请稍后再试');
        }

        $did = self::DEFAULT_DID;
        $tt = (string) time();
        $sign = self::evalSign($fnText, $roomId, $did, $tt);

        $first = self::fetchPlayInfo($roomId, $sign, 'rate=0');
        if ($first === null) {
            throw new \RuntimeException('获取斗鱼拉流地址失败，直播间可能未开播');
        }

        // 原画 + 按画质表逐档拉取（每档一次签名复用的快速请求）
        $streams = [];
        $seenRates = [];
        $rates = is_array($first['multirates'] ?? null) ? $first['multirates'] : [];
        foreach ($rates as $rate) {
            if (!is_array($rate) || isset($seenRates[$rate['rate']]) || count($streams) >= 5) {
                continue;
            }
            $seenRates[$rate['rate']] = true;
            $variant = self::fetchPlayInfo($roomId, $sign, 'rate=' . (string) $rate['rate']);
            $flv = $variant !== null ? self::buildFlvUrl($variant) : '';
            if ($flv !== '') {
                $streams[] = [
                    'format' => 'flv',
                    'quality' => (string) ($rate['name'] ?? ('rate' . $rate['rate'])),
                    'url' => $flv,
                ];
            }
        }

        if ($streams === []) {
            $flv = self::buildFlvUrl($first);
            if ($flv !== '') {
                $streams[] = ['format' => 'flv', 'quality' => '原画', 'url' => $flv];
            }
        }

        return $streams;
    }

    private static function buildFlvUrl(array $playInfo): string
    {
        $base = (string) ($playInfo['rtmp_url'] ?? '');
        $live = (string) ($playInfo['rtmp_live'] ?? '');
        if ($base === '' || $live === '') {
            return '';
        }

        return rtrim($base, '/') . '/' . $live;
    }

    private static function fetchPlayInfo(string $roomId, string $sign, string $extraQuery): ?array
    {
        $url = 'https://www.douyu.com/lapi/live/getH5Play/' . $roomId . '?' . $sign
            . ($extraQuery !== '' ? '&' . $extraQuery : '');
        $result = HttpClient::request($url, '', [
            'User-Agent' => self::DESKTOP_UA,
            'Referer' => 'https://www.douyu.com/' . $roomId,
        ], 0);
        if (!$result['success']) {
            return null;
        }
        $json = self::parseJson($result['data']);
        if (!is_array($json) || (int) ($json['error'] ?? -1) !== 0 || !is_array($json['data'] ?? null)) {
            Logger::error('斗鱼拉流接口返回异常', [
                'room_id' => $roomId,
                'msg' => is_array($json) ? (string) ($json['msg'] ?? '') : 'non-json',
            ]);
            return null;
        }

        return $json['data'];
    }

    /**
     * 经本地 Node 签名边车（/douyu）求值签名函数。
     */
    private static function evalSign(string $fnText, string $roomId, string $did, string $tt): string
    {
        $port = Config::env('A_BOGUS_PORT', '9876');
        $ch = curl_init('http://127.0.0.1:' . $port . '/douyu');
        if ($ch === false) {
            throw new \RuntimeException('斗鱼签名服务不可用，请启动本地签名服务（node scripts/a_bogus_server.js）');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['fn' => $fnText, 'rid' => $roomId, 'did' => $did, 'tt' => $tt], JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $output = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($output) || $httpCode !== 200 || trim($output) === '') {
            Logger::error('斗鱼签名服务请求失败', ['http_code' => $httpCode]);
            throw new \RuntimeException('斗鱼签名服务不可用，请启动本地签名服务（node scripts/a_bogus_server.js）');
        }

        return trim($output);
    }
}
