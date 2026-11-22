<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturnedColumn;

#[CoversClass(ReturnedColumn::class)]
#[Small]
final class ReturnedColumnTest extends TestCase
{
    public function testReturningFieldCarriesItsSlotAndName(): void
    {
        $slot = new ColumnReference(2);
        $name = new Identifier('before');
        $column = new ReturnedColumn($slot, $name);

        self::assertSame($slot, $column->reference);
        self::assertSame($name, $column->name);
    }
}
