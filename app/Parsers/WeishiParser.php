<?php

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 微视视频解析器
 */
class WeishiParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://weishi.qq.com/',
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        if (!preg_match('/(?:feed\/|feedid=|id=)([A-Za-z0-9_-]+)/', $target, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $feedId = $match[1];
        $result = self::fetch(
            'https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage?feedid=' . urlencode($feedId)
        );

        $data = $result ? self::parseJson($result['data']) : null;
        $item = is_array($data) ? ($data['data']['feeds'][0] ?? null) : null;
        $videoUrl = is_array($item) ? ($item['video_url'] ?? '') : '';

        // 微视旧 JSON 接口逐步下线，公开分享页仍保留 window.Vise.initState。
        if (!$videoUrl) {
            $page = self::fetch($target);
            $html = $page['data'] ?? '';
            if (preg_match('/window\.Vise\.initState\s*=\s*({.*?});\s*(?:}|<)/s', $html, $stateMatch)) {
                $state = self::parseJson(trim($stateMatch[1]));
                $item = is_array($state) ? ($state['feedsList'][0] ?? null) : null;
                $videoUrl = is_array($item) ? ($item['videoUrl'] ?? $item['video_url'] ?? '') : '';
            }
        }

        if (!$videoUrl || !is_array($item)) {
            throw new \RuntimeException('解析视频信息失败');
        }

        $title = $item['feed_desc_withat'] ?? '';
        if ($title === '') {
            $title = $item['shareInfo']['bodyMap']['0']['title'] ?? $item['materialDesc'] ?? '';
        }

        return [
            'author' => $item['poster']['nick'] ?? '',
            'avatar' => isset($item['poster']['avatar']) ? self::fixUrl($item['poster']['avatar']) : '',
            'time' => $item['poster']['createtime'] ?? 0,
            'title' => $title,
            'cover' => isset($item['images'][0]['url'])
                ? self::fixUrl($item['images'][0]['url'])
                : self::fixUrl($item['videoCover'] ?? ''),
            'url' => self::fixUrl($videoUrl),
        ];
    }
}
