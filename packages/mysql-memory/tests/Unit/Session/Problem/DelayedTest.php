<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Problem\Delayed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Delayed::class)]
#[Small]
final class DelayedTest extends TestCase
{
    public function testCheckRefusesDelayedRowsForAnInnodbTableIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1616);
        $this->expectExceptionMessage("DELAYED option not supported for table 't'");

        (new Delayed())->check($session->analyze('INSERT DELAYED INTO t VALUES (1)')->statement, $session);
    }

    public function testCheckTakesDelayedRowsOfAQueryOrOfAMyisamTable(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE m (a INT) ENGINE=MyISAM');
        $session->query('INSERT DELAYED INTO t SELECT 1');
        $session->query('INSERT DELAYED INTO m VALUES (2)');

        self::assertSame([['Warning', 1287, "'INSERT DELAYED' is deprecated and will be removed in a future release. Please use INSERT instead"]], $session->diagnostics->conditions);

        $result = $session->query('SELECT (SELECT a FROM t), (SELECT a FROM m)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2']], $result->rows);
    }

    public function testCheckRefusesAPartitionClauseFirst(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1747);

        $session->query('REPLACE DELAYED t PARTITION (p) SET a = 1');
    }
}
