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
final class TemporaryBlobTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['latin1', 'utf8mb4', 'utf16'] as $charset) {
            foreach (["GROUP_CONCAT(CONVERT(a USING {$charset}) ORDER BY id)", "CAST('x' AS CHAR(17000) CHARACTER SET {$charset})"] as $expression) {
                $derived = "(SELECT {$expression} AS q FROM t1 LIMIT 1) AS s";
                foreach (['q', "CONCAT(q,'')", "CONCAT_WS('',q,q)", "REPLACE(q,'a','b')", 'HEX(q)', "IFNULL(q,'x')"] as $outer) {
                    yield $expression . ': ' . $outer => ["SET NAMES utf8mb4; SELECT {$outer} FROM {$derived}"];
                }
                yield $expression . ': scalar' => ["SET NAMES utf8mb4; SELECT (SELECT q FROM {$derived})"];
                $nested = "(SELECT (SELECT {$expression} FROM t1 LIMIT 1) AS q) AS s";
                yield $expression . ': materialized scalar' => ["SET NAMES utf8mb4; SELECT q FROM {$nested}"];
                yield $expression . ': nested scalar' => ["SET NAMES utf8mb4; SELECT (SELECT q FROM {$nested})"];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testTemporaryStringColumnsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
