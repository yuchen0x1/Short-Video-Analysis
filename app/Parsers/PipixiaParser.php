<?php

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 皮皮虾视频解析器
 */
class PipixiaParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            'User-Agent' => UserAgent::mobile(),
            'Referer' => 'https://www.pipix.com/',
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        if (!preg_match('/item\/(\d+)/', $target, $match)) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $endpoint = 'https://api.pipix.com/bds/cell/cell_comment/?' . http_build_query([
            'offset' => 0,
            'cell_type' => 1,
            'api_version' => 1,
            'cell_id' => $match[1],
            'ac' => 'wifi',
            'channel' => 'huawei_1319_64',
            'aid' => 1319,
            'app_name' => 'super',
        ]);

        $result = self::fetch($endpoint);
        if (!$result) {
            throw new \RuntimeException('解析视频信息失败');
        }

        $data = self::parseJson($result['data']);
        $comment = $data['data']['cell_comments'][0]['comment_info'] ?? null;
        $item = $comment['item'] ?? null;
        if (!$item) {
            throw new \RuntimeException('视频数据解析失败');
        }

        $videoUrl = $item['origin_video_download']['url_list'][0]['url'] ?? '';
        if (!$videoUrl) {
            $videoUrl = $item['video']['video_high']['url_list'][0]['url'] ?? '';
        }
        if (!$videoUrl) {
            $videoUrl = $item['video']['play_addr']['url_list'][0]['url'] ?? '';
        }

        $authorId = $item['author']['id'] ?? null;
        foreach ($comment['comments'] ?? [] as $reply) {
            $commentItem = $reply['item'] ?? null;
            $commentAuthorId = $commentItem['author']['id'] ?? null;
            $commentVideoUrl = $commentItem['video']['video_high']['url_list'][0]['url'] ?? '';
            if ($authorId !== null && $commentAuthorId !== null && (string) $commentAuthorId === (string) $authorId && $commentVideoUrl) {
                $videoUrl = $commentVideoUrl;
                break;
            }
        }

        $images = [];
        foreach ($item['note']['multi_image'] ?? [] as $image) {
            $imageUrl = $image['url_list'][0]['url'] ?? '';
            if ($imageUrl) {
                $images[] = self::fixUrl($imageUrl);
            }
        }

        if (!$videoUrl && !$images) {
            throw new \RuntimeException('未找到视频 URL');
        }

        $cover = $item['cover']['url_list'][0]['url']
            ?? ($item['note']['multi_image'][0]['url_list'][0]['url'] ?? '');
        $isImage = count($images) > 0;

        return [
            'author' => $item['author']['name'] ?? '',
            'avatar' => self::fixUrl($item['author']['avatar']['download_list'][0]['url'] ?? ''),
            'time' => $data['data']['display_time'] ?? 0,
            'title' => $item['content'] ?? '',
            'cover' => self::fixUrl($cover),
            'url' => self::fixUrl($videoUrl ?: $images[0]),
            'media_type' => $isImage ? 'image' : 'video',
            'images' => $images,
        ];
    }
}
