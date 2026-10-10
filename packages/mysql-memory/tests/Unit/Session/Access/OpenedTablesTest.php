<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Access\OpenedTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenedTables::class)]
#[Small]
final class OpenedTablesTest extends TestCase
{
    public function testListingOpensDictionaryDependenciesEvenWithAFalsePredicate(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; FLUSH TABLES; SHOW TABLES WHERE 0');
        $opened = $session->instance->dictionary->cache->names();
        sort($opened);

        self::assertSame([['information_schema', 'TABLES'], ['mysql', 'catalogs'], ['mysql', 'collations'], ['mysql', 'schemata'], ['mysql', 'table_stats'], ['mysql', 'tables'], ['mysql', 'tablespaces']], $opened);
    }

    public function testListingOpensOnlySchemataWhenTheDatabaseIsAbsent(): void
    {
        $session = (new Instance())->connect();
        (new OpenedTables())->listing('absent', $session);

        self::assertSame([['mysql', 'schemata']], $session->instance->dictionary->cache->names());
    }

    public function testRoutineKeepsLoadedDefinitionsAcrossTableFlushesUntilRoutineDdl(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE PROCEDURE p() DO 1; FLUSH TABLES; CALL p()');
        $cold = $session->instance->dictionary->cache->names();
        $session->query('FLUSH TABLES; CALL p()');
        $warm = $session->instance->dictionary->cache->names();
        $session->query('CREATE PROCEDURE q() DO 2; FLUSH TABLES; CALL p()');

        self::assertSame([['mysql', 'proc']], $cold);
        self::assertSame([], $warm);
        self::assertSame([['mysql', 'proc']], $session->instance->dictionary->cache->names());
    }

    public function testRoutineSharesModernDictionaryDefinitionsAcrossSessions(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $first->query('CREATE DATABASE d; USE d; CREATE PROCEDURE p() DO 1; FLUSH TABLES; CALL p()');
        $cold = $instance->dictionary->cache->names();
        $second = $instance->connect(database: 'd');
        $second->query('FLUSH TABLES; CALL p(); CREATE PROCEDURE q() DO 2; FLUSH TABLES; CALL p()');
        $warm = $instance->dictionary->cache->names();
        $second->query("ALTER PROCEDURE p COMMENT 'x'; FLUSH TABLES; CALL p()");

        self::assertCount(3, $cold);
        self::assertSame([], $warm);
        self::assertSame($cold, $instance->dictionary->cache->names());
    }

    public function testPrepareOpensReachedTablesAndLeavesUnusedCteBodiesClosed(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a(id INT); CREATE TABLE b(id INT); FLUSH TABLES');
        $session->query('WITH unused AS (SELECT * FROM b) SELECT * FROM a WHERE FALSE');
        $result = $session->query('SHOW OPEN TABLES FROM d')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d', 'a', '0', '0']], $result->rows);
    }

    public function testOpenKeepsTemporaryTableShadowsOutOfTheSharedCache(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE a(id INT); CREATE TEMPORARY TABLE a(id INT); FLUSH TABLES; SELECT * FROM a');
        $result = $session->query('SHOW OPEN TABLES FROM d')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testUsesCountsAHandlerOwnedByAnotherSession(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $first->query('CREATE DATABASE d; USE d; CREATE TABLE a(id INT); HANDLER a OPEN');
        $second = $instance->connect();
        $held = $second->query('SHOW OPEN TABLES FROM d')[0];
        $first->query('HANDLER a CLOSE');
        $released = $second->query('SHOW OPEN TABLES FROM d')[0];

        self::assertInstanceOf(ResultSet::class, $held);
        self::assertInstanceOf(ResultSet::class, $released);
        self::assertSame([['d', 'a', '1', '0']], $held->rows);
        self::assertSame([['d', 'a', '0', '0']], $released->rows);
    }
}
