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
final class StorageEngineTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['NO_ENGINE_SUBSTITUTION', ''] as $mode) {
            foreach ([
                'ALTER TABLE absent ENGINE=missing',
                'ALTER TABLE absent ADD PARTITION (PARTITION p ENGINE missing)',
                'ALTER TABLE absent PARTITION BY HASH(a) (PARTITION p ENGINE missing)',
                'CREATE TABLE engine_table(a INT) ENGINE missing',
                'CREATE TABLE engine_table(a INT) ENGINE missing ENGINE InnoDB',
                'CREATE TABLE engine_table(a INT) ENGINE first ENGINE second',
                'CREATE TABLE engine_table(a INT) ENGINE first PARTITION BY HASH(a) (PARTITION p ENGINE second)',
                'CREATE TABLE engine_table(a INT) PARTITION BY HASH(a) (PARTITION p ENGINE missing)',
                'CREATE TABLE engine_table(a INT) PARTITION BY RANGE(a) SUBPARTITION BY HASH(a) (PARTITION p VALUES LESS THAN MAXVALUE (SUBPARTITION s ENGINE missing))',
                'CREATE TABLE engine_table(a INT,a INT) ENGINE missing',
                'CREATE TABLE t1(a INT) ENGINE missing',
                'CREATE TABLE IF NOT EXISTS t1(a INT) ENGINE missing',
                'CREATE TABLE absent.engine_table(a INT) ENGINE missing',
                'CREATE PROCEDURE engine_proc() CREATE TABLE engine_table(a INT) ENGINE missing',
            ] as $sql) {
                yield $mode . ': ' . $sql => ["SET sql_mode='" . $mode . "'; " . $sql];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testStorageEnginesMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
