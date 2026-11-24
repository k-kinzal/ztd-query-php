<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCachePages;

#[CoversClass(DefaultCachePages::class)]
#[Small]
final class DefaultCachePagesTest extends TestCase
{
    public function testKeepsResetAndUpperPageCounts(): void
    {
        self::assertSame(0, (new DefaultCachePages(0))->pages);
        self::assertSame(2147483647, (new DefaultCachePages(2147483647))->pages);
    }
}
