<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\IsTest;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(IsTest::class)]
#[Small]
final class IsTestTest extends TestCase
{
    public function testLegacyJsonNullWarnsAtTheAggregatePositionAndRestoresTheCurrentRow(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $frame->context->aggregateRow = 6;
        $domain = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Json, 4294967295);
        $plan = new \MySqlMemory\Plan\QueryPlan(new \MySqlMemory\Plan\Path\Source\Inline([[new Constant($domain, '[1]')]], 1), [$domain], ['j']);
        $read = new \MySqlMemory\Evaluation\Subquery\ScalarRead(new \MySqlMemory\Evaluation\Subquery\Rows($plan), $domain);

        self::assertFalse(IsTest::legacyJsonNull($read, $frame));
        self::assertSame(1, $frame->context->row);
        self::assertSame([['Warning', 3156, 'Invalid JSON value for CAST to INTEGER from column ? at row 6']], $session->diagnostics->conditions);
    }

    public function testEvaluateTestsForNullWithoutATruthValue(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $null = new IsTest(new Constant(Domain::null(), null), null, false, Domain::integer());
        $notNull = new IsTest(new Constant(Domain::integer(), 0), null, true, Domain::integer());

        self::assertSame([1, 1], [$null->evaluate($frame), $notNull->evaluate($frame)]);
    }

    public function testEvaluateTestsATruthValue(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $true = new IsTest(new Constant(Domain::integer(), 2), true, false, Domain::integer());
        $false = new IsTest(new Constant(Domain::integer(), 2), false, false, Domain::integer());
        $nullIsNotFalse = new IsTest(new Constant(Domain::null(), null), false, true, Domain::integer());

        self::assertSame([1, 0, 1], [$true->evaluate($frame), $false->evaluate($frame), $nullIsNotFalse->evaluate($frame)]);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();

        self::assertSame($domain, (new IsTest(new Constant(Domain::integer(), 1), true, false, $domain))->domain());
    }

    public function testEvaluateIsTrueAndIsFalseNeverAnswerNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 IS TRUE, 0 IS TRUE, NULL IS TRUE, 0 IS FALSE, NULL IS FALSE, NULL IS NOT TRUE, NULL IS NOT FALSE, 1 IS NOT FALSE, 0.1 IS TRUE')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '0', '1', '0', '1', '1', '1', '1']], $result->rows);
    }

    public function testEvaluateIsUnknownTestsForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT NULL IS UNKNOWN, 0 IS UNKNOWN, 1 IS NOT UNKNOWN, NULL IS NOT UNKNOWN, 'x' IS NULL, NULL IS NOT NULL")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '0', '0', '0']], $result->rows);
    }

    public function testEvaluateReadsAStringAsANumberWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT '0.5' IS TRUE, 'abc' IS FALSE")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testEvaluateTestsTheColumnOfEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY, v INT)');
        $session->query('INSERT INTO t VALUES (1, 5), (2, 0), (3, NULL)');
        $result = $session->query('SELECT id, v IS TRUE, v IS NOT FALSE, v IS NULL FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', '1', '0'], ['2', '0', '0', '0'], ['3', '0', '1', '1']], $result->rows);
    }

    public function testIsNullDoesNotEvaluateAnOperandThatCannotBeNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query("SELECT (s IS TRUE) IS NULL, ISNULL('a' + 0), (s IS TRUE) IS UNKNOWN FROM t")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0'], ['0', '0', '0'], ['0', '0', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testEvaluateIsNotNullEvaluatesAnOperandThatCannotBeNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query('SELECT (s IS TRUE) IS NOT NULL FROM t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1'], ['1'], ['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $warnings->rows);
    }

    public function testAbsentEvaluatesOnlyTheNullnessOfTheOperandsOfAComparison(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query("SELECT (s < 0) IS NULL, (NOT s) IS NULL, (s LIKE 0) IS NULL, (s XOR id) IS NULL, ((s <=> id) = 1) IS NULL, ('a' = id) IS NULL FROM t")[0];
        $quiet = $session->query('SHOW WARNINGS')[0];
        $session->query('SELECT (s = id) IS NULL FROM t');
        $whole = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0', '0', '0', '0'], ['0', '0', '0', '0', '0', '0'], ['1', '1', '1', '1', '0', '0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $quiet);
        self::assertSame([], $quiet->rows);
        self::assertInstanceOf(ResultSet::class, $whole);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $whole->rows);
    }

    public function testAbsentChecksTheEscapeOfANegatedLike(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (b VARCHAR(5))');
        $session->query("INSERT INTO t VALUES ('a')");
        $negated = $session->run("SELECT (b NOT LIKE 'a' ESCAPE USER()) IS NULL FROM t");
        $plain = $session->query("SELECT (b LIKE 'a' ESCAPE USER()) IS NULL FROM t")[0];

        self::assertInstanceOf(SqlError::class, $negated[0]);
        self::assertSame(1210, $negated[0]->getCode());
        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertSame([['0']], $plain->rows);
    }

}
