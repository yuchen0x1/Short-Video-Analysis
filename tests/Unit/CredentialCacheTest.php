<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ParseCache;
use App\Services\VideoParser;
use App\Utils\CookieManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CredentialCacheTest extends TestCase
{
    /** @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWeiboNeverUsesOldOrNewSharedCache(): void
    {
        $property = new \ReflectionProperty(ParseCache::class, 'cache');
        $property->setAccessible(true);
        $property->setValue(null, new ArrayAdapter());
        $url = 'https://weibo.com/tv/show?fid=1034:123';
        ParseCache::set(ParseCache::makeKey($url), ['title' => 'legacy private result']);
        ParseCache::set('public_v2_' . ParseCache::makeKey($url), ['title' => 'another account']);
        $mock = \Mockery::mock('alias:App\\Parsers\\WeiboParser');
        $mock->shouldReceive('parse')->twice()->with($url)->andReturn(
            ['title' => 'account A', 'url' => 'https://cdn.example/a.mp4'],
            ['title' => 'account B', 'url' => 'https://cdn.example/b.mp4']
        );
        CookieManager::set('weibo', 'fixture-a');
        $this->assertSame('account A', (new VideoParser())->parse($url)['title']);
        CookieManager::set('weibo', 'fixture-b');
        $this->assertSame('account B', (new VideoParser())->parse($url)['title']);
        $this->assertSame('another account', ParseCache::get('public_v2_' . ParseCache::makeKey($url))['title']);
        \Mockery::close();
    }

    /** @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPublicCacheAndForceRefreshRemainFunctional(): void
    {
        $property = new \ReflectionProperty(ParseCache::class, 'cache');
        $property->setAccessible(true);
        $property->setValue(null, new ArrayAdapter());
        $url = 'https://www.kuaishou.com/short-video/123';
        $mock = \Mockery::mock('alias:App\\Parsers\\KuaishouParser');
        $mock->shouldReceive('parse')->twice()->with($url)->andReturn(
            ['title' => 'first', 'url' => 'https://cdn.example/a.mp4'],
            ['title' => 'fresh', 'url' => 'https://cdn.example/b.mp4']
        );
        $parser = new VideoParser();
        $this->assertSame('first', $parser->parse($url)['title']);
        $this->assertSame('first', $parser->parse($url)['title']);
        $this->assertSame('fresh', $parser->parse($url, true)['title']);
        $this->assertSame('fresh', $parser->parse($url)['title']);
        \Mockery::close();
    }
}
