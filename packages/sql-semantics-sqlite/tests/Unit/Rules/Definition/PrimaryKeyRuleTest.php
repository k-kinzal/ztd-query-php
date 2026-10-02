<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\PrimaryKeyRule;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Statement\Identifier\Comparison;

#[CoversClass(PrimaryKeyRule::class)]
#[Medium]
final class PrimaryKeyRuleTest extends TestCase
{
    public function testKeyMakesAnIntegerPrimaryKeyColumnTheRowid(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, id INTEGER PRIMARY KEY)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = (new PrimaryKeyRule())->key($statement, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive);
        self::assertSame([1], $key->columns);
        self::assertSame(1, $key->rowid);
        self::assertSame(1, $key->constraints);
    }

    public function testKeyDoesNotMakeADescendingColumnConstraintTheRowid(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY DESC)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = (new PrimaryKeyRule())->key($statement, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive);
        self::assertSame([0], $key->columns);
        self::assertNull($key->rowid);
    }

    public function testKeyDoesNotMakeAnotherTypeTheRowid(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INT PRIMARY KEY, b BIGINT)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertNull((new PrimaryKeyRule())->key($statement, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive)->rowid);
    }

    public function testKeyCountsEveryPrimaryKeyConstraintAndUsesTheFirst(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a PRIMARY KEY, b, PRIMARY KEY (b))')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = (new PrimaryKeyRule())->key($statement, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive);
        self::assertSame(2, $key->constraints);
        self::assertSame([0], $key->columns);
    }

    public function testKeyIsEmptyWithoutAPrimaryKey(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, b)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = (new PrimaryKeyRule())->key($statement, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive);
        self::assertSame(0, $key->constraints);
        self::assertSame([], $key->columns);
        self::assertNull($key->rowid);
    }

    public function testTableKeyMakesASingleIntegerColumnTheRowidWhateverItsSortOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, id INTEGER, PRIMARY KEY (ID DESC AUTOINCREMENT))')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $constraint = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TablePrimaryKey::class, $constraint);
        $key = (new PrimaryKeyRule())->tableKey($statement, $constraint, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive);
        self::assertSame([1], $key->columns);
        self::assertSame(1, $key->rowid);
        self::assertTrue($key->autoincrement);
    }

    public function testTableKeyListsTheColumnsOfACompositeKeyInKeyOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a INTEGER, b, c, PRIMARY KEY (c, 'a', (b) COLLATE nocase, zz, a + 1))")->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $constraint = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TablePrimaryKey::class, $constraint);
        $key = (new PrimaryKeyRule())->tableKey($statement, $constraint, (new TableDeclaration())->domains($statement), Comparison::AsciiInsensitive);
        self::assertSame([2, 0, 1], $key->columns);
        self::assertNull($key->rowid);
    }
}
