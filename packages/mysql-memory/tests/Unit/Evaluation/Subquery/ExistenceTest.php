<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Evaluation\Subquery\Existence;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Existence::class)]
#[Small]
final class ExistenceTest extends TestCase
{
    public function testEvaluateTellsWhetherTheSubqueryHasARow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (v INT)');
        $session->query('CREATE TABLE e (v INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query('SELECT EXISTS (SELECT 1 FROM t), EXISTS (SELECT 1 FROM e), NOT EXISTS (SELECT * FROM e)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1']], $result->rows);
    }

    public function testEvaluateCountsARowOfNullsAsARow(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT EXISTS (SELECT NULL), EXISTS (SELECT NULL FROM (SELECT 1) AS x WHERE 1 = 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }

    public function testEvaluateTestsACorrelatedSubqueryForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20), (3, NULL)');
        $result = $session->query('SELECT id, EXISTS (SELECT 1 FROM t AS u WHERE u.v > t.v) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['2', '0'], ['3', '0']], $result->rows);
    }

    public function testEvaluateFiltersRowsInAWhereClause(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY)');
        $session->query('CREATE TABLE c (t_id INT, n INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (3)');
        $session->query('INSERT INTO c VALUES (1, 5), (1, 15), (2, 25), (3, 1)');
        $exists = $session->query('SELECT id FROM t WHERE EXISTS (SELECT 1 FROM c WHERE c.t_id = t.id AND c.n > 10) ORDER BY id')[0];
        $missing = $session->query('SELECT id FROM t WHERE NOT EXISTS (SELECT 1 FROM c WHERE c.t_id = t.id AND c.n > 10) ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $exists);
        self::assertSame([['1'], ['2']], $exists->rows);
        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertSame([['3']], $missing->rows);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();
        $existence = new Existence(new Rows(new QueryPlan(new ZeroRows(1), [Domain::integer()], ['a'])), $domain);

        self::assertSame($domain, $existence->domain());
    }
}
