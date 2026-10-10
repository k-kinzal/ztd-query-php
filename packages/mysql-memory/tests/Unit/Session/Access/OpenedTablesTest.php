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
