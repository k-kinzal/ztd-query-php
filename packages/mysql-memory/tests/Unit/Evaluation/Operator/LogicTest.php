<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Logic::class)]
#[Small]
final class LogicTest extends TestCase
{
    public function testEvaluateAndIsFalseWhenAnOperandIsFalse(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 AND 0, 0 AND NULL, NULL AND 0, 1 AND NULL, NULL AND NULL, 2 AND 3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0', null, null, '1']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
    }

    public function testEvaluateOrIsTrueWhenAnOperandIsTrue(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 OR NULL, NULL OR 1, 0 OR NULL, 0 OR 0, 0 OR 0.5')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1', null, '0', '1']], $result->rows);
    }

    public function testEvaluateXorIsNullWhenAnOperandIsNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 XOR 1, 1 XOR 0, 0 XOR 0, 1 XOR NULL, NULL XOR 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '0', null, null]], $result->rows);
    }

    public function testEvaluateWarnsForAStringThatIsNoNumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a' OR 0")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testEvaluateSkipsTheRightOperandWhenTheLeftDecides(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (0)');
        $result = $session->query('SELECT a <> 0 AND 1 / a, a = 0 OR 1 / a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1']], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testEvaluateFiltersRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->query('INSERT INTO t VALUES (1, 1), (1, 0), (0, 1), (NULL, 1)');
        $result = $session->query('SELECT a, b FROM t WHERE a = 1 AND b = 1 OR a IS NULL ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, '1'], ['1', '1']], $result->rows);
    }

    public function testDomainAnswersTheDomainOfTheTruthValue(): void
    {
        $domain = Domain::integer();
        $logic = new Logic(LogicalOperator::And, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 0), $domain);

        self::assertSame($domain, $logic->domain());
    }

    public function testEvaluateLeavesTheRightOperandOfXorUnreadForANullLeft(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT NULL XOR ('a' + 0)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

}
