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
final class BlobExpressionBoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'] as $type) {
            foreach (['latin1', 'utf8mb4'] as $charset) {
                foreach (['NULL', 'utf8mb4'] as $results) {
                    foreach (['s', '@x:=s', 'CONCAT(@x:=s)', 'HEX(@x:=s)', 'HEX(s)', 'HEX(HEX(@x:=s))', 'LEFT(@x:=s,1000000)', 'CONCAT(CONCAT(@x:=s))', 'CONCAT(CONCAT(s))'] as $expression) {
                        yield $type . ' ' . $charset . ' ' . $results . ' ' . $expression => ['SET NAMES utf8mb4;CREATE TEMPORARY TABLE blob_expression(s ' . $type . ' CHARACTER SET ' . $charset . ");INSERT INTO blob_expression VALUES('abc'),(NULL);SET character_set_results=" . $results . ';SELECT ' . $expression . ' FROM blob_expression'];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testBlobExpressionBoundsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
