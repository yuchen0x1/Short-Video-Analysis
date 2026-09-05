<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 快手视频解析器
 */
class KuaishouParser extends BaseParser
{
    public static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::mobile(),
            "Referer" => "https://www.kuaishou.com/",
            "Content-Type" => "application/json",
        ];
    }

    public static function parse(string $url): array
    {
        // 解析短链接或完整链接获取 photoId
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        // 快手分享短链接可能是 /photo/xxx 或 /v/xxx 格式，重定向后才有完整 photoId
        $photoId = '';
        if (preg_match('/(?:photo|short-video)\/(\w+)/i', $target, $match)) {
            $photoId = $match[1];
        } elseif (preg_match('#/(?:v|video)/(\w+)#i', $target, $m2)) {
            $photoId = $m2[1];
        }

        if ($photoId === '') {
            throw new \InvalidArgumentException("无法解析快手视频 ID");
        }

        $result = self::fetch("https://www.kuaishou.com/graphql", json_encode([
            "operationName" => "visionVideoDetail",
            "query" => "query visionVideoDetail(\$photoId: String, \$type: String, \$page: String, \$webPageArea: String) {\n  visionVideoDetail(photoId: \$photoId, type: \$type, page: \$page, webPageArea: \$webPageArea) {\n    status\n    type\n    author { id name headerUrl }\n    photo {\n      id\n      caption\n      coverUrl\n      duration\n      likeCount\n      viewCount\n      photoUrl\n      timestamp\n    }\n  }\n}",
            "variables" => ["photoId" => $photoId, "page" => "selected"],
        ], JSON_UNESCAPED_SLASHES));

        if (!$result) {
            throw new \RuntimeException("解析视频信息失败");
        }

        $data = self::parseJson($result["data"]);
        $errorText = json_encode($data["errors"] ?? $data["error_msg"] ?? "", JSON_UNESCAPED_UNICODE);
        if (($data["result"] ?? null) === 2 || isset($data["captcha"]) || preg_match('/captcha|验证码|风控/i', (string) $errorText)) {
            throw new \RuntimeException("快手接口需要验证码或登录态");
        }

        $detail = $data["data"]["visionVideoDetail"] ?? null;
        $photo = $detail["photo"] ?? null;
        if (!$photo) {
            throw new \RuntimeException("视频数据解析失败");
        }

        $videoUrl = $photo["photoUrl"] ?? "";
        if (!$videoUrl) {
            throw new \RuntimeException("未找到视频 URL");
        }

        return [
            "author" => $detail["author"]["name"] ?? "",
            "uid" => $detail["author"]["id"] ?? "",
            "avatar" => $detail["author"]["headerUrl"] ?? "",
            "like" => $photo["likeCount"] ?? 0,
            "time" => $photo["timestamp"] ?? 0,
            "title" => $photo["caption"] ?? "",
            "cover" => $photo["coverUrl"] ?? "",
            "url" => self::fixUrl($videoUrl),
            "music" => [
                "author" => "",
                "avatar" => "",
            ],
        ];
    }
}

