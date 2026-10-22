<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\AggregateOutputs;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateOutputs::class)]
#[Medium]
final class AggregateOutputsTest extends TestCase
{
    public function testOriginRetainsMissingDefaultsOnlyForStoredMinimumFields(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t(a INT PRIMARY KEY,g INT)');
        $session->query('INSERT INTO t VALUES(1,0),(2,0)');
        $stored = $session->query('SELECT MIN(a),COUNT(*) FROM t GROUP BY g ORDER BY MIN(a)')[0];
        $streamed = $session->query('SELECT MIN(a),COUNT(*) FROM t GROUP BY a')[0];

        self::assertInstanceOf(ResultSet::class, $stored);
        self::assertInstanceOf(ResultSet::class, $streamed);
        self::assertSame([36864, 32769], array_column($stored->columns, 'flags'));
        self::assertSame([32896, 32897], array_column($streamed->columns, 'flags'));
        self::assertSame([['1', '2']], $stored->rows);
        self::assertSame([['1', '1'], ['2', '1']], $streamed->rows);
    }

    public function testStreamingRetainsExpressionFlagsAfterAnIndexSuppliesGroupOrder(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t(a INT PRIMARY KEY,g INT)');
        $session->query('INSERT INTO t VALUES(1,0),(2,0)');
        $session->query('CREATE INDEX ig ON t(g)');
        $streamed = $session->query('SELECT MIN(a),COUNT(*) FROM t GROUP BY g')[0];

        self::assertInstanceOf(ResultSet::class, $streamed);
        self::assertSame([32896, 32897], array_column($streamed->columns, 'flags'));
        self::assertSame([['1', '2']], $streamed->rows);
    }
    public function testDomainKeepsStreamingTemporalAndStringExpressionMetadata(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t(id INT PRIMARY KEY, d DATE, s VARCHAR(20))');
        $session->query("INSERT INTO t VALUES(1,'2020-01-01','x')");
        $streamed = $session->query('SELECT MIN(d),MIN(s) FROM t GROUP BY id')[0];
        $stored = $session->query('SELECT MIN(d),MIN(s) FROM t GROUP BY id ORDER BY MIN(d)')[0];

        self::assertInstanceOf(ResultSet::class, $streamed);
        self::assertInstanceOf(ResultSet::class, $stored);
        self::assertSame([255, 255], array_column($streamed->columns, 'charset'));
        self::assertSame([0, 31], array_column($streamed->columns, 'decimals'));
        self::assertSame([63, 255], array_column($stored->columns, 'charset'));
        self::assertSame(['MIN(d)', 'MIN(s)'], array_column($stored->columns, 'originalName'));
        self::assertSame($streamed->rows, $stored->rows);
    }

    public function testCopiedNamesTheStoredAggregateAfterIndexedGrouping(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t(a INT PRIMARY KEY)');
        $session->query('INSERT INTO t VALUES(1),(2)');
        $result = $session->query('SELECT MIN(a) AS first,COUNT(*) AS n FROM t GROUP BY a ORDER BY MIN(a)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['first', 'n'], array_column($result->columns, 'originalName'));
        self::assertSame([['1', '1'], ['2', '1']], $result->rows);
    }

}
