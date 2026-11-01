<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Union;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Union\Streaming;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Streaming::class)]
#[Small]
final class StreamingTest extends TestCase
{
    public function testPreparesKeepsDistinctPrefixEffectsBeforeAZeroLimit(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d;USE d;CREATE TABLE t(n INT);INSERT INTO t VALUES(1),(2),(2),(3);SET @x=0');
        $session->query('SELECT @x:=@x+n AS v FROM t WHERE n<=2 UNION SELECT @x:=@x+n FROM t WHERE n=2 UNION ALL SELECT @x:=@x+n FROM t WHERE n=3 LIMIT 0');
        $result = $session->query('SELECT FOUND_ROWS(),@x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '9']], $result->rows);
    }

    public function testApplyMaterializesADistinctPrefixBeforeStreamingTheAllSuffix(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x=0;SELECT @x:=@x+1 AS v UNION SELECT @x:=@x+1 UNION ALL SELECT @x:=@x+1 LIMIT 1');
        $result = $session->query('SELECT @x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerLimits(): iterable
    {
        yield 'distinct' => ['', '', '1'];
        yield 'all' => ['ALL ', '', '1'];
        yield 'distinct offset' => ['', ' OFFSET 1', '3'];
        yield 'all offset' => ['ALL ', ' OFFSET 1', '3'];
    }

    #[DataProvider('providerLimits')]
    public function testApplyStopsOperandAssignmentsAtTheConsumedBound(string $duplicates, string $offset, string $expected): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d;USE d;CREATE TABLE t(n INT);INSERT INTO t VALUES(1),(2),(2),(3);SET @x=0');
        $session->query('SELECT @x:=@x+n AS v FROM t WHERE n<=2 UNION ' . $duplicates . 'SELECT @x:=@x+n FROM t WHERE n>=2 LIMIT 1' . $offset);
        $result = $session->query('SELECT @x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[$expected]], $result->rows);
    }
}
