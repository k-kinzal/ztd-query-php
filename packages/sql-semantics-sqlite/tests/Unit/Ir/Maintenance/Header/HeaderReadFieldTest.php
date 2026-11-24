<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderReadField;

#[CoversClass(HeaderReadField::class)]
#[Small]
final class HeaderReadFieldTest extends TestCase
{
    public function testCasesExposeOnlyTheReadFields(): void
    {
        self::assertSame(['freelist_count', 'data_version'], array_column(HeaderReadField::cases(), 'value'));
    }
}
