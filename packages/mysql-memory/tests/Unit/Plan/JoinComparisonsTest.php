<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Instance;
use MySqlMemory\Plan\JoinComparisons;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(JoinComparisons::class)]
#[Medium]
final class JoinComparisonsTest extends TestCase
{
    public function testPlanRetainsHashKeyDifferencesForUnindexedJoins(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(id INT PRIMARY KEY,v VARCHAR(64) COLLATE utf8mb4_unicode_520_ci); INSERT INTO t VALUES (0,'a'),(1,_utf8mb4 X'612000'),(2,'a '),(3,_utf8mb4 X'6100')");
        $joined = $session->query('SELECT COUNT(*) FROM t a JOIN t b ON a.v=b.v')[0];
        $compared = $session->query('SELECT SUM(a.v=b.v) FROM t a CROSS JOIN t b')[0];

        self::assertInstanceOf(ResultSet::class, $joined);
        self::assertInstanceOf(ResultSet::class, $compared);
        self::assertSame([[['10']], [['16']]], [$joined->rows, $compared->rows]);
    }

    public function testScannedKeepsIndexedAndConstantLookupsAsComparisons(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(id INT PRIMARY KEY,v VARCHAR(64) COLLATE utf8mb4_unicode_520_ci); INSERT INTO t VALUES (0,'a'),(1,_utf8mb4 X'612000')");
        $constant = $session->query('SELECT COUNT(*) FROM t a JOIN t b ON a.v=b.v WHERE a.id=0')[0];
        $session->query('ALTER TABLE t ADD KEY(v)');
        $indexed = $session->query('SELECT COUNT(*) FROM t a JOIN t b ON a.v=b.v')[0];

        self::assertInstanceOf(ResultSet::class, $constant);
        self::assertInstanceOf(ResultSet::class, $indexed);
        self::assertSame([[['2']], [['4']]], [$constant->rows, $indexed->rows]);
    }

    public function testConditionRetainsNullSafeEquality(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(id INT PRIMARY KEY,v VARCHAR(64) COLLATE utf8mb4_unicode_520_ci); INSERT INTO t VALUES (0,'a'),(1,_utf8mb4 X'612000'),(2,NULL)");
        $joined = $session->query('SELECT COUNT(*) FROM t a JOIN t b ON a.v<=>b.v')[0];

        self::assertInstanceOf(ResultSet::class, $joined);
        self::assertSame([['3']], $joined->rows);
    }
    public function testRangeKeepsIndexedInequalitiesAsComparisons(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(id INT PRIMARY KEY,v VARCHAR(64) COLLATE utf8mb4_unicode_520_ci); INSERT INTO t VALUES (0,'a'),(1,_utf8mb4 X'612000'),(2,NULL)");
        $joined = $session->query('SELECT COUNT(*) FROM t a JOIN t b ON a.v<=>b.v AND a.id<=b.id')[0];

        self::assertInstanceOf(ResultSet::class, $joined);
        self::assertSame([['4']], $joined->rows);
    }
}
