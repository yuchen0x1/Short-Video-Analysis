<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Parsers\TiktokParser;
use App\Services\VideoParser;
use PHPUnit\Framework\TestCase;

/**
 * TikTok 解析器纯函数与平台注册的行为测试。
 * 全部离线运行，不发起网络请求。
 */
final class TiktokBehaviorTest extends TestCase
{
    public function testExtractVideoIdFromDirectUrls(): void
    {
        $this->assertSame('7100000000000000001', TiktokParser::extractVideoId('https://www.tiktok.com/@user/video/7100000000000000001'));
        $this->assertSame('7200000000000000002', TiktokParser::extractVideoId('https://www.tiktok.com/@user/photo/7200000000000000002?is_from_webapp=1'));
        $this->assertSame('7300000000000000003', TiktokParser::extractVideoId('https://m.tiktok.com/v/7300000000000000003.html'));
    }

    public function testExtractVideoIdRejectsNonVideoUrls(): void
    {
        $this->assertNull(TiktokParser::extractVideoId('https://www.tiktok.com/@user'));
        $this->assertNull(TiktokParser::extractVideoId('https://www.tiktok.com/foryou'));
        $this->assertNull(TiktokParser::extractVideoId('https://www.douyin.com/video/7123456789012345678'));
    }

    public function testShortLinkDetection(): void
    {
        $this->assertTrue(TiktokParser::isShortLink('https://vm.tiktok.com/abc/'));
        $this->assertTrue(TiktokParser::isShortLink('https://vt.tiktok.com/xyz/'));
        $this->assertFalse(TiktokParser::isShortLink('https://www.tiktok.com/@user/video/7100000000000000001'));
    }

    public function testPlatformRegistryContainsTwelvePlatforms(): void
    {
        $parser = new VideoParser();
        $platforms = $parser->getSupportedPlatforms();
        $this->assertCount(12, $platforms);
        $this->assertContains('tiktok', $platforms);
    }

    public function testTiktokIsWiredAcrossLayers(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root . '/app/Services/VideoParser.php');
        $jsIndex = (string) file_get_contents($root . '/packages/parsers/src/index.js');
        $jsDomains = (string) file_get_contents($root . '/packages/parsers/src/domains.js');
        $readme = (string) file_get_contents($root . '/README.md');

        $this->assertStringContainsString('TiktokParser::class', $service);
        $this->assertStringContainsString("parsers/tiktok.js", $jsIndex);
        $this->assertStringContainsString("tiktok: { domains: ['tiktok.com'] }", $jsDomains);
        $this->assertStringContainsString('TikTok', $readme);
    }
}
