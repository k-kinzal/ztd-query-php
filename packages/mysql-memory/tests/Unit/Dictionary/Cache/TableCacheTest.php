<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary\Cache;

use MySqlMemory\Dictionary\Cache\TableCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableCache::class)]
#[Small]
final class TableCacheTest extends TestCase
{
    public function testOpenKeepsDistinctQualifiedNamesAndDeduplicatesRepeatedUses(): void
    {
        $cache = new TableCache();
        $cache->open('d', 'a.b');
        $cache->open('d.a', 'b');
        $cache->open('d', 'a.b');

        self::assertSame([['d', 'a.b'], ['d.a', 'b']], $cache->names());
    }

    public function testCloseCanEvictOneTableADatabaseOrEveryHandle(): void
    {
        $cache = new TableCache();
        $cache->open('d', 'a');
        $cache->open('d', 'b');
        $cache->open('other', 'a');
        $cache->close('d', 'a');
        self::assertSame([['d', 'b'], ['other', 'a']], $cache->names());
        $cache->close('d');
        self::assertSame([['other', 'a']], $cache->names());
        $cache->close();
        self::assertSame([], $cache->names());
    }

    public function testNamesStartsEmptyAndPreservesIdentifierSpelling(): void
    {
        $cache = new TableCache();
        self::assertSame([], $cache->names());
        $cache->open('Database', 'Table');
        $cache->open('database', 'table');

        self::assertSame([['Database', 'Table'], ['database', 'table']], $cache->names());
    }
}
