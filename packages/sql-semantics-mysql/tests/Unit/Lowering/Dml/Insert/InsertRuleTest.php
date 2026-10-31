<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\InsertRule;

#[CoversClass(InsertRule::class)]
#[Medium]
final class InsertRuleTest extends TestCase
{
    public function testSetRetainsItsHeadAndEveryAssignmentBeforeRefusal(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT LOW_PRIORITY INTO t SET a=DEFAULT, b=ROW(1,2) ON DUPLICATE KEY UPDATE c=3');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet::class, $operation->body);
        self::assertCount(2, $operation->body->assignments);
        self::assertCount(1, $operation->body->onDuplicate);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority::Low, $operation->body->into->priority);
    }
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('INSERT t SET a = 1 ON DUPLICATE KEY UPDATE a = 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert t set a = 1 on duplicate key update a = 2')->toString());
        self::assertSame('REPLACE t SELECT 1', (new Semantics(Dialect::MySql))->analyze('replace t select 1')->toString());
        self::assertSame('INSERT t (a) VALUES (1) AS n ON DUPLICATE KEY UPDATE a = n.a', (new Semantics(Dialect::MySql))->analyze('insert t (a) values (1) as n on duplicate key update a = n.a')->toString());
    }

    public function testLegacyLowersInsertAndReplace(): void
    {
        self::assertSame('INSERT INTO t SET a = 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('insert into t set a = 1')->toString());
        self::assertSame('REPLACE t SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('replace t select 1')->toString());
    }

    public function testLegacyRetainsTheHintsOfInsertAndReplace(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertSame('INSERT /*+ QB_NAME(`b`) */ INTO t VALUES (1)', $semantics->analyze('INSERT /*+ QB_NAME(b) */ INTO t VALUES (1)')->toString());
        self::assertSame('REPLACE /*+ QB_NAME(`b`) */ INTO t SET a = 2', $semantics->analyze('REPLACE /*+ QB_NAME(b) */ INTO t SET a=2')->toString());
    }

    public function testBuildChoosesRowsOrQuery(): void
    {
        self::assertSame('INSERT t VALUES (1)', (new Semantics(Dialect::MySql))->analyze('insert t values (1)')->toString());
        self::assertSame('INSERT t (a) TABLE u', (new Semantics(Dialect::MySql))->analyze('insert t (a) table u')->toString());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerContradictoryWidths(): iterable
    {
        yield 'unequal rows' => ['INSERT INTO t VALUES (1, 2), (3)', 2];
        yield 'trailing defaults' => ['INSERT INTO t VALUES (1, 2), ()', 2];
        yield 'leading defaults' => ['INSERT INTO t VALUES (), (1, 2)', 2];
        yield 'written column count' => ['INSERT INTO t (a) VALUES (1), (2, 3)', 2];
        yield 'duplicate column and different widths' => ['INSERT INTO t (a, b, a) VALUES (1, 2, 3), (4)', 2];
        yield 'replace defaults' => ['REPLACE INTO `2024-01-01`.t VALUES (DEFAULT, DEFAULT), (), (DEFAULT)', 2];
    }

    #[DataProvider('providerContradictoryWidths')]
    public function testBuildRetainsContradictoryStandaloneWidths(string $sql, int $row): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $operation);
        self::assertTrue($operation->hasExecutionCondition());
        self::assertContains("Column count doesn't match value count at row " . $row, array_map(static fn ($problem): string => $problem->message(), $operation->diagnostics()));
    }

    public function testTargetLowersTheLegacyTable(): void
    {
        self::assertSame('INSERT db.t PARTITION (p) VALUES (1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('insert db.t partition (p) values (1)')->toString());
    }

    public function testPriorityLowersEveryModifier(): void
    {
        self::assertSame('INSERT LOW_PRIORITY t VALUES (1)', (new Semantics(Dialect::MySql))->analyze('insert low_priority t values (1)')->toString());
        self::assertSame('REPLACE LOW_PRIORITY t VALUES (1)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('replace low_priority t values (1)')->toString());
    }

    public function testAliasLowersTheColumnNames(): void
    {
        self::assertSame('INSERT t VALUES (1) AS n (m) ON DUPLICATE KEY UPDATE a = m', (new Semantics(Dialect::MySql))->analyze('insert t values (1) as n (m) on duplicate key update a = m')->toString());
    }

    public function testUpdatesLowersEveryGeneration(): void
    {
        self::assertSame('INSERT t VALUES (1) ON DUPLICATE KEY UPDATE a = 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('insert t values (1) on duplicate key update a = 1')->toString());
    }
}
