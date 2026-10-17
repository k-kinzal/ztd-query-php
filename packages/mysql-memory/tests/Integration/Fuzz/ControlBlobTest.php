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
final class ControlBlobTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['latin1', 'utf8mb4', 'binary'] as $charset) {
            foreach (['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'] as $type) {
                $declaration = $charset === 'binary' ? str_replace('TEXT', 'BLOB', $type) : "{$type} CHARACTER SET {$charset}";
                $queries = [];
                foreach (["IFNULL(a,'')", "COALESCE(a,'')", "IF(TRUE,a,'')", "CASE WHEN TRUE THEN a ELSE '' END", "CONCAT(IFNULL(a,''),'')", "HEX(IFNULL(a,''))", "MIN(IFNULL(a,''))", "NULLIF(a,'')", "CONCAT(NULLIF(a,''),'')", "HEX(NULLIF(a,''))", "NULLIF(MIN(a),'')", "MIN(NULLIF(a,''))", "IFNULL(NULLIF(a,''),'')", "NULLIF(CONCAT(a,''),'')", "NULLIF(COALESCE(a,''),'')", "COALESCE(COALESCE(a,''),'')"] as $expression) {
                    $queries[] = "SELECT {$expression} FROM control_probe";
                }
                yield "{$charset}: {$type}" => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE control_probe(a {$declaration}); INSERT INTO control_probe VALUES('x'),(''),(NULL); " . implode('; ', $queries)];
            }
        }
        foreach (['latin1', 'utf8mb4'] as $charset) {
            foreach ([513, 1024, 4096, 4097, 70000] as $limit) {
                $aggregate = "GROUP_CONCAT(CONVERT('x' USING {$charset}))";
                yield "{$charset}: GROUP_CONCAT {$limit}" => ["SET NAMES utf8mb4; SET group_concat_max_len={$limit}; SELECT NULLIF({$aggregate},''); SELECT IFNULL({$aggregate},''); SELECT CONCAT(IFNULL({$aggregate},''),''); SELECT NULLIF(IFNULL({$aggregate},''),''); SELECT CONCAT(NULLIF({$aggregate},''),'')"];
            }
        }
        foreach (["'x'", "CAST('x' AS CHAR(17000) CHARACTER SET utf8mb4)", 'NULL', '123', '1.2', "CAST('x' AS BINARY(5))"] as $operand) {
            yield "computed: {$operand}" => ["SET NAMES utf8mb4; SELECT NULLIF({$operand},''); SELECT CONCAT(NULLIF({$operand},''),'')"];
        }
        foreach (['VARCHAR(10)', 'CHAR(10)', 'VARBINARY(10)', 'BINARY(10)', "ENUM('x','y')", "SET('x','y')"] as $type) {
            yield "column: {$type}" => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE control_probe(a {$type}); INSERT INTO control_probe VALUES('x'),(NULL); SELECT NULLIF(a,'') FROM control_probe; SELECT MIN(a) FROM control_probe"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testControlResultMetadataAndValuesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
