<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Membership;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Membership::class)]
#[Small]
final class MembershipTest extends TestCase
{
    public function testEvaluateFindsAnEqualElement(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::integer(), Domain::integer(), Collation::binary());
        $elements = [[new Constant(Domain::integer(), 1), $comparator], [new Constant(Domain::integer(), 2), $comparator]];
        $in = new Membership(new Constant(Domain::integer(), 2), $elements, false, Domain::integer());
        $notIn = new Membership(new Constant(Domain::integer(), 2), $elements, true, Domain::integer());
        $missing = new Membership(new Constant(Domain::integer(), 3), $elements, false, Domain::integer());

        self::assertSame([1, 0, 0], [$in->evaluate($frame), $notIn->evaluate($frame), $missing->evaluate($frame)]);
    }

    public function testEvaluateAnswersNullForANullValue(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $comparator = new Comparator(Kind::Integer, Domain::null(), Domain::integer(), Collation::binary());
        $membership = new Membership(new Constant(Domain::null(), null), [[new Constant(Domain::integer(), 1), $comparator]], true, Domain::integer());

        self::assertNull($membership->evaluate($frame));
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new Membership(new Constant(Domain::integer(), 1), [], false, $domain))->domain());
    }

    public function testEvaluateAnswersNullWhenNoElementMatchesAndOneIsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 2 IN (1, 2, NULL), 3 IN (1, 2, NULL), 3 NOT IN (1, 2, NULL), 3 NOT IN (1, 2), 2 NOT IN (1, 2), NULL IN (1), NULL NOT IN (1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', null, null, '1', '0', null, null]], $result->rows);
    }

    public function testEvaluateComparesStringsInTheCollationOfTheOperation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a' IN ('A', 'b'), 'a' COLLATE utf8mb4_bin IN ('A', 'b')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0']], $result->rows);
    }

    public function testEvaluateComparesANumberAndAStringAsDoubles(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 1 IN ('1x', 2)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: '1x'"]], $warnings->rows);
    }

    public function testEvaluateTestsTheColumnOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, 10), (2, 20), (3, NULL)');
        $result = $session->query('SELECT id, v IN (10, 30), v NOT IN (10, 30) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '0'], ['2', '0', '1'], ['3', null, null]], $result->rows);
    }

    public function testValuesEvaluatesConstantElementsOnceBeforeTheFirstValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query("SELECT 'x' IN (0, CONCAT('y', 1) IS FALSE) FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['1'], ['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'y1'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]], $warnings->rows);
    }

    public function testEvaluateEvaluatesEveryElementForANullValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query("SELECT s IN ('c' + id, 1) FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0'], ['0'], [null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'c'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'c'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'c'"]], $warnings->rows);
    }
}
