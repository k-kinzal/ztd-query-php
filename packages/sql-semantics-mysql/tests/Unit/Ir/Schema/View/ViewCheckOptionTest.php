<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCheckOption;

#[CoversClass(ViewCheckOption::class)]
#[Small]
final class ViewCheckOptionTest extends TestCase
{
    public function testCasesExposeTheNativeCatalogModes(): void
    {
        self::assertSame(['NONE', 'LOCAL', 'CASCADED'], array_column(ViewCheckOption::cases(), 'value'));
    }
}
