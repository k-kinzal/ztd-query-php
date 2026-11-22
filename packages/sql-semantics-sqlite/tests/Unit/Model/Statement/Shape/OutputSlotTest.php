<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

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
        $semantics = new Inspection();
        $t = $semantics->inspect('CREATE TABLE t (a INTEGER NOT NULL)')->declarations()[0];
        $u = $semantics->inspect('CREATE TABLE u (a INTEGER)')->declarations()[0];

        $slot = $semantics->inspect('SELECT t.a FROM u LEFT JOIN t ON u.a = t.a', [$t, $u])->field(0)->slot;

        self::assertSame(Nullability::Nullable, $slot->nullability);
        self::assertSame($t->columns[0], $slot->declaration());
        self::assertSame(Nullability::NotNull, $t->columns[0]->nullability);
        self::assertNotNull($slot->origin);
    }

    public function testUnnamedKeepsTheInputsAnUnknownNameDependsOn(): void
    {
        $missing = new SessionState('the client character set');

        $slot = new OutputSlot(null, new Known(Storage::Text), Nullability::Nullable, null, null, [$missing]);

        self::assertSame([$missing], $slot->unnamed);
    }

    public function testUnnamedRefusesInputsForAPositionThatHasAName(): void
    {
        $this->expectExceptionMessage('Only a position without a name depends on inputs for its name.');

        new OutputSlot(new Name('a'), new Known(Storage::Text), Nullability::Nullable, null, null, [new SessionState('the client character set')]);
    }
}
