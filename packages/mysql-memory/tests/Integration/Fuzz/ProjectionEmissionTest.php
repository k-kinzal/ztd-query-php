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
final class ProjectionEmissionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE projection_input(n INT,d DATE); INSERT INTO projection_input VALUES(1,'2001-01-02'),(2,'2001-01-03'),(3,'2001-01-04'); SET @x=0,@y=0; ";
        yield 'constant sorting alias warnings' => [$setup . "SELECT n,'x'+0 AS v FROM projection_input ORDER BY 'y'+0,v,n DESC; SHOW WARNINGS"];
        yield 'retained empty aggregate fields' => ['SELECT (SELECT COUNT(*),MAX(1) FROM DUAL LIMIT 0) <=> (0,NULL), (SELECT COUNT(*),MAX(1) FROM DUAL LIMIT 0 OFFSET 1) <=> (0,NULL)'];
        foreach (['@x:=@x+n AS v', '@x:=d AS v', 'CONCAT(@x:=d) AS v', 'ABS(n) AS v,@y:=@y+1 AS w', '@x:=@x+1 AS w,n+@x AS v', '@x:=@x+n AS w,RAND(1) AS v', '@x:=MIN(n) AS v', '@x:=MIN(MIN(d)) OVER () AS v', 'FIRST_VALUE(SUM(@y:=@y+1)) OVER () AS v', '@x:=MIN(MIN(d)) OVER () AS v,@y:=@y+1 AS w'] as $projection) {
            foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                foreach (['', ' ORDER BY n DESC', ' ORDER BY v DESC', ' ORDER BY ABS(v)'] as $order) {
                    foreach (['', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 5', ' LIMIT 0'] as $bounds) {
                        yield $projection . $calculate . $order . $bounds => [$setup . 'SELECT ' . $calculate . $projection . ' FROM projection_input' . $order . $bounds . '; SELECT @x,@y,CHARSET(@x),COLLATION(@x),FOUND_ROWS()'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testProjectedValuesAndEffectsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
