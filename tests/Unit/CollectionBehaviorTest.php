<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Parsers\DouyinParser;
use App\Services\VideoParser;
use PHPUnit\Framework\TestCase;

/**
 * 抖音合集（collection）相关纯函数与路由校验的行为测试。
 * 全部离线运行，不发起网络请求。
 */
final class CollectionBehaviorTest extends TestCase
{
    public function testExtractMixIdFromPath(): void
    {
        $method = new \ReflectionMethod(DouyinParser::class, 'extractMixId');
        $this->assertSame(
            '7190000000000000000',
            $method->invoke(null, 'https://www.douyin.com/collection/7190000000000000000')
        );
    }

    public function testExtractMixIdFromQuery(): void
    {
        $method = new \ReflectionMethod(DouyinParser::class, 'extractMixId');
        $this->assertSame(
            '123456',
            $method->invoke(null, 'https://www.douyin.com/note/abc?mix_id=123456')
        );
    }

    public function testExtractMixIdRejectsNonCollectionUrls(): void
    {
        $method = new \ReflectionMethod(DouyinParser::class, 'extractMixId');
        $this->assertNull($method->invoke(null, 'https://www.douyin.com/video/7123456789012345678'));
        $this->assertNull($method->invoke(null, 'https://www.douyin.com/user/MS4wLjABAAAAxxx'));
        $this->assertNull($method->invoke(null, 'https://www.douyin.com/collection/abc'));
    }

    public function testExtractVideoIdRejectsCollectionUrls(): void
    {
        $method = new \ReflectionMethod(DouyinParser::class, 'extractVideoId');
        // 合集链接的 mix_id 不能被 15-25 位数字兜底规则当作视频 ID
        $this->assertNull($method->invoke(null, 'https://www.douyin.com/collection/7190000000000000000'));
        $this->assertNull($method->invoke(null, 'https://www.douyin.com/note/abc?mix_id=7190000000000000000'));
    }

    public function testIsCollectionUrl(): void
    {
        $this->assertTrue(DouyinParser::isCollectionUrl('https://www.douyin.com/collection/7190000000000000000'));
        $this->assertTrue(DouyinParser::isCollectionUrl('https://www.douyin.com/note/abc?mix_id=123'));
        $this->assertFalse(DouyinParser::isCollectionUrl('https://www.douyin.com/video/7123456789012345678'));
        $this->assertFalse(DouyinParser::isCollectionUrl('https://www.douyin.com/user/MS4wLjABAAAAxxx'));
    }

    public function testParseCollectionRejectsNonCollectionUrlWithoutNetwork(): void
    {
        $parser = new VideoParser();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('合集');
        $parser->parseCollection('https://www.douyin.com/user/MS4wLjABAAAAxxx');
    }

    public function testParseCollectionRejectsNonDouyinHostWithoutNetwork(): void
    {
        $parser = new VideoParser();
        $this->expectException(\InvalidArgumentException::class);
        $parser->parseCollection('https://www.bilibili.com/collection/123');
    }

    public function testParseValidatedCollectionRejectsShortLinks(): void
    {
        $parser = new VideoParser();
        $this->expectException(\InvalidArgumentException::class);
        $parser->parseValidatedCollection('https://v.douyin.com/xxxx/');
    }
}
