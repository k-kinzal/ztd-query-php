<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OutputSlot::class)]
#[Medium]
final class OutputSlotTest extends TestCase
{
    public function testDeclarationFollowsReExposedSlotsToTheColumn(): void
    {
        $column = new Column(new Name('a'), Storage::Integer, Nullability::NotNull);
        $base = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
        $joined = new OutputSlot($column->name, $base->type, Nullability::Nullable, null, $base);
        $projected = new OutputSlot(new Name('x'), $joined->type, $joined->nullability, null, $joined);

        self::assertSame($column, $projected->declaration());
        self::assertNull($projected->column);
        self::assertSame(Nullability::Nullable, $projected->nullability);
    }

    public function testDeclarationIsNullWhenNoColumnIsReached(): void
    {
        $computed = new OutputSlot(null, new Known(Storage::Text), Nullability::NotNull);

        self::assertNull($computed->declaration());
        self::assertNull((new OutputSlot(new Name('x'), $computed->type, $computed->nullability, null, $computed))->declaration());
    }

    public function testDeclarationOfAnOuterJoinSlotIsTheUnchangedColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)')->declarations()[0];
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER)')->declarations()[0];

        $slot = $semantics->analyze('SELECT t.a FROM u LEFT JOIN t ON u.a = t.a', [$t, $u])->field(0)->slot;

        self::assertSame(Nullability::Nullable, $slot->nullability);
        self::assertSame($t->columns[0], $slot->declaration());
        self::assertSame(Nullability::NotNull, $t->columns[0]->nullability);
        self::assertNotNull($slot->origin);
    }
}
