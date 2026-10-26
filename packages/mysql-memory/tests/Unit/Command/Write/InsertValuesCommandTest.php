<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\InsertValuesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertValuesCommand::class)]
#[Medium]
final class InsertValuesCommandTest extends TestCase
{
    public function testClearsDiagnosticsStartsANewInsertArea(): void
    {
        self::assertTrue((new InsertValuesCommand())->clearsDiagnostics());
    }

    public function testPrepareResolvesTheColumnsWithoutConvertingOrWritingValues(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE t (a INT, b INT)');
        $statement = $session->analyze("INSERT t (b, a) VALUE ('invalid', '42')");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement::class, $statement);
        [$table, $positions] = (new InsertValuesCommand())->prepare($statement, $session);
        self::assertSame([1, 0], $positions);
        self::assertSame([], $table->data->rows);
    }

    public function testExecuteStringExpressionsConvertToColumnsAndReportMultipleRows(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE t (a INT, b VARCHAR(10))');
        $reply = $session->execute("INSERT INTO t (b, a) VALUES ('first', '42'), ('next', '-7')");
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([2, 0, 0, 'Records: 2  Duplicates: 0  Warnings: 0'], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings, $reply->info]);
        $table = $session->instance->dictionary->table('pbt', 't');
        self::assertNotNull($table);
        self::assertSame([[42, 'first'], [-7, 'next']], array_values($table->data->rows));
    }

    public function testAnInvalidConversionRollsBackEarlierRowsOfTheStatement(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE t (a INT)');
        try {
            $session->execute("INSERT INTO t VALUES ('42'), ('invalid')");
            self::fail('An invalid integer conversion must be reported.');
        } catch (SqlError $error) {
            self::assertSame(1366, $error->getCode());
        }
        $table = $session->instance->dictionary->table('pbt', 't');
        self::assertNotNull($table);
        self::assertSame([], array_values($table->data->rows));
    }

    public function testAnUnresolvedDestinationProducesAnUnknownColumnError(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE t (a INT)');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1054);
        $session->execute("INSERT INTO t (missing) VALUES ('42')");
    }

    public function testReadOnlyTransactionsRejectInsertBeforeOpeningTheTable(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
        $session->execute('START TRANSACTION READ ONLY');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1792);
        $session->execute("INSERT INTO missing VALUES ('42')");
    }
}
