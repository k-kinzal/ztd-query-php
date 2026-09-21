<?php

declare(strict_types=1);

namespace Tests\Unit\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Resource\SourceFetcher;

#[CoversClass(SourceFetcher::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class SourceFetcherTest extends TestCase
{
    public function testFetchReadsTheCachedCopy(): void
    {
        $directory = sys_get_temp_dir() . '/sql-parser-cache-' . uniqid();
        $fetcher = new SourceFetcher($directory);
        $url = 'https://example.invalid/grammar/parse.y';
        $path = $fetcher->cachePath($url);
        self::assertIsString($path);
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'cmd ::= SELECT.');

        self::assertSame('cmd ::= SELECT.', $fetcher->fetch($url));
        unlink($path);
        rmdir($directory);
    }

    public function testFetchRejectsAnUnreachableSource(): void
    {
        $this->expectException(RuntimeException::class);

        (new SourceFetcher())->fetch('http://127.0.0.1:9/unreachable');
    }

    public function testDownload(): void
    {
        self::assertNull((new SourceFetcher())->download('http://127.0.0.1:9/unreachable'));
    }

    public function testCachePath(): void
    {
        $fetcher = new SourceFetcher('/cache');

        self::assertSame('/cache/raw.githubusercontent.com_x_y_parse.y', $fetcher->cachePath('https://raw.githubusercontent.com/x/y/parse.y'));
        self::assertNull((new SourceFetcher())->cachePath('https://example.invalid/a'));
    }
}
