<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Projection::class)]
#[Medium]
final class ProjectionTest extends TestCase
{
    public function testItemsDerivesExpressionsAndStars(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT *, b, t.* FROM t', [$t]);

        self::assertSame(['a', 'b', 'b', 'a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame($t->columns[0], $operation->field(3)->column());
    }

    public function testStarReportsAStarWithoutTables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT *');

        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
    }

    public function testQualifiedReportsAnUnknownTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT u.* FROM t', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
    }

    public function testExpandAppendsAnOpenStarForUndeclaredColumns(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT t.*, 1 FROM t');

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
        $semantics = new Semantics(Dialect::MySql);
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
