<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\PartitionDefinitions;
use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionDefinitions::class)]
#[Small]
final class PartitionDefinitionsTest extends TestCase
{
    public function testPartitioningAnswersHowTheTableSplitsItsRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE h (a INT) PARTITION BY HASH (a) PARTITIONS 3');
        $table = $session->instance->dictionary->table('d', 'h');

        self::assertNotNull($table);
        self::assertSame([PartitionMethod::Hash, ['p0', 'p1', 'p2']], [$table->definition->partitioning?->method, array_map(static fn ($partition): string => $partition->name, $table->definition->partitioning->partitions ?? [])]);
    }

    public function testPartitioningRefusesATemporaryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1562);
        $this->expectExceptionMessage('Cannot create temporary table with partitions');

        $session->query('CREATE TEMPORARY TABLE t (a INT) PARTITION BY HASH (a)');
    }

    public function testMethodAnswersTheMethodOfAClause(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE r (a INT, b CHAR(2)) PARTITION BY RANGE COLUMNS (a, b) (PARTITION p0 VALUES LESS THAN (5, 'm'))");
        $table = $session->instance->dictionary->table('d', 'r');

        self::assertNotNull($table);
        self::assertSame(PartitionMethod::RangeColumns, $table->definition->partitioning?->method);
    }

    public function testNamesRefusesRangeWithoutPartitions(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1492);
        $this->expectExceptionMessage('For LIST partitions each partition must be defined');

        $session->query('CREATE TABLE t (a INT) PARTITION BY LIST (a)');
    }

    public function testNamesRefusesAPartitionNamedTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1517);
        $this->expectExceptionMessage('Duplicate partition name p0');

        $session->query('CREATE TABLE t (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p0 VALUES LESS THAN (20))');
    }

    public function testSourceRefusesAColumnOfAnotherTypeThanAnInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1659);
        $this->expectExceptionMessage("Field 'a' is of a not allowed type for this type of partitioning");

        $session->query('CREATE TABLE t (a VARCHAR(5)) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10))');
    }

    public function testSourceRefusesAnExpressionThatIsNoInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1564);
        $this->expectExceptionMessage('This partition function is not allowed');

        $session->query('CREATE TABLE t (a INT) PARTITION BY RANGE (a / 2) (PARTITION p0 VALUES LESS THAN (10))');
    }

    public function testUniqueRefusesAUniqueKeyWithoutThePartitioningColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1503);
        $this->expectExceptionMessage("A UNIQUE INDEX must include all columns in the table's partitioning function (prefixed columns are not considered).");

        $session->query('CREATE TABLE t (a INT, b INT, UNIQUE (a)) PARTITION BY HASH (b)');
    }

    public function testTextWritesTheExpressionAsTheServerStoresIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a (a INT) PARTITION BY RANGE (a + 1) (PARTITION p0 VALUES LESS THAN (1 + 1))');

        $result1 = $session->query('SHOW CREATE TABLE a')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame("CREATE TABLE `a` (\n  `a` int DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n/*!50100 PARTITION BY RANGE ((`a` + 1))\n(PARTITION p0 VALUES LESS THAN (2) ENGINE = InnoDB) */", $result1->rows[0][1]);
    }
}
