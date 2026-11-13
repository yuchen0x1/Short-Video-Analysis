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
 * - GET/POST /api/v1/collection
 * - GET/POST /api/v1/live
 * - GET /api/v1/hot
 * - GET /api/v1/search
 * - GET /api/v1/comments
 */

require_once __DIR__ . "/../../vendor/autoload.php";

use App\Services\VideoParser;
use App\Services\LiveParser;
use App\Services\Discovery;
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
    $maxBytes = (int) Config::get('api.max_json_body_bytes', 1048576);
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        Response::error('请求体过大', 413);
    }
    $stream = fopen('php://input', 'rb');
    if ($stream === false) {
        return $parsed = [];
    }
    $body = stream_get_contents($stream, $maxBytes + 1);
    fclose($stream);
    if ($body === false || $body === "") {
        return $parsed = [];
    }
    if (strlen($body) > $maxBytes) {
        Response::error('请求体过大', 413);
    }
    $json = json_decode($body, true);
    return $parsed = is_array($json) ? $json : [];
}

function parseGithubReleaseJson(string $jsonText, string $owner, string $repo): ?array {
    $data = json_decode($jsonText, true);
    if (!is_array($data)) {
        return null;
    }
    $tag = trim((string) ($data['tag_name'] ?? ''));
    if ($tag === '' || preg_match('/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/i', $tag) !== 1) {
        return null;
    }

    $assets = [];
    if (is_array($data['assets'] ?? null)) {
        foreach ($data['assets'] as $asset) {
            if (!is_array($asset)) continue;
            $name = trim((string) ($asset['name'] ?? ''));
            $downloadUrl = trim((string) ($asset['browser_download_url'] ?? ''));
            if ($name === '' || !preg_match('#^https://#i', $downloadUrl)) continue;
            $assets[] = [
                'name' => $name,
                'browser_download_url' => $downloadUrl,
                'size' => (int) ($asset['size'] ?? 0),
                'digest' => (string) ($asset['digest'] ?? ''),
            ];
        }
    }

    return [
        'tag_name' => $tag,
        'html_url' => (string) ($data['html_url'] ?? sprintf('https://github.com/%s/%s/releases/tag/%s', $owner, $repo, rawurlencode($tag))),
        'published_at' => (string) ($data['published_at'] ?? ''),
        'body' => (string) ($data['body'] ?? ''),
        'assets' => $assets,
    ];
}

function parseGithubExpandedAssets(string $htmlText, string $owner, string $repo, string $tag): array {
    if (strlen($htmlText) > 2 * 1024 * 1024) {
        return [];
    }
    $previous = libxml_use_internal_errors(true);
    $dom = new \DOMDocument();
    $loaded = $dom->loadHTML($htmlText, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return [];
    }

    $assets = [];
    $downloadPrefix = sprintf('/%s/%s/releases/download/%s/', rawurlencode($owner), rawurlencode($repo), rawurlencode($tag));
    foreach ($dom->getElementsByTagName('a') as $link) {
        $href = trim($link->getAttribute('href'));
        $name = trim($link->textContent);
        if ($name === '' || !str_starts_with($href, $downloadPrefix)) {
            continue;
        }
        $assetName = rawurldecode(substr($href, strlen($downloadPrefix)));
        if ($assetName === '' || str_contains($assetName, '/')) {
            continue;
        }
        $assets[] = [
            'name' => $name,
            'browser_download_url' => 'https://github.com' . $href,
            'size' => 0,
            'digest' => '',
        ];
    }
    return $assets;
}

function parseGithubReleaseAtom(string $xmlText, string $owner, string $repo): ?array {
    if (!function_exists('simplexml_load_string') || strlen($xmlText) > 2 * 1024 * 1024) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xmlText, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$feed || !isset($feed->entry[0])) {
        return null;
    }

    $entry = $feed->entry[0];
    $title = trim((string) $entry->title);
    $tag = '';
    if (preg_match('/^v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?/i', $title, $matches) === 1) {
        $tag = $matches[0];
    }
    if ($tag === '' && preg_match('#/releases/tag/(v?\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?)$#i', (string) ($entry->id ?? ''), $matches) === 1) {
        $tag = $matches[1];
    }
    if ($tag === '') {
        return null;
    }

    $releaseUrl = sprintf('https://github.com/%s/%s/releases/tag/%s', rawurlencode($owner), rawurlencode($repo), rawurlencode($tag));
    $body = html_entity_decode((string) $entry->content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $assets = [];
    $dom = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    if ($dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = trim($link->getAttribute('href'));
            $name = trim($link->textContent);
            if ($name === '' || !preg_match('~^https://github\.com/' . preg_quote($owner, '~') . '/' . preg_quote($repo, '~') . '/releases/download/' . preg_quote($tag, '~') . '/([^/?#]+)$~i', $href, $matches)) {
                continue;
            }
            $assets[] = [
                'name' => $name,
                'browser_download_url' => $href,
                'size' => 0,
                'digest' => '',
            ];
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return [
        'tag_name' => $tag,
        'html_url' => $releaseUrl,
        'published_at' => (string) ($entry->updated ?? ''),
        'body' => strip_tags($body),
        'assets' => $assets,
    ];
}

$corsOrigin = Config::get("app.cors_allow_origin", "*");
header("Access-Control-Allow-Origin: " . $corsOrigin);
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key");
header("Vary: Origin");
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

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
        // mb_substr/mb_strlen 在抖音与发现模块中使用，缺失会直接 fatal
        "mbstring" => extension_loaded("mbstring"),
        // parseGithubExpandedAssets 用 DOMDocument 解析 Release 附件页
        "dom" => class_exists(\DOMDocument::class),
    ];
    $ready = !in_array(false, $dependencies, true);

    // 抖音签名 sidecar 只影响抖音主页/合集/热榜/搜索，不阻塞整体就绪判定：
    // 以 warning 暴露，便于运维定位，同时不影响只跑网页解析的部署。
    $aBogusPort = (int) Config::env("A_BOGUS_PORT", "9876");
    $sidecarSocket = @fsockopen("127.0.0.1", $aBogusPort, $sidecarErrno, $sidecarErrstr, 0.5);
    $sidecarReachable = is_resource($sidecarSocket);
    if ($sidecarReachable) {
        fclose($sidecarSocket);
    }
    $warnings = [];
    if (!$sidecarReachable) {
        $warnings[] = "a_bogus 签名服务未启动，抖音主页、合集、热榜与搜索不可用";
    }

    // 版本从 composer 安装清单读取，避免与 composer.json 漂移
    $version = class_exists(\Composer\InstalledVersions::class)
        ? (\Composer\InstalledVersions::getPrettyVersion("yuchen0x1/short-video-watermark-remover") ?? "dev")
        : "dev";
    Response::success([
        "status" => $ready ? ($warnings === [] ? "ok" : "degraded") : "unavailable",
        "version" => $version,
        "php_version" => PHP_VERSION,
        "ready" => $ready,
        "dependencies" => $dependencies,
        "sidecar" => [
            "port" => $aBogusPort,
            "reachable" => $sidecarReachable,
        ],
        "warnings" => $warnings,
        "timestamp" => time(),
    ], $ready ? "服务正常" : "服务不完整", $ready ? 200 : 503);
}

// GET /api/v1/update
// 从 GitHub Release API 获取公开最新版本，供网页版查询当前正式版本。
if ($path === "/api/v1/update" || $path === "/api/v1/update/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }
    $owner = trim((string) Config::get('updates.github_owner', 'yuchen0x1'));
    $repo = trim((string) Config::get('updates.github_repo', 'Short-Video-Analysis'));
    $apiUrl = trim((string) Config::get('updates.github_api_url', sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repo)));
    $atomUrl = sprintf('https://github.com/%s/%s/releases.atom', rawurlencode($owner), rawurlencode($repo));
    if (!preg_match('#^https://api\.github\.com/repos/[^/]+/[^/]+/releases/latest$#i', $apiUrl)) {
        Response::error('更新源地址配置无效', 500);
    }

    $currentVersion = class_exists(\Composer\InstalledVersions::class)
        ? (string) (\Composer\InstalledVersions::getPrettyVersion("yuchen0x1/short-video-watermark-remover") ?? "dev")
        : "dev";
    // 短 TTL 缓存：避免每次页面加载都消耗 GitHub 匿名配额（按出口 IP 计算）
    $cacheKey = 'desktop-web|' . $currentVersion . '|' . $apiUrl;
    if (($_GET['force'] ?? '') === '') {
        $cachedUpdate = \App\Services\UpdateCache::get($cacheKey);
        if (is_array($cachedUpdate)) {
            Response::success($cachedUpdate, '版本检查完成（缓存）');
        }
    }
    $headers = [
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'qushuiyin-web-updater/1.1.8 (+https://github.com/yuchen0x1/Short-Video-Analysis)',
    ];
    $response = HttpClient::request($apiUrl, null, $headers, 1, $respHeaders, 8);
    $release = $response['success']
        ? parseGithubReleaseJson((string) $response['data'], $owner, $repo)
        : null;

    if (!is_array($release)) {
        // GitHub 匿名 REST API 按出口 IP 限流；回退到官方 Release Atom 与附件页。
        $atomResponse = HttpClient::request($atomUrl, null, [
            'Accept' => 'application/atom+xml',
            'User-Agent' => 'qushuiyin-web-updater/1.1.8 (+https://github.com/yuchen0x1/Short-Video-Analysis)',
        ], 1, $respHeaders, 8);
        $release = $atomResponse['success']
            ? parseGithubReleaseAtom((string) $atomResponse['data'], $owner, $repo)
            : null;
        if (is_array($release)) {
            $assetsUrl = sprintf('https://github.com/%s/%s/releases/expanded_assets/%s', rawurlencode($owner), rawurlencode($repo), rawurlencode((string) $release['tag_name']));
            $assetsResponse = HttpClient::request($assetsUrl, null, [
                'Accept' => 'text/html',
                'User-Agent' => 'qushuiyin-web-updater/1.1.8 (+https://github.com/yuchen0x1/Short-Video-Analysis)',
            ], 1, $respHeaders, 8);
            if ($assetsResponse['success']) {
                $release['assets'] = parseGithubExpandedAssets((string) $assetsResponse['data'], $owner, $repo, (string) $release['tag_name']);
            }
            Logger::warning('GitHub Release API 不可用，已使用 Release 页面回退', [
                'api_error' => $response['error'] ?? '无有效 Release 数据',
            ]);
        }
    }

    if (!is_array($release)) {
        Logger::error('在线更新检查失败', [
            'url' => $apiUrl,
            'error' => $response['error'] ?? '无有效 Release 数据',
            'atom_error' => $atomResponse['error'] ?? '无有效 Atom 数据',
        ]);
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
        if ($name === '' || !preg_match('#^https://#i', $url) || preg_match('/\.(?:apk|aab|ipa)$/i', $name)) continue;
        $assets[] = [
            'name' => $name,
            'url' => $url,
            'size' => (int) ($asset['size'] ?? 0),
            'digest' => (string) ($asset['digest'] ?? ''),
        ];
    }
    $windows = null;
    foreach ($assets as $asset) {
        if ($windows === null && preg_match('/\.exe$/i', $asset['name']) && preg_match('/setup/i', $asset['name'])) $windows = $asset;
    }
    $compareCurrent = preg_replace('/^v/i', '', $currentVersion);
    $updateAvailable = preg_match('/^\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/', (string) $compareCurrent) === 1
        && version_compare($latestVersion, (string) $compareCurrent, '>');
    $payload = [
        'current_version' => $currentVersion,
        'latest_version' => $latestVersion,
        'update_available' => $updateAvailable,
        'release_url' => (string) ($release['html_url'] ?? sprintf('https://github.com/%s/%s/releases/tag/v%s', $owner, $repo, $latestVersion)),
        'published_at' => (string) ($release['published_at'] ?? ''),
        'notes' => (string) ($release['body'] ?? ''),
        'assets' => $assets,
        'android_asset' => null, // 兼容旧 API 字段；项目不再提供独立移动安装包。
        'windows_asset' => $windows,
    ];
    \App\Services\UpdateCache::set($cacheKey, $payload);
    Response::success($payload, '版本检查完成');
}

// GET /api/v1/platforms
if ($path === "/api/v1/platforms" || $path === "/api/v1/platforms/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }
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
    // force=1 / refresh=true 跳过解析缓存，用于媒体直链失效后的重新获取
    $forceRefresh = in_array(
        strtolower(trim((string) ($_GET["force"] ?? $_GET["refresh"] ?? $jsonBody["force"] ?? ""))),
        ["1", "true", "yes"],
        true
    );

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
        $data = $parser->parse($url, $forceRefresh);
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
        $json = requestJsonBody();
        if (!empty($json["url"])) {
            $url = trim((string) $json["url"]);
        }
        if (isset($json["cursor"])) {
            $cursor = max(0, (int) $json["cursor"]);
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
        && (preg_match('~^/user/[^/?#]+~i', $profilePath) === 1
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

// GET/POST /api/v1/collection
if ($path === "/api/v1/collection" || $path === "/api/v1/collection/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $url = trim($_GET["url"] ?? $_POST["url"] ?? "");
    $cursor = max(0, (int) ($_GET["cursor"] ?? $_POST["cursor"] ?? 0));

    if ($url === "" && $method === "POST") {
        $json = requestJsonBody();
        if (!empty($json["url"])) {
            $url = trim((string) $json["url"]);
        }
        if (isset($json["cursor"])) {
            $cursor = max(0, (int) $json["cursor"]);
        }
    }

    if ($url === "") {
        Response::validationError(["url" => "合集 URL 参数不能为空"]);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $url)) {
        Response::validationError(["url" => "无效的合集 URL 格式"]);
    }

    // 入口处限定仅允许抖音合集链接，避免服务端发起任意 URL 请求（SSRF）
    $collectionHost = strtolower((string) parse_url($url, PHP_URL_HOST));
    $collectionHost = preg_replace("/^www\./", "", $collectionHost);
    $collectionPath = (string) parse_url($url, PHP_URL_PATH);
    $collectionQuery = (string) parse_url($url, PHP_URL_QUERY);
    $isDouyinCollection = ($collectionHost === 'douyin.com' || str_ends_with($collectionHost, '.douyin.com'))
        && (preg_match('#^/collection/\d+#i', $collectionPath) === 1
            || preg_match('/(?:^|&)mix_id=\d+/i', $collectionQuery) === 1);
    if (!$isDouyinCollection) {
        Response::validationError(["url" => "仅支持明确的抖音合集链接，不支持短链合集"]);
    }

    try {
        $parser = new VideoParser();
        $data = $parser->parseValidatedCollection($url, $cursor);
        Response::success($data, "合集作品解析成功");
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        Logger::error("合集解析失败", ["url" => $url, "cursor" => $cursor, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 500);
    } catch (\Throwable $e) {
        Logger::exception($e, ["url" => $url, "cursor" => $cursor, "ip" => $ip]);
        Response::error("合集解析失败，请稍后再试", 500);
    }
}

// GET/POST /api/v1/live
if ($path === "/api/v1/live" || $path === "/api/v1/live/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $url = SharedUrl::extract((string) ($_GET["url"] ?? $_POST["url"] ?? ""));
    if ($url === "" && $method === "POST") {
        $jsonBody = requestJsonBody();
        if (!empty($jsonBody["url"])) {
            $url = SharedUrl::extract((string) $jsonBody["url"]);
        }
    }

    if ($url === "") {
        Response::validationError(["url" => "直播间 URL 参数不能为空"]);
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $url)) {
        Response::validationError(["url" => "无效的直播间 URL 格式"]);
    }

    // LiveParser::parse 内部先按白名单域名路由，非直播平台直接拒绝，不会发起任意请求（SSRF）
    try {
        $parser = new LiveParser();
        $data = $parser->parse($url);
        Response::success($data, "直播间解析成功");
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        Logger::error("直播解析失败", ["url" => $url, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 500);
    } catch (\Throwable $e) {
        Logger::exception($e, ["url" => $url, "ip" => $ip]);
        Response::error("直播解析失败，请稍后再试", 500);
    }
}

// GET /api/v1/hot?platform=bilibili&page=1
if ($path === "/api/v1/hot" || $path === "/api/v1/hot/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $platform = strtolower(trim((string) ($_GET["platform"] ?? "bilibili")));
    $page = max(1, (int) ($_GET["page"] ?? 1));

    try {
        $discovery = new Discovery();
        $data = $discovery->hotList($platform, $page);
        Response::success($data, "热榜获取成功");
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        Logger::error("热榜获取失败", ["platform" => $platform, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 502);
    } catch (\Throwable $e) {
        Logger::exception($e, ["platform" => $platform, "ip" => $ip]);
        Response::error("热榜获取失败，请稍后再试", 500);
    }
}

// GET /api/v1/search?platform=bilibili&keyword=...&page=1
if ($path === "/api/v1/search" || $path === "/api/v1/search/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $platform = strtolower(trim((string) ($_GET["platform"] ?? "bilibili")));
    $keyword = trim((string) ($_GET["keyword"] ?? ""));
    $page = max(1, (int) ($_GET["page"] ?? 1));

    if ($keyword === "") {
        Response::validationError(["keyword" => "搜索关键词不能为空"]);
    }

    try {
        $discovery = new Discovery();
        $data = $discovery->search($platform, $keyword, $page);
        Response::success($data, "搜索完成");
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        Logger::error("搜索失败", ["platform" => $platform, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 502);
    } catch (\Throwable $e) {
        Logger::exception($e, ["platform" => $platform, "ip" => $ip]);
        Response::error("搜索失败，请稍后再试", 500);
    }
}

// GET /api/v1/comments?url=<video-url>&page=1
if ($path === "/api/v1/comments" || $path === "/api/v1/comments/") {
    if (Config::get("rate_limit.enabled", true)) {
        $rateLimitResult = RateLimiter::check($ip);
        if (!$rateLimitResult["allowed"]) {
            Response::error("请求过于频繁，请稍后再试", 429);
        }
    }

    $url = trim((string) ($_GET["url"] ?? ""));
    $page = max(1, (int) ($_GET["page"] ?? 1));

    if ($url === "") {
        Response::validationError(["url" => "视频 URL 参数不能为空"]);
    }
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $url)) {
        Response::validationError(["url" => "无效的视频 URL 格式"]);
    }

    try {
        $discovery = new Discovery();
        $data = $discovery->comments($url, $page);
        Response::success($data, "评论获取成功");
    } catch (\InvalidArgumentException $e) {
        Response::error($e->getMessage(), 400);
    } catch (\RuntimeException $e) {
        Logger::error("评论获取失败", ["url" => $url, "ip" => $ip, "error" => $e->getMessage()]);
        Response::error($e->getMessage(), 502);
    } catch (\Throwable $e) {
        Logger::exception($e, ["url" => $url, "ip" => $ip]);
        Response::error("评论获取失败，请稍后再试", 500);
    }
}

// 404
Response::error("接口不存在", 404);

