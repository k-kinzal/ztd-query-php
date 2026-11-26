<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewColumn;

#[CoversClass(ViewColumn::class)]
#[Small]
final class ViewColumnTest extends TestCase
{
    public function testHoldsTheMaximumWidthCatalogName(): void
    {
        $name = new Identifier(str_repeat('v', 64));
        $column = new ViewColumn($name);

        self::assertSame($name, $column->name);
    }
}
