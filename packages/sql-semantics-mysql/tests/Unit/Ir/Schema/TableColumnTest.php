<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;

#[CoversClass(TableColumn::class)]
#[Small]
final class TableColumnTest extends TestCase
{
    public function testToStringPreservesSignedDefaultAndNullability(): void
    {
        $column = new TableColumn(new Identifier('n'), IntegerStorage::Small, false, new IntegerLiteral('-7'));

        self::assertSame('`n` SMALLINT NOT NULL DEFAULT -7', $column->toString());
    }
}
