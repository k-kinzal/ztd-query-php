<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator\Comparison;

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
}
