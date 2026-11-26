<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PageLimit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragmaLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\RequestedPageSize;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(PagePragmaLowering::class)]
#[Small]
final class PagePragmaLoweringTest extends TestCase
{
    public function testLowerCapsLimitsAndKeepsZeroFallbackAsAWrite(): void
    {
        $semantics = new Semantics();
        $cap = $semantics->analyze('PRAGMA max_page_count(4294967295)');
        $pending = $semantics->analyze('PRAGMA temp.page_size(DEFAULT)');
        $count = $semantics->analyze('PRAGMA page_count(17)');

        self::assertInstanceOf(PagePragma::class, $cap);
        self::assertInstanceOf(PageLimit::class, $cap->value);
        self::assertSame('4294967294', $cap->value->decimal);
        self::assertInstanceOf(PagePragma::class, $pending);
        self::assertInstanceOf(RequestedPageSize::class, $pending->value);
        self::assertSame(0, $pending->value->bytes);
        self::assertSame('PRAGMA "main".page_count', $count->toString());
    }
}
