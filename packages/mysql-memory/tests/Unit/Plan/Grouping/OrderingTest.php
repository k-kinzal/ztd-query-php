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
}
