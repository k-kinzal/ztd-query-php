<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Minus;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Minus::class)]
#[Small]
final class MinusTest extends TestCase
{
    public function testEvaluateNegatesIntegersDecimalsAndDoubles(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT -5, -(1.50), -(2e0), -'3', -9223372036854775807")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-5', '-1.50', '-2', '-3', '-9223372036854775807']], $result->rows);
        self::assertSame([Field::LongLong, Field::NewDecimal, Field::Double, Field::Double], [$result->columns[0]->type, $result->columns[1]->type, $result->columns[2]->type, $result->columns[3]->type]);
    }

    public function testEvaluateNegatesANegativeIntegerConstantAsADecimal(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT -(-(3)), -(1-4), -(CAST(-3 AS UNSIGNED)), -(-9223372036854775808), -(-0), -(9223372036854775808)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '3', '-18446744073709551613', '9223372036854775808', '0', '-9223372036854775808']], $result->rows);
        self::assertSame([[Field::NewDecimal, 2], [Field::NewDecimal, 3], [Field::NewDecimal, 22], [Field::NewDecimal, 20], [Field::LongLong, 2], [Field::LongLong, 20]], array_map(static fn ($column): array => [$column->type, $column->length], $result->columns));
    }

    public function testEvaluateReturnsNullForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT -NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testEvaluateNegatesTableColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (i INT, c DECIMAL(5,2))');
        $session->query('INSERT INTO t VALUES (3, 1.25), (4, -0.50)');
        $result = $session->query('SELECT -i, -c FROM t ORDER BY i')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-3', '-1.25'], ['-4', '0.50']], $result->rows);
    }

    public function testIntegerNegatesAnUnsignedValueToTheSmallestBigint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (b BIGINT UNSIGNED)');
        $session->query('INSERT INTO t VALUES (9223372036854775808)');
        $result = $session->query('SELECT -b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-9223372036854775808']], $result->rows);
    }

    public function testIntegerRaisesOutOfRangeForAnUnsignedValueAboveTheSmallestBigint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (b BIGINT UNSIGNED)');
        $session->query('INSERT INTO t VALUES (9223372036854775809)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage('BIGINT value is out of range in');

        $session->query('SELECT -b FROM t');
    }

    public function testIntegerNegatesASignedValue(): void
    {
        $minus = new Minus(new Constant(Domain::integer(), 5), Domain::integer(), '-(5)');

        self::assertSame([-5, 0], [$minus->integer(5, false), $minus->integer(0, false)]);
    }

    public function testIntegerRaisesOutOfRangeWithTheTextOfTheExpression(): void
    {
        $minus = new Minus(new Constant(Domain::integer(unsigned: true), -1), Domain::integer(), '-(x)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT value is out of range in '-(x)'");

        $minus->integer(-1, true);
    }

    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::integer();
        $minus = new Minus(new Constant(Domain::integer(), 5), $domain, '-(5)');

        self::assertSame($domain, $minus->domain());
    }
}
