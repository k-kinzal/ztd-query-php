<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Numbers;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Numbers::class)]
#[Small]
final class NumbersTest extends TestCase
{
    public function testRoutinesNamesTheNumericFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Numbers())->routines());

        self::assertSame(['ABS', 'SIGN', 'CEILING', 'CEIL', 'FLOOR', 'ROUND', 'TRUNCATE', 'PI', 'SQRT', 'EXP', 'LN', 'LOG2', 'LOG10', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'ATAN', 'COT', 'DEGREES', 'RADIANS', 'POW', 'POWER'], $names);
    }

    public function testAbsKeepsTheTypeOfTheArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ABS(-5), ABS(-1.50), ABS(-2.5e0), ABS(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5', '1.50', '2.5', null]], $result->rows);
        self::assertSame([Field::LongLong, Field::NewDecimal, Field::Double], [$result->columns[0]->type, $result->columns[1]->type, $result->columns[2]->type]);
    }

    public function testAbsReadsAStringAsADoubleWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ABS('-3x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['3']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: '-3x'"]], $warnings->rows);
    }

    public function testAbsKeepsTheLargestUnsignedBigint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (u BIGINT UNSIGNED)');
        $session->query('INSERT INTO t VALUES (18446744073709551615)');
        $result = $session->query('SELECT ABS(u) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551615']], $result->rows);
    }

    public function testAbsRefusesTheSmallestBigint(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("BIGINT value is out of range in 'abs(-9223372036854775808)'");

        $session->query('SELECT ABS(-9223372036854775808)');
    }

    public function testAbsoluteAnswersTheAbsoluteValueOfAnInt(): void
    {
        self::assertSame([3, 3, 0], [(new Numbers())->absolute(-3), (new Numbers())->absolute(3), (new Numbers())->absolute(0)]);
    }

    public function testAbsoluteRefusesTheSmallestInt(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT value is out of range in 'abs(-9223372036854775808)'");

        (new Numbers())->absolute(PHP_INT_MIN);
    }

    public function testSignAnswersMinusOneZeroOrOne(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT SIGN(-32), SIGN(0), SIGN(234), SIGN(2.5), SIGN(-0.5e0), SIGN(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-1', '0', '1', '1', '-1', null]], $result->rows);
    }

    public function testTowardRoundsAnExactValueToAnInteger(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CEILING(1.23), CEIL(-1.23), FLOOR(1.23), FLOOR(-1.23), CEILING(-0.5), FLOOR(7)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '-1', '1', '-2', '0', '7']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
    }

    public function testTowardKeepsADoubleADouble(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT FLOOR(1.5e0), CEILING(2.5e0), FLOOR(-2.5e0), FLOOR(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '3', '-3', null]], $result->rows);
        self::assertSame(Field::Double, $result->columns[0]->type);
    }

    public function testTowardKeepsADecimalTooLargeForABigint(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CEILING(99999999999999999999.5), FLOOR(-99999999999999999999.5)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['100000000000000000000', '-100000000000000000000']], $result->rows);
        self::assertSame(Field::NewDecimal, $result->columns[0]->type);
    }

    public function testRoundRoundsAnExactValueHalfAwayFromZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ROUND(2.5), ROUND(-2.5), ROUND(-1.23), ROUND(1.298, 1), ROUND(1.005, 2), ROUND(23.298, -1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '-3', '-1', '1.3', '1.01', '20']], $result->rows);
        self::assertSame(Field::NewDecimal, $result->columns[0]->type);
    }

    public function testRoundRoundsADoubleHalfToEven(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ROUND(2.5e0), ROUND(3.5e0), ROUND(-1.5e0), ROUND(1.5e0, 1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '4', '-2', '1.5']], $result->rows);
        self::assertSame(Field::Double, $result->columns[0]->type);
    }

    public function testRoundKeepsAnIntegerAnInteger(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ROUND(1234, -2), ROUND(1250, -2), TRUNCATE(122, -2)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1200', '1300', '100']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
    }

    public function testRoundTruncatesTowardZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT TRUNCATE(1.223, 1), TRUNCATE(1.999, 1), TRUNCATE(1.999, 0), TRUNCATE(-1.999, 1), TRUNCATE(1.25e0, 1), TRUNCATE(-2.5e0, 0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1.2', '1.9', '1', '-1.9', '1.2', '-2']], $result->rows);
    }

    public function testRoundLimitsTheDecimalsToThirty(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ROUND(5.5, 50)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['5.500000000000000000000000000000']], $result->rows);
    }

    public function testRoundAnswersNullForANullArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ROUND(NULL), ROUND(1.5, NULL), TRUNCATE(NULL, 1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }

    public function testRoutinesAnswerPiWithSixDecimals(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT PI()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3.141593']], $result->rows);
        self::assertSame([Field::Double, 6], [$result->columns[0]->type, $result->columns[0]->decimals]);
    }

    public function testRealComputesTheFunctionsOfRealNumbers(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT SQRT(4), EXP(0), EXP(1), LN(1), LOG2(8), LOG10(100), SIN(0), COS(0), TAN(0), ACOS(1), ATAN(1), DEGREES(PI()), RADIANS(180)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '1', '2.718281828459045', '0', '3', '2', '0', '1', '0', '0', '0.7853981633974483', '180', '3.141592653589793']], $result->rows);
        self::assertSame(Field::Double, $result->columns[0]->type);
    }

    public function testRealAnswersNullOutsideTheDomainOfTheFunction(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT SQRT(-16), LN(0), LOG2(-1), LOG10(0), ASIN(2), ACOS(-1.5), SQRT(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null, null, null, null, null]], $result->rows);
    }

    public function testRealReadsAStringAsADoubleWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SQRT('x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['0']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]], $warnings->rows);
    }

    public function testRealTurnsANotANumberResultIntoNull(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertNull((new Numbers())->real($frame, [new Constant(Domain::double(), -1.0)], static fn (float $x): float => sqrt($x)));
        self::assertSame(3.0, (new Numbers())->real($frame, [new Constant(Domain::double(), 9.0)], static fn (float $x): float => sqrt($x)));
        self::assertNull((new Numbers())->real($frame, [new Constant(Domain::double(), null)], static fn (float $x): float => $x));
    }

    public function testPowerRaisesTheBaseToTheExponent(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT POW(2, 2), POW(2, -2), POWER(2, 10), POW(NULL, 2), POW(2, NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['4', '0.25', '1024', null, null]], $result->rows);
        self::assertSame(Field::Double, $result->columns[0]->type);
    }

    public function testPowerRefusesAResultOutOfTheRangeOfADouble(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("DOUBLE value is out of range in 'pow(10,400)'");

        $session->query('SELECT POW(10, 400)');
    }
}
