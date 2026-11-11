<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MediaProxyRequest;
use App\Services\RateLimiter;
use App\Utils\Config;
use App\Utils\HttpClient;
use PHPUnit\Framework\TestCase;

final class RegressionFixesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testRateLimiterDisabledStorageDoesNotThrow(): void
    {
        putenv('RATE_LIMIT_CACHE_ENABLED=false');
        Config::clearCache();
        $result = RateLimiter::check('127.0.0.1');
        $this->assertTrue($result['allowed']);
        putenv('RATE_LIMIT_CACHE_ENABLED');
    }

    /**
     * PHP 单条解析结果必须与 JS normalizeResult 契约一致：
     * platform/media_type/images/music 字段齐全，协议相对地址补全。
     */
    public function testNormalizeResultMatchesJsContract(): void
    {
        $result = \App\Services\VideoParser::normalizeResult('xiaohongshu', [
            'title' => '图文笔记',
            'author' => '作者',
            'avatar' => '//sns-avatar.xhscdn.com/a.jpg',
            'cover' => '//sns-img.xhscdn.com/cover.jpg',
            'url' => '',
            'media_type' => 'image',
            'images' => ['//sns-img.xhscdn.com/1.jpg', '', 'https://sns-img.xhscdn.com/2.jpg'],
        ]);

        $this->assertSame('xiaohongshu', $result['platform']);
        $this->assertSame('image', $result['media_type']);
        $this->assertSame('https://sns-avatar.xhscdn.com/a.jpg', $result['avatar']);
        $this->assertSame('https://sns-img.xhscdn.com/cover.jpg', $result['cover']);
        $this->assertSame([
            'https://sns-img.xhscdn.com/1.jpg',
            'https://sns-img.xhscdn.com/2.jpg',
        ], $result['images']);
        $this->assertSame('https://sns-img.xhscdn.com/1.jpg', $result['url'], '图集缺少 url 时应回落到首图');
        $this->assertIsArray($result['music']);
    }

    public function testNormalizeResultDefaultsForVideoWithoutOptionalFields(): void
    {
        $result = \App\Services\VideoParser::normalizeResult('bilibili', [
            'title' => '视频',
            'url' => 'https://cdn.example/v.mp4',
        ]);

        $this->assertSame('video', $result['media_type']);
        $this->assertSame([], $result['images']);
        $this->assertSame(['author' => '', 'avatar' => ''], $result['music']);
        $this->assertSame(0, $result['like']);
        $this->assertSame('', $result['author']);
    }

    public function testApiResponsesAreNotCached(): void
    {
        $source = (string) file_get_contents($this->root . '/app/Utils/Response.php');
        $this->assertSame(2, substr_count($source, "header('Cache-Control: no-store');"));
    }

    public function testParserFetchFailuresThrowCleanErrors(): void
    {
        foreach (['WeiboParser.php', 'IzuiyouParser.php', 'PipigxParser.php'] as $file) {
            $source = (string) file_get_contents($this->root . '/app/Parsers/' . $file);
            $this->assertStringContainsString("throw new \RuntimeException('视频数据获取失败');", $source);
        }
    }

    public function testParsersUseSafeArrayAccess(): void
    {
        $weibo = (string) file_get_contents($this->root . '/app/Parsers/WeiboParser.php');
        $izuiyou = (string) file_get_contents($this->root . '/app/Parsers/IzuiyouParser.php');
        $pipigx = (string) file_get_contents($this->root . '/app/Parsers/PipigxParser.php');

        $this->assertStringContainsString('is_array($item)', $weibo);
        $this->assertStringContainsString('array_key_first($item[\'videos\'])', $izuiyou);
        $this->assertStringContainsString('is_array($item[\'videos\'] ?? null)', $pipigx);
    }

    public function testDouyinImagePostsAreNotReturnedAsVideoOrAudio(): void
    {
        $method = new \ReflectionMethod(\App\Parsers\DouyinParser::class, 'buildResult');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            'desc' => '图片图集',
            'images' => [
                [
                    'download_url_list' => ['https://cdn.example.com/water-v2/image-1.jpeg'],
                    'url_list' => ['https://cdn.example.com/aweme-images/image-1.jpeg'],
                ],
                ['url_list' => ['https://cdn.example.com/aweme-images/image-2.jpeg']],
            ],
            'video' => [
                'play_addr' => ['url_list' => ['https://cdn.example.com/background.mp3']],
            ],
        ]);

        $this->assertSame('image', $result['media_type']);
        $this->assertSame('https://cdn.example.com/aweme-images/image-1.jpeg', $result['url']);
        $this->assertCount(2, $result['images']);
        $this->assertStringNotContainsString('.mp3', $result['url']);
        $this->assertStringNotContainsString('water-v2', $result['url']);
    }

    public function testDouyinBuildResultExposesMusicPlayUrl(): void
    {
        $method = new \ReflectionMethod(\App\Parsers\DouyinParser::class, 'buildResult');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            'desc' => '带背景音乐的视频',
            'video' => [
                'play_addr' => ['uri' => 'v0300fixture', 'url_list' => ['https://cdn.example.com/video.mp4']],
            ],
            'music' => [
                'author' => '音乐作者',
                'title' => '背景音乐',
                'play_url' => ['url_list' => ['https://cdn.example.com/music.mp3']],
            ],
        ]);

        $this->assertSame('音乐作者', $result['music']['author']);
        $this->assertSame('https://cdn.example.com/music.mp3', $result['music']['url']);
    }

    public function testDouyinProfileLinksExposeSecUserId(): void
    {
        $method = new \ReflectionMethod(\App\Parsers\DouyinParser::class, 'extractSecUserId');
        $method->setAccessible(true);

        $this->assertSame(
            'MS4wLjABAAAAexample',
            $method->invoke(null, 'https://www.douyin.com/user/MS4wLjABAAAAexample')
        );
        $this->assertSame(
            'MS4wLjABAAAAquery',
            $method->invoke(null, 'https://www.douyin.com/?sec_user_id=MS4wLjABAAAAquery')
        );
    }

    public function testDouyinProfilePaginationUsesCursorContract(): void
    {
        $parser = (string) file_get_contents($this->root . '/app/Parsers/DouyinParser.php');
        $service = (string) file_get_contents($this->root . '/app/Services/VideoParser.php');
        $api = (string) file_get_contents($this->root . '/public/api/v1.php');
        $frontend = (string) file_get_contents($this->root . '/public/app.php');
        $readme = (string) file_get_contents($this->root . '/README.md');
        $openapi = (string) file_get_contents($this->root . '/public/openapi.yaml');

        $this->assertStringContainsString('parseProfile(string $url, int $cursor = 0)', $parser);
        $this->assertStringContainsString("string \$cursorKey = 'max_cursor'", $parser);
        $this->assertStringContainsString("\$cursorKey => \$cursor > 0 ? (string) \$cursor : ''", $parser);
        $this->assertStringContainsString("'next_cursor'", $parser);
        $this->assertStringContainsString("'has_more'", $parser);
        $this->assertStringContainsString('parseCollection(string $url, int $cursor = 0)', $parser);
        $this->assertStringContainsString('parseValidatedProfile($url, $cursor)', $api);
        $this->assertStringContainsString('parseValidatedCollection($url, $cursor)', $api);
        $this->assertStringContainsString('parseProfile($url, $cursor)', $service);
        $this->assertStringContainsString('normalizeItems(', $service);
        $this->assertStringContainsString('isExplicitDouyinCollectionUrl', $service);
        $this->assertStringContainsString('json["cursor"]', $api);
        $this->assertStringContainsString('state.profile.pages[nextPage]', $frontend);
        $this->assertStringContainsString('state.profile.selected', $frontend);
        $this->assertStringContainsString('profilePrev.addEventListener', $frontend);
        $this->assertStringContainsString('profileNext.addEventListener', $frontend);
        $this->assertStringContainsString('profileLoadAll', $frontend);
        $this->assertStringContainsString('loadAllPages', $frontend);
        $this->assertStringNotContainsString('max_pages', $readme);
        $this->assertStringNotContainsString('max_pages', $openapi);
        $this->assertStringContainsString('/api/v1/collection', $readme);
        $this->assertStringContainsString('/collection:', $openapi);
    }

    public function testDouyinProfilePaginationReadsContinuationHeaders(): void
    {
        $method = new \ReflectionMethod(\App\Parsers\DouyinParser::class, 'headerValue');
        $method->setAccessible(true);
        $headers = "HTTP/2 200\r\nwhale_cut_token: token-value==\r\ncookie_ttwidinfo_webid: 123456\r\n";

        $this->assertSame('token-value==', $method->invoke(null, $headers, 'whale_cut_token'));
        $this->assertSame('123456', $method->invoke(null, $headers, 'cookie_ttwidinfo_webid'));
        $this->assertNull($method->invoke(null, $headers, 'missing-header'));
    }

    public function testDouyinRequestsIncludeArgusGatewayHeader(): void
    {
        $phpParser = (string) file_get_contents($this->root . '/app/Parsers/DouyinParser.php');
        $jsParser = (string) file_get_contents($this->root . '/packages/parsers/src/parsers/douyin.js');

        $this->assertStringContainsString("'x-tt-argus' => '1'", $phpParser);
        $this->assertStringContainsString("'x-tt-argus': '1'", $jsParser);
        $this->assertStringContainsString("'verifyFp' => \$verifyFp", $phpParser);
        $this->assertStringContainsString("'fp' => \$verifyFp", $phpParser);
        $this->assertStringContainsString("'; s_v_web_id=' . \$verifyFp", $phpParser);
        $this->assertStringContainsString("verifyFp,", $jsParser);
        $this->assertStringContainsString("fp: verifyFp,", $jsParser);
    }

    public function testKuaishouParserSupportsShareTokensAndModernIds(): void
    {
        $source = (string) file_get_contents($this->root . '/app/Parsers/KuaishouParser.php');

        $this->assertStringContainsString('kuaishou\\.com/f/', $source);
        $this->assertStringContainsString('$secondLocation = HttpClient::getLocation($target);', $source);
        $this->assertStringContainsString('[A-Za-z0-9_-]+', $source);
    }

    public function testTiktokStatsV2TakesPrecedenceOverLegacyStats(): void
    {
        $method = new \ReflectionMethod(\App\Parsers\TiktokParser::class, 'webItemToResult');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            'author' => [
                'nickname' => 'TikTok 作者',
                'uniqueId' => 'tiktok-user',
            ],
            'stats' => ['diggCount' => 99],
            'statsV2' => ['diggCount' => 1234],
            'video' => [
                'playAddr' => 'https://cdn.example.com/tiktok.mp4',
                'cover' => 'https://cdn.example.com/tiktok-cover.jpg',
            ],
        ]);

        $this->assertSame(1234, $result['like']);
    }

    public function testStartScriptsAndDockerfileLoadRouter(): void
    {
        $this->assertFileExists($this->root . '/public/router.php');
        $dockerfile = (string) file_get_contents($this->root . '/Dockerfile');
        $this->assertStringContainsString('public/router.php', $dockerfile);
        $this->assertStringContainsString('docker-php-ext-install -j$(nproc) curl dom mbstring zip', $dockerfile);
        $this->assertStringContainsString('public\\router.php', (string) file_get_contents($this->root . '/start.bat'));
        $this->assertStringContainsString('public/router.php', (string) file_get_contents($this->root . '/start.sh'));
    }

    public function testRouterBlocksPathTraversal(): void
    {
        $source = (string) file_get_contents($this->root . '/public/router.php');
        $this->assertStringContainsString('realpath(__DIR__ . $uri)', $source);
        $this->assertStringContainsString('str_starts_with($file, $publicDir . DIRECTORY_SEPARATOR)', $source);
        $this->assertStringContainsString('/(^|\\/)\.\.(\\/|$)/', $source);
    }

    public function testRateLimiterFailClosedIsDefault(): void
    {
        $appConfig = (string) file_get_contents($this->root . '/config/app.php');
        $this->assertStringContainsString("'fail_open' => Config::env('RATE_LIMIT_FAIL_OPEN', 'false') === 'true'", $appConfig);

        $limiter = (string) file_get_contents($this->root . '/app/Services/RateLimiter.php');
        $this->assertStringContainsString("'allowed' => false", $limiter);
    }

    public function testRateLimitCachePrefixRemoved(): void
    {
        $source = (string) file_get_contents($this->root . '/config/cache.php');
        $this->assertStringNotContainsString("'prefix' => 'video_spider_ratelimit_'", $source);
    }

    public function testFinalUiGuardsBatchWorkAndRefreshesExpiredMedia(): void
    {
        $source = (string) file_get_contents($this->root . '/public/app.php');
        $this->assertStringContainsString('BATCH_LIMIT=20', $source);
        $this->assertStringContainsString('batchAbortController.abort()', $source);
        $this->assertStringContainsString("mediaRefreshKey=''", $source);
        $this->assertStringContainsString("status.classList.toggle('is-offline'", $source);
    }

    public function testHealthEndpointReportsRuntimeDependencies(): void
    {
        $source = (string) file_get_contents($this->root . '/public/api/v1.php');
        $this->assertStringContainsString('"curl" => extension_loaded("curl")', $source);
        $this->assertStringContainsString('"openssl" => extension_loaded("openssl")', $source);
        $this->assertStringContainsString('"mbstring" => extension_loaded("mbstring")', $source);
        $this->assertStringContainsString('"dom" => class_exists(\DOMDocument::class)', $source);
        $this->assertStringContainsString('"ready" => $ready', $source);
        $this->assertStringContainsString('$ready ? 200 : 503', $source);
        // 抖音签名 sidecar 未启动时以 warning 暴露，不阻塞整体就绪
        $this->assertStringContainsString('a_bogus 签名服务未启动', $source);
    }

    public function testUpdateEndpointCachesGithubResponses(): void
    {
        $source = (string) file_get_contents($this->root . '/public/api/v1.php');
        $this->assertStringContainsString('UpdateCache::get($cacheKey)', $source);
        $this->assertStringContainsString('UpdateCache::set($cacheKey, $payload)', $source);
        $this->assertTrue(class_exists(\App\Services\UpdateCache::class), 'UpdateCache 服务必须存在');
        $this->assertSame(900, (int) \App\Utils\Config::get('updates.cache_ttl', 0));
    }

    public function testParseEndpointSupportsForceRefresh(): void
    {
        $api = (string) file_get_contents($this->root . '/public/api/v1.php');
        $service = (string) file_get_contents($this->root . '/app/Services/VideoParser.php');
        $frontend = (string) file_get_contents($this->root . '/public/app.php');

        $this->assertStringContainsString('$forceRefresh', $api);
        $this->assertStringContainsString('$parser->parse($url, $forceRefresh)', $api);
        $this->assertStringContainsString('parse(string $url, bool $force = false)', $service);
        $this->assertStringContainsString('if (!$force) {', $service);
        // 播放失败后的自动重取必须带 force，否则会命中同一个 24h 缓存
        $this->assertStringContainsString('requestParse(currentResult.url,null,true)', $frontend);
    }

    public function testOnlineUpdatesUseGitHubReleases(): void
    {
        $files = [
            '.env.example',
            'config/app.php',
            'public/api/v1.php',
            'packages/update-checker/src/index.js',
            'apps/app-shell/package.json',
            'apps/app-shell/electron/main.cjs',
            'apps/app-shell/electron/updateChannel.cjs',
        ];
        $source = '';
        foreach ($files as $file) {
            $source .= (string) file_get_contents($this->root . '/' . $file);
        }

        $this->assertStringNotContainsString('https://gitee.com/yuchen0x1/short-video-watermark-remover/releases', $source);
        $this->assertStringNotContainsString('UPDATE_GITEE', $source);
        $this->assertStringContainsString('Short-Video-Analysis', $source);
    }

    public function testOnlineUpdateFallsBackWhenGithubApiIsRateLimited(): void
    {
        $api = (string) file_get_contents($this->root . '/public/api/v1.php');
        $checker = (string) file_get_contents($this->root . '/packages/update-checker/src/index.js');

        $this->assertStringContainsString('parseGithubReleaseAtom', $api);
        $this->assertStringContainsString('parseGithubExpandedAssets', $api);
        $this->assertStringContainsString('releases.atom', $api);
        $this->assertStringContainsString('fetchGithubReleaseFallback', $checker);
        $this->assertStringContainsString('releases/expanded_assets/', $checker);
    }

    public function testMediaProxyPropagatesUpstreamFailureStatus(): void
    {
        $source = (string) file_get_contents($this->root . '/app/Services/MediaProxy.php');
        $this->assertStringContainsString("'媒体源返回 HTTP ' . \$httpCode", $source);
        $this->assertStringContainsString('$safeStatus = $httpCode >= 400 && $httpCode <= 599 ? $httpCode : 502;', $source);
        $this->assertStringContainsString('$state[\'forward\'] = $status >= 200 && $status < 300 && $status !== 204;', $source);
    }

    public function testMediaProxyEntryBuildsValidatedRequestBeforeStreaming(): void
    {
        $source = (string) file_get_contents($this->root . '/public/index.php');
        $proxy = (string) file_get_contents($this->root . '/app/Services/MediaProxy.php');

        $this->assertStringContainsString('MediaProxyRequest::fromQuery($_GET)', $source);
        $this->assertStringContainsString('MediaProxy::streamRequest($mediaRequest);', $source);
        $this->assertStringNotContainsString('MediaProxy::stream($mediaUrl', $source);
        $this->assertStringContainsString('public static function streamRequest(MediaProxyRequest $request): void', $proxy);
        $this->assertStringContainsString('private static function stream(string $url', $proxy);
    }

    public function testMediaProxyRequestRejectsPrivateHostsAndSanitizesFilename(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MediaProxyRequest::fromQuery(['url' => 'http://127.0.0.1/video.mp4']);
    }

    public function testMediaProxyRequestRejectsNonCanonicalIpv4AndLoopbackIpv6(): void
    {
        foreach (['http://2130706433/video.mp4', 'http://0x7f000001/video.mp4', 'http://017700000001/video.mp4', 'http://[::1]/video.mp4'] as $url) {
            try {
                MediaProxyRequest::fromQuery(['url' => $url]);
                $this->fail('应拒绝媒体地址: ' . $url);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMediaProxyRequestSanitizesDownloadFilename(): void
    {
        $request = MediaProxyRequest::fromQuery([
            'url' => 'https://cdn.example.com/video.mp4',
            'download' => '1',
            'filename' => '../bad/name?.mp4',
        ]);

        $this->assertSame('https://cdn.example.com/video.mp4', $request->url);
        $this->assertTrue($request->download);
        $this->assertSame('_bad_name_.mp4', $request->filename);
    }

    public function testLintUsesRelativePathsWithoutMbstring(): void
    {
        $source = (string) file_get_contents($this->root . '/bin/lint.php');
        $this->assertStringContainsString('chdir($root)', $source);
        $this->assertStringNotContainsString('ltrim(substr', $source);
        $this->assertStringNotContainsString('proc_open', $source);
        $this->assertStringContainsString('ParserFactory', $source);
        $this->assertStringNotContainsString('mb_convert_encoding', $source);
        $this->assertStringNotContainsString("getenv('PHP_BINARY')", $source);
    }

    /**
     * @dataProvider redirectProvider
     */
    public function testRelativeRedirectsResolveAgainstBase(string $base, string $location, string|false $expected): void
    {
        $method = new \ReflectionMethod(HttpClient::class, 'resolveLocation');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invoke(null, $base, $location));
    }

    public static function redirectProvider(): array
    {
        return [
            'absolute' => ['https://example.com/a', 'https://other.example/x', 'https://other.example/x'],
            'protocol-relative' => ['https://example.com/a/b', '//cdn.example.com/video.mp4', 'https://cdn.example.com/video.mp4'],
            'root path' => ['https://example.com/a/b', '/next', 'https://example.com/next'],
            'relative path' => ['https://example.com/a/b/c', 'd?x=1#f', 'https://example.com/a/b/d?x=1#f'],
            'parent path' => ['https://example.com/a/b/c', '../d', 'https://example.com/a/d'],
            'query only' => ['https://example.com/a/video.mp4', '?token=1', 'https://example.com/a/video.mp4?token=1'],
            'fragment only' => ['https://example.com/a/video.mp4', '#part-1', 'https://example.com/a/video.mp4#part-1'],
            'port preserved' => ['https://example.com:8443/a', '/next', 'https://example.com:8443/next'],
            'non-http scheme rejected' => ['https://example.com/a', 'file:///etc/passwd', false],
            'invalid base rejected' => ['not a url', '/next', false],
        ];
    }
}
