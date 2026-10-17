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
final class IndexVisibilityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerStatements(): iterable
    {
        $version = getenv('MYSQL_VERSION');
        if (is_string($version) && str_starts_with($version, '5.')) {
            yield 'unsupported visibility' => ['CREATE TABLE invisible_probe(a INT,KEY k(a) INVISIBLE)', 1064];

            return;
        }
        $setup = 'CREATE TABLE invisible_probe(a BIT(8),b BIT(64),KEY k(a,b) INVISIBLE); INSERT INTO invisible_probe VALUES(65,1),(0,2),(65,1); ';
        $list = "ANALYZE TABLE invisible_probe; FLUSH TABLES; SHOW CREATE TABLE invisible_probe; SHOW INDEX FROM invisible_probe; SELECT INDEX_NAME,IS_VISIBLE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='fz' AND TABLE_NAME='invisible_probe' ORDER BY INDEX_NAME,SEQ_IN_INDEX";
        yield 'invisible listing and sort' => [$setup . $list . '; SELECT DISTINCT a,b FROM invisible_probe ORDER BY a', null];
        yield 'enabled invisible access' => [$setup . "SET optimizer_switch='use_invisible_indexes=on'; SELECT DISTINCT a,b FROM invisible_probe USE INDEX(k) ORDER BY a", null];
        yield 'hidden hint' => [$setup . 'SELECT DISTINCT a FROM invisible_probe USE INDEX(k) ORDER BY a', 1176];
        yield 'alter visible' => [$setup . 'ALTER TABLE invisible_probe ALTER INDEX k VISIBLE; ' . $list . '; SELECT DISTINCT a,b FROM invisible_probe ORDER BY a', null];
        yield 'alter and rename' => [$setup . 'ALTER TABLE invisible_probe ADD c INT; ALTER TABLE invisible_probe RENAME INDEX k TO j; ' . $list, null];
        yield 'table copy' => [$setup . 'CREATE TABLE invisible_copy LIKE invisible_probe; SHOW CREATE TABLE invisible_copy; SHOW INDEX FROM invisible_copy', null];
        yield 'unnamed and repeated options' => ['CREATE TABLE invisible_probe(a INT,KEY(a) VISIBLE INVISIBLE,KEY b(a) INVISIBLE VISIBLE); ' . $list, null];
        yield 'explicit primary' => ['CREATE TABLE invisible_probe(a INT,PRIMARY KEY(a) INVISIBLE)', 3522];
        yield 'implicit primary' => ['CREATE TABLE invisible_probe(a INT NOT NULL,UNIQUE KEY k(a) INVISIBLE)', 3522];
        yield 'alter implicit primary' => ['CREATE TABLE invisible_probe(a INT NOT NULL,UNIQUE KEY k(a)); ALTER TABLE invisible_probe ALTER INDEX k INVISIBLE', 3522];
        yield 'unique enforcement' => ['CREATE TABLE invisible_probe(id INT PRIMARY KEY,a INT NOT NULL,UNIQUE KEY k(a) INVISIBLE); INSERT INTO invisible_probe VALUES(1,65); INSERT INTO invisible_probe VALUES(2,65)', 1062];
        yield 'temporary listing' => ['CREATE TEMPORARY TABLE invisible_probe(a INT,KEY k(a) INVISIBLE); SHOW CREATE TABLE invisible_probe; SHOW INDEX FROM invisible_probe', null];
    }

    #[DataProvider('providerStatements')]
    public function testVisibilityAndAccessMetadataMatchTheServer(string $sql, ?int $error): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
        $actual = $target->run($target->memory, 'root', '', $sql)['error'] ?? [null];
        self::assertIsArray($actual);
        self::assertSame($error, $actual[0]);
    }
}
