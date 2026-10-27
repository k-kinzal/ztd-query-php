<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversNothing]
#[Medium]
final class TableDefinitionAnalysisBoundaryTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testAnalysisPreservesTheCatalogTransactionDiagnosticsAndCounters(string $version): void
    {
        $session = (new Instance($version, [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION');
        $table = $session->instance->dictionary->table('d', 't');
        $status = clone $session->instance->registry->status;
        $diagnostics = clone $session->diagnostics;
        $rows = [$session->variables->rowCount, $session->variables->foundRows];

        $operation = $session->analyze('CREATE TABLE IF NOT EXISTS t ENGINE=InnoDB');
        self::assertInstanceOf(CreateTable::class, $operation);
        self::assertTrue($operation->hasExecutionCondition());
        self::assertSame($table, $session->instance->dictionary->table('d', 't'));
        self::assertTrue($session->transaction->open);
        self::assertEquals($status, $session->instance->registry->status);
        self::assertEquals($diagnostics, $session->diagnostics);
        self::assertSame($rows, [$session->variables->rowCount, $session->variables->foundRows]);
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    public function testExecutionOwnsTheConditionalCompletionAndImplicitCommit(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION');
        $operation = $session->analyze('CREATE TABLE IF NOT EXISTS t ENGINE=InnoDB');
        $session->text = 'opaque definition label';

        $reply = (new Execution($session))->statement($operation);

        self::assertInstanceOf(Completion::class, $reply);
        self::assertFalse($session->transaction->open);
        self::assertSame([['Note', 1050, "Table 't' already exists"]], $session->diagnostics->conditions);
        self::assertSame('opaque definition label', $session->text);
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    public function testAlterAnalysisRetainsTheRequestAndExecutionUnwindsItsFailure(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION');
        $status = clone $session->instance->registry->status;
        $operation = $session->analyze('ALTER TABLE t WITH VALIDATION, FORCE');
        self::assertTrue($session->transaction->open);
        self::assertEquals($status, $session->instance->registry->status);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $operation);
        self::assertTrue($operation->hasExecutionCondition());
        $session->text = 'opaque alteration label';
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect usage of ALTER and WITH VALIDATION');
        try {
            (new Execution($session))->statement($operation);
        } finally {
            self::assertFalse($session->transaction->open);
            self::assertSame('opaque alteration label', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        }
    }

    public function testStoredDefinitionsUseTheSameConditionalExecutionBoundary(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE PROCEDURE p() CREATE TABLE IF NOT EXISTS t ENGINE=InnoDB');
        self::assertInstanceOf(Completion::class, $session->query('CALL p()')[0]);
        $session->query('DROP TABLE t');
        $failure = $session->run('CALL p()')[0];
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(4028, $failure->getCode());
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            yield $version => [$version];
        }
    }
}
