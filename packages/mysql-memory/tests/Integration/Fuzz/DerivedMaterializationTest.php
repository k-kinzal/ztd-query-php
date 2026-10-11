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
final class DerivedMaterializationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $settings = ['default' => ''];
        if (getenv('MYSQL_VERSION') !== '5.6.51') {
            $settings += ['disabled' => "SET optimizer_switch='derived_merge=off'; "];
        }
        foreach ($settings as $setting => $prefix) {
            foreach (['id', '(SELECT id WHERE TRUE)', '(SELECT id)', '(SELECT (SELECT id))', '(SELECT 1)', '(SELECT 1 WHERE FALSE)', '(SELECT MAX(id) FROM t2)', '(SELECT q.id FROM t2 q WHERE q.id=t1.id)', 'EXISTS(SELECT 1 FROM t2)', 'id IN (SELECT id FROM t2)', '1 IN (SELECT id FROM t2)', 'id = ANY (SELECT id FROM t2)', 'id IN (SELECT 1)', '(id IN (SELECT id FROM t2))+1', '@v:=id', '(SELECT RAND(1) FROM t2 LIMIT 1)'] as $expression) {
                yield $setting . ' ' . $expression => [$prefix . 'SELECT v,v+0 FROM (SELECT ' . $expression . ' AS v FROM t1) d ORDER BY v'];
            }
            yield $setting . ' datetime' => [$prefix . "SELECT v,v+0 FROM (SELECT CAST('2001-02-03 04:05:06' AS DATETIME) AS v FROM t1) d ORDER BY v"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testDerivedMaterializationMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
