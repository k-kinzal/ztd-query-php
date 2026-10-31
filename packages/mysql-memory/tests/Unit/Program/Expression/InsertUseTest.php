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
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerImmediateConditions(): iterable
    {
        yield 'row width' => ['INSERT INTO t VALUES (1), (1, 2)', 1136];
        yield 'row operand' => ['INSERT INTO t VALUE (ROW(a, 2))', 1241];
        yield 'set operand' => ['INSERT INTO t SET a=ROW(a, 2)', 1241];
        yield 'read columns' => ['INSERT INTO t SELECT *', 1096];
        yield 'query duplicate' => ['INSERT INTO t SELECT 1 ON DUPLICATE KEY UPDATE a=ROW(a, 2)', 1241];
    }

    public function testExplainedUsesItsDeclaredDatabaseAndRestoresTheCaller(): void
    {
        $session = (new Instance('9.1.0', [], ['d', 'e']))->connect(database: 'e');
        $session->query('CREATE TABLE t(a INT)');
        $session->variables->database = 'd';
        $explain = $session->analyze('EXPLAIN FOR DATABASE e INSERT INTO t VALUE(ROW(a,2))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $explain);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        try {
            (new InsertUse())->explained($explain, $session);
        } finally {
            self::assertSame('d', $session->variables->database);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerImmediateConditions')]
    public function testStatementUsesPublishedBindings(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $write = $session->analyze($sql);
        self::assertTrue($write instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows || $write instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet || $write instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new InsertUse())->statement($write, $session);
    }

    public function testCheckUsesThePublishedMissingTableCondition(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $write = $session->analyze('INSERT INTO absent SET a=ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet::class, $write);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Table 'd.absent' doesn't exist");
        (new InsertUse())->check($write, $write->statementInformation(), $session);
    }

    public function testSourceIgnoresOuterValuesLimitBeforeDuplicateKeyPreparation(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $write = $session->analyze('INSERT INTO t (VALUES ROW(a)) LIMIT 0 ON DUPLICATE KEY UPDATE a=ROW(a,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery::class, $write);
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        (new InsertUse())->statement($write, $session);
    }

    public function testSetKeepsDestinationBindingAheadOfTheDeferredConversion(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze("CREATE PROCEDURE p() INSERT INTO t SET a=CAST(ROW(b,2) AT TIME ZONE 'invalid' AS DATETIME)");
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet::class, $operation->body);
        $write = $operation->body;
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

    public function testErrorChecksReachedLimitBindingsBeforeOpeningTheWriteTarget(): void
    {
        $session = (new Instance())->connect();
        $session->run('INSERT INTO absent.dst WITH c AS (TABLE absent.src LIMIT n) SELECT * WHERE EXISTS(TABLE c)');
        self::assertSame([['Error', 1327, 'Undeclared variable: n']], $session->diagnostics->conditions);
        $session->run('INSERT INTO absent.dst WITH c AS (TABLE absent.src LIMIT n) SELECT *');
        self::assertSame([['Error', 1049, "Unknown database 'absent'"]], $session->diagnostics->conditions);
    }

    public function testErrorOpensTheDestinationBeforeNestedValueDependencies(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze('CREATE PROCEDURE p() INSERT INTO absent_schema.t VALUES (WEIGHT_STRING(ROW((TABLE no_table),2)))');
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->body);
        $session->text = 'unrelated invalid input';
        $error = (new InsertUse())->error($operation->body, $session);
        self::assertSame(1049, $error->getCode());
        self::assertSame("Unknown database 'absent_schema'", $error->getMessage());
    }

    public function testRaiseRebindsTheSameWriteAfterItsTargetBecomesAvailable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (WEIGHT_STRING(ROW((TABLE d.other),2)))');
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->body);
        self::assertSame("Table 'd.t' doesn't exist", (new InsertUse())->error($operation->body, $session)->getMessage());
        $session->query('CREATE TABLE t(a INT)');
        self::assertSame("Table 'd.other' doesn't exist", (new InsertUse())->error($operation->body, $session)->getMessage());
        $session->query('CREATE TABLE other(a INT); INSERT INTO other VALUES (7)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        (new InsertUse())->raise($operation->body, $session);
    }

    public function testPrepareBindsValuesInTheirDestinationColumnScope(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $operation = $session->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (WEIGHT_STRING(ROW(a,2)))');
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->body);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1241);
        (new InsertUse())->raise($operation->body, $session);
    }

    public function testWidthChecksAnEmptyDefaultsRowAgainstTheFirstRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (1), ()');
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
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (absent, 2), (3)');
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
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t (absent) VALUES (1, 2)');
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
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t VALUES (1, 2), (3) ON DUPLICATE KEY UPDATE a = ROW(4, 5)');
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        self::assertSame("Column count doesn't match value count at row 2", (new InsertUse())->error($procedure->body, $session)->getMessage());
        $onlyUpdate = $session->analyze('CREATE PROCEDURE q() INSERT INTO t VALUES (1, 2) ON DUPLICATE KEY UPDATE a = ROW(4, 5)');
        self::assertInstanceOf(CreateProcedure::class, $onlyUpdate);
        self::assertInstanceOf(DeferredInsertRows::class, $onlyUpdate->body);
        self::assertSame('Operand should contain 1 column(s)', (new InsertUse())->error($onlyUpdate->body, $session)->getMessage());
    }

    public function testHeadKeepsLegacyFirstRowOperandsBeforeWrittenColumnCounts(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $procedure = $session->analyze('CREATE PROCEDURE p() INSERT INTO t(a) VALUES (unknown_value, 2)');
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        self::assertInstanceOf(DeferredInsertRows::class, $procedure->body);
        self::assertSame("Unknown column 'unknown_value' in 'field list'", (new InsertUse())->error($procedure->body, $session)->getMessage());
    }

}
