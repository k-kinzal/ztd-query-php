<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturningLayout;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(ReturningLayout::class)]
#[Small]
final class ReturningLayoutTest extends TestCase
{
    public function testCheckRejectsASlotOutsideTheTarget(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('id')]);
        $field = new ReturnedColumn(new ColumnReference(1), new Identifier('other'));
        $this->expectExceptionMessage('returned slot must belong to its target');

        (new ReturningLayout())->check($target, [$field]);
    }

    public function testCheckRequiresTheNativeKindAtTheReturnedOrdinal(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('label')], false, [ColumnKind::Text]);
        $name = new Identifier('returned');
        $check = new ReturningLayout();
        $check->check($target, [new ReturnedColumn(new TextColumnReference(0), $name)]);

        $this->expectExceptionMessage('returned slot must belong to its target');

        $check->check($target, [new ReturnedColumn(new ColumnReference(0), $name)]);
    }
}
