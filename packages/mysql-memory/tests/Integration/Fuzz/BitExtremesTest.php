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
final class BitExtremesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['b BIT(8),KEY k(a,b)', 'b BIT(8),KEY k(a,b DESC)', 'b VARCHAR(10),KEY k(a,b(3))'] as $definition) {
            yield "index directions: {$definition}" => ["CREATE TABLE bit_extreme_probe(a BIT(8),{$definition}); INSERT INTO bit_extreme_probe VALUES(65,1),(0,2),(65,1); SELECT DISTINCT a,b FROM bit_extreme_probe ORDER BY a; SELECT DISTINCT a,b FROM bit_extreme_probe ORDER BY a,b; SELECT DISTINCT a,b FROM bit_extreme_probe ORDER BY a DESC,b ASC; SELECT DISTINCT a,b FROM bit_extreme_probe ORDER BY a DESC,b DESC; SELECT DISTINCT a FROM bit_extreme_probe ORDER BY a"];
        }
        foreach (['USE INDEX()', 'IGNORE INDEX(k)', 'USE INDEX(k)', 'FORCE INDEX(k)', 'IGNORE INDEX(K)', 'USE INDEX(j)', 'USE INDEX(j) USE INDEX(k)', 'USE INDEX(k) IGNORE INDEX(k)', 'IGNORE INDEX FOR ORDER BY(k)', 'IGNORE INDEX FOR GROUP BY(k)', 'IGNORE INDEX FOR JOIN(k)', 'IGNORE INDEX FOR ORDER BY(k) IGNORE INDEX FOR GROUP BY(k)'] as $hint) {
            yield "sorted hint: {$hint}" => ["CREATE TABLE bit_extreme_probe(a BIT(8),b BIT(64),KEY k(a,b),KEY j(b)); INSERT INTO bit_extreme_probe VALUES(65,1),(0,2),(65,1); SELECT DISTINCT a,b FROM bit_extreme_probe {$hint} ORDER BY a"];
        }
        foreach (['' => '', 'primary' => ',PRIMARY KEY(a)', 'nullable unique' => ',UNIQUE(a)', 'covering index' => ',KEY(a,b)', 'partial index' => ',KEY(a)'] as $label => $index) {
            yield "sorted fields: {$label}" => ["CREATE TABLE bit_extreme_probe(a BIT(8),b BIT(64){$index}); INSERT INTO bit_extreme_probe VALUES(65,18446744073709551615),(0,0); SELECT DISTINCT a,b FROM bit_extreme_probe z ORDER BY (a); SELECT DISTINCT * FROM bit_extreme_probe ORDER BY 1; SELECT DISTINCT a FROM bit_extreme_probe ORDER BY a; SELECT DISTINCT a FROM bit_extreme_probe ORDER BY a+0; SELECT DISTINCT a FROM bit_extreme_probe ORDER BY a LIMIT 0"];
        }
        foreach ([8 => '(0),(65),(255),(NULL)', 64 => '(0),(18446744073709551615),(NULL)'] as $bits => $values) {
            $setup = "SET NAMES utf8mb4; CREATE TABLE bit_extreme_probe(a BIT({$bits})); INSERT INTO bit_extreme_probe VALUES{$values}; ";
            foreach (['MIN', 'MAX'] as $function) {
                $expression = "{$function}(a)";
                $select = "SELECT {$expression} x FROM bit_extreme_probe";
                yield "{$bits} {$function} direct" => [$setup . "SELECT {$expression},{$expression}+0,CONCAT({$expression},''),HEX({$expression}),COERCIBILITY({$expression}) FROM bit_extreme_probe"];
                yield "{$bits} {$function} scalar" => [$setup . "SELECT ({$select}),({$select})+0,CONCAT(({$select}),''),HEX(({$select}))"];
                yield "{$bits} {$function} derived" => [$setup . "SELECT x,x+0,CONCAT(x,''),HEX(x),COERCIBILITY(x) FROM ({$select}) d"];
                yield "{$bits} {$function} union" => [$setup . "{$select} UNION {$select}"];
                yield "{$bits} {$function} union all" => [$setup . "{$select} UNION ALL {$select}"];
                yield "{$bits} {$function} union consumer" => [$setup . "SELECT x,x+0,CONCAT(x,''),HEX(x),COERCIBILITY(x) FROM ({$select} UNION ALL {$select}) d"];
                yield "{$bits} {$function} stored" => [$setup . "CREATE TABLE bit_extreme_stored AS {$select}; SELECT x,x+0,CONCAT(x,''),HEX(x) FROM bit_extreme_stored; SHOW CREATE TABLE bit_extreme_stored"];
                yield "{$bits} {$function} nullif" => [$setup . "SELECT NULLIF({$expression},NULL),CONCAT(NULLIF({$expression},NULL),'') FROM bit_extreme_probe; CREATE TABLE bit_extreme_stored AS SELECT NULLIF({$expression},NULL) x FROM bit_extreme_probe; SELECT * FROM bit_extreme_stored; SHOW CREATE TABLE bit_extreme_stored"];
            }
            yield "{$bits} grouped keys" => [$setup . 'SELECT a,COUNT(*) FROM bit_extreme_probe GROUP BY a ORDER BY a; SELECT DISTINCT a FROM bit_extreme_probe ORDER BY a; SELECT a FROM bit_extreme_probe UNION SELECT a FROM bit_extreme_probe'];
            yield "{$bits} unique keys" => ["CREATE TABLE bit_extreme_probe(a BIT({$bits}) UNIQUE); INSERT INTO bit_extreme_probe VALUES{$values}; SELECT a FROM bit_extreme_probe ORDER BY a"];
            yield "{$bits} empty" => ["CREATE TABLE bit_extreme_probe(a BIT({$bits})); SELECT MIN(a),MAX(a),MIN(a)+0,CONCAT(MAX(a),'') FROM bit_extreme_probe"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testBitExtremaMatchTheirReportedAndMaterializedTypes(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
        self::assertArrayNotHasKey('error', $target->run($target->memory, 'root', '', $sql));
    }
}
