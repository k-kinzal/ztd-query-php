<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\PartitionText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(PartitionText::class)]
#[Small]
final class PartitionTextTest extends TestCase
{
    public function testTextWritesTheListsWithNullFirst(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE h (a INT) PARTITION BY LIST (a) (PARTITION p0 VALUES IN (3,1,NULL,-2))');

        $result1 = $session->query('SHOW CREATE TABLE h')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame("CREATE TABLE `h` (\n  `a` int DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci\n/*!50100 PARTITION BY LIST (`a`)\n(PARTITION p0 VALUES IN (NULL,3,1,-2) ENGINE = InnoDB) */", $result1->rows[0][1]);
    }

    public function testKeyWritesTheAlgorithmInItsOwnComment(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE i (a INT) PARTITION BY KEY ALGORITHM=1 (a) PARTITIONS 2');

        $result2 = $session->query('SHOW CREATE TABLE i')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result2);
        self::assertStringEndsWith("/*!50100 PARTITION BY KEY */ /*!50611 ALGORITHM = 1 */ /*!50100 (a)\nPARTITIONS 2 */", (string) $result2->rows[0][1]);
    }

    public function testValuesWritesColumnsValuesQuoted(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE e (a INT, b VARCHAR(3)) PARTITION BY LIST COLUMNS (a, b) (PARTITION p0 VALUES IN ((1,'x'),(NULL,'y')))");

        $result3 = $session->query('SHOW CREATE TABLE e')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result3);
        self::assertStringEndsWith("/*!50500 PARTITION BY LIST  COLUMNS(a,b)\n(PARTITION p0 VALUES IN ((1,'x'),(NULL,'y')) ENGINE = InnoDB) */", (string) $result3->rows[0][1]);
    }

    public function testValueQuotesTextAndTemporalValues(): void
    {
        self::assertSame(["'2020-01-01'", '5', 'NULL'], [(new PartitionText())->value('2020-01-01', Kind::Date), (new PartitionText())->value(5, Kind::Integer), (new PartitionText())->value(null, null)]);
    }
}
