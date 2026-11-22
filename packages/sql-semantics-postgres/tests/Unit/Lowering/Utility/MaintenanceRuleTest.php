<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\MaintenanceRule;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Explain;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Lock;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testStatementLowersEachCommand(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['VACUUM FULL VERBOSE', 'ANALYZE (skip_locked) t', 'CLUSTER t USING i', 'REINDEX TABLE t', 'CHECKPOINT', 'LOCK t'],
            [$semantics->inspect('VACUUM FULL VERBOSE')->toString(), $semantics->inspect('ANALYZE (SKIP_LOCKED) t')->toString(), $semantics->inspect('CLUSTER t USING i')->toString(), $semantics->inspect('REINDEX TABLE t')->toString(), $semantics->inspect('CHECKPOINT')->toString(), $semantics->inspect('LOCK t')->toString()],
        );
    }

    public function testExplainLowersEachSyntax(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['EXPLAIN SELECT 1', 'EXPLAIN ANALYZE SELECT 1', 'EXPLAIN VERBOSE SELECT 1', 'EXPLAIN (costs off) SELECT 1'],
            [$semantics->inspect('EXPLAIN SELECT 1')->toString(), $semantics->inspect('EXPLAIN ANALYZE SELECT 1')->toString(), $semantics->inspect('EXPLAIN VERBOSE SELECT 1')->toString(), $semantics->inspect('EXPLAIN (COSTS off) SELECT 1')->toString()],
        );
    }

    public function testExplainedLowersEveryExplainableStatement(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['EXPLAIN INSERT INTO t VALUES (1)', 'EXPLAIN UPDATE t SET a = 1', 'EXPLAIN DELETE FROM t', 'EXPLAIN DECLARE c CURSOR FOR SELECT 1', 'EXPLAIN CREATE TABLE x AS SELECT 1', 'EXPLAIN REFRESH MATERIALIZED VIEW v', 'EXPLAIN EXECUTE p (1)'],
            [
                $semantics->inspect('EXPLAIN INSERT INTO t VALUES (1)')->toString(),
                $semantics->inspect('EXPLAIN UPDATE t SET a = 1')->toString(),
                $semantics->inspect('EXPLAIN DELETE FROM t')->toString(),
                $semantics->inspect('EXPLAIN DECLARE c CURSOR FOR SELECT 1')->toString(),
                $semantics->inspect('EXPLAIN CREATE TABLE x AS SELECT 1')->toString(),
                $semantics->inspect('EXPLAIN REFRESH MATERIALIZED VIEW v')->toString(),
                $semantics->inspect('EXPLAIN EXECUTE p(1)')->toString(),
            ],
        );
        self::assertInstanceOf(Explain::class, $semantics->inspect('EXPLAIN MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE')->statement);
    }

    public function testTargetsLowersTheTablesAndColumns(): void
    {
        $statement = (new Inspection())->inspect('VACUUM (ANALYZE) a (x, y), b')->statement;
        self::assertInstanceOf(Vacuum::class, $statement);
        self::assertSame([2, 0], [count($statement->targets[0]->columns), count($statement->targets[1]->columns)]);
    }

    public function testIndexIsNullWithoutUsing(): void
    {
        $statement = (new Inspection())->inspect('CLUSTER (VERBOSE) t')->statement;
        self::assertInstanceOf(Cluster::class, $statement);
        self::assertNull($statement->index);
    }

    public function testRebuiltLowersEachTarget(): void
    {
        $semantics = new Inspection();
        $statements = [$semantics->inspect('REINDEX INDEX i')->statement, $semantics->inspect('REINDEX TABLE t')->statement, $semantics->inspect('REINDEX SCHEMA s')->statement, $semantics->inspect('REINDEX SYSTEM')->statement, $semantics->inspect('REINDEX DATABASE d')->statement];
        self::assertSame(
            ReindexTarget::cases(),
            array_map(static fn (object $statement): ?ReindexTarget => $statement instanceof Reindex ? $statement->target : null, $statements),
        );
    }

    public function testReindexOptionsLowersTheParenthesizedList(): void
    {
        $statement = (new Inspection())->inspect('REINDEX (VERBOSE, CONCURRENTLY) INDEX i')->statement;
        self::assertInstanceOf(Reindex::class, $statement);
        self::assertSame(['verbose', 'concurrently'], [$statement->options[0]->option(), $statement->options[1]->option()]);
    }

    public function testLockLowersTheModeAndNowait(): void
    {
        $statement = (new Inspection())->inspect('LOCK TABLE t IN ROW EXCLUSIVE MODE NOWAIT')->statement;
        self::assertInstanceOf(Lock::class, $statement);
        self::assertSame([LockMode::RowExclusive, true], [$statement->mode, $statement->nowait]);
    }
}
