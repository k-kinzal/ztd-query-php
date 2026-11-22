<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Projection::class)]
#[Medium]
final class ProjectionTest extends TestCase
{
    public function testItemsDerivesExpressionsAndStars(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->diagnose('SELECT *, b, t.* FROM t', [$t]);

        self::assertSame(['a', 'b', 'b', 'a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame($t->columns[0], $operation->field(3)->column());
    }

    public function testStarReportsAStarWithoutTables(): void
    {
        $operation = (new Inspection())->diagnose('SELECT *');

        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
    }

    public function testQualifiedReportsAnUnknownTable(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->diagnose('SELECT u.* FROM t', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
    }

    public function testExpandAppendsAnOpenStarForUndeclaredColumns(): void
    {
        $operation = (new Inspection())->diagnose('SELECT t.*, 1 FROM t');

        self::assertNull($operation->fields());
        self::assertNotNull($operation->shape());
        self::assertFalse($operation->shape()->complete());
        self::assertCount(1, $operation->shape()->slots);
    }

    public function testFieldReferencesTheSlotOfTheRelation(): void
    {
        $relation = new VisibleRelation(new Dual(), new RowShape([]));
        $slot = new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull);
        $field = (new Projection())->field(3, $relation, $slot);

        self::assertSame(3, $field->position);
        self::assertSame($slot, $field->slot->origin);
    }

    public function testAdmitsMatchesTheCorrelationOrTableName(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $aliased = new VisibleRelation(new Dual(), new RowShape([]), new Name('x'), new QualifiedName(new Name('t'), new Name('db')));
        $named = new VisibleRelation(new Dual(), new RowShape([]), null, new QualifiedName(new Name('t'), new Name('db')));

        self::assertTrue((new Projection())->admits($derivation, $aliased, new QualifiedName(new Name('x'))));
        self::assertFalse((new Projection())->admits($derivation, $aliased, new QualifiedName(new Name('t'))));
        self::assertTrue((new Projection())->admits($derivation, $named, new QualifiedName(new Name('t'), new Name('db'))));
        self::assertFalse((new Projection())->admits($derivation, $named, new QualifiedName(new Name('t'), new Name('other'))));
    }

    public function testMissingCollectsTheMissingInputs(): void
    {
        $missing = new SessionState('x');

        self::assertSame([$missing], (new Projection())->missing([new VisibleRelation(new Dual(), new RowShape([], [$missing])), new VisibleRelation(new Dual(), new RowShape([]))]));
    }
}
