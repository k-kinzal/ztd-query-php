<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableShapes::class)]
#[Medium]
final class TableShapesTest extends TestCase
{
    public function testNamedPrefersACommonTable(): void
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
        $operation = $semantics->inspect('WITH t AS (SELECT 1 AS z) SELECT * FROM t', [$t]);

        self::assertSame(['z'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testImplicitFindsInvisibleColumnsByNameOnly(): void
    {
        $semantics = new Inspection();
        $hidden = new Column(new Name('h'), new Integral(IntegralKind::Int), Nullability::Nullable);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull)], [new ImplicitColumn([new Name('h')], $hidden)]);
        $named = $semantics->inspect('SELECT h FROM t', [$table]);
        $star = $semantics->inspect('SELECT * FROM t', [$table]);
        $fact = $named->statement instanceof Select && $named->statement->from !== null ? $named->facts->relation($named->statement->from) : null;

        self::assertSame($hidden, $named->field('h')->column());
        self::assertSame([], $named->facts->diagnostics);
        self::assertSame(['a'], array_map(static fn (Field $field): ?string => $field->name?->value, $star->fields()->items ?? []));
        self::assertNotNull($fact);
        self::assertCount(1, (new TableShapes())->implicit($fact));
    }

    public function testExplicitAnswersEveryColumn(): void
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
        $operation = $semantics->inspect('TABLE t', [$t]);
        $table = $operation->statement;

        self::assertInstanceOf(ExplicitTable::class, $table);
        self::assertCount(2, (new TableShapes())->explicit($table, $operation->facts->relation($table), new Derivation($semantics->context()))->projection);
    }

    public function testExplicitKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Inspection())->inspect("WITH c AS (SELECT 'é') TABLE c");

        self::assertSame(1, $operation->fields()?->count());
        self::assertEquals([new SessionState('character_set_client')], $operation->field(0)->slot->unnamed);
    }
}
