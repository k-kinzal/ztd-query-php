<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\PartitionChange;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionChange::class)]
#[Small]
final class PartitionChangeTest extends TestCase
{
    public function testAffectedCountsOnlyRebuiltPartitionsOnMySql56(): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT) PARTITION BY HASH (a) PARTITIONS 2; INSERT INTO t VALUES (1), (2), (3)');
        $completion = $session->query('ALTER TABLE t REBUILD PARTITION NO_WRITE_TO_BINLOG P1')[0];
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $completion);
        self::assertSame(2, $completion->affectedRows);
        self::assertSame('Records: 2  Duplicates: 0  Warnings: 0', $completion->info);
        $rows = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['1'], ['2'], ['3']], $rows->rows);
    }

    public function testRebuiltPreservesTheRowsAndReportsAnInPlaceCompletion(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT) PARTITION BY HASH (a) PARTITIONS 2; INSERT INTO t VALUES (1), (2)');
        $completion = $session->query('ALTER TABLE t REBUILD PARTITION LOCAL p0')[0];
        $rows = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $completion);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame(0, $completion->affectedRows);
        self::assertSame('Records: 0  Duplicates: 0  Warnings: 0', $completion->info);
        self::assertSame([['1'], ['2']], $rows->rows);
    }

    public function testSelectedRefusesAnUnknownRebuildPartitionBeforeChangingRows(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT) PARTITION BY HASH (a) PARTITIONS 2; INSERT INTO t VALUES (1), (2)');
        $failure = $session->run('ALTER TABLE t REBUILD PARTITION p0, absent')[0];
        $rows = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame(1507, $failure->getCode());
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
        self::assertSame([['1'], ['2']], $rows->rows);
    }

    public function testApplyPartitionsATableAnew(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (15), (30)');

        $completion = $session->query('ALTER TABLE t PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (40))')[0];

        $result1 = $session->query('SELECT * FROM t PARTITION (p1)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $completion);
        self::assertSame([3, [['15'], ['30']]], [$completion->affectedRows, $result1->rows]);
    }

    public function testApplyRefusesARowNoPartitionHolds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (30)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1526);
        $this->expectExceptionMessage('Table has no partition for value 30');

        $session->query('ALTER TABLE t PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10))');
    }

    public function testRemovedRefusesATableThatIsNotPartitioned(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1505);

        $session->query('ALTER TABLE t REMOVE PARTITIONING');
    }

    public function testCountAnswersTheNumberOfPartitions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) PARTITION BY HASH (a) PARTITIONS 2; ALTER TABLE t ADD PARTITION PARTITIONS 2');

        $result2 = $session->query('SHOW CREATE TABLE t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertStringEndsWith('PARTITIONS 4 */', (string) $result2->rows[0][1]);
    }

    public function testCoalescedRefusesZeroPartitions(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT) PARTITION BY HASH(a) PARTITIONS 2');
        $this->expectExceptionCode(1515);
        $this->expectExceptionMessage('At least one partition must be coalesced');
        $session->query('ALTER TABLE t COALESCE PARTITION 0');
    }

    public function testCoalescedRefusesRangePartitioning(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE r (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1509);
        $this->expectExceptionMessage('COALESCE PARTITION can only be used on HASH/KEY partitions');

        $session->query('ALTER TABLE r COALESCE PARTITION 1');
    }

    public function testDroppedDropsThePartitionWithItsRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (40)); INSERT INTO t VALUES (1), (15); ALTER TABLE t DROP PARTITION p0');

        $result3 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([['15']], $result3->rows);
    }

    public function testDroppedRefusesHashPartitioning(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) PARTITION BY HASH (a) PARTITIONS 2');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1512);
        $this->expectExceptionMessage('DROP PARTITION can only be used on RANGE/LIST partitions');

        $session->query('ALTER TABLE t DROP PARTITION p0');
    }

    public function testTruncatedEmptiesAPartition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (40)); INSERT INTO t VALUES (1), (15); ALTER TABLE t TRUNCATE PARTITION p1');

        $result4 = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertSame([['1']], $result4->rows);
    }
}
