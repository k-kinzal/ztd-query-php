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
final class ProjectionSourceWarningTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['CHAR(10) CHARACTER SET latin1', 'VARCHAR(10) CHARACTER SET latin1', 'VARCHAR(10) CHARACTER SET utf8mb4', 'TEXT CHARACTER SET utf8mb4', 'DATE', 'DATETIME'] as $type) {
            foreach (['@x:=s', 'CONCAT(@x:=s)', 'CONCAT(s)', 'CAST(s AS CHAR)'] as $expression) {
                foreach (['ABS(v)', 'v+0'] as $key) {
                    $sql = 'SET NAMES utf8mb4;CREATE TEMPORARY TABLE projection_source(s ' . $type . ");INSERT INTO projection_source VALUES('2001-01-02'),('2001-01-03'),('2001-01-04');SET @x=0;SELECT " . $expression . ' AS v FROM projection_source ORDER BY ' . $key . ';SHOW WARNINGS;SELECT @x';
                    yield $type . ' ' . $expression . ' ' . $key => [$sql];
                    yield 'physical ' . $type . ' ' . $expression . ' ' . $key => [str_replace('CREATE TEMPORARY TABLE', 'CREATE TABLE', $sql)];
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testSourceTypesPreserveProjectionWarnings(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
