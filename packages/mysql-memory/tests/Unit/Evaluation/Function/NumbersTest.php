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

        self::assertSame(['ABS', 'SIGN', 'CEILING', 'CEIL', 'FLOOR', 'ROUND', 'TRUNCATE', 'PI', 'SQRT', 'EXP', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'COT', 'DEGREES', 'RADIANS', 'LN', 'LOG2', 'LOG10', 'LOG', 'POW', 'POWER'], $names);
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
        $this->expectExceptionMessage("BIGINT value is out of range in 'abs(-(9223372036854775808))'");

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
        $this->expectExceptionMessage("BIGINT value is out of range in 'abs(-(9223372036854775808))'");

        (new Numbers())->absolute(PHP_INT_MIN, 'abs(-(9223372036854775808))');
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

        self::assertNull((new Numbers())->real($frame, [new Constant(Domain::double(), -1.0)], static fn (float $x): float => sqrt($x), 'sqrt(-1)'));
        self::assertSame(3.0, (new Numbers())->real($frame, [new Constant(Domain::double(), 9.0)], static fn (float $x): float => sqrt($x), 'sqrt(9)'));
        self::assertNull((new Numbers())->real($frame, [new Constant(Domain::double(), null)], static fn (float $x): float => $x, 'f(NULL)'));
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

    public function testRealRefusesAnInfiniteResult(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("DOUBLE value is out of range in 'cot(0)'");

        $session->query('SELECT COT(0)');
    }

    public function testRealRefusesAnOverflowingExponential(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("DOUBLE value is out of range in 'exp(1000)'");

        $session->query('SELECT EXP(1000)');
    }

    public function testLogarithmWarnsForEachArgumentOutsideItsDomain(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LN(0), LOG2(-1), LOG10(0), LOG(0), LOG(1, 2), LOG(-1, NULL), LOG(NULL, 0), LOG(2, 8), LOG(8), LN('x')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null, null, null, null, null, null, null, '3', '2.0794415416798357', null]], $result->rows);
        self::assertSame(Field::Double, $result->columns[4]->type);
        self::assertSame([
            ['Warning', '3020', 'Invalid argument for logarithm'],
            ['Warning', '3020', 'Invalid argument for logarithm'],
            ['Warning', '3020', 'Invalid argument for logarithm'],
            ['Warning', '3020', 'Invalid argument for logarithm'],
            ['Warning', '3020', 'Invalid argument for logarithm'],
            ['Warning', '3020', 'Invalid argument for logarithm'],
            ['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"],
            ['Warning', '3020', 'Invalid argument for logarithm'],
        ], $warnings->rows);
    }

    public function testLogarithmRefusesAnInvalidArgumentWrittenUnderAStrictMode(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DOUBLE)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3020);

        $session->query('INSERT INTO t SELECT LN(0)');
    }

    public function testRoundRefusesAnUnsignedResultOutOfRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (u BIGINT UNSIGNED)');
        $session->query('INSERT INTO t VALUES (18446744073709551615)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessageMatches("/\\ABIGINT UNSIGNED value is out of range in 'round\\(.*u`,-\\(1\\)\\)'\\z/");

        $session->query('SELECT ROUND(u, -1) FROM t');
    }

    public function testRoundRefusesASignedResultOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("BIGINT value is out of range in 'round(9223372036854775807,-(1))'");

        $session->query('SELECT ROUND(9223372036854775807, -1)');
    }

    public function testRoundKeepsAnIntegerThatRoundsWithinRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (u BIGINT UNSIGNED, s BIGINT)');
        $session->query('INSERT INTO t VALUES (18446744073709551615, -9223372036854775808)');
        $result = $session->query('SELECT ROUND(u, -2), ROUND(u, -20), TRUNCATE(u, -1), TRUNCATE(s, -1) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551600', '0', '18446744073709551610', '-9223372036854775800']], $result->rows);
    }

    public function testRoundRealHandlesScalesBeyondTheRangeOfADouble(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ROUND(1.7e308, 2), ROUND(1e300, -309), ROUND(1.5e308, -308), ROUND(1e-320, 330), ROUND(1.5e0, 400), TRUNCATE(-1.5e308, -308), ROUND(-0.4e0), ROUND(2.5e0)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1.7e308', '0', '0', '1e-320', '1.5', '-1e308', '-0', '2']], $result->rows);
    }

    public function testRoundRealRoundsHalfToEvenOrTruncates(): void
    {
        self::assertSame([2.0, 4.0, 0.12, 1.7e308, 0.0, -1.0e308, 1.5], [
            (new Numbers())->roundReal(2.5, 0, false),
            (new Numbers())->roundReal(3.5, 0, false),
            (new Numbers())->roundReal(0.125, 2, false),
            (new Numbers())->roundReal(1.7e308, 2, false),
            (new Numbers())->roundReal(1.5, -400, false),
            (new Numbers())->roundReal(-1.5e308, -308, true),
            (new Numbers())->roundReal(1.5, 400, true),
        ]);
    }

    public function testSignReadsAStringAsADouble(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SIGN('abc'), SIGN('-1e-400'), SIGN(-0e0), SIGN(-0.0)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['0', '0', '0', '0']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'abc'"]], $warnings->rows);
    }

    public function testRealConvertsUnitsWithoutANegativeZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT DEGREES(-0e0), RADIANS(-0e0), DEGREES(-1e-320), RADIANS(-1e-320), DEGREES(PI()), RADIANS(180)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '-5.72953e-319', '-1.73e-322', '180', '3.141592653589793']], $result->rows);
    }

    public function testTowardMakesAnIntegerABigintAndADateADouble(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (bi BIGINT, dt DATE)');
        $session->query("INSERT INTO t VALUES (-9000000000000000000, '2020-01-02')");
        $result = $session->query('SELECT CEILING(bi), FLOOR(dt) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-9000000000000000000', '20200102']], $result->rows);
        self::assertSame([[Field::LongLong, 21], [Field::Double, 23]], [[$result->columns[0]->type, $result->columns[0]->length], [$result->columns[1]->type, $result->columns[1]->length]]);
    }

    public function testRoundTruncatesADecimalToTheDecimalsItHasAndKeepsAnIntegerABigint(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (bi BIGINT, d DECIMAL(10,3))');
        $session->query('INSERT INTO t VALUES (-9000000000000000000, -2.567)');
        $result = $session->query('SELECT TRUNCATE(d, 2), TRUNCATE(d, 5), TRUNCATE(d, -2), TRUNCATE(bi, 2) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-2.56', '-2.567', '0', '-9000000000000000000']], $result->rows);
        self::assertSame([[11, 2], [12, 3], [8, 0], [21, 0]], array_map(static fn ($column): array => [$column->length, $column->decimals], $result->columns));
    }
}
