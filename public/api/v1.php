<?php

declare(strict_types=1);

/**
 * API v1 入口
 *
 * 版本化 API 路由，支持：
 * - GET /api/v1/parse?url=...
 * - POST /api/v1/parse (url in body)
 * - GET /api/v1/platforms
 * - GET /api/v1/health
 * - GET /api/v1/update
 * - GET/POST /api/v1/profile
 */

require_once __DIR__ . "/../../vendor/autoload.php";

use App\Services\VideoParser;
use App\Services\MediaProxy;
use App\Services\RateLimiter;
use App\Utils\Response;
use App\Utils\Config;
use App\Utils\ApiAuth;
use App\Utils\Logger;
use App\Utils\CookieManager;
use App\Utils\HttpClient;
use App\Utils\SharedUrl;

function requestJsonBody(): array {
    static $parsed = null;
    if ($parsed !== null) {
        return $parsed;
    }
    $body = file_get_contents("php://input");
    if ($body === false || $body === "") {
        return $parsed = [];
    }
    $json = json_decode($body, true);
    return $parsed = is_array($json) ? $json : [];
}

function parseGiteeReleaseHtml(string $html, string $owner, string $repo): ?array {
    $tag = '';
    if (preg_match('/class=["\'][^"\']*releases-sidebar-item[^"\']*["\'][^>]*data-tag=["\']([^"\']+)["\']/i', $html, $tagMatch)) {
        $tag = trim(html_entity_decode($tagMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($tag === '' || preg_match('/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/i', $tag) !== 1) {
        return null;
    }

    $assets = [];
    $assetPattern = '#href=["\'](/' . preg_quote($owner, '#') . '/' . preg_quote($repo, '#') . '/releases/download/' . preg_quote($tag, '#') . '/[^"\']+)["\']#i';
    if (preg_match_all($assetPattern, $html, $assetMatches)) {
        foreach ($assetMatches[1] as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $name = rawurldecode((string) basename(parse_url($href, PHP_URL_PATH) ?: ''));
            if ($name === '' || preg_match('/^[A-Za-z0-9._+-]+$/', $name) !== 1) continue;
            $assets[] = [
                'name' => $name,
                'browser_download_url' => 'https://gitee.com' . $href,
                'size' => 0,
                'digest' => '',
            ];
        }
    }

    return [
        'tag_name' => $tag,
        'html_url' => sprintf('https://gitee.com/%s/%s/releases/tag/%s', $owner, $repo, rawurlencode($tag)),
        'published_at' => '',
        'body' => '',
        'assets' => $assets,
    ];
}

$corsOrigin = Config::get("app.cors_allow_origin", "*");
header("Access-Control-Allow-Origin: " . $corsOrigin);
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key");
header("Vary: Origin");
header("Content-Type: application/json; charset=utf-8");

$method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");

if ($method === "OPTIONS") {
    http_response_code(204);
    exit;
}

if (!in_array($method, ["GET", "POST"], true)) {
    Response::error("不支持的请求方法", 405);
}

// Parse the route from REQUEST_URI
$requestUri = $_SERVER["REQUEST_URI"] ?? "/";
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, "/");

// Rate limiting
$ip = RateLimiter::getClientIp();

// ---- API Key 鉴权（可选开启，默认关闭）----
// health 保持公开，便于健康检查；其余接口按配置校验 X-API-Key 或 Authorization: Bearer
if (!in_array($path, ["/api/v1/health", "/api/v1/update"], true)) {
    ApiAuth::verify();
}

// ---- Routes ----

// GET /api/v1/health
if ($path === "/api/v1/health") {
    $dependencies = [
        "curl" => extension_loaded("curl"),
        "json" => extension_loaded("json"),
        "openssl" => extension_loaded("openssl"),
    ];
    $ready = !in_array(false, $dependencies, true);
    // 版本从 composer 安装清单读取，避免与 composer.json 漂移
    $version = class_exists(\Composer\InstalledVersions::class)
        ? (\Composer\InstalledVersions::getPrettyVersion("yuchen0x1/short-video-watermark-remover") ?? "dev")
        : "dev";
    Response::success([
        "status" => $ready ? "ok" : "degraded",
        "version" => $version,
        "php_version" => PHP_VERSION,
        "ready" => $ready,
        "dependencies" => $dependencies,
        "timestamp" => time(),
    ], $ready ? "服务正常" : "服务不完整", $ready ? 200 : 503);
}

// GET /api/v1/update
// 从国内 Gitee Release 获取公开最新版本，供网站和移动端统一使用。
if ($path === "/api/v1/update" || $path === "/api/v1/update/") {
    $owner = trim((string) Config::get('updates.gitee_owner', 'yuchen0x1'));
    $repo = trim((string) Config::get('updates.gitee_repo', 'short-video-watermark-remover'));
    $apiUrl = trim((string) Config::get('updates.gitee_url', ''));
    if (preg_match('#^https://gitee\.com/[^/]+/[^/]+/releases/?$#i', $apiUrl) !== 1) {
        Response::error('更新源地址配置无效', 500);
    }

    $currentVersion = class_exists(\Composer\InstalledVersions::class)
        ? (string) (\Composer\InstalledVersions::getPrettyVersion("yuchen0x1/short-video-watermark-remover") ?? "dev")
        : "dev";
    $response = HttpClient::request($apiUrl, null, ['Accept' => 'text/html'], 0);
    $release = $response['success']
        ? parseGiteeReleaseHtml((string) $response['data'], $owner, $repo)
        : null;

    if (!is_array($release)) {
        Logger::error('在线更新检查失败', ['url' => $apiUrl, 'error' => $response['error'] ?? '无有效 Release 数据']);
        Response::error('暂时无法获取最新版本，请稍后重试', 502);
    }
    $latestVersion = preg_replace('/^v/i', '', trim((string) ($release['tag_name'] ?? '')));
    if (!is_string($latestVersion) || !preg_match('/^\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/', $latestVersion)) {
        Response::error('最新 Release 缺少有效版本号', 502);
    }
    $assets = [];
    foreach (is_array($release['assets'] ?? null) ? $release['assets'] : [] as $asset) {
        $name = trim((string) ($asset['name'] ?? ''));
        $url = trim((string) ($asset['browser_download_url'] ?? ''));
        if ($name === '' || !preg_match('#^https://#i', $url)) continue;
        $assets[] = [
            'name' => $name,
            'url' => $url,
            'size' => (int) ($asset['size'] ?? 0),
            'digest' => (string) ($asset['digest'] ?? ''),
        ];
    }
    $android = null;
    $androidScore = PHP_INT_MAX;
    $windows = null;
    foreach ($assets as $asset) {
        if (preg_match('/\.apk$/i', $asset['name'])) {
            $score = preg_match('/unsigned/i', $asset['name']) ? 2 : (preg_match('/debug/i', $asset['name']) ? 1 : 0);
            if ($score < $androidScore) { $android = $asset; $androidScore = $score; }
        }
        if ($windows === null && preg_match('/\.exe$/i', $asset['name']) && preg_match('/setup/i', $asset['name'])) $windows = $asset;
    }
    $compareCurrent = preg_replace('/^v/i', '', $currentVersion);
    $updateAvailable = preg_match('/^\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/', (string) $compareCurrent) === 1
        && version_compare($latestVersion, (string) $compareCurrent, '>');
    Response::success([
        'current_version' => $currentVersion,
        'latest_version' => $latestVersion,
        'update_available' => $updateAvailable,
        'release_url' => (string) ($release['html_url'] ?? sprintf('https://gitee.com/%s/%s/releases/tag/v%s', $owner, $repo, $latestVersion)),
        'published_at' => (string) ($release['published_at'] ?? ''),
        'notes' => (string) ($release['body'] ?? ''),
        'assets' => $assets,
        'android_asset' => $android,
        'windows_asset' => $windows,
    ], '版本检查完成');
}

// GET /api/v1/platforms
if ($path === "/api/v1/platforms" || $path === "/api/v1/platforms/") {
    $parser = new VideoParser();
    Response::success($parser->getSupportedPlatforms(), "支持平台列表");
}

// GET/POST /api/v1/parse
if ($path === "/api/v1/parse" || $path === "/api/v1/parse/") {
    // Rate limit check
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $url = SharedUrl::extract((string) ($_GET["url"] ?? $_POST["url"] ?? ""));
    $jsonBody = requestJsonBody();
    if ($url === "" && $method === "POST") {
        if (!empty($jsonBody["url"])) {
            $url = SharedUrl::extract((string) $jsonBody["url"]);
        }
    }

    $weiboCookie = trim((string) ($jsonBody["weiboCookie"] ?? ""));

    if ($url === "") {
        Response::validationError(["url" => "URL 参数不能为空"]);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $url)) {
        Response::validationError(["url" => "无效的 URL 格式"]);
    }

    if ($weiboCookie !== "") {
        CookieManager::set("weibo", $weiboCookie);
    }

    $data = null;
    try {
        $parser = new VideoParser();
        $data = $parser->parse($url);
    } catch (\InvalidArgumentException $e) {
        CookieManager::clear("weibo");
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        CookieManager::clear("weibo");
        Logger::error("解析失败", ["url" => $url, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 500);
    } catch (\Throwable $e) {
        CookieManager::clear("weibo");
        Logger::exception($e, ["url" => $url, "ip" => $ip]);
        Response::error("服务器内部错误，请稍后再试", 500);
    }
    CookieManager::clear("weibo");

    // 序列化失败（如上游标题含非法 UTF-8）时返回规范的 JSON 错误而非 HTML fatal
    try {
        Response::success($data, "解析成功");
    } catch (\JsonException $e) {
        Logger::error("解析结果序列化失败", ["url" => $url, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error("解析结果包含无法序列化的内容，请稍后再试", 500);
    }
}


// GET/POST /api/v1/profile
if ($path === "/api/v1/profile" || $path === "/api/v1/profile/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $url = trim($_GET["url"] ?? $_POST["url"] ?? "");
    $cursor = max(0, (int) ($_GET["cursor"] ?? $_POST["cursor"] ?? 0));

    if ($url === "" && $method === "POST") {
        $body = file_get_contents("php://input");
        if ($body !== false && $body !== "") {
            $json = json_decode($body, true);
            if (is_array($json)) {
                if (!empty($json["url"])) {
                    $url = trim($json["url"]);
                }
                if (isset($json["cursor"])) {
                    $cursor = max(0, (int) $json["cursor"]);
                }
            }
        }
    }

    if ($url === "") {
        Response::validationError(["url" => "个人主页 URL 参数不能为空"]);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $url)) {
        Response::validationError(["url" => "无效的个人主页 URL 格式"]);
    }

    // 入口处限定仅允许抖音域名，避免服务端发起任意 URL 请求（SSRF）
    $profileHost = strtolower((string) parse_url($url, PHP_URL_HOST));
    $profileHost = preg_replace("/^www\./", "", $profileHost);
    $profilePath = (string) parse_url($url, PHP_URL_PATH);
    $profileQuery = (string) parse_url($url, PHP_URL_QUERY);
    $isDouyinProfile = ($profileHost === 'douyin.com' || str_ends_with($profileHost, '.douyin.com'))
        && (preg_match('#^/user/[^/?#]+#i', $profilePath) === 1
            || preg_match('/(?:^|&)(?:sec_user_id|sec_uid)=[^&]+/i', $profileQuery) === 1);
    if (!$isDouyinProfile) {
        Response::validationError(["url" => "仅支持明确的抖音个人主页链接，不支持短链主页"]);
    }

    try {
        $parser = new VideoParser();
        $data = $parser->parseValidatedProfile($url, $cursor);
        Response::success($data, "主页作品解析成功");
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        Logger::error("主页解析失败", ["url" => $url, "cursor" => $cursor, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 500);
    } catch (\Throwable $e) {
        Logger::exception($e, ["url" => $url, "cursor" => $cursor, "ip" => $ip]);
        Response::error("主页解析失败，请稍后再试", 500);
    }
}

// 404
Response::error("接口不存在", 404);

