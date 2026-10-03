<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOption;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CreateTable::class)]
#[Medium]
final class CreateTableTest extends TestCase
{
    public function testWithoutRowidAndStrictReadTheKnownOptions(): void
    {
        $plain = new CreateTable(new QualifiedName(new Name('t')), [new ColumnDefinition(new Name('a'))]);
        $both = new CreateTable(new QualifiedName(new Name('t')), [new ColumnDefinition(new Name('a'))], [], [new TableOption(new Word(new Name('rowid')), true), new TableOption(new Word(new Name('STRICT')))]);

        self::assertFalse($plain->withoutRowid());
        self::assertFalse($plain->strict());
        self::assertTrue($both->withoutRowid());
        self::assertTrue($both->strict());
    }

    public function testStrictIgnoresAnOptionSqliteRejects(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a) "strict"')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertFalse($statement->strict());
    }

    public function testDeriveStatementDeclaresTheColumnsInOrderWithTheirDeclaredTypes(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER, name VARCHAR(10), raw)')->declarations()[0];

        self::assertSame(['id', 'name', 'raw'], array_map(static fn (object $column): string => $column->name->value, $table->columns));
        self::assertSame(['INTEGER', 'VARCHAR(10)', ''], array_map(static fn (object $column): string => $column->type->name(), $table->columns));
    }

    public function testDeriveStatementBindsAQueryToTheVeryObjectsItDeclares(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $table = $create->declarations()[0];
        $query = $semantics->analyze('SELECT name, rowid FROM t WHERE t.id > 1', [$create]);
        $input = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(DeclaredTable::class, $input);
        self::assertSame($table, $input->table);
        self::assertSame($table->columns[1], $query->field('name')->column());
        self::assertSame($table->columns[0], $query->field(1)->column());
        self::assertSame($table->columns[0], $query->facts->scalar($query->statement->where->left)->resolution->slot->column);
    }

    public function testDeriveStatementProvidesExactlyOneDeclaration(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE IF NOT EXISTS t (a TEXT NOT NULL, b)', []);
        $table = $operation->declarations()[0];

        self::assertCount(1, $operation->declarations());
        self::assertSame('t', $table->name->name->value);
        self::assertNull($table->name->schema);
        self::assertTrue($table->complete);
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $table->columns[1]->nullability);
        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveStatementDeclaresATemporaryTableInTheTempSchema(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TEMP TABLE t (a)');

        self::assertSame('temp', $operation->declarations()[0]->name->schema?->value);
    }

    public function testDeriveStatementDeclaresAnImplicitRowIdentifier(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a TEXT)')->declarations()[0];

        self::assertCount(1, $table->implicit);
        self::assertSame(['rowid', 'oid', '_rowid_'], array_map(static fn (object $name): string => $name->value, $table->implicit[0]->names));
        self::assertNotContains($table->implicit[0]->column, $table->columns);
        self::assertSame(Nullability::NotNull, $table->implicit[0]->column->nullability);
        self::assertInstanceOf(ColumnDomain::class, $table->implicit[0]->column->type);
        self::assertSame(Affinity::Integer, $table->implicit[0]->column->type->affinity);
    }

    public function testDeriveStatementMakesAnIntegerPrimaryKeyTheRowIdentifier(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a)')->declarations()[0];

        self::assertSame($table->columns[0], $table->implicit[0]->column);
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    public function testDeriveStatementDeclaresNoRowIdentifierWithoutRowid(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a) WITHOUT ROWID')->declarations()[0];

        self::assertSame([], $table->implicit);
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    public function testDeriveStatementRecordsTheShapeOfTheNewTableForTheStatementNode(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, b)');
        $fact = $operation->facts->relation($operation->statement);

        self::assertCount(2, $fact->shape->slots);
        self::assertSame($operation->declarations()[0]->columns[1], $fact->shape->slots[1]->column);
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($operation->declarations()[0], $fact->table->table);
    }

    public function testDeriveRelationDeclaresTheTableAndAnswersItsShape(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('CREATE TABLE t (a, b PRIMARY KEY, c PRIMARY KEY)')->statement;
        $derivation = new Derivation($semantics->context());

        self::assertInstanceOf(CreateTable::class, $statement);
        $fact = $statement->deriveRelation($derivation, $derivation->environment());
        self::assertSame(['a', 'b', 'c'], array_map(static fn (object $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertTrue($fact->shape->complete());
        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame([$fact->table->table], $derivation->facts()->declarations);
        self::assertSame($fact->table->table->columns[0], $fact->shape->slots[0]->column);
        self::assertInstanceOf(PrimaryKeyProblem::class, $derivation->facts()->diagnostics[0]);
    }

    public function testRenderKeepsACommaWrittenBeforeTheFirstOption(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a PRIMARY KEY) , WITHOUT ROWID , WITHOUT ROWID');

        self::assertInstanceOf(CreateTable::class, $operation->statement);
        self::assertTrue($operation->statement->optionsComma);
        self::assertSame('CREATE TABLE t (a PRIMARY KEY) , WITHOUT ROWID, WITHOUT ROWID', $operation->toString());
    }

    public function testConstructRefusesACommaWithoutAnOptionAfterIt(): void
    {
        $this->expectException(InvalidConstruction::class);

        new CreateTable(new QualifiedName(new Name('t')), [new ColumnDefinition(new Name('a'))], [], [], false, false, true);
    }

    public function testRenderWritesEveryPartInGrammarOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create temp table if not exists temp.t (id integer primary key, a text, unique (a)) strict, without rowid');

        self::assertSame('CREATE TEMP TABLE IF NOT EXISTS `temp`.t (id integer PRIMARY KEY, a text, UNIQUE (a)) strict, WITHOUT rowid', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltDefinition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new CreateTable(new QualifiedName(new Name('t'), new Name('main')), [new ColumnDefinition(new Name('a')), new ColumnDefinition(new Name('b'))]));

        self::assertSame('CREATE TABLE main.t (a, b)', $operation->toString());
        self::assertCount(2, $operation->declarations()[0]->columns);
    }
}
