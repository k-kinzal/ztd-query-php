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
final class ProjectionWarningTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET NAMES utf8mb4;CREATE TEMPORARY TABLE projection_warning(s VARCHAR(10));INSERT INTO projection_warning VALUES('12x'),('3x'),('-2x');SET @x=0;";
        foreach (['@x:=s', 'CONCAT(@x:=s)', 'IF(@x:=1,s,s)', "IFNULL(@x:=s,'0')", "COALESCE(@x:=s,'0')", 's', 'CONCAT(s)', "IFNULL(s,'0')", "COALESCE(s,'0')", "GREATEST(s,'0')", "LEAST(s,'z')"] as $projection) {
            foreach (['ABS(v)', 'ABS((+v))', 'v+0'] as $key) {
                foreach (['', ' LIMIT 1 OFFSET 1'] as $bounds) {
                    yield $projection . ' ' . $key . $bounds => [$setup . 'SELECT ' . $projection . ' AS v FROM projection_warning ORDER BY ' . $key . $bounds . ';SHOW WARNINGS;SELECT @x'];
                }
            }
        }
        foreach (["IFNULL(s,'0')", "COALESCE(s,'0')", "GREATEST(s,'0')", "LEAST(s,'z')"] as $expression) {
            foreach (['ABS(' . $expression . ')', '(' . $expression . ')+0'] as $projection) {
                yield 'direct ' . $projection => [$setup . 'SELECT ' . $projection . ' FROM projection_warning;SHOW WARNINGS'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testProjectionWarningsAndValuesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
