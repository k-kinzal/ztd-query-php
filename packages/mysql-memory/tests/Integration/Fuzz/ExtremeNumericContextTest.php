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
final class ExtremeNumericContextTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "SET NAMES utf8mb4;CREATE TEMPORARY TABLE extreme_numeric(s VARCHAR(10));INSERT INTO extreme_numeric VALUES('12x'),('3x'),('-2x'),(NULL);SET @x=0;";
        foreach (["LEAST(s,'z')", "GREATEST(s,'0')", "LEAST(GREATEST(s,'2x'),'4x')", "LEAST(CONCAT(s),'z')", "LEAST(@x:=s,'z')", "GREATEST(@x:=s,'0')"] as $expression) {
            foreach (['ABS(%s)', '(%s)+0', '(%s)=0'] as $read) {
                yield $expression . ' direct ' . $read => [$setup . 'SELECT ' . sprintf($read, $expression) . ' FROM extreme_numeric;SHOW WARNINGS;SELECT @x'];
                yield $expression . ' alias ' . $read => [$setup . 'SELECT ' . $expression . ' AS v FROM extreme_numeric ORDER BY ' . sprintf($read, 'v') . ';SHOW WARNINGS;SELECT @x'];
            }
        }
        foreach (["LEAST('11','45','2')", "LEAST('12x','z')", "LEAST(GREATEST('11','2'),'4')"] as $expression) {
            yield 'constant ' . $expression => [$setup . 'SELECT s,(' . $expression . ')=0 FROM extreme_numeric;SHOW WARNINGS'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testNumericConsumersPreserveExtremaValuesAndDiagnostics(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
