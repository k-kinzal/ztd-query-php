<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;

#[CoversClass(ReturnedColumn::class)]
#[Small]
final class ReturnedColumnTest extends TestCase
{
    public function testKeepsTheTargetSlotAndTheIndependentResultName(): void
    {
        $reference = new ColumnReference(1);
        $name = new Identifier('deleted');
        $field = new ReturnedColumn($reference, $name);

        self::assertSame($reference, $field->reference);
        self::assertSame($name, $field->name);
    }
}
