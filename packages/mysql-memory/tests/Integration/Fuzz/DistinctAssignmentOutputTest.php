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
final class DistinctAssignmentOutputTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET NAMES utf8mb4;CREATE TEMPORARY TABLE distinct_assignment(n INT,d DATE);INSERT INTO distinct_assignment VALUES(3,'2001-01-03'),(1,'2001-01-01'),(2,'2001-01-02');SET @x=0;";
        foreach (['@x:=@x+n', '@x:=@x+1', '@x:=d', 'CONCAT(@x:=d)'] as $projection) {
            foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                foreach (['', ' ORDER BY v', ' ORDER BY v DESC'] as $order) {
                    foreach (['', ' LIMIT 1 OFFSET 1', ' LIMIT 1 OFFSET 5', ' LIMIT 0'] as $bounds) {
                        yield $projection . ' ' . $calculate . $order . $bounds => [$setup . 'SELECT ' . $calculate . 'DISTINCT ' . $projection . ' AS v FROM distinct_assignment' . $order . $bounds . ';SELECT @x,CHARSET(@x),COLLATION(@x),FOUND_ROWS()'];
                    }
                }
            }
        }
        $setup = "SET NAMES utf8mb4;CREATE TEMPORARY TABLE distinct_field_controls(n INT,d DATE,b VARCHAR(10));INSERT INTO distinct_field_controls VALUES(3,'2001-01-03','three'),(1,'2001-01-01','one'),(2,'2001-01-02','two');SET @x=0;";
        foreach (["CONCAT('x')", 'CONCAT(d)', '@x:=d', 'CONCAT(@x:=d)', "@x:='x'", 'b', 'CONCAT(b)', '@x:=n'] as $projection) {
            foreach (['', ' WHERE 0', ' LIMIT 0', ' ORDER BY v', ' LIMIT 1', ' CALC LIMIT 0'] as $tail) {
                $calculate = str_contains($tail, 'CALC') ? 'SQL_CALC_FOUND_ROWS ' : '';
                yield 'field control ' . $projection . $tail => [$setup . 'SELECT ' . $calculate . 'DISTINCT ' . $projection . ' AS v FROM distinct_field_controls' . str_replace(' CALC', '', $tail) . ';SELECT @x,CHARSET(@x),FOUND_ROWS()'];
            }
        }
        $setup = 'SET NAMES utf8mb4;CREATE TEMPORARY TABLE constant_assignment(n INT);INSERT INTO constant_assignment VALUES(3),(1),(2);SET @x=0;';
        foreach (["@x:='7' AS v", "@x:='7' AS v,@x AS w", "@x AS w,@x:='7' AS v", "n,@x:='7' AS v"] as $projection) {
            foreach (['', 'SQL_CALC_FOUND_ROWS '] as $calculate) {
                foreach (['', ' ORDER BY v'] as $order) {
                    foreach (['', ' LIMIT 1 OFFSET 1', ' LIMIT 0'] as $limit) {
                        yield 'constant effects ' . $projection . $calculate . $order . $limit => [$setup . 'SELECT ' . $calculate . 'DISTINCT ' . $projection . ' FROM constant_assignment' . $order . $limit . ';SELECT @x,CHARSET(@x),FOUND_ROWS()'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testCopiedAssignmentsPreserveTheirStoredValuesAndMetadata(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
