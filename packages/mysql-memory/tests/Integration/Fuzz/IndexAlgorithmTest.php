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
final class IndexAlgorithmTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $listing = 'SHOW WARNINGS; SHOW CREATE TABLE algorithm_probe; SHOW INDEX FROM algorithm_probe; ';
        $query = 'INSERT INTO algorithm_probe VALUES(65,1),(0,2),(65,1); SELECT DISTINCT a,b FROM algorithm_probe ORDER BY a,b';
        foreach (['InnoDB', 'MyISAM', 'MEMORY'] as $engine) {
            foreach (['', ' USING BTREE', ' USING HASH'] as $algorithm) {
                yield $engine . $algorithm => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8),b BIT(8),KEY k' . $algorithm . '(a,b)) ENGINE=' . $engine . '; ' . $listing . $query];
            }
        }
        $setup = 'CREATE TABLE algorithm_probe(a BIT(8),b BIT(8),KEY k(a,b) USING BTREE) ENGINE=MEMORY; ';
        yield 'last written algorithm' => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8),b BIT(8),KEY USING BTREE(a,b) USING HASH USING BTREE) ENGINE=MEMORY; ' . $listing . $query];
        yield 'alter add' => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8),b BIT(8)) ENGINE=MEMORY; ALTER TABLE algorithm_probe ADD KEY k(a,b) USING BTREE; ' . $listing . $query];
        yield 'alter unrelated column' => [$setup . 'ALTER TABLE algorithm_probe ADD c INT DEFAULT 1; ' . $listing . 'INSERT INTO algorithm_probe(a,b) VALUES(65,1),(0,2),(65,1); SELECT DISTINCT a,b FROM algorithm_probe ORDER BY a,b'];
        yield 'copy' => [$setup . 'CREATE TABLE algorithm_copy LIKE algorithm_probe; SHOW CREATE TABLE algorithm_copy; SHOW INDEX FROM algorithm_copy'];
        yield 'change engine' => ['CREATE TABLE algorithm_probe(a BIT(8),b BIT(8),KEY k(a,b) USING HASH) ENGINE=MEMORY; ALTER TABLE algorithm_probe ENGINE=InnoDB; ' . $listing . $query];
        yield 'primary hash avoids duplicate storage' => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8) PRIMARY KEY,b BIT(8)) ENGINE=MEMORY; ' . $listing . 'INSERT INTO algorithm_probe VALUES(65,1),(0,2); SELECT DISTINCT a,b FROM algorithm_probe ORDER BY a,b'];
        yield 'different structures are not duplicates' => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8),b BIT(8),KEY k(a,b) USING BTREE,KEY j(a,b) USING HASH) ENGINE=MEMORY; ' . $listing . $query];
        yield 'replaced unsupported algorithm' => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8),b BIT(8),KEY k USING HASH(a,b) USING BTREE); ' . $listing . $query];
        yield 'populated unique hash count' => ['CREATE TEMPORARY TABLE algorithm_probe(a BIT(8),b BIT(8),UNIQUE KEY k(a,b)) ENGINE=MEMORY; ' . $listing . 'INSERT INTO algorithm_probe VALUES(65,1),(0,2); SHOW INDEX FROM algorithm_probe'];
        yield 'statistics' => [$setup . "SELECT INDEX_NAME,INDEX_TYPE,COLLATION FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='fz' AND TABLE_NAME='algorithm_probe' ORDER BY INDEX_NAME,SEQ_IN_INDEX"];
    }

    #[DataProvider('providerStatements')]
    public function testIndexStorageAndOrderedResultMetadataMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
        self::assertNull($target->run($target->memory, 'root', '', $sql)['error'] ?? null);
    }
}
