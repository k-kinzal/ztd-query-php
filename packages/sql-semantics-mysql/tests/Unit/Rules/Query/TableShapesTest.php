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
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $named->statement);
        $fact = $named->statement->query instanceof Select && $named->statement->query->from !== null ? $named->facts->relation($named->statement->query->from) : null;

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
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $table);
        $table = $table->query;

        self::assertInstanceOf(ExplicitTable::class, $table);
        self::assertCount(2, (new TableShapes())->explicit($table, $operation->facts->relation($table), new Derivation($semantics->context()))->projection);
    }

    public function testExplicitKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("WITH c AS (SELECT 'é') TABLE c");

        self::assertSame(1, $operation->fields()?->count());
        self::assertEquals([new SessionState('character_set_client')], $operation->field(0)->slot->unnamed);
    }
    public function testExplicitDerivesAConstantProjectionForExistenceWithoutChangingTheInputShape(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
        $definition = $semantics->analyze('CREATE TABLE t(a INT, b INT)');
        $operation = $semantics->analyze('SELECT EXISTS(TABLE t UNION SELECT 1)', [$definition]);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->query);
        $item = $operation->statement->query->items[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $item);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists::class, $item->expression);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation::class, $item->expression->query);
        $table = $item->expression->query->left;
        self::assertInstanceOf(ExplicitTable::class, $table);
        self::assertCount(2, $operation->facts->relation($table)->shape->slots);
        self::assertCount(1, $operation->facts->query($table)->projection);
        self::assertSame(Nullability::NotNull, $operation->field(0)->slot->nullability);
    }

}
