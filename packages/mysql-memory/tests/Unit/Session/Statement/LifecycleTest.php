<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Statement;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Statement\Lifecycle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Lifecycle::class)]
#[Medium]
final class LifecycleTest extends TestCase
{
    public function testRunUnwindsWritesHintsAndActiveScopesOnImplementationFailure(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (n INT)');
        $operation = $session->analyze('SELECT /*+ SET_VAR(unique_checks = OFF) */ 1');
        $temporaries = $session->temporaries;
        $resolution = $session->preparation->resolution;
        $failure = new RuntimeException('The command lost access to its execution resource.');
        $command = $this->createMock(Command::class);
        $command->method('clearsDiagnostics')->willReturn(true);
        $command->expects(self::once())->method('execute')->willReturnCallback(static function () use ($session, $failure): never {
            self::assertSame('OFF', $session->variables->read('unique_checks'));
            $session->execute("INSERT t VALUES ('1')");
            throw $failure;
        });

        $this->expectExceptionObject($failure);
        try {
            (new Execution($session))->perform($operation, $command);
            self::fail('An implementation failure must escape unchanged.');
        } finally {
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
            self::assertSame($temporaries, $session->temporaries);
            self::assertSame($resolution, $session->preparation->resolution);
            self::assertSame('ON', $session->variables->read('unique_checks'));
            self::assertSame(-1, $session->variables->rowCount);
            self::assertSame([], $session->diagnostics->conditions);
            $table = $session->instance->dictionary->table('d', 't');
            self::assertNotNull($table);
            self::assertSame([], $table->data->rows);
        }
    }

    public function testRunRetainsTheEnclosingJournalWhenASqlErrorBecomesAReply(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query("SET sql_mode = 'STRICT_ALL_TABLES'; CREATE TABLE t (n INT)");
        $operation = $session->analyze('SELECT 1');
        $command = $this->createMock(Command::class);
        $command->method('clearsDiagnostics')->willReturn(true);
        $command->expects(self::once())->method('execute')->willReturnCallback(static function () use ($session): Reply {
            $session->execute("INSERT t VALUES ('1')");
            $answers = $session->run("INSERT t VALUES ('2'), ('invalid')");
            self::assertCount(1, $answers);
            self::assertInstanceOf(SqlError::class, $answers[0]);
            self::assertSame(1366, $answers[0]->getCode());
            self::assertCount(1, $session->transaction->statements->running);
            self::assertCount(1, $session->running->using);
            return new Completion(1);
        });

        (new Execution($session))->perform($operation, $command);

        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame([[1]], array_values($table->data->rows));
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
        self::assertSame(1, $session->variables->rowCount);
    }

    #[DataProvider('providerInserts')]
    public function testStartInitializesDiagnosticsForBothStatementForms(string $sql): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (n INT); SET max_error_count = 1; SELECT 1 / 0');
        $statement = $session->analyze($sql);
        $session->text = 'not SQL';

        (new Execution($session))->statement($statement);

        self::assertSame([1, 0], $session->diagnostics->previous);
        self::assertSame(1, $session->diagnostics->limit);
        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame('not SQL', $session->text);
        self::assertSame(1, $session->variables->rowCount);
    }

    #[DataProvider('providerInserts')]
    public function testReleaseMismatchFailsBeforeExecution(string $sql): void
    {
        $source = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $statement = $source->analyze($sql);
        $destination = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $destination->variables->rowCount = 17;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The statement and execution session must select the same MySQL release.');
        try {
            (new Execution($destination))->statement($statement);
            self::fail('A statement cannot execute under a different grammar release.');
        } finally {
            self::assertSame(17, $destination->variables->rowCount);
            self::assertSame([], $destination->transaction->statements->running);
        }
    }

    public function testConcreteInsertRetainsItsBoundTargetWhileTriggersRun(): void
    {
        $session = (new Instance('8.4.7', databases: ['d', 'other']))->connect(database: 'd');
        $session->query('CREATE TABLE t (n INT); CREATE TRIGGER b BEFORE INSERT ON t FOR EACH ROW INSERT t VALUES (2)');
        $statement = $session->analyze("INSERT t VALUES ('1')");
        $session->query('USE other');

        try {
            (new Execution($session))->statement($statement);
            self::fail('A trigger cannot modify the bound target of its invoking statement.');
        } catch (SqlError $error) {
            self::assertSame(1442, $error->getCode());
        }

        self::assertSame([], $session->running->using);
        self::assertSame([], $session->transaction->statements->running);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertSame([], $table->data->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInserts(): iterable
    {
        yield 'integer expression' => ['INSERT t VALUES (1)'];
        yield 'character expression' => ["INSERT t VALUES ('1')"];
    }
}
