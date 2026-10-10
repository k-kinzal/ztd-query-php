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
final class TableCacheTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $show = 'SHOW OPEN TABLES FROM fz';
        $warm = 'SELECT id FROM t1; SELECT id FROM t2; ';
        yield 'fresh fixture has an empty cache' => ['SHOW OPEN TABLES'];
        yield 'used tables remain cached' => [$warm . $show];
        yield 'reading retains statistics metadata' => ['SELECT id FROM t1; SHOW OPEN TABLES'];
        yield 'flush clears cached handles' => ['FLUSH TABLES; SHOW OPEN TABLES'];
        yield 'creation does not open a handle' => ['FLUSH TABLES; CREATE TABLE unused(a INT); ' . $show];
        yield 'reading opens an empty table' => ['CREATE TABLE unused(a INT); FLUSH TABLES; SELECT * FROM unused; ' . $show];
        yield 'false predicates still open tables' => ['FLUSH TABLES; SELECT * FROM t1 WHERE FALSE; ' . $show];
        yield 'named flush preserves other handles' => [$warm . 'FLUSH TABLES t1; ' . $show];
        yield 'flush keeps definitions and rows' => ['FLUSH TABLES; SELECT COUNT(*) FROM t1; ' . $show];
        yield 'show create opens its table' => ['FLUSH TABLES; SHOW CREATE TABLE t1; ' . $show];
        yield 'show tables does not open base handles' => ['FLUSH TABLES; SHOW TABLES; ' . $show];
        yield 'handlers retain a use' => ['FLUSH TABLES; HANDLER t1 OPEN; ' . $show . '; HANDLER t1 CLOSE; ' . $show];
        yield 'handlers reopen after flush' => ['HANDLER t1 OPEN; FLUSH TABLES; ' . $show . '; HANDLER t1 READ FIRST; ' . $show . '; HANDLER t1 CLOSE'];
        yield 'table locks retain a use' => ['FLUSH TABLES; LOCK TABLES t1 READ; ' . $show . '; UNLOCK TABLES; ' . $show];
        yield 'drop removes the handle' => [$warm . 'DROP TABLE t1; ' . $show];
        yield 'drop database removes its handles' => [$warm . 'DROP DATABASE fz; ' . $show];
        yield 'rename invalidates the old handle' => [$warm . 'RENAME TABLE t1 TO renamed; ' . $show];
        yield 'alter opens its target' => ['FLUSH TABLES; ALTER TABLE t1 ADD extra INT; ' . $show];
        yield 'temporary tables have no shared handle' => ['FLUSH TABLES; CREATE TEMPORARY TABLE temporary_table(a INT); INSERT INTO temporary_table VALUES(1); SELECT * FROM temporary_table; ' . $show];
    }

    #[DataProvider('providerStatements')]
    public function testTableCacheMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
