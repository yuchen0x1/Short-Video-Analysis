<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Parsers\Live\BilibiliLiveParser;
use App\Parsers\Live\DouyuLiveParser;
use App\Parsers\Live\DouyinLiveParser;
use App\Parsers\Live\HuyaLiveParser;
use App\Services\LiveParser;
use App\Services\VideoParser;
use PHPUnit\Framework\TestCase;

/**
 * 直播解析相关纯函数、路由与契约接线的测试。
 * 全部离线运行，不发起网络请求。
 */
final class LiveBehaviorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testDouyinLiveUrlDetection(): void
    {
        $this->assertTrue(DouyinLiveParser::isDouyinLiveUrl('https://live.douyin.com/123456789'));
        $this->assertFalse(DouyinLiveParser::isDouyinLiveUrl('https://www.douyin.com/video/7123456789012345678'));
        $this->assertFalse(DouyinLiveParser::isDouyinLiveUrl('https://www.bilibili.com/video/BV1fixture'));
    }

    public function testDouyinLiveExtractRoomId(): void
    {
        $this->assertSame('123456789', DouyinLiveParser::extractRoomId('https://live.douyin.com/123456789'));
        $this->assertSame(
            '712345678901',
            DouyinLiveParser::extractRoomId('https://webcast.amemv.com/douyin/webcast/room/reflow/712345678901')
        );
        $this->assertNull(DouyinLiveParser::extractRoomId('https://live.douyin.com/'));
    }

    public function testBilibiliLiveExtractRoomId(): void
    {
        $this->assertSame('6', BilibiliLiveParser::extractRoomId('https://live.bilibili.com/6'));
        $this->assertSame('123', BilibiliLiveParser::extractRoomId('https://live.bilibili.com/123?spm_id_from=x'));
        $this->assertNull(BilibiliLiveParser::extractRoomId('https://live.bilibili.com/p/ed/index'));
    }

    public function testLiveParserRoutesSupportedHosts(): void
    {
        $parser = new LiveParser();
        $this->assertTrue($parser->isLiveUrl('https://live.douyin.com/123456789'));
        $this->assertTrue($parser->isLiveUrl('https://live.bilibili.com/6'));
        $this->assertTrue($parser->isLiveUrl('https://www.huya.com/11342412'));
        $this->assertTrue($parser->isLiveUrl('https://huya.com/11342412'));
        $this->assertTrue($parser->isLiveUrl('https://www.douyu.com/9999'));
        $this->assertFalse($parser->isLiveUrl('https://www.douyin.com/video/7123456789012345678'));
        // 路由按域名判断；裸域名同样命中直播平台，由解析器报出更具体的错误
        $this->assertTrue($parser->isLiveUrl('https://live.bilibili.com'));
        $this->assertSame(['douyin', 'bilibili', 'huya', 'douyu'], $parser->getSupportedPlatforms());
    }

    public function testHuyaLiveUrlAndRoomId(): void
    {
        $this->assertTrue(HuyaLiveParser::isHuyaLiveUrl('https://www.huya.com/11342412'));
        $this->assertTrue(HuyaLiveParser::isHuyaLiveUrl('https://m.huya.com/11342412'));
        $this->assertFalse(HuyaLiveParser::isHuyaLiveUrl('https://live.bilibili.com/6'));
        $this->assertSame('11342412', HuyaLiveParser::extractRoomId('https://www.huya.com/11342412'));
        $this->assertSame('6', HuyaLiveParser::extractRoomId('https://m.huya.com/6?s=Y'));
        $this->assertNull(HuyaLiveParser::extractRoomId('https://www.huya.com/'));
    }

    public function testDouyuLiveUrlAndRoomId(): void
    {
        $this->assertTrue(DouyuLiveParser::isDouyuLiveUrl('https://www.douyu.com/9999'));
        $this->assertTrue(DouyuLiveParser::isDouyuLiveUrl('https://douyu.com/9999'));
        $this->assertFalse(DouyuLiveParser::isDouyuLiveUrl('https://www.douyin.com/video/7123456789012345678'));
        $this->assertSame('9999', DouyuLiveParser::extractRoomId('https://www.douyu.com/9999'));
        $this->assertSame('6', DouyuLiveParser::extractRoomId('https://www.douyu.com/topic/xyz?rid=6'));
        $this->assertNull(DouyuLiveParser::extractRoomId('https://www.douyu.com/dir/LOL'));
    }

    public function testLiveParserRejectsNonLiveUrlWithoutNetwork(): void
    {
        $parser = new LiveParser();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('直播平台');
        $parser->parse('https://www.douyin.com/video/7123456789012345678');
    }

    public function testVideoParserRejectsLiveUrlWithLiveHint(): void
    {
        $parser = new VideoParser();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('直播');
        $parser->parse('https://live.bilibili.com/6');
    }

    public function testLiveContractIsWired(): void
    {
        $api = (string) file_get_contents($this->root . '/public/api/v1.php');
        $service = (string) file_get_contents($this->root . '/app/Services/LiveParser.php');
        $openapi = (string) file_get_contents($this->root . '/public/openapi.yaml');
        $readme = (string) file_get_contents($this->root . '/README.md');

        $this->assertStringContainsString('/api/v1/live', $api);
        $this->assertStringContainsString('new LiveParser()', $api);
        $this->assertStringContainsString('DouyinLiveParser::class', $service);
        $this->assertStringContainsString('BilibiliLiveParser::class', $service);
        $this->assertStringContainsString('HuyaLiveParser::class', $service);
        $this->assertStringContainsString('DouyuLiveParser::class', $service);
        $this->assertStringContainsString('/live:', $openapi);
        $this->assertStringContainsString('LiveResult', $openapi);
        $this->assertStringContainsString('/api/v1/live', $readme);
    }
}
