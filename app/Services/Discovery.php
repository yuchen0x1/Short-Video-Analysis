<?php

declare(strict_types=1);

namespace App\Services;

use App\Parsers\DouyinParser;
use App\Utils\HttpClient;
use App\Utils\Logger;
use App\Utils\UserAgent;

/**
 * 发现服务：平台公开热榜、关键词搜索与单视频公开评论。
 *
 * 合规边界：仅响应用户主动发起的查询，按需分页，不做自动翻页采集、不落库。
 * 数据源均为平台公开接口；部分平台接口受风控限制时返回受控错误。
 */
class Discovery
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    public const PLATFORMS = ['bilibili', 'douyin'];

    /**
     * 平台热榜单页。
     *
     * @return array{platform: string, title: string, items: array<int, array<string, mixed>>, has_more: bool}
     */
    public function hotList(string $platform, int $page = 1): array
    {
        return match ($platform) {
            'bilibili' => $this->bilibiliHot($page),
            'douyin' => $this->douyinHot(),
            default => throw new \InvalidArgumentException('不支持的热榜平台'),
        };
    }

    /**
     * 关键词搜索单页（仅 B站；抖音搜索需完整登录态，暂不开放）。
     */
    public function search(string $platform, string $keyword, int $page = 1): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new \InvalidArgumentException('搜索关键词不能为空');
        }
        if (mb_strlen($keyword) > 100) {
            throw new \InvalidArgumentException('搜索关键词过长');
        }

        return match ($platform) {
            'bilibili' => $this->bilibiliSearch($keyword, $page),
            'douyin' => throw new \InvalidArgumentException('抖音搜索需要完整登录态，暂不开放'),
            default => throw new \InvalidArgumentException('不支持的搜索平台'),
        };
    }

    /**
     * 单视频公开评论单页（仅 B站）。
     */
    public function comments(string $url, int $page = 1): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';
        if ($host !== 'bilibili.com' && !str_ends_with($host, '.bilibili.com') && $host !== 'b23.tv') {
            throw new \InvalidArgumentException('目前仅支持 B 站视频评论查看');
        }

        $aid = $this->bilibiliAid($url);
        $page = max(1, $page);
        $payload = $this->bilibiliApi(
            'https://api.bilibili.com/x/v2/reply?type=1&oid=' . $aid . '&pn=' . $page . '&ps=20&sort=1'
        );
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $pageInfo = is_array($data['page'] ?? null) ? $data['page'] : [];

        $comments = [];
        foreach (is_array($data['replies'] ?? null) ? $data['replies'] : [] as $reply) {
            if (!is_array($reply)) {
                continue;
            }
            $comments[] = [
                'user' => (string) ($reply['member']['uname'] ?? ''),
                'avatar' => $this->fixUrl((string) ($reply['member']['avatar'] ?? '')),
                'content' => (string) ($reply['content']['message'] ?? ''),
                'likes' => (int) ($reply['like'] ?? 0),
                'time' => (int) ($reply['ctime'] ?? 0),
            ];
        }

        $count = (int) ($pageInfo['count'] ?? 0);
        $size = max(1, (int) ($pageInfo['size'] ?? 20));
        $num = max(1, (int) ($pageInfo['num'] ?? $page));

        return [
            'platform' => 'bilibili',
            'total' => $count,
            'page' => $page,
            'has_more' => $num * $size < $count,
            'next_page' => $page + 1,
            'comments' => $comments,
        ];
    }

    // ---- B站 ----

    private function bilibiliHot(int $page): array
    {
        $page = max(1, $page);
        $payload = $this->bilibiliApi('https://api.bilibili.com/x/web-interface/popular?ps=20&pn=' . $page);
        $list = is_array($payload['data']['list'] ?? null) ? $payload['data']['list'] : [];

        $items = [];
        foreach ($list as $item) {
            if (!is_array($item) || empty($item['bvid'])) {
                continue;
            }
            $items[] = [
                'title' => $this->stripTags((string) ($item['title'] ?? '')),
                'url' => 'https://www.bilibili.com/video/' . $item['bvid'],
                'cover' => $this->fixUrl((string) ($item['pic'] ?? '')),
                'author' => (string) ($item['owner']['name'] ?? ''),
                'hot' => (int) ($item['stat']['view'] ?? 0),
                'duration' => (string) ($item['duration'] ?? ''),
            ];
        }

        return [
            'platform' => 'bilibili',
            'title' => 'B站综合热门',
            'items' => $items,
            'has_more' => $items !== [],
            'next_page' => $page + 1,
        ];
    }

    private function bilibiliSearch(string $keyword, int $page): array
    {
        $page = max(1, $page);
        $payload = $this->bilibiliApi(
            'https://api.bilibili.com/x/web-interface/search/type?search_type=video&keyword='
            . rawurlencode($keyword) . '&page=' . $page
        );
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $results = is_array($data['result'] ?? null) ? $data['result'] : [];

        $items = [];
        foreach ($results as $item) {
            if (!is_array($item) || empty($item['bvid'])) {
                continue;
            }
            $items[] = [
                'title' => $this->stripTags((string) ($item['title'] ?? '')),
                'url' => 'https://www.bilibili.com/video/' . $item['bvid'],
                'cover' => $this->fixUrl((string) ($item['pic'] ?? '')),
                'author' => (string) ($item['author'] ?? ''),
                'hot' => (int) ($item['play'] ?? 0),
                'duration' => (string) ($item['duration'] ?? ''),
            ];
        }

        $total = (int) ($data['numResults'] ?? count($items));

        return [
            'platform' => 'bilibili',
            'keyword' => $keyword,
            'items' => $items,
            'total' => $total,
            'has_more' => $page * 20 < $total,
            'next_page' => $page + 1,
        ];
    }

    /**
     * B站搜索/评论接口需要 buvid3 Cookie；获取一次并缓存 1 小时。
     */
    private function bilibiliApi(string $url): array
    {
        $cookieKey = ParseCache::makeKey('bilibili-buvid-cookie');
        $cached = ParseCache::get($cookieKey);
        $cookie = is_array($cached) && is_string($cached['cookie'] ?? null) ? $cached['cookie'] : '';
        if ($cookie === '') {
            $spi = HttpClient::request(
                'https://api.bilibili.com/x/frontend/finger/spi',
                null,
                ['User-Agent' => self::DESKTOP_UA, 'Referer' => 'https://www.bilibili.com/'],
                0
            );
            $spiJson = $spi['success'] ? json_decode((string) $spi['data'], true) : null;
            $b3 = is_array($spiJson) ? (string) ($spiJson['data']['b_3'] ?? '') : '';
            $b4 = is_array($spiJson) ? (string) ($spiJson['data']['b_4'] ?? '') : '';
            if ($b3 !== '') {
                $cookie = 'buvid3=' . $b3 . ($b4 !== '' ? '; buvid4=' . $b4 : '');
                ParseCache::set($cookieKey, ['cookie' => $cookie], 3600);
            } else {
                $cookie = '';
            }
        }

        $headers = [
            'User-Agent' => self::DESKTOP_UA,
            'Referer' => 'https://www.bilibili.com/',
        ];
        if ($cookie !== '') {
            $headers['Cookie'] = $cookie;
        }

        $result = HttpClient::request($url, null, $headers, 0);
        if (!$result['success']) {
            Logger::error('B站发现接口请求失败', ['url' => $url, 'http_code' => $result['http_code'] ?? 0, 'error' => $result['error'] ?? '']);
            throw new \RuntimeException('B站接口请求失败，请稍后再试');
        }

        $json = json_decode((string) $result['data'], true);
        if (!is_array($json) || (int) ($json['code'] ?? -1) !== 0) {
            Logger::error('B站发现接口返回异常', ['url' => $url, 'body' => mb_substr((string) $result['data'], 0, 200)]);
            throw new \RuntimeException('B站接口返回异常，请稍后再试');
        }

        return $json;
    }

    /**
     * 从 B站视频链接解析 aid：bvid 先走 view 接口换取。
     */
    private function bilibiliAid(string $url): string
    {
        $target = HttpClient::getLocation($url) ?: $url;

        if (preg_match('/av(\d+)/i', $target, $match)) {
            return $match[1];
        }
        if (preg_match('/(BV\w+)/i', $target, $match)) {
            $payload = $this->bilibiliApi('https://api.bilibili.com/x/web-interface/view?bvid=' . $match[1]);
            $aid = (string) ($payload['data']['aid'] ?? '');
            if ($aid !== '') {
                return $aid;
            }
        }

        throw new \InvalidArgumentException('无法识别 B 站视频链接');
    }

    // ---- 抖音热榜（尽力而为，受风控时抛受控异常）----

    private function douyinHot(): array
    {
        $ua = self::DESKTOP_UA;
        $ttwid = DouyinParser::getTtwid($ua);
        if ($ttwid === null) {
            throw new \RuntimeException('获取抖音会话失败，请稍后再试');
        }

        $query = http_build_query([
            'device_platform' => 'webapp',
            'aid' => '6383',
            'channel' => 'channel_pc_web',
            'search_channel' => 'aweme_hot',
            'version_code' => '170400',
            'version_name' => '17.4.0',
            'browser_language' => 'zh-CN',
            'browser_platform' => 'Win32',
            'browser_name' => 'Chrome',
            'browser_version' => '123.0.0.0',
        ]);
        $aBogus = DouyinParser::generateABogus($query, $ua);
        $url = 'https://www.douyin.com/aweme/v1/web/hot/search/list/?' . $query
            . '&a_bogus=' . rawurlencode($aBogus);

        $result = HttpClient::request($url, null, [
            'User-Agent' => $ua,
            'Accept' => 'application/json, text/plain, */*',
            'Referer' => 'https://www.douyin.com/hot',
            'Cookie' => 'ttwid=' . $ttwid,
        ], 0);

        $json = $result['success'] ? json_decode((string) $result['data'], true) : null;
        $payload = is_array($json['data'] ?? null) ? $json['data'] : [];
        $list = is_array($payload['word_list'] ?? null) ? $payload['word_list'] : [];
        if ($list === []) {
            Logger::error('抖音热榜接口返回空数据', ['http_code' => $result['http_code'] ?? 0, 'body' => mb_substr((string) ($result['data'] ?? ''), 0, 200)]);
            throw new \RuntimeException('抖音热榜暂时受限，请稍后再试');
        }

        $items = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $word = (string) ($item['word'] ?? '');
            if ($word === '') {
                continue;
            }
            $items[] = [
                'title' => $word,
                'url' => 'https://www.douyin.com/search/' . rawurlencode($word),
                'cover' => $this->fixUrl((string) ($item['word_cover']['url_list'][0] ?? '')),
                'author' => '',
                'hot' => (int) ($item['hot_value'] ?? 0),
                'duration' => '',
            ];
        }

        return [
            'platform' => 'douyin',
            'title' => '抖音热点榜',
            'items' => $items,
            'has_more' => false,
            'next_page' => 1,
        ];
    }

    private function stripTags(string $title): string
    {
        return trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function fixUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        return str_starts_with($url, 'http') ? $url : 'https:' . $url;
    }
}
