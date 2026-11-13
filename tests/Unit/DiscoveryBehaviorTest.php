<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Discovery;
use App\Services\MediaProxy;
use PHPUnit\Framework\TestCase;

/**
 * 发现服务（热榜/搜索/评论）与 m3u8 播放列表改写的行为测试。
 * 全部离线运行，不发起网络请求。
 */
final class DiscoveryBehaviorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testHotListRejectsUnknownPlatformWithoutNetwork(): void
    {
        $discovery = new Discovery();
        $this->expectException(\InvalidArgumentException::class);
        $discovery->hotList('kuaishou');
    }

    public function testSearchRejectsEmptyKeywordWithoutNetwork(): void
    {
        $discovery = new Discovery();
        $this->expectException(\InvalidArgumentException::class);
        $discovery->search('bilibili', '   ');
    }

    public function testDouyinSearchAcceptsBothPayloadShapesAndAnEmptyFinalPage(): void
    {
        $method = new \ReflectionMethod(Discovery::class, 'douyinSearchPayload');
        $method->setAccessible(true);
        $service = new Discovery();
        $flat = ['status_code' => 0, 'data' => [['aweme_id' => '123']], 'has_more' => 1];
        $nested = ['data' => $flat];
        $this->assertSame($flat, $method->invoke($service, $flat));
        $this->assertSame($flat, $method->invoke($service, $nested));
        $empty = ['data' => [], 'has_more' => 0];
        $this->assertSame($empty, $method->invoke($service, $empty));
        $this->assertNull($method->invoke($service, ['status_code' => 1, 'data' => []]));
        $this->assertNull($method->invoke($service, ['data' => ['status_code' => 1, 'data' => []]]));
        $this->assertNull($method->invoke($service, ['data' => ['unexpected' => true]]));
        $this->assertNull($method->invoke($service, null));
    }

    public function testSearchRejectsUnsupportedPlatformWithoutNetwork(): void
    {
        $discovery = new Discovery();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持的搜索平台');
        // 快手没有搜索实现；抖音自 v1.1.6 起已支持搜索，不能再作为"不支持"的样例
        $discovery->search('kuaishou', '关键词');
    }

    public function testCommentsRejectsNonBilibiliUrlWithoutNetwork(): void
    {
        $discovery = new Discovery();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('B 站');
        $discovery->comments('https://www.douyin.com/video/7123456789012345678');
    }

    public function testRewritePlaylistProxiesRelativeAndAbsoluteUris(): void
    {
        $playlist = "#EXTM3U\n"
            . "#EXT-X-STREAM-INF:BANDWIDTH=1280000\n"
            . "360p/index.m3u8\n"
            . "#EXT-X-KEY:METHOD=AES-128,URI=\"key.key\",IV=0x1\n"
            . "seg-1.ts\n"
            . "https://other.cdn.example.com/abs.ts\n";

        $base = 'https://cdn.example.com/live/playlist.m3u8';
        $out = MediaProxy::rewritePlaylist($playlist, $base);

        $this->assertStringContainsString('#EXTM3U', $out);
        $this->assertStringContainsString(
            '/index.php?action=media&url=' . rawurlencode('https://cdn.example.com/live/360p/index.m3u8'),
            $out
        );
        $this->assertStringContainsString(
            'URI="/index.php?action=media&url=' . rawurlencode('https://cdn.example.com/live/key.key') . '"',
            $out
        );
        $this->assertStringContainsString(
            '/index.php?action=media&url=' . rawurlencode('https://cdn.example.com/live/seg-1.ts'),
            $out
        );
        $this->assertStringContainsString(
            '/index.php?action=media&url=' . rawurlencode('https://other.cdn.example.com/abs.ts'),
            $out
        );
        // IV 等非 URI 属性保持原样
        $this->assertStringContainsString('IV=0x1', $out);
    }

    public function testRewritePlaylistKeepsNonUriAttributes(): void
    {
        $playlist = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:6\n";
        $out = MediaProxy::rewritePlaylist($playlist, 'https://cdn.example.com/a.m3u8');
        $this->assertSame($playlist, $out);
    }

    public function testDiscoveryContractIsWired(): void
    {
        $api = (string) file_get_contents($this->root . '/public/api/v1.php');
        $proxy = (string) file_get_contents($this->root . '/app/Services/MediaProxy.php');
        $frontend = (string) file_get_contents($this->root . '/public/app.php');
        $readme = (string) file_get_contents($this->root . '/README.md');
        $mediaDomains = (string) file_get_contents($this->root . '/config/media_domains.php');
        $electron = (string) file_get_contents($this->root . '/apps/app-shell/electron/main.cjs');

        $this->assertStringContainsString('/api/v1/hot', $api);
        $this->assertStringContainsString('/api/v1/search', $api);
        $this->assertStringContainsString('/api/v1/comments', $api);
        $this->assertStringContainsString('new Discovery()', $api);
        $this->assertStringContainsString('rewritePlaylist', $proxy);
        $this->assertStringContainsString('discover-results', $frontend);
        $this->assertStringContainsString('comments-results', $frontend);
        $this->assertStringContainsString('exportCsv', $frontend);
        $this->assertStringContainsString('validMediaUrl(item.cover)?mediaUrl(item.cover)', $frontend);
        $this->assertStringContainsString('batch-thumb-placeholder', $frontend);
        $this->assertStringContainsString('discover-thumb-placeholder', (string) file_get_contents($this->root . '/apps/app-shell/src/main.js'));
        $this->assertStringContainsString('mediaUrl(item.cover)', (string) file_get_contents($this->root . '/apps/app-shell/src/main.js'));
        $this->assertStringContainsString('douyinpic.com', $mediaDomains);
        $this->assertStringContainsString('hdslb.com', $mediaDomains);
        $this->assertStringContainsString('media_domains.php', (string) file_get_contents($this->root . '/config/app.php'));
        $this->assertStringContainsString("'hdslb.com'", $electron);
        $this->assertStringContainsString('发现', $readme);
    }
}
