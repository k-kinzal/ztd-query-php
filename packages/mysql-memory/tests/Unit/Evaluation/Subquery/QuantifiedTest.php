<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Subquery;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Subquery\Quantified;
use MySqlMemory\Evaluation\Subquery\Rows;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Quantified::class)]
#[Small]
final class QuantifiedTest extends TestCase
{
    public function testEvaluateAnswersAllTrueAndAnyFalseOverNoRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE e (v INT)');
        $result = $session->query('SELECT 5 > ALL (SELECT v FROM e), 5 > ANY (SELECT v FROM e), 5 IN (SELECT v FROM e), 5 NOT IN (SELECT v FROM e), NULL IN (SELECT v FROM e), NULL > ALL (SELECT v FROM e)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '0', '1', '0', '1']], $result->rows);
    }

    public function testEvaluateAnswersNullWhenTheComparisonIsUnknownForARow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE n (v INT)');
        $session->query('INSERT INTO n VALUES (1), (NULL)');
        $result = $session->query('SELECT 1 IN (SELECT v FROM n), 2 IN (SELECT v FROM n), 2 NOT IN (SELECT v FROM n), 1 NOT IN (SELECT v FROM n), 1 = SOME (SELECT v FROM n)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null, null, '0', '1']], $result->rows);
    }

    public function testEvaluateStopsAllAtAFailingRowDespiteANullRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE n (v INT)');
        $session->query('INSERT INTO n VALUES (1), (NULL)');
        $result = $session->query('SELECT 0 < ALL (SELECT v FROM n), 2 < ALL (SELECT v FROM n), 0 < ANY (SELECT v FROM n), 2 < ANY (SELECT v FROM n), 2 <> ALL (SELECT v FROM n)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '0', '1', null, null]], $result->rows);
    }

    public function testEvaluateComparesWithEveryRowForAllAndAny(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (v INT)');
        $session->query('INSERT INTO t VALUES (10), (20), (NULL)');
        $result = $session->query('SELECT 15 > ALL (SELECT v FROM t WHERE v IS NOT NULL), 25 > ALL (SELECT v FROM t WHERE v IS NOT NULL), 15 > ANY (SELECT v FROM t), 5 > ANY (SELECT v FROM t), NULL IN (SELECT v FROM t WHERE v = 10), NULL = ANY (SELECT 1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '1', null, null, null]], $result->rows);
    }

    public function testEvaluateComparesStringsInTheCollationOfTheOperation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (s VARCHAR(10))');
        $session->query("INSERT INTO t VALUES ('a'), ('B')");
        $result = $session->query("SELECT 'A' IN (SELECT s FROM t), 'b' IN (SELECT s FROM t), 'c' IN (SELECT s FROM t)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0']], $result->rows);
    }

    public function testEvaluateComparesACorrelatedSubqueryForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('CREATE TABLE c (t_id INT, n INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20), (3, NULL)');
        $session->query('INSERT INTO c VALUES (1, 5), (1, 15), (2, 25), (3, 1)');
        $result = $session->query('SELECT id, v > ALL (SELECT n FROM c WHERE c.t_id = t.id), v > ANY (SELECT n FROM c WHERE c.t_id = t.id), v IN (SELECT n * 2 FROM c WHERE c.t_id = t.id) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '1'], ['2', '0', '0', '0'], ['3', null, null, null]], $result->rows);
    }

    public function testEvaluateRejectsASubqueryOfMoreThanOneColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, v INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Operand should contain 1 column(s)');

        $session->query('SELECT 1 IN (SELECT id, v FROM t)');
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();
        $quantified = new Quantified(new Constant(Domain::integer(), 1), new Rows(new QueryPlan(new ZeroRows(1), [Domain::integer()], ['a'])), ComparisonOperator::Equal, false, false, new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary()), $domain);

        self::assertSame($domain, $quantified->domain());
    }

    public function testResultAnswersTheTruthValue(): void
    {
        $quantified = new Quantified(new Constant(Domain::integer(), 1), new Rows(new QueryPlan(new ZeroRows(1), [Domain::integer()], ['a'])), ComparisonOperator::Equal, false, false, new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary()), Domain::integer());

        self::assertSame([1, 0], [$quantified->result(true), $quantified->result(false)]);
    }

    public function testResultNegatesTheTruthValueForNotIn(): void
    {
        $quantified = new Quantified(new Constant(Domain::integer(), 1), new Rows(new QueryPlan(new ZeroRows(1), [Domain::integer()], ['a'])), ComparisonOperator::Equal, false, true, new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary()), Domain::integer());

        self::assertSame([0, 1], [$quantified->result(true), $quantified->result(false)]);
    }

    public function testEvaluateComparesWithTheRowsWithoutConversionWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2)');
        $result = $session->query("SELECT 'x' IN (SELECT a FROM t), 'x' > ANY (SELECT a FROM t)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

}
