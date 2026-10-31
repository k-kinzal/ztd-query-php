<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Instance;
use MySqlMemory\Iterator\Transform\PrepareIterator;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrepareIterator::class)]
#[Small]
final class PrepareIteratorTest extends TestCase
{
    public function testInitPreparesUnreadBranchesEvenWhenRuntimeFiltersProduceNoRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(20)); INSERT INTO t VALUES (1,'apple')");
        $result = $session->query('SELECT CASE WHEN 0 THEN (0,1) IN (SELECT s,id FROM t) ELSE 7 END FROM t WHERE RAND(17)<0')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'apple'"]], $warnings->rows);
    }

    public function testReadLeavesUnchosenResultsUnreadWithoutLegacyConditionRechecks(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(20)); INSERT INTO t VALUES (1,'apple')");
        $result = $session->query('SELECT CASE WHEN 0 THEN (0,1) IN (SELECT s,id FROM t) ELSE 7 END')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertCount(1, $warnings->rows);
    }
}
