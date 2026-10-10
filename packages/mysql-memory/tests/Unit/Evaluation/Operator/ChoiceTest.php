<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Choice;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Choice::class)]
#[Small]
final class ChoiceTest extends TestCase
{
    public function testEvaluateChoosesTheBranchWhoseValueEqualsTheOperand(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE 2 WHEN 1 THEN 'one' WHEN 2 THEN 'two' ELSE 'many' END, CASE 3 WHEN 1 THEN 'one' WHEN 2 THEN 'two' ELSE 'many' END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['two', 'many']], $result->rows);
    }

    public function testEvaluateComparesStringsInTheirCollation(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE 'A' WHEN 'a' THEN 'ci' ELSE 'cs' END, CASE 'A' WHEN BINARY 'a' THEN 'ci' ELSE 'cs' END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ci', 'cs']], $result->rows);
    }

    public function testEvaluateNeverMatchesANullOperand(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE NULL WHEN NULL THEN 'n' ELSE 'e' END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['e']], $result->rows);
    }

    public function testEvaluateChoosesTheFirstConditionThatHolds(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE WHEN 0 THEN 'a' WHEN 2 > 1 THEN 'b' WHEN 1 THEN 'c' END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['b']], $result->rows);
    }

    public function testEvaluateReturnsNullWhenNoBranchIsChosenWithoutAnElse(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE WHEN 0 THEN 'a' WHEN NULL THEN 'b' END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testEvaluateConvertsTheChosenResultToTheAggregatedType(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE WHEN 1 THEN 2 ELSE 'x' END, CASE WHEN 0 THEN 1 ELSE 2e0 END, CASE WHEN 0 THEN 1 ELSE 1.5 END")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '2', '1.5']], $result->rows);
        self::assertSame([Field::VarString, Field::Double, Field::NewDecimal], [$result->columns[0]->type, $result->columns[1]->type, $result->columns[2]->type]);
    }

    public function testEvaluateWarnsForAConditionThatIsNoNumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE WHEN 'abc' THEN 1 ELSE 0 END")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testEvaluateComparesANumberWithAStringAsNumbers(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CASE 1 WHEN '1abc' THEN 'y' ELSE 'n' END")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['y']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: '1abc'"]], $warnings->rows);
    }

    public function testEvaluateChoosesABranchForEachRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (NULL)');
        $result = $session->query("SELECT CASE a WHEN 1 THEN 'one' ELSE 'other' END, CASE WHEN a > 1 THEN a * 10 END FROM t ORDER BY a")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['other', null], ['one', null], ['other', '20']], $result->rows);
    }

    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::integer();
        $choice = new Choice(null, [[new Constant(Domain::integer(), 1), null, new Constant(Domain::integer(), 2)]], null, $domain);

        self::assertSame($domain, $choice->domain());
    }
}
