<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class WindowAssignmentStorageTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['latin1', 'utf8mb4'] as $charset) {
            foreach (['DATE' => ['2001-01-02', '2001-01-03'], 'TIME(3)' => ['10:20:30.123', '11:20:30.456'], 'DATETIME(3)' => ['2001-01-02 10:20:30.123', '2001-01-03 11:20:30.456']] as $type => [$first, $last]) {
                $setup = 'SET NAMES ' . $charset . '; CREATE TEMPORARY TABLE window_assignment_storage(n INT,d ' . $type . "); INSERT INTO window_assignment_storage VALUES(1,'" . $first . "'),(2,'" . $last . "'); SET @x=0; ";
                foreach (['', ' GROUP BY d', ' GROUP BY d WITH ROLLUP'] as $group) {
                    foreach (['', 'DISTINCT '] as $distinct) {
                        foreach (['', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 5', ' ORDER BY v DESC LIMIT 1 OFFSET 5', ' ORDER BY 1 DESC LIMIT 1', ' LIMIT 0'] as $tail) {
                            yield $charset . $type . $group . $distinct . $tail => [$setup . 'SELECT ' . $distinct . '@x:=FIRST_VALUE(d) OVER () AS v FROM window_assignment_storage' . $group . $tail . '; SELECT @x,CHARSET(@x),COLLATION(@x)'];
                        }
                    }
                }
            }
        }
        yield from self::providerParents();
        yield from self::providerKeyOrder();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerParents(): iterable
    {
        $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE window_assignment_parent(n INT,d DATE); INSERT INTO window_assignment_parent VALUES(1,'2001-01-02'),(2,'2001-01-03'); SET @x=0; ";
        foreach (['@x:=DATE_ADD(FIRST_VALUE(d) OVER (),INTERVAL 1 DAY)', '@x:=CAST(FIRST_VALUE(d) OVER () AS DATE)', '@x:=IF(1,FIRST_VALUE(d) OVER (),NULL)', 'CONCAT(@x:=FIRST_VALUE(d) OVER ())', 'CAST(@x:=FIRST_VALUE(d) OVER () AS DATE)'] as $value) {
            foreach (['', 'DISTINCT '] as $distinct) {
                foreach (['', ' LIMIT 1 OFFSET 3', ' ORDER BY v DESC LIMIT 1 OFFSET 3'] as $tail) {
                    yield $value . $distinct . $tail => [$setup . 'SELECT ' . $distinct . $value . ' AS v FROM window_assignment_parent' . $tail . '; SELECT @x,CHARSET(@x),COLLATION(@x)'];
                }
            }
        }
        foreach ([' GROUP BY n', ' GROUP BY n WITH ROLLUP'] as $group) {
            foreach (['', 'DISTINCT '] as $distinct) {
                yield 'aggregate ' . $group . $distinct => [$setup . 'SELECT ' . $distinct . '@x:=MIN(MIN(d)) OVER () AS v FROM window_assignment_parent' . $group . '; SELECT @x,CHARSET(@x),COLLATION(@x)'];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerKeyOrder(): iterable
    {
        $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE window_assignment_keys(n INT,d DATE); INSERT INTO window_assignment_keys VALUES(1,'2001-01-02'),(2,'2001-01-03'); SET @x=0; ";
        foreach (['@x:=FIRST_VALUE(d) OVER () AS a,@x:=LAST_VALUE(d) OVER () AS b', '@x:=FIRST_VALUE(d) OVER () AS a,@x:=ROW_NUMBER() OVER () AS b'] as $projection) {
            foreach (['a', 'b', 'a,b', 'b,a'] as $order) {
                foreach (['', 'DISTINCT '] as $distinct) {
                    yield 'keys ' . $projection . $order . $distinct => [$setup . 'SELECT ' . $distinct . $projection . ' FROM window_assignment_keys ORDER BY ' . $order . ' LIMIT 1 OFFSET 5; SELECT @x,CHARSET(@x),COLLATION(@x)'];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testStoredAssignmentReadsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
