<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\PartitionRule;

#[CoversClass(PartitionRule::class)]
#[Medium]
final class PartitionRuleTest extends TestCase
{
    public function testPartitioningLowersTheClauseOfEveryGeneration(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY KEY (a)')->toString());
    }

    public function testMarkedLowersThe56Clause(): void
    {
        self::assertSame('ALTER TABLE t FORCE PARTITION BY HASH (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t FORCE PARTITION BY HASH (a)')->toString());
    }

    public function testEntryLowersThePartitionEntry(): void
    {
        self::assertSame('PARTITION BY KEY ()', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('PARTITION BY KEY ()')->toString());
    }

    public function testClauseLowersEveryPart(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) PARTITIONS 1 SUBPARTITION BY KEY (b) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN (1))', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY RANGE (a) PARTITIONS 1 SUBPARTITION BY KEY (b) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN (1))')->toString());
    }

    public function testMethodLowersEveryMethod(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LIST COLUMNS (a)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t PARTITION BY LIST COLUMNS (a)')->toString());
    }

    public function testSubpartitioningLowersHash(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (b)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (b)')->toString());
    }

    public function testLinearLowersLinear(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LINEAR HASH (a)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY LINEAR HASH (a)')->toString());
    }

    public function testAlgorithmLowersTheAlgorithm(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY ALGORITHM = 1 (a)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY KEY ALGORITHM = 1 (a)')->toString());
    }

    public function testCountLowersThePartitionCount(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY () PARTITIONS 4', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY KEY () PARTITIONS 4')->toString());
    }

    public function testExpressionLowersTheFunction(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a * 2)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t PARTITION BY RANGE (a * 2)')->toString());
    }

    public function testRememberedLowersThe56Function(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY RANGE (a)')->toString());
    }

    public function testColumnsLowersAnEmptyList(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE COLUMNS ()', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY RANGE COLUMNS ()')->toString());
    }

    public function testColumnLowersOneName(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (a, b)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t PARTITION BY KEY (a, b)')->toString());
    }
}
