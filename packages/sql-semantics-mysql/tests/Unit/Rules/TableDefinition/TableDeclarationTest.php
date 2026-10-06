<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TableDeclaration::class)]
#[Medium]
final class TableDeclarationTest extends TestCase
{
    public function testColumnDeclaresOneColumnDefinition(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT NOT NULL)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);

        self::assertSame(Nullability::NotNull, (new TableDeclaration())->column($column)->nullability);
    }

    public function testColumnDeclaresGeneratedColumnsGenerated(): void
    {
        $columns = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, g INT AS (a + 1), s INT GENERATED ALWAYS AS (a) STORED)')->declarations()[0]->columns;

        self::assertSame([false, true, true], [$columns[0]->generated, $columns[1]->generated, $columns[2]->generated]);
    }

    public function testPrimaryColumnsNamesTheTableLevelKeyColumns(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT, PRIMARY KEY (b, a))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        self::assertSame('b', (new TableDeclaration())->primaryColumns($statement)[0]->value);
    }

    public function testTableOrdersTheColumnsOfCreateTableSelect(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT NOT NULL) SELECT 1 AS b, 2 AS c');
        $columns = $create->declarations()[0]->columns;

        self::assertSame(['a', 'b', 'c'], [$columns[0]->name->value, $columns[1]->name->value, $columns[2]->name->value]);
    }

    public function testRenamedNamesADefinedColumnAfterTheSelectedField(): void
    {
        $columns = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (A INT NOT NULL, B INT) SELECT 1 AS a')->declarations()[0]->columns;

        self::assertSame(['B', 'a'], [$columns[0]->name->value, $columns[1]->name->value]);
        self::assertSame(Nullability::NotNull, $columns[1]->nullability);
    }

    public function testSplitKeepsInvisibleColumnsImplicit(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT INVISIBLE)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $table = (new TableDeclaration())->split(new QualifiedName(new Name('t')), [[$column, $create->declarations()[0]->implicit[0]->column]], $create->profile(), true);

        self::assertSame([], $table->columns);
        self::assertCount(1, $table->implicit);
    }

    public function testViewNamesTheColumnsByTheList(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE VIEW v (x) AS SELECT 1 AS a');

        self::assertSame('x', $create->declarations()[0]->columns[0]->name->value);
        self::assertSame(RelationKind::View, $create->declarations()[0]->kind);
        self::assertSame(RelationKind::BaseTable, (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t AS SELECT 1 AS a')->declarations()[0]->kind);
    }

    public function testLikeLeavesAnUndeclaredSourceIncomplete(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)');

        self::assertFalse((new TableDeclaration())->like(new QualifiedName(new Name('c')), null, $create->profile())->complete);
        self::assertCount(1, (new TableDeclaration())->like(new QualifiedName(new Name('c')), $create->declarations()[0], $create->profile())->columns);
    }

    public function testLikeKeepsGeneratedColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $source = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1), h INT AS (a) INVISIBLE)')->declarations();
        $copy = $semantics->analyze('CREATE TABLE c LIKE t', $source)->declarations()[0];

        self::assertSame([false, true, true], [$copy->columns[0]->generated, $copy->columns[1]->generated, $copy->implicit[0]->column->generated]);
    }

    public function testTableDeclaresSelectedColumnsNotGenerated(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $source = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1))')->declarations();
        $columns = $semantics->analyze('CREATE TABLE c SELECT * FROM t', $source)->declarations()[0]->columns;
        $view = $semantics->analyze('CREATE VIEW v AS SELECT * FROM t', $source)->declarations()[0]->columns;

        self::assertSame([false, false, false, false], [$columns[0]->generated, $columns[1]->generated, $view[0]->generated, $view[1]->generated]);
    }

    public function testSettledStopsAtAnOpenStar(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE VIEW v AS SELECT 1 AS a, t.* FROM t');
        $statement = $create->statement;
        self::assertInstanceOf(CreateView::class, $statement);

        self::assertCount(1, (new TableDeclaration())->settled($create->facts->query($statement->definition->query)));
    }

    public function testFieldColumnNeedsAKnownType(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE VIEW v AS SELECT NULL AS a');
        $statement = $create->statement;
        self::assertInstanceOf(CreateView::class, $statement);
        $field = (new TableDeclaration())->settled($create->facts->query($statement->definition->query))[0];

        self::assertNull((new TableDeclaration())->fieldColumn($field, new Name('a')));
    }

    public function testFindAnswersThePositionOfADefinedColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        $first = $statement->elements[0];
        $second = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $first);
        self::assertInstanceOf(ColumnDefinition::class, $second);

        self::assertSame(1, (new TableDeclaration())->find(new Name('B'), [[$first, $create->declarations()[0]->columns[0]], [$second, $create->declarations()[0]->columns[1]]], Comparison::AsciiInsensitive));
    }

    public function testNamedComparesNames(): void
    {
        self::assertTrue((new TableDeclaration())->named(new Name('A'), [new Name('a')], Comparison::AsciiInsensitive));
        self::assertFalse((new TableDeclaration())->named(new Name('b'), [new Name('a')], Comparison::AsciiInsensitive));
    }
}
