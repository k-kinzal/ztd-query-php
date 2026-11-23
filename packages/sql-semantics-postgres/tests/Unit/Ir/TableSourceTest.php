<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(TableSource::class)]
#[Small]
final class TableSourceTest extends TestCase
{
    public function testCopiesColumnSlotsByValue(): void
    {
        $column = new Identifier('id');
        $source = new TableSource(new Identifier('main'), new Identifier('accounts'), [&$column]);
        $column = new Identifier('other');

        self::assertSame('id', $source->columns[0]->value);
    }

    public function testColumnKindCopiesTheExplicitRoleAndHandlesSignedShorthand(): void
    {
        $kind = ColumnKind::Text;
        $schema = new Identifier('public');
        $table = new Identifier('sample');
        $name = new Identifier('field');
        $typed = new TableSource($schema, $table, [$name], false, [&$kind]);
        $signed = new TableSource($schema, $table, [$name]);
        $kind = ColumnKind::Int4;

        self::assertSame(ColumnKind::Text, $typed->columnKind(0));
        self::assertSame(ColumnKind::Int4, $signed->columnKind(0));
        self::assertNull($typed->columnKind(1));
    }

    public function testRejectsDuplicateSlots(): void
    {
        $this->expectExceptionMessage('repeat a column name');

        new TableSource(new Identifier('main'), new Identifier('accounts'), [new Identifier('id'), new Identifier('id')]);
    }
}
