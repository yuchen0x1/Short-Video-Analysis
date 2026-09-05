<?php

namespace App\Parsers;

use App\Utils\UserAgent;

/**
 * 最右视频解析器
 */
class IzuiyouParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::desktop(),
            'Referer' => 'https://share.izuiyou.com/'
        ];
    }

    public static function parse(string $url): array
    {
        $pid = '';
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            $pid = isset($params['pid']) && preg_match('/^\d+$/', (string) $params['pid'])
                ? (string) $params['pid']
                : '';
        }
        if ($pid === '' && preg_match('#/detail/(\d+)#i', $url, $match)) {
            $pid = $match[1];
        }
        if ($pid === '' && preg_match('/[?&]pid=(\d+)\b/i', $url, $match)) {
            $pid = $match[1];
        }
        if ($pid === '') {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $postData = '{"h_av":"5.2.13.011", "pid":' . $pid . '}';

        $result = self::fetch('https://share.xiaochuankeji.cn/planck/share/post/detail_h5', $postData);

        if (!$result || empty($result['data'])) {
            throw new \RuntimeException('视频数据获取失败');
        }

        $data = self::parseJson($result['data']);
        if (!$data) {
            throw new \RuntimeException('视频数据解析失败');
        }


        $item = is_array($data) ? ($data['data']['post'] ?? null) : null;
        if (!is_array($item) || empty($item['videos']) || !is_array($item['videos'])) {
            throw new \RuntimeException('视频数据解析失败');
        }
        $videoKey = array_key_first($item['videos']);
        $videoUrl = $videoKey !== null && isset($item['videos'][$videoKey]['url'])
            ? $item['videos'][$videoKey]['url']
            : '';

        if (!$videoUrl) {
            throw new \RuntimeException('未找到视频 URL');
        }

        return [
            'title' => $item['content'] ?? '',
            'author' => $item['member']['name'] ?? '',
            'avatar' => isset($item['member']['avatar_urls']['origin']['urls'][0]) 
                ? self::fixUrl($item['member']['avatar_urls']['origin']['urls'][0]) 
                : '',
            'cover' => isset($item['imgs'][0]['url']) 
                ? self::fixUrl($item['imgs'][0]['url']) 
                : '',
            'url' => self::fixUrl($videoUrl)
        ];
    }
}
