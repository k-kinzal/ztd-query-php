<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PageLimit;

#[CoversClass(PageLimit::class)]
#[Small]
final class PageLimitTest extends TestCase
{
    public function testDecimalExposesTheNativeUnsignedRange(): void
    {
        self::assertSame('1', (new PageLimit('1'))->decimal);
        self::assertSame('4294967294', (new PageLimit('4294967294'))->decimal);
    }
}
