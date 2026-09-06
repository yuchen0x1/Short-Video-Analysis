<?php

declare(strict_types=1);

namespace App\Parsers\Live;

use App\Parsers\BaseParser;
use App\Utils\UserAgent;

/**
 * B站直播解析器：Room/get_info + xlive getRoomPlayInfo，返回 hls/flv 拉流地址
 */
class BilibiliLiveParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://live.bilibili.com/',
        ];
    }

    public static function parse(string $url): array
    {
        $roomId = self::extractRoomId($url);
        if ($roomId === null) {
            throw new \InvalidArgumentException('无法识别 B 站直播间链接');
        }

        $info = self::fetchJson('https://api.live.bilibili.com/room/v1/Room/get_info?room_id=' . $roomId);
        if ($info === null || (int) ($info['code'] ?? -1) !== 0) {
            throw new \RuntimeException('获取 B 站直播间信息失败，直播间可能不存在');
        }

        $data = is_array($info['data'] ?? null) ? $info['data'] : [];
        $realRoomId = (string) ($data['room_id'] ?? $roomId);
        $isLive = (int) ($data['live_status'] ?? 0) === 1;

        $uname = '';
        $face = '';
        if (!empty($data['uid'])) {
            $master = self::fetchJson('https://api.live.bilibili.com/live_user/v1/Master/info?uid=' . $data['uid']);
            $anchor = is_array($master['data']['info'] ?? null) ? $master['data']['info'] : [];
            $uname = (string) ($anchor['uname'] ?? '');
            $face = (string) ($anchor['face'] ?? '');
        }

        $live = [
            'room_id' => $realRoomId,
            'title' => (string) ($data['title'] ?? ''),
            'anchor' => $uname,
            'avatar' => self::fixUrl($face),
            'cover' => self::fixUrl((string) ($data['keyframe'] ?? $data['user_cover'] ?? $data['cover'] ?? '')),
            'viewers' => isset($data['online']) ? (int) $data['online'] : null,
            'status' => $isLive ? 'live' : 'offline',
        ];

        return [
            'platform' => 'bilibili',
            'live' => $live,
            'streams' => $isLive ? self::pickStreams($realRoomId) : [],
        ];
    }

    public static function extractRoomId(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/(\d+)(?:[/?#]|$)~i', $path, $match)) {
            return $match[1];
        }

        return null;
    }

    private static function fetchJson(string $url): ?array
    {
        $result = self::fetch($url);
        return $result ? self::parseJson($result['data']) : null;
    }

    /**
     * 优先使用 xlive getRoomPlayInfo（hls + flv 多画质）；失败时回退旧版 playUrl（flv）。
     * v2 接口的完整拉流地址为 url_info[0].host + base_url + url_info[0].extra；
     * url_info 是 CDN 候选数组，每个编码取第一条即可。
     */
    private static function pickStreams(string $roomId): array
    {
        $streams = [];
        $payload = self::fetchJson(
            'https://api.live.bilibili.com/xlive/web-room/v2/index/getRoomPlayInfo?room_id=' . $roomId
            . '&protocol=0,1&format=0,1,2&codec=0,1&qn=10000&platform=web&ptype=8'
        );
        $playurl = $payload['data']['playurl_info']['playurl'] ?? null;

        if (is_array($playurl)) {
            self::collectStreams($playurl, $streams, false);
        }

        // 部分响应只回 http_stream；补一次 h5 平台请求拉取 hls
        if (!in_array('hls', array_column($streams, 'format'), true)) {
            $hlsPayload = self::fetchJson(
                'https://api.live.bilibili.com/xlive/web-room/v2/index/getRoomPlayInfo?room_id=' . $roomId
                . '&protocol=1&format=0,2&codec=0&qn=10000&platform=h5&ptype=8'
            );
            $hlsPlayurl = $hlsPayload['data']['playurl_info']['playurl'] ?? null;
            if (is_array($hlsPlayurl)) {
                self::collectStreams($hlsPlayurl, $streams, true);
            }
        }

        if ($streams === []) {
            $legacy = self::fetchJson(
                'https://api.live.bilibili.com/room/v1/Room/playUrl?cid=' . $roomId . '&qn=10000&platform=web'
            );
            foreach (is_array($legacy['data']['durl'] ?? null) ? $legacy['data']['durl'] : [] as $item) {
                $url = (string) ($item['url'] ?? '');
                if ($url !== '') {
                    $streams[$url] = ['format' => 'flv', 'quality' => '原画', 'url' => $url];
                }
            }
        }

        return array_values($streams);
    }

    /**
     * 展平 playurl.stream[].format[].codec[] 为拉流地址列表；画质取 codec.current_qn 对照 quality_description。
     */
    private static function collectStreams(array $playurl, array &$streams, bool $hlsOnly): void
    {
        $qualityDesc = [];
        // v2 接口的画质表在 quality_description 或 g_qn_desc，两处都取
        foreach ([is_array($playurl['quality_description'] ?? null) ? $playurl['quality_description'] : [], is_array($playurl['g_qn_desc'] ?? null) ? $playurl['g_qn_desc'] : []] as $descList) {
            foreach ($descList as $desc) {
                if (is_array($desc)) {
                    $key = (string) ($desc['qn'] ?? '');
                    if ($key !== '' && (!isset($qualityDesc[$key]) || $qualityDesc[$key] === '')) {
                        $qualityDesc[$key] = (string) ($desc['desc'] ?? '');
                    }
                }
            }
        }

        foreach (is_array($playurl['stream'] ?? null) ? $playurl['stream'] : [] as $stream) {
            if (!is_array($stream)) {
                continue;
            }
            if ($hlsOnly && ($stream['protocol_name'] ?? '') !== 'http_hls') {
                continue;
            }
            $format = ($stream['protocol_name'] ?? '') === 'http_hls' ? 'hls' : 'flv';
            foreach (is_array($stream['format'] ?? null) ? $stream['format'] : [] as $fmt) {
                foreach (is_array($fmt['codec'] ?? null) ? $fmt['codec'] : [] as $codec) {
                    $base = (string) ($codec['base_url'] ?? '');
                    if ($base === '') {
                        continue;
                    }
                    $qn = (string) ($codec['current_qn'] ?? '');
                    $quality = $qualityDesc[$qn] ?? ($qn !== '' ? $qn : '原画');
                    foreach (is_array($codec['url_info'] ?? null) ? $codec['url_info'] : [] as $info) {
                        $host = (string) ($info['host'] ?? '');
                        $extra = (string) ($info['extra'] ?? '');
                        if ($host === '') {
                            continue;
                        }
                        $url = $host . $base . $extra;
                        if (!isset($streams[$url])) {
                            $streams[$url] = ['format' => $format, 'quality' => $quality, 'url' => $url];
                        }
                        break; // 每个 codec 只取第一条 CDN
                    }
                }
            }
        }
    }
}
