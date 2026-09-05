<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 西瓜视频解析器
 */
class XiguaParser extends BaseParser
{
    private static function decodeEncryptedUrl(string $value): string
    {
        if ($value === '' || !extension_loaded('openssl')) return '';
        $raw = base64_decode(strrev($value), true);
        if ($raw === false || strlen($raw) <= 16 || substr($raw, 0, 8) !== 'Salted__') return '';

        $salt = substr($raw, 8, 8);
        $cipher = substr($raw, 16);
        $derived = '';
        $previous = '';
        while (strlen($derived) < 48) {
            $previous = md5($previous . 'xigua.fe.web_mobile' . $salt, true);
            $derived .= $previous;
        }
        $plain = openssl_decrypt(substr($cipher, 0), 'aes-256-cbc', substr($derived, 0, 32), OPENSSL_RAW_DATA, substr($derived, 32, 16));
        return is_string($plain) && preg_match('#^https?://#i', trim($plain)) ? trim($plain) : '';
    }

    private static function extractVideoItem(?array $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $data = $payload['data'] ?? null;
        $item = $payload['video_info']
            ?? (is_array($data) ? ($data['video_info'] ?? $data) : null);

        return is_array($item) ? $item : null;
    }

    private static function extractMainUrl(array $item): string
    {
        $videoList = $item['video']['video_list'] ?? [];
        if (!is_array($videoList)) {
            return '';
        }

        foreach (['video_list_4k', 'video_list_3', 'video_list_2', 'video_list_1', 'video_list_0'] as $key) {
            $mainUrl = $videoList[$key]['main_url'] ?? '';
            if (is_string($mainUrl) && $mainUrl !== '') {
                return $mainUrl;
            }
        }

        foreach (array_reverse($videoList) as $entry) {
            $mainUrl = is_array($entry) ? ($entry['main_url'] ?? '') : '';
            if (is_string($mainUrl) && $mainUrl !== '') {
                return $mainUrl;
            }
        }

        return '';
    }

    private static function decodeMainUrl(string $value): string
    {
        $decoded = base64_decode($value, true);
        return is_string($decoded) && preg_match('#^https?://#i', $decoded) ? $decoded : $value;
    }

    private static function buildApiResult(array $item, string $videoUrl): array
    {
        $user = $item['user_info'] ?? $item['author'] ?? [];

        return [
            'author' => $user['name'] ?? $user['screen_name'] ?? '',
            'uid' => $user['user_id'] ?? $user['id'] ?? '',
            'avatar' => $user['avatar_url'] ?? '',
            'like' => $item['statistics']['digg_count'] ?? $item['digg_count'] ?? 0,
            'time' => $item['create_time'] ?? $item['createTime'] ?? 0,
            'title' => $item['title'] ?? $item['video']['title'] ?? '',
            'cover' => $item['video']['poster_url'] ?? $item['cover_url'] ?? '',
            'url' => self::fixUrl(self::decodeMainUrl($videoUrl)),
        ];
    }

    protected static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::desktop(),
            "Referer" => "https://www.ixigua.com/",
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        // 提取 video_id：优先匹配 /video/xxx 或 /item/xxx 路径中的数字
        if (!preg_match('#/(?:video|item)/(\d+)#i', $target, $match)) {
            // 兼容仅含纯数字 ID 的短链/直链
            if (!preg_match('/\b(\d{10,25})\b/', $target, $match)) {
                throw new \InvalidArgumentException("无法解析西瓜视频 ID");
            }
        }

        $videoId = $match[1];

        $item = null;
        foreach (["vid", "video_id"] as $key) {
            $result = self::fetch(
                "https://www.ixigua.com/api/video/info?{$key}={$videoId}"
            );
            if (!$result) {
                continue;
            }

            $candidate = self::extractVideoItem(self::parseJson($result["data"]));
            $videoUrl = $candidate ? self::extractMainUrl($candidate) : '';
            if ($candidate && $videoUrl !== '') {
                return self::buildApiResult($candidate, $videoUrl);
            }
            $item = $candidate ?: $item;
        }

        // 旧 JSON 接口下线时，兼容西瓜分享页的页面数据。
        $pageResult = self::fetch($target);
        $pageHtml = $pageResult["data"] ?? "";
        if (preg_match('/window\._SSR_DATA\s*=\s*({.*?});?\s*</s', $pageHtml, $ssrMatch)) {
            $ssr = self::parseJson(trim($ssrMatch[1]));
            $info = $ssr["data"]["storeState"]["detail"]["videoData"]["result"] ?? null;
            $decodedUrl = is_array($info) ? self::decodeEncryptedUrl((string) ($info["url"] ?? "")) : "";
            if ($decodedUrl !== "") {
                return [
                    "author" => $info["media_user"]["screen_name"] ?? "",
                    "uid" => $info["media_user"]["id"] ?? "",
                    "avatar" => self::fixUrl($info["media_user"]["avatar_url"] ?? ""),
                    "title" => $info["title"] ?? $info["abstract"] ?? "",
                    "cover" => self::fixUrl($info["cover_image_url"] ?? ""),
                    "url" => $decodedUrl,
                ];
            }
        }

        // 页面数据和分享页数据作为 API 无可用媒体地址时的后备。
        $shareUrl = "https://m.ixigua.com/douyin/share/video/" . rawurlencode($videoId)
            . "?aweme_type=107&schema_type=1&utm_source=copy&utm_campaign=copy&utm_medium=android&app=aweme";
        $shareResult = self::fetch($shareUrl);
        $html = $shareResult["data"] ?? "";
        if (preg_match('/window\._ROUTER_DATA\s*=\s*({.*?});\s*</s', $html, $routerMatch)) {
            $router = self::parseJson(trim($routerMatch[1]));
            $routerItem = $router["loaderData"]["video_(id)/page"]["videoInfoRes"]["item_list"][0] ?? null;
            $videoUrl = is_array($routerItem) ? ($routerItem["video"]["play_addr"]["url_list"][0] ?? "") : "";
            if ($videoUrl !== "") {
                $user = $routerItem["author"] ?? [];
                return [
                    "author" => $user["nickname"] ?? "",
                    "uid" => $user["user_id"] ?? $user["unique_id"] ?? "",
                    "avatar" => self::fixUrl($user["avatar_thumb"]["url_list"][0] ?? ""),
                    "like" => $routerItem["statistics"]["digg_count"] ?? 0,
                    "time" => $routerItem["create_time"] ?? 0,
                    "title" => $routerItem["desc"] ?? "",
                    "cover" => self::fixUrl($routerItem["video"]["cover"]["url_list"][0] ?? $routerItem["video"]["origin_cover"]["url_list"][0] ?? ""),
                    "url" => self::fixUrl(str_replace("playwm", "play", $videoUrl)),
                ];
            }
        }

        if (preg_match('/window\._SSR_HYDRATED_DATA\s*=\s*({[^<]*})[^<]*<\/script>/i', $html, $hydratedMatch)) {
            $hydrated = self::parseJson(trim($hydratedMatch[1]));
            $videoList = $hydrated["anyVideo"]["gidInformation"]["packerData"]["video"]["videoResource"]["normal"]["video_list"] ?? [];
            if (is_array($videoList)) {
                uasort($videoList, static fn (array $left, array $right): int => (int) ($right["vheight"] ?? 0) <=> (int) ($left["vheight"] ?? 0));
                $selected = reset($videoList);
                $mainUrl = is_array($selected) ? (string) ($selected["main_url"] ?? "") : "";
                $decodedUrl = self::decodeMainUrl($mainUrl);
                if (preg_match('#^https?://#i', $decodedUrl)) {
                    $info = $hydrated["anyVideo"]["gidInformation"] ?? [];
                    return [
                        "author" => $info["author"]["name"] ?? "",
                        "uid" => $info["author"]["id"] ?? "",
                        "avatar" => self::fixUrl($info["author"]["avatar"] ?? ""),
                        "title" => $info["title"] ?? "",
                        "cover" => self::fixUrl(is_array($selected) ? ($selected["poster_url"] ?? $info["cover_url"] ?? "") : ($info["cover_url"] ?? "")),
                        "url" => self::fixUrl(preg_replace('#^http://#i', 'https://', $decodedUrl)),
                    ];
                }
            }
        }


        if (!$item) {
            throw new \RuntimeException("视频数据解析失败");
        }

        throw new \RuntimeException("未找到视频 URL");
    }
}

