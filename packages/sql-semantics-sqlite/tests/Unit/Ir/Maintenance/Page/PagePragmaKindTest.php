<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragmaKind;

#[CoversClass(PagePragmaKind::class)]
#[Small]
final class PagePragmaKindTest extends TestCase
{
    public function testCasesExposeTheNativeOperationNames(): void
    {
        self::assertSame(['page_size', 'page_count', 'max_page_count'], array_column(PagePragmaKind::cases(), 'value'));
    }
}
