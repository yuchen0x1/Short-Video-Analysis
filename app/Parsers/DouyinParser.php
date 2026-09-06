<?php

namespace App\Parsers;

use App\Services\ParseCache;
use App\Utils\Config;
use App\Utils\HttpClient;
use App\Utils\Logger;
use App\Utils\UserAgent;

/**
 * 抖音视频解析器
 */
class DouyinParser extends BaseParser
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    private static ?string $profileTtwid = null;

    private static ?string $profileMsToken = null;

    private static ?string $profileVerifyFp = null;

    private static ?string $profileWhaleCutToken = null;

    private static ?string $profileWebId = null;

    public static function getHeaders(): array
    {
        return ['User-Agent' => UserAgent::mobile()];
    }

    public static function parse(string $url): array
    {
        $videoId = self::extractVideoId($url);
        if ($videoId === null) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $item = null;
        $error = '';

        try {
            $item = self::fetchAwemeDetail($videoId);
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($item === null) {
            $item = self::fetchLegacy($videoId);
        }

        if ($item === null) {
            throw new \RuntimeException($error !== '' ? $error : '解析视频信息失败，视频可能已删除或接口暂时失效');
        }

        return self::buildResult($item);
    }

    /**
     * 读取抖音公开主页的单页作品。
     * cursor=0 表示第一页，后续使用上一次响应返回的 next_cursor。
     */
    public static function parseProfile(string $url, int $cursor = 0): array
    {
        $secUserId = self::extractSecUserId($url);
        if ($secUserId === null) {
            throw new \InvalidArgumentException('无法识别抖音个人主页链接');
        }

        $cursor = max(0, $cursor);
        $pageData = self::fetchProfilePage($secUserId, $cursor);
        if ($pageData === null) {
            throw new \RuntimeException('获取抖音个人主页作品失败，主页可能需要登录或接口暂时失效');
        }

        $items = [];
        $awemeList = $pageData['aweme_list'] ?? [];
        $profile = [];

        $profile = $pageData['user'] ?? $pageData['user_info'] ?? [];
        if (!is_array($awemeList)) {
            $awemeList = [];
        }

        foreach ($awemeList as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($profile === [] && is_array($item['author'] ?? null)) {
                $profile = $item['author'];
            }

            $result = self::buildResult($item);
            $awemeId = (string) ($item['aweme_id'] ?? $item['group_id'] ?? '');
            $result['aweme_id'] = $awemeId;
            $result['source_url'] = $awemeId !== ''
                ? 'https://www.douyin.com/video/' . $awemeId
                : '';
            $items[] = $result;
        }

        return [
            'platform' => 'douyin',
            'profile' => [
                'uid' => $profile['uid'] ?? $profile['sec_uid'] ?? $secUserId,
                'sec_user_id' => $profile['sec_uid'] ?? $secUserId,
                'author' => $profile['nickname'] ?? $profile['unique_id'] ?? '',
                'avatar' => self::firstUrl($profile['avatar_larger']['url_list'] ?? null)
                    ?? self::firstUrl($profile['avatar_medium']['url_list'] ?? null)
                    ?? self::firstUrl($profile['avatar_thumb']['url_list'] ?? null)
                    ?? '',
                'signature' => $profile['signature'] ?? '',
            ],
            'items' => $items,
            'total' => count($items),
            'cursor' => $cursor,
            'next_cursor' => (int) ($pageData['max_cursor'] ?? $cursor),
            'has_more' => (int) ($pageData['has_more'] ?? 0) === 1,
            'page_size' => count($items),
        ];
    }

    /**
     * 读取抖音合集（collection）的单页作品。
     * cursor=0 表示第一页，后续使用上一次响应返回的 next_cursor。
     */
    public static function parseCollection(string $url, int $cursor = 0): array
    {
        $mixId = self::extractMixId($url);
        if ($mixId === null) {
            throw new \InvalidArgumentException('无法识别抖音合集链接');
        }

        $cursor = max(0, $cursor);
        $pageData = self::fetchCollectionPage($mixId, $cursor);
        if ($pageData === null) {
            throw new \RuntimeException('获取抖音合集作品失败，合集可能已失效或接口暂时受限');
        }

        $items = [];
        $awemeList = $pageData['aweme_list'] ?? [];
        if (!is_array($awemeList)) {
            $awemeList = [];
        }

        foreach ($awemeList as $item) {
            if (!is_array($item)) {
                continue;
            }

            $result = self::buildResult($item);
            $awemeId = (string) ($item['aweme_id'] ?? $item['group_id'] ?? '');
            $result['aweme_id'] = $awemeId;
            $result['source_url'] = $awemeId !== ''
                ? 'https://www.douyin.com/video/' . $awemeId
                : '';
            $items[] = $result;
        }

        $collection = [
            'mix_id' => $mixId,
            'title' => '',
            'author' => '',
            'avatar' => '',
            'desc' => '',
            'total' => 0,
        ];
        $mixInfo = self::fetchMixInfo($mixId);
        if ($mixInfo !== null) {
            $collection['title'] = (string) ($mixInfo['mix_name'] ?? '');
            $creator = is_array($mixInfo['creator'] ?? null) ? $mixInfo['creator'] : ($mixInfo['author'] ?? []);
            if (!is_array($creator)) {
                $creator = [];
            }
            $collection['author'] = (string) ($creator['nickname'] ?? '');
            $collection['avatar'] = self::firstUrl($creator['avatar_medium']['url_list'] ?? null)
                ?? self::firstUrl($creator['avatar_thumb']['url_list'] ?? null)
                ?? '';
            $collection['desc'] = (string) ($mixInfo['desc'] ?? $mixInfo['mix_desc'] ?? '');
            $collection['total'] = (int) ($mixInfo['statis']['updated_to_eposide'] ?? $mixInfo['eposide_count'] ?? 0);
        }

        return [
            'platform' => 'douyin',
            'collection' => $collection,
            'items' => $items,
            'total' => count($items),
            'cursor' => $cursor,
            'next_cursor' => (int) ($pageData['cursor'] ?? $pageData['max_cursor'] ?? $cursor),
            'has_more' => (int) ($pageData['has_more'] ?? 0) === 1,
            'page_size' => count($items),
        ];
    }

    /**
     * 判断是否为抖音合集链接（/collection/<id> 或带 mix_id 参数的链接）。
     */
    public static function isCollectionUrl(string $url): bool
    {
        return preg_match('#/collection/\d+#i', $url) === 1
            || preg_match('/[?&]mix_id=\d+/i', $url) === 1;
    }

    protected static function extractMixId(string $url): ?string
    {
        if (preg_match('#/collection/(\d+)#i', $url, $match)) {
            return $match[1];
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            if (!empty($params['mix_id']) && is_string($params['mix_id'])) {
                return trim($params['mix_id']);
            }
        }

        return null;
    }

    protected static function extractVideoId(string $url): ?string
    {
        // 合集链接的 mix_id 不能当作单条视频 ID 解析
        if (self::isCollectionUrl($url)) {
            return null;
        }

        // 直播间房间号不能当作单条视频 ID 解析
        if (Live\DouyinLiveParser::isDouyinLiveUrl($url)) {
            return null;
        }

        if (preg_match('#/(?:video|note|share/video)/(\d+)#i', $url, $match)) {
            return $match[1];
        }

        if (preg_match('#https?://v\.douyin\.com/#i', $url)) {
            $location = HttpClient::getLocation($url);
            if ($location && self::isAllowedDouyinLocation($location) && preg_match('#/(?:video|note|share/video)/(\d+)#i', $location, $match)) {
                return $match[1];
            }

            $result = self::fetch($url);
            if ($result && preg_match('#/(?:video|note|share/video)/(\d+)#i', $result['data'], $match)) {
                return $match[1];
            }
        }

        if (preg_match('/\b\d{15,25}\b/', $url, $match)) {
            return $match[0];
        }

        return null;
    }

    private static function isAllowedDouyinLocation(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        return $host === 'douyin.com' || str_ends_with($host, '.douyin.com');
    }

    private static function extractSecUserId(string $url): ?string {
        $target = $url;
        if (preg_match('~/user/([^/?#]+)~i', $target, $match)) {
            return urldecode($match[1]);
        }

        if (preg_match('#https?://v\.douyin\.com/#i', $target)) {
            $location = HttpClient::getLocation($target);
            if ($location && $location !== $target && self::isAllowedDouyinLocation($location)) {
                return self::extractSecUserId($location);
            }
        }

        $query = parse_url($target, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            foreach (['sec_user_id', 'sec_uid'] as $key) {
                if (!empty($params[$key]) && is_string($params[$key])) {
                    return trim($params[$key]);
                }
            }
        }

        if (preg_match('~\bMS4w[^\s/?#]+~i', $target, $match)) {
            return $match[0];
        }

        return null;
    }

    private static function fetchProfilePage(string $secUserId, int $cursor): ?array
    {
        return self::fetchSignedPage(
            'douyin-profile-session|' . $secUserId,
            'https://www.douyin.com/aweme/v1/web/aweme/post/',
            'https://www.douyin.com/user/' . rawurlencode($secUserId),
            ['sec_user_id' => $secUserId, 'count' => 18],
            $cursor
        );
    }

    private static function fetchCollectionPage(string $mixId, int $cursor): ?array
    {
        return self::fetchSignedPage(
            'douyin-collection-session|' . $mixId,
            'https://www.douyin.com/aweme/v1/web/mix/aweme/',
            'https://www.douyin.com/collection/' . $mixId,
            ['mix_id' => $mixId, 'count' => 15],
            $cursor,
            'cursor'
        );
    }

    /**
     * 带 a_bogus 签名的作品列表分页请求；主页与合集共用同一套 ttwid/msToken 会话与重试逻辑。
     */
    private static function fetchSignedPage(
        string $sessionKey,
        string $endpoint,
        string $referer,
        array $staticParams,
        int $cursor,
        string $cursorKey = 'max_cursor'
    ): ?array {
        $ua = self::DESKTOP_UA;
        $cacheKey = ParseCache::makeKey($sessionKey);
        $session = ParseCache::get($cacheKey) ?? [];

        if (self::$profileTtwid === null && is_string($session['ttwid'] ?? null)) {
            self::$profileTtwid = $session['ttwid'];
        }
        if (self::$profileMsToken === null && is_string($session['ms_token'] ?? null)) {
            self::$profileMsToken = $session['ms_token'];
        }
        if (self::$profileVerifyFp === null && is_string($session['verify_fp'] ?? null)) {
            self::$profileVerifyFp = $session['verify_fp'];
        }
        if (self::$profileWhaleCutToken === null && is_string($session['whale_cut_token'] ?? null)) {
            self::$profileWhaleCutToken = $session['whale_cut_token'];
        }
        if (self::$profileWebId === null && is_string($session['web_id'] ?? null)) {
            self::$profileWebId = $session['web_id'];
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            if (self::$profileTtwid === null) {
                self::$profileTtwid = self::getTtwid($ua);
            }

            $ttwid = self::$profileTtwid;
            if ($ttwid === null) {
                usleep(150000 * ($attempt + 1));
                continue;
            }

            if (self::$profileMsToken === null) {
                self::$profileMsToken = self::randomToken(107);
            }
            if (self::$profileVerifyFp === null) {
                self::$profileVerifyFp = self::randomVerifyFp();
            }

            ParseCache::set($cacheKey, [
                'ttwid' => $ttwid,
                'ms_token' => self::$profileMsToken,
                'verify_fp' => self::$profileVerifyFp,
                'whale_cut_token' => self::$profileWhaleCutToken,
                'web_id' => self::$profileWebId,
            ], 1800);

            $params = array_merge([
                'device_platform' => 'webapp',
                'aid' => '6383',
                'channel' => 'channel_pc_web',
                'verifyFp' => self::$profileVerifyFp,
                'fp' => self::$profileVerifyFp,
                'webid' => self::$profileWebId ?? '',
                $cursorKey => $cursor > 0 ? (string) $cursor : '',
                'locate_query' => 'false',
                'show_live_replay_strategy' => 1,
                'need_time_list' => 1,
                'time_list_query' => 0,
                'whale_cut_token' => self::$profileWhaleCutToken ?? '',
                'cut_version' => '1',
                'publish_video_strategy_type' => 2,
                'msToken' => self::$profileMsToken,
            ], $staticParams);
            $query = http_build_query($params);
            $aBogus = self::generateABogus($query, $ua);
            if ($aBogus === '') {
                throw new \RuntimeException('抖音签名生成失败，请启动本地 a_bogus 签名服务（node scripts/a_bogus_server.js）');
            }

            $url = $endpoint . '?' . $query . '&a_bogus=' . rawurlencode($aBogus);
            $rawHeaders = '';
            $result = HttpClient::request($url, null, [
                'User-Agent' => $ua,
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                'Referer' => $referer,
                'Cookie' => 'ttwid=' . $ttwid
                    . '; s_v_web_id=' . self::$profileVerifyFp
                    . '; msToken=' . self::$profileMsToken,
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-origin',
            ], null, $rawHeaders);

            $whaleCutToken = self::headerValue($rawHeaders, 'whale_cut_token');
            if ($whaleCutToken !== null) {
                self::$profileWhaleCutToken = $whaleCutToken;
            }
            $webId = self::headerValue($rawHeaders, 'cookie_ttwidinfo_webid');
            if ($webId !== null) {
                self::$profileWebId = $webId;
            }
            ParseCache::set($cacheKey, [
                'ttwid' => $ttwid,
                'ms_token' => self::$profileMsToken,
                'verify_fp' => self::$profileVerifyFp,
                'whale_cut_token' => self::$profileWhaleCutToken,
                'web_id' => self::$profileWebId,
            ], 1800);

            $json = $result['success'] ? self::parseJson($result['data']) : null;
            if (is_array($json) && isset($json['aweme_list'])) {
                return $json;
            }

            Logger::error('抖音作品列表接口返回空数据', [
                'endpoint' => $endpoint,
                'cursor' => $cursor,
                'attempt' => $attempt + 1,
                'error' => $result['error'] ?? '',
                'http_code' => $result['http_code'] ?? 0,
                'body' => mb_substr((string) ($result['data'] ?? ''), 0, 300),
            ]);
            if ($cursor > 0 && $attempt === 0) {
                ParseCache::delete($cacheKey);
                self::$profileTtwid = null;
                self::$profileMsToken = null;
                self::$profileVerifyFp = null;
                self::$profileWhaleCutToken = null;
                self::$profileWebId = null;
            }
            usleep(250000 * ($attempt + 1));
        }

        return null;
    }

    /**
     * 读取合集详情（标题/作者/总条数）；失败时返回 null，不影响作品列表解析。
     */
    private static function fetchMixInfo(string $mixId): ?array
    {
        try {
            $ua = self::DESKTOP_UA;
            $ttwid = self::getTtwid($ua);
            if ($ttwid === null) {
                return null;
            }

            $query = http_build_query([
                'device_platform' => 'webapp',
                'aid' => '6383',
                'channel' => 'channel_pc_web',
                'mix_id' => $mixId,
                'msToken' => self::randomToken(107),
            ]);
            $aBogus = self::generateABogus($query, $ua);
            $url = 'https://www.douyin.com/aweme/v1/web/mix/detail/?' . $query
                . '&a_bogus=' . rawurlencode($aBogus);
            $result = HttpClient::request($url, null, [
                'User-Agent' => $ua,
                'Accept' => 'application/json, text/plain, */*',
                'Referer' => 'https://www.douyin.com/collection/' . $mixId,
                'Cookie' => 'ttwid=' . $ttwid,
            ]);

            if (!$result['success']) {
                return null;
            }
            $json = self::parseJson($result['data']);
            return is_array($json) && is_array($json['mix_info'] ?? null) ? $json['mix_info'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function fetchAwemeDetail(string $videoId): ?array
    {
        $ua = self::DESKTOP_UA;
        $ttwid = self::getTtwid($ua);
        if ($ttwid === null) {
            Logger::error('获取抖音 ttwid 失败', ['video_id' => $videoId]);
            return null;
        }

        $msToken = self::randomToken(107);
        $query = http_build_query([
            'device_platform' => 'webapp',
            'aid' => '6383',
            'channel' => 'channel_pc_web',
            'aweme_id' => $videoId,
            'msToken' => $msToken,
        ]);

        $aBogus = self::generateABogus($query, $ua);
        if ($aBogus === '') {
            throw new \RuntimeException('抖音签名生成失败，请启动本地 a_bogus 签名服务（node scripts/a_bogus_server.js）');
        }

        $url = 'https://www.douyin.com/aweme/v1/web/aweme/detail/?' . $query . '&a_bogus=' . rawurlencode($aBogus);
        $headers = [
            'User-Agent' => $ua,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'Referer' => 'https://www.douyin.com/video/' . $videoId,
            'Cookie' => 'ttwid=' . $ttwid,
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
        ];

        $rawHeaders = '';
        $result = HttpClient::request($url, null, $headers, null, $rawHeaders);
        if (!$result['success']) {
            Logger::error('抖音详情接口请求失败', [
                'video_id' => $videoId,
                'error' => $result['error'],
                'http_code' => $result['http_code'],
            ]);
            return null;
        }

        $json = self::parseJson($result['data']);
        if (!is_array($json) || !isset($json['aweme_detail']) || !is_array($json['aweme_detail'])) {
            Logger::error('抖音详情接口未返回数据', [
                'video_id' => $videoId,
                'body' => mb_substr($result['data'], 0, 500),
            ]);
            return null;
        }

        return $json['aweme_detail'];
    }

    public static function getTtwid(string $ua): ?string
    {
        $payload = json_encode([
            'region' => 'cn',
            'aid' => 6383,
            'need_t' => 1,
            'service' => 'www.douyin.com',
            'migrate_priority' => 0,
            'cb_url_protocol' => 'https',
            'domain' => '.douyin.com',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $rawHeaders = '';
        $result = HttpClient::request(
            'https://ttwid.bytedance.com/ttwid/union/register/',
            $payload,
            [
                'User-Agent' => $ua,
                'Content-Type' => 'application/json',
            ],
            null,
            $rawHeaders
        );

        if (!$result['success']) {
            return null;
        }

        if (preg_match('/Set-Cookie:\s*ttwid=([^;\s]+)/i', $rawHeaders, $match)) {
            return urldecode($match[1]);
        }

        return null;
    }

    public static function generateABogus(string $query, string $ua): string
    {
        // Try persistent signing server first (much faster)
        $serverPort = Config::env('A_BOGUS_PORT', '9876');
        $serverHost = '127.0.0.1';

        $startTime = microtime(true);

        // Try HTTP signing server with short timeout
        $ch = curl_init("http://{$serverHost}:{$serverPort}/");
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'ua' => $ua]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($output !== false && $httpCode === 200 && trim($output) !== '') {
                Logger::debug('a_bogus 签名服务耗时', ['ms' => round((microtime(true) - $startTime) * 1000)]);
                return trim($output);
            }
        }

        // 签名服务不可用时不执行外部命令，避免把请求参数传入子进程。
        throw new \RuntimeException('抖音签名服务不可用，请启动本地 a_bogus 服务');
    }

    private static function randomToken(int $length): string
    {
        $chars = 'ABCDEFGHIGKLMNOPQRSTUVWXYZabcdefghigklmnopqrstuvwxyz0123456789=';
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }

    private static function randomVerifyFp(): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $timestamp = base_convert((string) floor(microtime(true) * 1000), 10, 36);
        $uuid = '';

        for ($i = 0; $i < 36; $i++) {
            if (in_array($i, [8, 13, 18, 23], true)) {
                $uuid .= '_';
                continue;
            }
            if ($i === 14) {
                $uuid .= '4';
                continue;
            }
            $value = random_int(0, strlen($alphabet) - 1);
            if ($i === 19) {
                $value = 3 & $value | 8;
            }
            $uuid .= $alphabet[$value];
        }

        return 'verify_' . $timestamp . '_' . $uuid;
    }

    private static function headerValue(string $headers, string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':\\s*(.+)$/im', $headers, $match)) {
            return trim($match[1]);
        }

        return null;
    }


    private static function fetchLegacy(string $videoId): ?array
    {
        $result = self::fetch("https://www.iesdouyin.com/share/video/{$videoId}");
        if (!$result || !preg_match('/window\._ROUTER_DATA\s*=\s*(.*?)\<\/script>/s', $result['data'], $matches)) {
            return null;
        }

        $data = self::parseJson(trim($matches[1]));

        return $data['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0] ?? null;
    }

    protected static function buildResult(array $item): array
    {
        $images = self::pickImageUrls($item);
        $isImagePost = $images !== [];
        $mediaUrl = $isImagePost ? $images[0] : self::pickVideoUrl($item);

        return [
            'author' => $item['author']['nickname'] ?? '',
            'uid' => $item['author']['uid'] ?? $item['author']['unique_id'] ?? '',
            'avatar' => self::firstUrl($item['author']['avatar_medium']['url_list'] ?? null)
                ?? self::firstUrl($item['author']['avatar_thumb']['url_list'] ?? null)
                ?? self::firstUrl($item['author']['avatar_larger']['url_list'] ?? null)
                ?? '',
            'like' => $item['statistics']['digg_count'] ?? 0,
            'time' => $item['create_time'] ?? 0,
            'title' => $item['desc'] ?? '',
            'cover' => $isImagePost ? $images[0] : self::firstUrl($item['video']['cover']['url_list'] ?? null)
                ?? self::firstUrl($item['video']['origin_cover']['url_list'] ?? null)
                ?? '',
            'url' => $mediaUrl,
            'media_type' => $isImagePost ? 'image' : 'video',
            'images' => $images,
            'music' => [
                'author' => $item['music']['author'] ?? '',
                'title' => $item['music']['title'] ?? '',
                'avatar' => self::firstUrl($item['music']['cover_large']['url_list'] ?? null)
                    ?? self::firstUrl($item['music']['cover_thumb']['url_list'] ?? null)
                    ?? '',
                'url' => self::firstUrl($item['music']['play_url']['url_list'] ?? null)
                    ?? self::firstUrl($item['music']['download_url']['url_list'] ?? null)
                    ?? '',
            ],
        ];
    }

    private static function pickVideoUrl(array $item): string
    {
        // 优先使用视频 uri 走无水印播放接口；play_addr 在部分响应中本身就是带水印流
        $uri = $item['video']['play_addr']['uri'] ?? '';
        if ($uri !== '' && !self::isAudioUrl($uri)) {
            return "https://www.douyin.com/aweme/v1/play/?video_id={$uri}&ratio=1080p&line=0&watermark=0";
        }

        $candidates = [];
        $playUrls = $item['video']['play_addr']['url_list'] ?? [];
        if (is_array($playUrls) && isset($playUrls[0])) {
            $candidates[] = $playUrls[0];
        }

        $bitRates = $item['video']['bit_rate'] ?? [];
        if (is_array($bitRates)) {
            usort($bitRates, static fn(array $a, array $b): int => (int) ($b['bit_rate'] ?? 0) <=> (int) ($a['bit_rate'] ?? 0));
            foreach ($bitRates as $bitRate) {
                $urls = $bitRate['play_addr']['url_list'] ?? [];
                if (is_array($urls) && isset($urls[0])) {
                    $candidates[] = $urls[0];
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && self::fixUrl($candidate) !== '' && !self::isAudioUrl($candidate) && !self::isWatermarkedUrl($candidate)) {
                return self::fixUrl($candidate);
            }
        }

        // 兜底：v1/play 接口，显式 watermark=0（接口默认会下发水印地址）
        $uri = $item['video']['play_addr']['uri'] ?? '';
        if ($uri !== '' && !self::isAudioUrl($uri)) {
            return "https://www.douyin.com/aweme/v1/play/?video_id={$uri}&ratio=1080p&line=0&watermark=0";
        }

        throw new \RuntimeException('未找到视频 URL');
    }

    /**
     * download_addr / v1/play 中转地址带 watermark=1；只有 douyinvod 等直链才是无水印源。
     */
    private static function isWatermarkedUrl(string $url): bool
    {
        return str_contains($url, 'watermark=1') || str_contains($url, '/aweme/v1/play/');
    }

    /**
     * 图集作品没有可播放的视频地址，图片通常位于 images 或 image_post_info.images。
     * 优先使用 url_list / display_image.url_list。
     * download_url_list 在抖音接口中可能是带水印的下载地址，不能用于无水印结果。
     */
    private static function pickImageUrls(array $item): array
    {
        $groups = [
            $item['images'] ?? [],
            $item['image_post_info']['images'] ?? [],
        ];
        $images = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $image) {
                if (!is_array($image)) {
                    continue;
                }

                $url = self::firstUrl($image['url_list'] ?? null)
                    ?? self::firstUrl($image['display_image']['url_list'] ?? null);

                if ($url !== null && !in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    private static function isAudioUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        return (bool) preg_match('/\.(?:mp3|m4a|aac|wav|ogg|flac)$/i', $path);
    }

    private static function firstUrl($urlList): ?string
    {
        if (!is_array($urlList) || !isset($urlList[0]) || !is_string($urlList[0]) || $urlList[0] === '') {
            return null;
        }

        return self::fixUrl($urlList[0]);
    }
}
