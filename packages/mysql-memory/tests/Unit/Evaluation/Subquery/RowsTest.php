<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rows::class)]
#[Small]
final class RowsTest extends TestCase
{
    public function testStartRerunsTheSubqueryForEachOuterRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('CREATE TABLE c (t_id INT, n INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20), (3, NULL)');
        $session->query('INSERT INTO c VALUES (1, 5), (1, 15), (2, 25), (3, 1)');
        $result = $session->query('SELECT id, (SELECT SUM(n) FROM c WHERE c.t_id = t.id), (SELECT COUNT(*) FROM c WHERE c.n > t.v) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '20', '2'], ['2', '25', '1'], ['3', '1', '0']], $result->rows);
    }

    public function testStartReadsTheOuterRowInAWhereClause(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $result = $session->query('SELECT id FROM t WHERE (SELECT COUNT(*) FROM t AS u WHERE u.id <= t.id) = 2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2']], $result->rows);
    }

    public function testStartRestartsTheSameSubqueryInsideAnotherSubquery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $result = $session->query('SELECT id, (SELECT COUNT(*) FROM t AS u WHERE EXISTS (SELECT 1 FROM t AS w WHERE w.id = u.id + t.id)) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '2'], ['2', '1'], ['3', '0']], $result->rows);
    }
}
