<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\InsertUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;

#[CoversClass(InsertUse::class)]
#[Medium]
final class InsertUseTest extends TestCase
{
    public function testSetKeepsDestinationBindingAheadOfTheDeferredConversion(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze("CREATE PROCEDURE p() INSERT INTO t SET a=CAST(ROW(b,2) AT TIME ZONE 'invalid' AS DATETIME)");
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet::class, $operation->statement->body);
        $write = $operation->statement->body;
        $session->text = 'unrelated invalid input';
        self::assertSame(1146, (new InsertUse())->error($write, $session)->getCode());
        $session->query('CREATE TABLE t(x INT)');
        self::assertSame("Unknown column 'a' in 'field list'", (new InsertUse())->error($write, $session)->getMessage());
        $session->query('ALTER TABLE t ADD a INT');
        self::assertSame("Unknown column 'b' in 'field list'", (new InsertUse())->error($write, $session)->getMessage());
        $session->query('ALTER TABLE t ADD b INT; INSERT INTO t VALUES(1,2,3)');
        $session->text = 'unrelated invalid input';
        self::assertSame(1241, (new InsertUse())->error($write, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }
    public function testErrorOpensTheDestinationBeforeAQueryReadCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->run('INSERT INTO t SELECT *');
        self::assertSame([['Error', 1146, "Table 'd.t' doesn't exist"]], $session->diagnostics->conditions);
        $session->query('CREATE TABLE t(a INT)');
        $session->run('INSERT INTO t SELECT *');
        self::assertSame([['Error', 1096, 'No tables used']], $session->diagnostics->conditions);
        $session->run('INSERT INTO t SELECT ROW(1, 2)');
        self::assertSame([['Error', 1241, 'Operand should contain 1 column(s)']], $session->diagnostics->conditions);
    }

    public function testErrorOpensTheDestinationBeforeNestedValueDependencies(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze('CREATE PROCEDURE p() INSERT INTO absent_schema.t VALUES (WEIGHT_STRING(ROW((TABLE no_table),2)))');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        $session->text = 'unrelated invalid input';
        $error = (new InsertUse())->error($operation->statement->body, $session);
        self::assertSame(1049, $error->getCode());
        self::assertSame("Unknown database 'absent_schema'", $error->getMessage());
    }

    public function testRaiseRebindsTheSameWriteAfterItsTargetBecomesAvailable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (WEIGHT_STRING(ROW((TABLE d.other),2)))');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        self::assertSame("Table 'd.t' doesn't exist", (new InsertUse())->error($operation->statement->body, $session)->getMessage());
        $session->query('CREATE TABLE t(a INT)');
        self::assertSame("Table 'd.other' doesn't exist", (new InsertUse())->error($operation->statement->body, $session)->getMessage());
        $session->query('CREATE TABLE other(a INT); INSERT INTO other VALUES (7)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        (new InsertUse())->raise($operation->statement->body, $session);
    }

    public function testPrepareBindsValuesInTheirDestinationColumnScope(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $operation = $session->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (WEIGHT_STRING(ROW(a,2)))');
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        (new InsertUse())->raise($operation->statement->body, $session);
    }

    public function testWidthChecksAnEmptyDefaultsRowAgainstTheFirstRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (1), ()')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $this->expectExceptionMessage("Column count doesn't match value count at row 2");
        (new InsertUse())->width($procedure->body, 1, $table);
    }

    public function testValuesBindTheFirstRowBeforeALaterWidthFailure(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, b INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (absent, 2), (3)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        $session->text = 'unrelated invalid input';
        $error = (new InsertUse())->error($procedure->body, $session);
        self::assertSame(1054, $error->getCode());
        self::assertSame([[1136, "Column count doesn't match value count at row 2"]], $error->following);
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testRowsKeepModernDestinationBindingBeforeFirstRowWidth(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t (absent) VALUES (1, 2)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        $error = (new InsertUse())->error($procedure->body, $session);
        self::assertSame("Unknown column 'absent' in 'field list'", $error->getMessage());
        self::assertSame([], $error->following);
    }
    public function testUpdatesPrepareOnlyAfterPrecedingRowsOnModernReleases(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT, b INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (1, 2), (3) ON DUPLICATE KEY UPDATE a = ROW(4, 5)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        self::assertSame("Column count doesn't match value count at row 2", (new InsertUse())->error($procedure->body, $session)->getMessage());
        $onlyUpdate = $session->analyze('CREATE PROCEDURE q() INSERT INTO t VALUES (1, 2) ON DUPLICATE KEY UPDATE a = ROW(4, 5)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $onlyUpdate);
        self::assertInstanceOf(DeferredInsertRows::class, $onlyUpdate->body);
        self::assertSame('Operand should contain 1 column(s)', (new InsertUse())->error($onlyUpdate->body, $session)->getMessage());
    }

    public function testHeadKeepsLegacyFirstRowOperandsBeforeWrittenColumnCounts(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t(a) VALUES (unknown_value, 2)')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        self::assertSame("Unknown column 'unknown_value' in 'field list'", (new InsertUse())->error($procedure->body, $session)->getMessage());
    }

}
