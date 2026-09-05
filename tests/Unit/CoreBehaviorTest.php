<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MediaProxy;
use App\Services\ParseCache;
use App\Services\RateLimiter;
use App\Services\VideoParser;
use App\Utils\Config;
use PHPUnit\Framework\TestCase;
use App\Utils\SharedUrl;

/**
 * 核心纯函数的行为测试：缓存键归一化、平台识别、媒体代理 SSRF 策略、限流开关。
 * 全部离线运行，不发起网络请求。
 */
final class CoreBehaviorTest extends TestCase
{
    public function testSharedUrlExtractsAndTrimsShareText(): void
    {
        $this->assertSame(
            'https://www.douyin.com/video/1234567890',
            SharedUrl::extract('复制打开抖音，看看这个视频 https://www.douyin.com/video/1234567890。')
        );
        $this->assertSame('', SharedUrl::extract('没有链接的文本'));
    }
    protected function tearDown(): void
    {
        Config::clearCache();
    }

    // ---- ParseCache::makeKey ----

    public function testMakeKeyIgnoresUtmParameters(): void
    {
        $withUtm = ParseCache::makeKey('https://www.douyin.com/video/123?spm=x&utm_source=share');
        $withoutUtm = ParseCache::makeKey('https://www.douyin.com/video/123?spm=x');

        $this->assertSame($withoutUtm, $withUtm);
    }

    public function testMakeKeyIsOrderAndCaseInsensitive(): void
    {
        $this->assertSame(
            ParseCache::makeKey('https://WWW.Douyin.com/video/1?b=2&a=1'),
            ParseCache::makeKey('https://www.douyin.com/video/1?a=1&b=2')
        );
    }

    public function testMakeKeySeparatesDifferentPaths(): void
    {
        $this->assertNotSame(
            ParseCache::makeKey('https://www.douyin.com/video/1'),
            ParseCache::makeKey('https://www.douyin.com/video/2')
        );
    }

    public function testMakeKeyFallsBackToRawHashForGarbageInput(): void
    {
        $this->assertSame(md5('not a url'), ParseCache::makeKey('not a url'));
    }

    // ---- VideoParser 平台识别 ----

    /**
     * @dataProvider platformProvider
     */
    public function testGetPlatformDetectsSupportedDomains(string $url, ?string $expected): void
    {
        $method = new \ReflectionMethod(VideoParser::class, 'getPlatform');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(new VideoParser(), $url));
    }

    public static function platformProvider(): array
    {
        return [
            'douyin www' => ['https://www.douyin.com/video/123', 'douyin'],
            'douyin subdomain' => ['https://v.douyin.com/abc/', 'douyin'],
            'bilibili short link' => ['https://b23.tv/abc', 'bilibili'],
            'kuaishou cn' => ['https://www.kuaishou.cn/x', 'kuaishou'],
            'weishi exact host' => ['https://weishi.qq.com/x', 'weishi'],
            'lookalike domain rejected' => ['https://evildouyin.com/video/1', null],
            'unknown platform' => ['https://example.com/a', null],
            'no host' => ['not-a-url', null],
        ];
    }

    // ---- MediaProxy SSRF 策略（显式传入解析结果，避免 DNS 依赖） ----

    private static function isAllowed(string $url, array $ips): bool
    {
        $method = new \ReflectionMethod(MediaProxy::class, 'isAllowedUrl');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $url, $ips);
    }

    public function testMediaProxyRejectsLoopbackAndPrivateTargets(): void
    {
        $this->assertFalse(self::isAllowed('http://localhost/a.mp4', ['127.0.0.1']));
        $this->assertFalse(self::isAllowed('http://127.0.0.1/a.mp4', ['127.0.0.1']));
        $this->assertFalse(self::isAllowed('http://10.0.0.1/a.mp4', ['10.0.0.1']));
        $this->assertFalse(self::isAllowed('http://192.168.1.5/a.mp4', ['192.168.1.5']));
        $this->assertFalse(self::isAllowed('http://169.254.1.1/a.mp4', ['169.254.1.1']), '链路本地地址也必须拒绝');
    }

    public function testMediaProxyRejectsPublicIpsOutsideDomainWhitelist(): void
    {
        // 默认启用域名白名单：非白名单公网地址 fail-closed
        $this->assertFalse(self::isAllowed('https://1.1.1.1/a.mp4', ['1.1.1.1']));
    }

    public function testMediaProxyAcceptsWhitelistedDomainsWithPublicIps(): void
    {
        $this->assertTrue(self::isAllowed('https://www.douyinvod.com/v.mp4', ['93.184.216.34']));
        $this->assertTrue(self::isAllowed('https://cdn.douyinvod.com/v.mp4', ['93.184.216.34']), '白名单域名的子域名应放行');
    }

    public function testMediaProxyFailsClosedWhenResolutionIsEmpty(): void
    {
        $this->assertFalse(self::isAllowed('https://www.douyinvod.com/v.mp4', []));
    }

    public function testMatchesDomainMatchesExactAndSubdomainsOnly(): void
    {
        $method = new \ReflectionMethod(MediaProxy::class, 'matchesDomain');
        $method->setAccessible(true);
        $domains = ['douyinvod.com'];

        $this->assertTrue((bool) $method->invoke(null, 'douyinvod.com', $domains));
        $this->assertTrue((bool) $method->invoke(null, 'a.b.douyinvod.com', $domains));
        $this->assertFalse((bool) $method->invoke(null, 'evildouyinvod.com', $domains), '后缀相似但非子域名的必须拒绝');
        $this->assertFalse((bool) $method->invoke(null, '', $domains));
    }

    // ---- RateLimiter 关闭时的行为 ----

    public function testDisabledRateLimiterAllowsRepeatedRequests(): void
    {
        putenv('RATE_LIMIT_CACHE_ENABLED=false');
        Config::clearCache();
        try {
            for ($i = 0; $i < 50; $i++) {
                $result = RateLimiter::check('203.0.113.' . ($i % 5 + 1));
                $this->assertTrue($result['allowed'], "第 {$i} 次请求不应被限流");
            }
        } finally {
            putenv('RATE_LIMIT_CACHE_ENABLED');
        }
    }

    public function testEnabledRateLimiterBlocksAfterLimit(): void
    {
        putenv('RATE_LIMIT_CACHE_ENABLED=true');
        putenv('RATE_LIMIT_MAX_REQUESTS=3');
        putenv('RATE_LIMIT_TIME_WINDOW=60');
        Config::clearCache();
        self::resetLimiterFactory();
        // 文件型限流状态会跨测试运行残留，先清空保证隔离
        foreach (glob(__DIR__ . '/../../storage/cache/rate_limit/*') ?: [] as $file) {
            @unlink($file);
        }
        try {
            $ip = '198.51.100.' . random_int(10, 99);

            $results = [];
            for ($i = 0; $i < 4; $i++) {
                $results[] = RateLimiter::check($ip)['allowed'];
            }
            $this->assertSame([true, true, true, false], $results, '超过限额后的第 4 次请求必须被拒绝');
        } finally {
            putenv('RATE_LIMIT_MAX_REQUESTS');
            putenv('RATE_LIMIT_TIME_WINDOW');
            self::resetLimiterFactory();
        }
    }

    private static function resetLimiterFactory(): void
    {
        $factory = new \ReflectionProperty(RateLimiter::class, 'factory');
        $factory->setAccessible(true);
        $factory->setValue(null, null);
    }
}
