<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Reclaim;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\PageReclaimLimit;

#[CoversClass(PageReclaimLimit::class)]
#[Small]
final class PageReclaimLimitTest extends TestCase
{
    public function testAcceptsBothNativeEndpoints(): void
    {
        self::assertSame(1, (new PageReclaimLimit(1))->pages);
        self::assertSame(2147483647, (new PageReclaimLimit())->pages);
        self::assertSame(2147483647, (new PageReclaimLimit(2147483647))->pages);
    }
}
