<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\Ordering;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ordering::class)]
#[Small]
final class OrderingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOrders(): iterable
    {
        yield 'direct key' => ['n DESC', 'b1,a2'];
        yield 'output position' => ['1 DESC', 'b1,a2'];
        yield 'leading prefix' => ['n DESC,k ASC', 'b1,a2'];
        yield 'different expression' => ['n+0 DESC', 'a1,b2'];
        yield 'subtotal key' => ['n DESC,GROUPING(n) DESC', 'a1,b2'];
        yield 'extra key' => ['n DESC,k ASC,n ASC', 'a1,b2'];
    }

    #[DataProvider('providerOrders')]
    public function testDirectionsRequiresEverySortKeyToMatchTheGroupingPrefix(string $order, string $total): void
    {
        $session = (new Instance())->connect();
        $session->query("USE mysql;CREATE TABLE t(n INT,k INT,v VARCHAR(2));INSERT INTO t VALUES(2,1,'b'),(1,1,'a');SET @x=0");
        $result = $session->query('SELECT n,GROUP_CONCAT(v,@x:=@x+1),GROUPING(n,k) FROM t GROUP BY n,k WITH ROLLUP ORDER BY ' . $order)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertContains([null, $total, '3'], $result->rows);
        self::assertSame(2, $session->variables->user('x')[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacy(): iterable
    {
        yield '5.6' => ['5.6.51'];
        yield '5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacy')]
    public function testArrivalRetainsTheFirstDuplicateValueBeforeImplicitGroupSorting(string $version): void
    {
        $session = (new Instance($version))->connect();
        $session->query("USE mysql;SET sql_mode='';CREATE TABLE t(n INT);INSERT INTO t VALUES(0),(NULL),(1)");
        $result = $session->query('SELECT DISTINCT ABS(n) FROM t GROUP BY n')[0];
        $direct = $session->query('SELECT DISTINCT n FROM t GROUP BY n LIMIT 1')[0];
        $ordered = $session->query('SELECT DISTINCT n FROM t GROUP BY n ORDER BY MIN(1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0'], ['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $direct);
        self::assertSame([[null]], $direct->rows);
        self::assertInstanceOf(ResultSet::class, $ordered);
        self::assertSame([['0'], [null], ['1']], $ordered->rows);
    }
}
