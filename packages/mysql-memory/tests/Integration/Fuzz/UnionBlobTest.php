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
final class UnionBlobTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['latin1', 'utf8mb4', 'utf16'] as $charset) {
            foreach ([1024, 4096] as $limit) {
                foreach (['UNION', 'UNION ALL'] as $operator) {
                    $union = "SELECT GROUP_CONCAT(CONVERT(a USING {$charset}) ORDER BY id) AS q FROM t1 {$operator} SELECT CONVERT('x' USING {$charset})";
                    foreach ([$union, "SELECT ({$union} LIMIT 1)", "SELECT CONCAT(q,'') FROM ({$union}) AS s", "{$union} {$operator} SELECT CONVERT('y' USING {$charset})", "{$union} ORDER BY q", "{$union} LIMIT 1"] as $query) {
                        yield "{$charset}: {$limit}: {$query}" => ["SET NAMES utf8mb4; SET group_concat_max_len={$limit}; {$query}"];
                    }
                }
            }
        }
        foreach (['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'] as $type) {
            $union = "SELECT a AS q FROM union_blob_probe UNION SELECT 'x'";
            foreach ([$union, "SELECT ({$union} LIMIT 1)", "SELECT CONCAT(q,'') FROM ({$union}) AS s"] as $query) {
                yield "{$type}: {$query}" => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE union_blob_probe(a {$type} CHARACTER SET utf8mb4); {$query}"];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testSetStorageBoundsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
