<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\Addition;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Addition::class)]
#[Small]
final class AdditionTest extends TestCase
{
    public function testRewritePreservesAnImplicitSubpartitionLayoutAndExistingRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT) PARTITION BY RANGE(a) SUBPARTITION BY HASH(a) (PARTITION p0 VALUES LESS THAN(10)); INSERT INTO t VALUES(1)');
        $session->query('ALTER TABLE t ADD PARTITION (PARTITION p1 VALUES LESS THAN(20) (SUBPARTITION s0)); INSERT INTO t VALUES(11)');
        $result = $session->query('SELECT * FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['11']], $result->rows);
    }

    public function testRewriteRetainsExplicitHashNamesForDuplicateDetection(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT) PARTITION BY HASH(a) PARTITIONS 2');
        $this->expectExceptionCode(1517);
        $session->query('ALTER TABLE t ADD PARTITION (PARTITION p1)');
    }
}
