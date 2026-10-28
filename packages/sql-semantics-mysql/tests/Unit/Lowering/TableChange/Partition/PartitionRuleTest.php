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
    public function testExpressionRetainsIntrinsicOperandsWithTheirOccurrenceRanges(): void
    {
        $sql = 'ALTER TABLE t PARTITION BY HASH(USER())';
        $statement = (new Semantics(Dialect::MySql))->analyze($sql);
        $problem = $statement->diagnostics()[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression::class, $problem);
        $origin = $problem->expression->source();
        self::assertSame(strpos($sql, 'USER()'), $origin?->offset);
        self::assertSame(6, $origin->length);
        self::assertSame('ALTER TABLE t PARTITION BY HASH (USER())', $statement->toString());
    }

    public function testPartitioningLowersTheClauseOfEveryGeneration(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY KEY (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY KEY (a)')->toString());
    }

    public function testMarkedLowersThe56Clause(): void
    {
        self::assertSame('ALTER TABLE t FORCE PARTITION BY HASH (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t FORCE PARTITION BY HASH (a)')->toString());
    }

    public function testEntryRejectsThePartitionEntry(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('A partitioning clause is not a statement a client can send.');

        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $entry = $semantics->parser()->parse('PARTITION BY KEY ()')->find('partition_entry')[0];
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-5.7.44.php'), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());

        (new PartitionRule($lowering))->entry($entry);
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

    public function testKeyAlgorithmKeepsHexadecimalSpellingOfAValidHash(): void
    {
        self::assertSame("ALTER TABLE t PARTITION BY KEY ALGORITHM = x'01' (a)", (new Semantics(Dialect::MySql))->analyze("ALTER TABLE t PARTITION BY KEY ALGORITHM = x'01' (a)")->toString());
    }

    public function testCompletedRetainsTheAlgorithmRefusalBeforeLaterSyntax(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $node = $semantics->parser()->parse("CREATE TABLE t(a INT) PARTITION BY KEY ALGORITHM = x'' (a)")->find('opt_key_algo')[0];
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering(\SqlSemantics\Lowering\Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-5.7.44.php'), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $notice = (new PartitionRule($lowering))->completed($node);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm::class, $notice?->problem);
        self::assertTrue($notice->aborts);
        self::assertSame(51, $notice->problem->offset);
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
    public function testPartitioningRetainsTheSubpartitionFuzzerCounterexample(): void
    {
        $sql = 'ALTER TABLE t PARTITION BY KEY () SUBPARTITION BY HASH ((USER())) SUBPARTITIONS 1';
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
        self::assertSame($sql, $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression::class, $statement->diagnostics()[0]);
    }

}
