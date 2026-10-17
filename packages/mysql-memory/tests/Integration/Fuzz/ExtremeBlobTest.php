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
final class ExtremeBlobTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'] as $type) {
            foreach (['MIN', 'MAX'] as $function) {
                foreach (["SELECT {$function}(a) FROM extreme_probe", "SELECT {$function}((a)) FROM extreme_probe", "SELECT (SELECT {$function}(a) FROM extreme_probe)", "SELECT CONCAT({$function}(a),'') FROM extreme_probe", "SELECT HEX({$function}(a)) FROM extreme_probe", "SELECT CONCAT((SELECT {$function}(a) FROM extreme_probe),'')"] as $query) {
                    yield "{$type}: {$query}" => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE extreme_probe(a {$type} CHARACTER SET utf8mb4); INSERT INTO extreme_probe VALUES('x'),('y'); {$query}"];
                }
            }
        }
        foreach (['MIN', 'MAX'] as $function) {
            foreach (["CAST('x' AS CHAR(17000) CHARACTER SET utf8mb4)", "CONCAT(b,'')"] as $expression) {
                yield "computed: {$function}: {$expression}" => ["SET NAMES utf8mb4; SELECT {$function}({$expression}) FROM t1"];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testStringExtremesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
