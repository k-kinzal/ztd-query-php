<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame('ALTER TABLE t PARTITION BY LIST COLUMNS (a) (PARTITION p VALUES IN (1))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t PARTITION BY LIST COLUMNS (a) (PARTITION p VALUES IN (1))')->toString());
    }

    public function testSubpartitioningLowersHash(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (b) (PARTITION p VALUES LESS THAN (1))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (b) (PARTITION p VALUES LESS THAN (1))')->toString());
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
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a * 2) (PARTITION p VALUES LESS THAN (1))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t PARTITION BY RANGE (a * 2) (PARTITION p VALUES LESS THAN (1))')->toString());
    }

    public function testRememberedLowersThe56Function(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) (PARTITION p VALUES LESS THAN (1))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY RANGE (a) (PARTITION p VALUES LESS THAN (1))')->toString());
    }

    public function testColumnsLowersAnEmptyList(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE COLUMNS () (PARTITION p VALUES LESS THAN (1))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t PARTITION BY RANGE COLUMNS () (PARTITION p VALUES LESS THAN (1))')->toString());
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


    /**
     * @return iterable<string, array{string, string, string, int, bool}>
     */
    public static function providerAgreements(): iterable
    {
        $count = 'CREATE TABLE t (a INT) PARTITION BY HASH (a) PARTITIONS 3 (PARTITION x, PARTITION y)';
        $missing = 'ALTER TABLE t PARTITION BY LIST (a)';
        yield 'modern count mismatch at the opening parenthesis' => ['mysql-8.4.7', $count, 'Wrong number of partitions defined, mismatch with previous setting', (int) strpos($count, '(PARTITION'), false];
        yield 'legacy count mismatch at the closing parenthesis' => ['mysql-5.7.44', $count, 'Wrong number of partitions defined, mismatch with previous setting', strlen($count) - 1, true];
        yield 'modern missing definitions once read' => ['mysql-8.4.7', $missing, 'For LIST partitions each partition must be defined.', strlen($missing), false];
        yield 'legacy missing definitions while reading' => ['mysql-5.6.51', $missing, 'For LIST partitions each partition must be defined.', strlen($missing), true];
    }

    #[DataProvider('providerAgreements')]
    public function testAgreeRefusesDefinitionsTheMethodOrCountExcludes(string $release, string $sql, string $message, int $end, bool $aborts): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze($sql);
            self::fail('The partitioning must be refused.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            $problem = $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail ? $failure->problem->problem : $failure->problem;
            self::assertSame([$message, $end, $aborts], [$problem->message(), $failure->end, $failure->aborts]);
        }
    }

    public function testAgreeAcceptsACountEqualToTheDefinitions(): void
    {
        self::assertSame('CREATE TABLE t (a INT) PARTITION BY KEY (a) PARTITIONS 2 (PARTITION x, PARTITION y)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT) PARTITION BY KEY (a) PARTITIONS 2 (PARTITION x, PARTITION y)')->toString());
    }
}
