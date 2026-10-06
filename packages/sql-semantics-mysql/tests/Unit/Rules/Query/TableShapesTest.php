<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TableShapes::class)]
#[Medium]
final class TableShapesTest extends TestCase
{
    public function testNamedPrefersACommonTable(): void
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
        $operation = $semantics->analyze('WITH t AS (SELECT 1 AS z) SELECT * FROM t', [$t]);

        self::assertSame(['z'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testImplicitFindsInvisibleColumnsByNameOnly(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $hidden = new Column(new Name('h'), new Integral(IntegralKind::Int), Nullability::Nullable);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull)], [new ImplicitColumn([new Name('h')], $hidden)]);
        $named = $semantics->analyze('SELECT h FROM t', [$table]);
        $star = $semantics->analyze('SELECT * FROM t', [$table]);
        $fact = $named->statement instanceof Select && $named->statement->from !== null ? $named->facts->relation($named->statement->from) : null;

        self::assertSame($hidden, $named->field('h')->column());
        self::assertSame([], $named->facts->diagnostics);
        self::assertSame(['a'], array_map(static fn (Field $field): ?string => $field->name?->value, $star->fields()->items ?? []));
        self::assertNotNull($fact);
        self::assertCount(1, (new TableShapes())->implicit($fact));
    }

    public function testExplicitAnswersEveryColumn(): void
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
        $operation = $semantics->analyze('TABLE t', [$t]);
        $table = $operation->statement;

        self::assertInstanceOf(ExplicitTable::class, $table);
        self::assertCount(2, (new TableShapes())->explicit($table, $operation->facts->relation($table), new Derivation($semantics->context()))->projection);
    }

    public function testExplicitKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("WITH c AS (SELECT 'é') TABLE c");

        self::assertSame(1, $operation->fields()?->count());
        self::assertEquals([new SessionState('character_set_client')], $operation->field(0)->slot->unnamed);
    }
}
