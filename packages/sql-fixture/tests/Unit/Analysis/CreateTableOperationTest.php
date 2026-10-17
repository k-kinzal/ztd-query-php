<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation as Subject;
use SqlFixture\Schema\Exception\ExpectedCreateTableException;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlSemantics\Diagnostic\AnalysisException;

#[CoversClass(Subject::class)]
#[UsesClass(InvalidSqlException::class)]
#[UsesClass(ExpectedCreateTableException::class)]
final class CreateTableOperationTest extends TestCase
{
    public function testLocateFindsTheStatementThatCreatesATable(): void
    {
        $operation = (new Subject())->locate(\Tests\Statement\MySqlStatements::semantics(), 'DROP TABLE IF EXISTS t; CREATE TABLE t (id INT) ENGINE=InnoDB');

        self::assertSame('CREATE TABLE t (id INT) ENGINE InnoDB', $operation->toString());
    }

    public function testLocateAcceptsAForeignKeyToATableTheInputDoesNotDeclare(): void
    {
        $operation = (new Subject())->locate(\Tests\Statement\PostgreSqlStatements::semantics(), 'CREATE TABLE t (id INT REFERENCES parent (id))');

        self::assertSame('t', (new Subject())->tableName($operation));
    }

    public function testLocateRejectsEmptyInput(): void
    {
        $this->expectException(InvalidSqlException::class);
        $this->expectExceptionMessage('No statements found');
        (new Subject())->locate(\Tests\Statement\MySqlStatements::semantics(), ' -- nothing ');
    }

    public function testLocateRejectsSyntaxErrorsWithTheAnalysisMessage(): void
    {
        try {
            (new Subject())->locate(\Tests\Statement\MySqlStatements::semantics(), 'CREATE TABLE t (');
            self::fail('A syntax error must be rejected.');
        } catch (InvalidSqlException $exception) {
            self::assertInstanceOf(AnalysisException::class, $exception->getPrevious());
        }
    }

    public function testLocateRejectsOtherStatements(): void
    {
        $this->expectException(ExpectedCreateTableException::class);
        (new Subject())->locate(\Tests\Statement\SqliteStatements::semantics(), 'CREATE VIEW v AS SELECT 1');
    }

    public function testLocateRejectsSeveralTables(): void
    {
        $this->expectException(InvalidSqlException::class);
        $this->expectExceptionMessage('More than one CREATE TABLE statement');
        (new Subject())->locate(\Tests\Statement\SqliteStatements::semantics(), 'CREATE TABLE a (id INT); CREATE TABLE b (id INT)');
    }

    public function testLocateRejectsAStatementTheServerRefuses(): void
    {
        try {
            (new Subject())->locate(\Tests\Statement\MySqlStatements::semantics(), 'CREATE TABLE t (a INT, a INT)');
            self::fail('A duplicate column must be rejected.');
        } catch (InvalidSqlException $exception) {
            self::assertNull($exception->getPrevious());
            self::assertStringContainsString('Duplicate column name', $exception->getMessage());
        }
    }

    public function testTableAnswersOnlyBaseTables(): void
    {
        $semantics = \Tests\Statement\PostgreSqlStatements::semantics();

        self::assertNotNull((new Subject())->table($semantics->analyze('CREATE TABLE t (id INT)')));
        self::assertNull((new Subject())->table($semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')));
        self::assertNull((new Subject())->table($semantics->analyze('SELECT 1')));
    }

    public function testTableNameDropsTheSchema(): void
    {
        $semantics = \Tests\Statement\PostgreSqlStatements::semantics();

        self::assertSame('Order Items', (new Subject())->tableName($semantics->analyze('CREATE TABLE shop."Order Items" (id INT)')));
        self::assertSame('', (new Subject())->tableName($semantics->analyze('SELECT 1')));
    }

    public function testColumnsKeysTheDeclaredColumnsByName(): void
    {
        $semantics = \Tests\Statement\SqliteStatements::semantics();

        self::assertSame(['id', 'name'], array_keys((new Subject())->columns($semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)'))));
        self::assertSame(['a'], array_keys((new Subject())->columns($semantics->analyze('CREATE TABLE t AS SELECT 1 AS a'))));
        self::assertSame([], (new Subject())->columns($semantics->analyze('SELECT 1')));
    }

    public function testColumnNameComparesNamesAsTheDialectDoes(): void
    {
        $mysql = (\Tests\Statement\MySqlStatements::semantics())->analyze('CREATE TABLE t (Id INT)');
        $postgres = (\Tests\Statement\PostgreSqlStatements::semantics())->analyze('CREATE TABLE t ("Id" INT)');

        self::assertSame('Id', (new Subject())->columnName($mysql, 'ID'));
        self::assertSame('missing', (new Subject())->columnName($mysql, 'missing'));
        self::assertSame('id', (new Subject())->columnName($postgres, 'id'));
        self::assertSame('Id', (new Subject())->columnName($postgres, 'Id'));
    }
}
