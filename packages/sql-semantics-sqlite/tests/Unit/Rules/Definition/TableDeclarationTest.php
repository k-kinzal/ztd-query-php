<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TableDeclaration::class)]
#[Medium]
final class TableDeclarationTest extends TestCase
{
    public function testDomainsRecordTheDeclaredTypeOfEachColumn(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a integer, b, c ANY) STRICT')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $domains = (new TableDeclaration())->domains($statement);
        self::assertSame(['INTEGER', '', 'ANY'], array_map(static fn (ColumnDomain $domain): string => $domain->declared, $domains));
        self::assertTrue($domains[0]->strict);
    }

    public function testNamePutsATemporaryObjectIntoTheTempSchema(): void
    {
        $written = new QualifiedName(new Name('t'), new Name('main'));

        self::assertSame($written, (new TableDeclaration())->name($written, false));
        self::assertSame('temp', (new TableDeclaration())->name($written, true)->schema?->value);
        self::assertSame('t', (new TableDeclaration())->name($written, true)->name->value);
    }

    public function testTableAllowsNullInAPrimaryKeyColumnOfAnOrdinaryTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $single = $semantics->analyze('CREATE TABLE t (a TEXT PRIMARY KEY, b)')->declarations()[0];
        $composite = $semantics->analyze('CREATE TABLE t (a INTEGER, b, PRIMARY KEY (a, b))')->declarations()[0];
        $descending = $semantics->analyze('CREATE TABLE t (a INTEGER PRIMARY KEY DESC)')->declarations()[0];

        self::assertSame(Nullability::Nullable, $single->columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $composite->columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $descending->columns[0]->nullability);
        self::assertNotSame($descending->columns[0], $descending->implicit[0]->column);
    }

    public function testTableForbidsNullInPrimaryKeyColumnsOfWithoutRowidAndStrictTables(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $withoutRowid = $semantics->analyze('CREATE TABLE t (a TEXT, b, c, PRIMARY KEY (a, b)) WITHOUT ROWID')->declarations()[0];
        $strict = $semantics->analyze('CREATE TABLE t (a TEXT PRIMARY KEY, b TEXT) STRICT')->declarations()[0];

        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::Nullable], array_map(static fn (Column $column): Nullability => $column->nullability, $withoutRowid->columns));
        self::assertSame([], $withoutRowid->implicit);
        self::assertSame(Nullability::NotNull, $strict->columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $strict->columns[1]->nullability);
        self::assertCount(1, $strict->implicit);
    }

    public function testTableMakesTheIntegerPrimaryKeyOfATableConstraintTheRowid(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, id INTEGER, PRIMARY KEY (id DESC))')->declarations()[0];

        self::assertSame($table->columns[1], $table->implicit[0]->column);
        self::assertSame(Nullability::NotNull, $table->columns[1]->nullability);
    }

    public function testTableDeclaresGeneratedColumnsAsGeneratedNullableColumns(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT, b INT AS (a + 1) STORED, c AS (a) NOT NULL, d GENERATED ALWAYS AS (a) VIRTUAL)')->declarations()[0];

        self::assertCount(4, $table->columns);
        self::assertSame([false, true, true, true], array_map(static fn (Column $column): bool => $column->generated, $table->columns));
        self::assertSame(Nullability::Nullable, $table->columns[1]->nullability);
        self::assertSame(Nullability::NotNull, $table->columns[2]->nullability);
        self::assertSame('INT', $table->columns[1]->type->name());
    }

    public function testTableBuildsTheDeclarationFromAnEstablishedKey(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('CREATE TABLE t (a, b)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $rule = new TableDeclaration();
        $table = $rule->table($statement, $rule->domains($statement), new TableKey(), $semantics->profile());
        self::assertSame(['a', 'b'], array_map(static fn (Column $column): string => $column->name->value, $table->columns));
        self::assertSame($statement->columns[0]->name, $table->columns[0]->name);
        self::assertTrue($table->complete);
    }

    public function testRowidAliasesTheGivenColumnOrDeclaresAnIntegerColumn(): void
    {
        $rule = new TableDeclaration();
        $alias = new Column(new Name('id'), new ColumnDomain('INTEGER'), Nullability::NotNull);

        self::assertSame($alias, $rule->rowid($alias)->column);
        self::assertSame('rowid', $rule->rowid(null)->column->name->value);
        self::assertSame(Nullability::NotNull, $rule->rowid(null)->column->nullability);
        self::assertCount(3, $rule->rowid(null)->names);
    }
}
