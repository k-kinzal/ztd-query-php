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
}
