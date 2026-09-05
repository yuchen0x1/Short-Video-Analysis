<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 小红书视频/笔记解析器
 */
class XiaohongshuParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::mobile(),
            "Referer" => "https://www.xiaohongshu.com/",
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        // 匿名短链可能落到 /404，但页面 URL 中仍保留真实分享地址。
        $query = parse_url($target, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            foreach (['redirectPath', 'originalUrl'] as $key) {
                if (!empty($params[$key]) && is_string($params[$key]) && preg_match('/^https?:\/\//i', $params[$key])) {
                    $target = $params[$key];
                    break;
                }
            }
        }
        if (preg_match('/[?&]redirectPath=([^&]+)/i', $target, $redirectMatch)) {
            $redirect = rawurldecode($redirectMatch[1]);
            if (preg_match('/^https?:\/\//i', $redirect)) {
                $target = $redirect;
            }
        }

        // 提取 note_id
        if (!preg_match('/explore\/([a-f0-9]+)|discovery\/item\/([a-f0-9]+)/i', $target, $match)) {
            // 尝试匹配短链接格式
            if (preg_match('/(?:discovery\/item\/|explore\/)([a-f0-9]+)/i', $target, $m2)) {
                $noteId = $m2[1];
            } else {
                throw new \InvalidArgumentException("无法解析小红书笔记 ID");
            }
        } else {
            $noteId = $match[1] ?: $match[2];
        }

        // 小红书没有公开的无 token API，通过页面 SSR 数据提取
        $pageUrl = preg_match('#/(?:explore|discovery/item)/[a-f0-9]+#i', $target)
            ? $target
            : "https://www.xiaohongshu.com/explore/{$noteId}";
        $result = self::fetch($pageUrl);

        if (!$result) {
            throw new \RuntimeException("解析笔记信息失败");
        }

        $html = $result["data"];

        // 从 window.__INITIAL_STATE__ 提取数据（discovery 分享页状态后无分号）
        if (!preg_match('/window\.__INITIAL_STATE__\s*=\s*({.*?});?\s*</s', $html, $matches)) {
            throw new \RuntimeException("提取页面数据失败");
        }

        $data = self::parseJson(trim($matches[1]));
        // /explore/ 页数据在 note.noteDetailMap，/discovery/item/ 分享页在 noteData.data.noteData
        $noteMap = $data["note"]["noteDetailMap"] ?? [];
        $note = is_array($noteMap) ? ($noteMap[$noteId] ?? null) : null;

        // 尝试不同的数据路径
        if (!$note) {
            foreach ($data["note"] ?? [] as $key => $val) {
                if (is_array($val) && isset($val["note"])) {
                    $note = $val;
                    break;
                }
            }
        }

        $noteData = $note["note"] ?? $note;
        if (!$noteData) {
            $discoveryNote = $data["noteData"]["data"]["noteData"] ?? null;
            if (is_array($discoveryNote) && (isset($discoveryNote["noteId"]) || isset($discoveryNote["imageList"]) || isset($discoveryNote["video"]))) {
                $noteData = $discoveryNote;
            }
        }
        if (!$noteData) {
            throw new \RuntimeException("笔记数据解析失败");
        }

        $videoUrl = "";
        // 视频链接可能在不同位置；discovery 分享页 stream 按编码分组且字段为 camelCase
        $video = $noteData["video"] ?? [];
        if ($video) {
            $media = $video["media"] ?? [];
            $stream = $media["stream"] ?? [];
            if ($stream) {
                $videoUrl = $stream["master_url"] ?? "";
                if (!$videoUrl) {
                    foreach (["h264", "h265", "h266", "av1"] as $codec) {
                        foreach ($stream[$codec] ?? [] as $variant) {
                            $videoUrl = $variant["masterUrl"] ?? $variant["master_url"] ?? "";
                            if ($videoUrl) {
                                break 2;
                            }
                        }
                    }
                }
            }
            // 备用路径
            if (!$videoUrl) {
                $consumerList = $video["consumer"] ?? [];
                $originKey = $consumerList["origin_url_key"] ?? $consumerList["originVideoKey"] ?? "";
                if ($originKey) {
                    $videoUrl = $video[$originKey] ?? "";
                }
                if (!$videoUrl) {
                    $videoUrl = $video["url"] ?? "";
                }
            }
        }

        // 图文笔记没有视频流，从 imageList 提取全部图片
        $images = [];
        if (!$videoUrl) {
            foreach ($noteData["imageList"] ?? [] as $image) {
                $url = self::fixUrl($image["urlDefault"] ?? $image["url"] ?? $image["liveImage"] ?? "");
                if (preg_match('/^https?:\/\//i', $url) === 1) {
                    $images[] = $url;
                }
            }
        }

        if (!$videoUrl && !$images) {
            throw new \RuntimeException("未能获取该笔记的视频或图片，笔记可能需要登录后查看");
        }

        $user = $noteData["user"] ?? [];
        $isVideo = $videoUrl !== "";
        return [
            "author" => $user["nickname"] ?? $user["nickName"] ?? "",
            "uid" => $user["userId"] ?? "",
            "avatar" => $user["avatar"] ?? "",
            "like" => $noteData["likedCount"] ?? $noteData["interactInfo"]["likedCount"] ?? 0,
            "time" => $noteData["time"] ?? $noteData["createTime"] ?? 0,
            "title" => $noteData["title"] ?? $noteData["desc"] ?? "",
            "cover" => ($noteData["imageList"][0]["urlDefault"] ?? $noteData["cover"]["urlDefault"] ?? ($images[0] ?? "")),
            "url" => self::fixUrl($videoUrl ?: ($images[0] ?? "")),
            "media_type" => $isVideo ? "video" : "image",
            "images" => $isVideo ? [] : $images,
        ];
    }
}
