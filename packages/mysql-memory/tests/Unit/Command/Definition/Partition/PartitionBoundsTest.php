<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\PartitionBounds;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionBounds::class)]
#[Small]
final class PartitionBoundsTest extends TestCase
{
    public function testPartitionsReadsTheBoundsAndValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE l (a INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN (3, 1, NULL))');
        $table = $session->instance->dictionary->table('d', 'l');

        self::assertNotNull($table);
        self::assertSame([[3], [1], [null]], $table->definition->partitioning->partitions[0]->values ?? []);
    }

    public function testPartitionsRefusesValuesInForRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1480);
        $this->expectExceptionMessage('Only LIST PARTITIONING can use VALUES IN in partition definition');

        $session->query('CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES IN (1))');
    }

    public function testPartitionsRefusesAListConstantTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1495);
        $this->expectExceptionMessage('Multiple definition of same constant in list partitioning');

        $session->query('CREATE TABLE t (a INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN (1), PARTITION p1 VALUES IN (1))');
    }

    public function testBoundRefusesMaxValueBeforeTheLastPartition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1481);
        $this->expectExceptionMessage('MAXVALUE can only be used in last partition definition');

        $session->query('CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN MAXVALUE, PARTITION p1 VALUES LESS THAN (20))');
    }

    public function testBoundRefusesABoundThatDoesNotIncrease(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1493);
        $this->expectExceptionMessage('VALUES LESS THAN value must be strictly increasing for each partition');

        $session->query('CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (5))');
    }

    public function testValuesRefusesNullInLessThan(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1566);
        $this->expectExceptionMessage('Not allowed to use NULL value in VALUES LESS THAN');

        $session->query('CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (NULL))');
    }

    public function testValuesRefusesAValueThatIsNoInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1697);
        $this->expectExceptionMessage("VALUES value for partition 'p0' must have type INT");

        $session->query("CREATE TABLE t (a INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN ('x'))");
    }

    public function testCompareOrdersBoundsWithMaxValueAboveAnything(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE rc (a INT, b CHAR(2)) PARTITION BY RANGE COLUMNS (a, b) (PARTITION p0 VALUES LESS THAN (5, 'm'), PARTITION p1 VALUES LESS THAN (MAXVALUE, MAXVALUE)); INSERT INTO rc VALUES (5, 'a'), (5, 'z'), (4, 'z')");

        $result1 = $session->query('SELECT * FROM rc PARTITION (p0)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['5', 'a'], ['4', 'z']], $result1->rows);
    }

    public function testKeyIsEqualForEqualValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1495);

        $session->query("CREATE TABLE t (a INT, b VARCHAR(3)) PARTITION BY LIST COLUMNS (a, b) (PARTITION p0 VALUES IN ((1, 'x')), PARTITION p1 VALUES IN ((1, 'x')))");
    }
}
