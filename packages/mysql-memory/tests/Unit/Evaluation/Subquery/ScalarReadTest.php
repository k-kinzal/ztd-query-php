<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Evaluation\Subquery\ScalarRead;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScalarRead::class)]
#[Small]
final class ScalarReadTest extends TestCase
{
    public function testEvaluateAnswersTheValueOfTheOneRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20), (3, NULL)');
        $result = $session->query('SELECT (SELECT v FROM t WHERE id = 2), (SELECT 1), (SELECT MAX(v) FROM t) + 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['20', '1', '21']], $result->rows);
    }

    public function testEvaluateIsNullForAnEmptySubquery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (3, NULL)');
        $result = $session->query("SELECT (SELECT v FROM t WHERE id = 9), (SELECT 'x' FROM t WHERE id = 9) IS NULL, (SELECT v FROM t WHERE id = 3) IS NULL")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '1', '1']], $result->rows);
    }

    public function testEvaluateReadsACorrelatedSubqueryForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, s VARCHAR(10))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'B'), (3, 'c')");
        $result = $session->query('SELECT id, (SELECT u.s FROM t AS u WHERE u.id = t.id + 1) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'B'], ['2', 'c'], ['3', null]], $result->rows);
    }

    public function testEvaluateAcceptsOneRowForEachOuterRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('CREATE TABLE c (t_id INT, n INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $session->query('INSERT INTO c VALUES (1, 5), (1, 15), (2, 25), (3, 1)');
        $result = $session->query('SELECT id, (SELECT n FROM c WHERE c.t_id = t.id) FROM t WHERE id > 1 ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '25'], ['3', '1']], $result->rows);
    }

    public function testEvaluateRejectsMoreThanOneRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (v INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Subquery returns more than 1 row');
        $this->expectExceptionCode(1242);

        $session->query('SELECT (SELECT v FROM t)');
    }

    public function testEvaluateRejectsMoreThanOneRowForAnOuterRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('CREATE TABLE c (t_id INT, n INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $session->query('INSERT INTO c VALUES (1, 5), (1, 15), (2, 25)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Subquery returns more than 1 row');

        $session->query('SELECT id, (SELECT n FROM c WHERE c.t_id = t.id) FROM t ORDER BY id');
    }

    public function testEvaluateRejectsASubqueryOfMoreThanOneColumn(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Operand should contain 1 column(s)');

        $session->query('SELECT (SELECT 1, 2)');
    }

    public function testDomainAnswersTheDomainOfTheColumn(): void
    {
        $domain = Domain::integer();
        $read = new ScalarRead(new Rows(new QueryPlan(new ZeroRows(1), [Domain::integer()], ['a'])), $domain);

        self::assertSame($domain, $read->domain());
    }

    public function testEvaluateKeepsTheValueOfASubqueryRunOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query('SELECT (SELECT a FROM t ORDER BY a LIMIT 1), (SELECT 1/0 FROM t LIMIT 1) FROM t')[0];
        $warnings = $session->query('SHOW COUNT(*) WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null], ['1', null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['1']], $warnings->rows);
    }

    public function testReadAnswersNullForNoRow(): void
    {
        $read = new ScalarRead(new Rows(new QueryPlan(new ZeroRows(1), [Domain::null()], ['x'])), Domain::null(), true);
        $session = (new Instance())->connect();

        self::assertNull($read->read(new \MySqlMemory\Evaluation\Frame(new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0))));
    }

}
