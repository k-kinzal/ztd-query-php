<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class ConditionalRequestAnalysisBoundaryTest extends TestCase
{
    #[DataProvider('providerRequests')]
    public function testAnalysisPreservesTheCatalogTransactionDiagnosticsAndCounters(string $version, string $sql): void
    {
        $session = (new Instance($version, [], ['d', 'other']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION');
        $table = $session->instance->dictionary->table('d', 't');
        $status = clone $session->instance->registry->status;
        $diagnostics = clone $session->diagnostics;
        $rows = [$session->variables->rowCount, $session->variables->foundRows];

        $operation = $session->analyze($sql);
        self::assertSame($table, $session->instance->dictionary->table('d', 't'));
        self::assertTrue($session->transaction->open);
        self::assertEquals($status, $session->instance->registry->status);
        self::assertEquals($diagnostics, $session->diagnostics);
        self::assertSame($rows, [$session->variables->rowCount, $session->variables->foundRows]);
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    public function testTriggerExecutionResolvesTheTargetBeforeItsSchemaCondition(): void
    {
        $session = (new Instance('8.4.7', [], ['a', 'b']))->connect();
        $operation = $session->analyze('CREATE TRIGGER a.tr BEFORE INSERT ON b.t FOR EACH ROW SET @x=1');
        $prepared = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session);
        $missing = $prepared->run($session, 'opaque trigger request', [])[0];
        self::assertInstanceOf(SqlError::class, $missing);
        self::assertSame(1146, $missing->getCode());
        $session->query('CREATE TABLE b.t(a INT); INSERT INTO b.t VALUES(7); START TRANSACTION');
        $reached = $prepared->run($session, 'same request, new catalog', [])[0];
        self::assertInstanceOf(SqlError::class, $reached);
        self::assertSame(1435, $reached->getCode());
        self::assertFalse($session->transaction->open);
        self::assertSame($operation, $prepared->statement);
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
        $this->expectExceptionCode(1295);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
    }

    public function testConditionalUpdateResolvesItsPredicateBeforeRewritingAView(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE VIEW v AS SELECT a FROM t WHERE a > 0');
        $operation = $session->analyze('UPDATE v SET a = 1 WHERE ROW(1, 2)');
        $reply = \MySqlMemory\Command\Prepared\PreparedStatement::capture($operation, $session)->run($session, 'conditional view update', [])[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1241, $reply->getCode());
        self::assertSame('conditional view update', $session->text);
        self::assertSame([], $session->transaction->statements->running);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRequests(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            yield $version . ' index' => [$version, 'CREATE INDEX `PRIMARY` ON t(a)'];
            yield $version . ' update' => [$version, 'UPDATE t SET a=ROW(1,2)'];
            yield $version . ' revoke' => [$version, 'REVOKE ALL ON PROCEDURE * FROM u'];
            yield $version . ' trigger' => [$version, 'CREATE TRIGGER d.tr BEFORE INSERT ON other.t FOR EACH ROW SET @x=1'];
        }
    }
}
