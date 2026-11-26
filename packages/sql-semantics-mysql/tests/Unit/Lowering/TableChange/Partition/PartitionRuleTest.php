<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\PartitionRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PartitionRule::class)]
#[Medium]
final class PartitionRuleTest extends TestCase
{
    public function testPartitioningLowersTheClauseOfEveryGeneration(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (a)', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t PARTITION BY KEY (a)')->toString());
    }

    public function testMarkedLowersThe56Clause(): void
    {
        self::assertSame('ALTER TABLE t FORCE PARTITION BY HASH (a)', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t FORCE PARTITION BY HASH (a)')->toString());
    }

    public function testEntryLowersThePartitionEntry(): void
    {
        self::assertSame('PARTITION BY KEY ()', (new Inspection('mysql-5.7.44'))->diagnose('PARTITION BY KEY ()')->toString());
    }

    public function testClauseLowersEveryPart(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) PARTITIONS 1 SUBPARTITION BY KEY (b) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN (1))', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY RANGE (a) PARTITIONS 1 SUBPARTITION BY KEY (b) SUBPARTITIONS 2 (PARTITION p VALUES LESS THAN (1))')->toString());
    }

    public function testMethodLowersEveryMethod(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LIST COLUMNS (a)', (new Inspection('mysql-5.7.44'))->diagnose('ALTER TABLE t PARTITION BY LIST COLUMNS (a)')->toString());
    }

    public function testSubpartitioningLowersHash(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (b)', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (b)')->toString());
    }

    public function testLinearLowersLinear(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LINEAR HASH (a)', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY LINEAR HASH (a)')->toString());
    }

    public function testAlgorithmLowersTheAlgorithm(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY ALGORITHM = 1 (a)', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY KEY ALGORITHM = 1 (a)')->toString());
    }

    public function testCountLowersThePartitionCount(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY () PARTITIONS 4', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY KEY () PARTITIONS 4')->toString());
    }

    public function testExpressionLowersTheFunction(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a * 2)', (new Inspection('mysql-5.7.44'))->diagnose('ALTER TABLE t PARTITION BY RANGE (a * 2)')->toString());
    }

    public function testRememberedLowersThe56Function(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a)', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t PARTITION BY RANGE (a)')->toString());
    }

    public function testColumnsLowersAnEmptyList(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE COLUMNS ()', (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t PARTITION BY RANGE COLUMNS ()')->toString());
    }

    public function testColumnLowersOneName(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (a, b)', (new Inspection('mysql-5.7.44'))->diagnose('ALTER TABLE t PARTITION BY KEY (a, b)')->toString());
    }
}
