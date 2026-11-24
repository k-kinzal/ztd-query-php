<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PageLimitArgument;

#[CoversClass(PageLimitArgument::class)]
#[Small]
final class PageLimitArgumentTest extends TestCase
{
    public function testReadUsesTheNativeSpanAndOverflowBoundaries(): void
    {
        $argument = new PageLimitArgument();

        self::assertSame('4294967294', $argument->read('9223372036854775807')?->decimal);
        self::assertNull($argument->read('9223372036854775808'));
        self::assertSame('32', $argument->read("32\rtail")?->decimal);
        self::assertNull($argument->read('32tail'));
        self::assertSame('512', $argument->read('0x0200')?->decimal);
        self::assertNull($argument->read('0x0200 '));
    }
}
