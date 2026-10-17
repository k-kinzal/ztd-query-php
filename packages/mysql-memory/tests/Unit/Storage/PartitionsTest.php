<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Storage\Partitions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Partitions::class)]
#[Small]
final class PartitionsTest extends TestCase
{
    public function testHashTakesTheAbsoluteValueAndNullAsTwoToTheSixtyThird(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE h (a INT) PARTITION BY HASH (a) PARTITIONS 3; INSERT INTO h VALUES (1), (2), (3), (4), (-5), (NULL)');

        $result1 = $session->query('SELECT * FROM h PARTITION (p0)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        $result2 = $session->query('SELECT * FROM h PARTITION (p1)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        $result3 = $session->query('SELECT * FROM h PARTITION (p2)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertSame([[['3']], [['1'], ['4']], [['2'], ['-5'], [null]]], [$result1->rows, $result2->rows, $result3->rows]);
    }

    public function testHashMasksTheValueForLinearHash(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE lh (a INT) PARTITION BY LINEAR HASH (a) PARTITIONS 6; INSERT INTO lh VALUES (1), (2), (3), (4), (5), (6), (7), (8), (9), (10)');

        $result4 = $session->query('SELECT a FROM lh PARTITION (p2)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result4);
        self::assertSame([['2'], ['6'], ['10']], $result4->rows);
    }

    public function testLocateRefusesARowNoPartitionHolds(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE l (a INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN (1))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1526);
        $this->expectExceptionMessage('Table has no partition for value NULL');

        $session->query('INSERT INTO l VALUES (NULL)');
    }

    public function testRangePlacesNullInTheFirstPartition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE r (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20)); INSERT INTO r VALUES (15), (NULL), (3)');

        $result5 = $session->query('SELECT * FROM r')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result5);
        self::assertSame([[null], ['3'], ['15']], $result5->rows);
    }

    public function testBelowComparesColumnsValuesItemByItem(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE rc (a INT, b CHAR(2)) PARTITION BY RANGE COLUMNS (a, b) (PARTITION p0 VALUES LESS THAN (5, 'm'), PARTITION p1 VALUES LESS THAN (MAXVALUE, MAXVALUE)); INSERT INTO rc VALUES (5, 'z')");

        $result6 = $session->query('SELECT * FROM rc PARTITION (p1)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result6);
        self::assertSame([['5', 'z']], $result6->rows);
    }

    public function testListedFindsThePartitionThatListsNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE l (a INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN (1, 2), PARTITION p1 VALUES IN (3, NULL)); INSERT INTO l VALUES (NULL), (1), (3)');

        $result7 = $session->query('SELECT * FROM l PARTITION (p1)')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result7);
        self::assertSame([[null], ['3']], $result7->rows);
    }

    public function testEqualTakesNullEqualToNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE l (a INT, b INT) PARTITION BY LIST COLUMNS (a, b) (PARTITION p0 VALUES IN ((1, NULL))); INSERT INTO l VALUES (1, NULL)');

        $result8 = $session->query('SELECT * FROM l')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result8);
        self::assertSame([['1', null]], $result8->rows);
    }

    public function testSelectedRefusesAnUnknownPartition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE r (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1735);

        $session->query('DELETE FROM r PARTITION (zz)');
    }

    public function testPlaceRefusesARowOutsideThePartitionsNamed(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE r (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20))');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1748);
        $this->expectExceptionMessage('Found a row not matching the given partition set');

        $session->query('INSERT INTO r PARTITION (p1) VALUES (1)');
    }

    public function testOrderedReadsPartitionByPartition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE r (a INT) PARTITION BY RANGE (a) (PARTITION p0 VALUES LESS THAN (10), PARTITION p1 VALUES LESS THAN (20))');
        $table = $session->instance->dictionary->table('d', 'r');
        self::assertNotNull($table);
        $partitioning = $table->definition->partitioning;
        self::assertNotNull($partitioning);

        $ordered = (new Partitions($table->definition, $partitioning, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)))->ordered([4 => [15], 5 => [1]], [0, 1]);

        self::assertSame([5 => [1], 4 => [15]], $ordered);
    }
}
