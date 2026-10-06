<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\MaintenanceRule;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Cluster;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Explain;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Lock;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\LockMode;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum;

#[CoversClass(MaintenanceRule::class)]
#[Medium]
final class MaintenanceRuleTest extends TestCase
{
    public function testStatementLowersEachCommand(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['VACUUM FULL VERBOSE', 'ANALYZE (skip_locked) t', 'CLUSTER t USING i', 'REINDEX TABLE t', 'CHECKPOINT', 'LOCK t'],
            [$semantics->analyze('VACUUM FULL VERBOSE')->toString(), $semantics->analyze('ANALYZE (SKIP_LOCKED) t')->toString(), $semantics->analyze('CLUSTER t USING i')->toString(), $semantics->analyze('REINDEX TABLE t')->toString(), $semantics->analyze('CHECKPOINT')->toString(), $semantics->analyze('LOCK t')->toString()],
        );
    }

    public function testExplainLowersEachSyntax(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['EXPLAIN SELECT 1', 'EXPLAIN ANALYZE SELECT 1', 'EXPLAIN VERBOSE SELECT 1', 'EXPLAIN (costs off) SELECT 1'],
            [$semantics->analyze('EXPLAIN SELECT 1')->toString(), $semantics->analyze('EXPLAIN ANALYZE SELECT 1')->toString(), $semantics->analyze('EXPLAIN VERBOSE SELECT 1')->toString(), $semantics->analyze('EXPLAIN (COSTS off) SELECT 1')->toString()],
        );
    }

    public function testExplainedLowersEveryExplainableStatement(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['EXPLAIN INSERT INTO t VALUES (1)', 'EXPLAIN UPDATE t SET a = 1', 'EXPLAIN DELETE FROM t', 'EXPLAIN DECLARE c CURSOR FOR SELECT 1', 'EXPLAIN CREATE TABLE x AS SELECT 1', 'EXPLAIN REFRESH MATERIALIZED VIEW v', 'EXPLAIN EXECUTE p (1)'],
            [
                $semantics->analyze('EXPLAIN INSERT INTO t VALUES (1)')->toString(),
                $semantics->analyze('EXPLAIN UPDATE t SET a = 1')->toString(),
                $semantics->analyze('EXPLAIN DELETE FROM t')->toString(),
                $semantics->analyze('EXPLAIN DECLARE c CURSOR FOR SELECT 1')->toString(),
                $semantics->analyze('EXPLAIN CREATE TABLE x AS SELECT 1')->toString(),
                $semantics->analyze('EXPLAIN REFRESH MATERIALIZED VIEW v')->toString(),
                $semantics->analyze('EXPLAIN EXECUTE p(1)')->toString(),
            ],
        );
        self::assertInstanceOf(Explain::class, $semantics->analyze('EXPLAIN MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE')->statement);
    }

    public function testTargetsLowersTheTablesAndColumns(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('VACUUM (ANALYZE) a (x, y), b')->statement;
        self::assertInstanceOf(Vacuum::class, $statement);
        self::assertSame([2, 0], [count($statement->targets[0]->columns), count($statement->targets[1]->columns)]);
    }

    public function testIndexIsNullWithoutUsing(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('CLUSTER (VERBOSE) t')->statement;
        self::assertInstanceOf(Cluster::class, $statement);
        self::assertNull($statement->index);
    }

    public function testRebuiltLowersEachTarget(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statements = [$semantics->analyze('REINDEX INDEX i')->statement, $semantics->analyze('REINDEX TABLE t')->statement, $semantics->analyze('REINDEX SCHEMA s')->statement, $semantics->analyze('REINDEX SYSTEM')->statement, $semantics->analyze('REINDEX DATABASE d')->statement];
        self::assertSame(
            ReindexTarget::cases(),
            array_map(static fn (object $statement): ?ReindexTarget => $statement instanceof Reindex ? $statement->target : null, $statements),
        );
    }

    public function testReindexOptionsLowersTheParenthesizedList(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('REINDEX (VERBOSE, CONCURRENTLY) INDEX i')->statement;
        self::assertInstanceOf(Reindex::class, $statement);
        self::assertSame(['verbose', 'concurrently'], [$statement->options[0]->option(), $statement->options[1]->option()]);
    }

    public function testLockLowersTheModeAndNowait(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('LOCK TABLE t IN ROW EXCLUSIVE MODE NOWAIT')->statement;
        self::assertInstanceOf(Lock::class, $statement);
        self::assertSame([LockMode::RowExclusive, true], [$statement->mode, $statement->nowait]);
    }
}
