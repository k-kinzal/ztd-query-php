<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Arithmetic;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Arithmetic::class)]
#[Small]
final class ArithmeticTest extends TestCase
{
    public function testEvaluateComputesIntegerOperators(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 + 2, 7 - 10, 6 * 7, 7 % 3, -7 % 3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '-3', '42', '1', '-1']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
    }

    public function testEvaluateReturnsNullForANullOperand(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 + NULL, NULL DIV 1, 2 * NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }

    public function testRealComputesStringOperandsInDoublePrecision(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT '3' + '4', 7e0 % 2.5e0, 1e0 + 1")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '2', '2']], $result->rows);
        self::assertSame(Field::Double, $result->columns[0]->type);
    }

    public function testRealWarnsForAStringThatIsNoNumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a' + 1")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"]], $warnings->rows);
    }

    public function testRealRaisesOutOfRangeForAnInfiniteResult(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("DOUBLE value is out of range in '(1e308 * 10)'");

        $session->query('SELECT 1e308 * 10');
    }

    public function testDecimalDividesWithFourMoreDecimals(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 7 / 2, 10 / 3, 1.0 / 3.0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3.5000', '3.3333', '0.33333']], $result->rows);
        self::assertSame(Field::NewDecimal, $result->columns[0]->type);
        self::assertSame(4, $result->columns[0]->decimals);
    }

    public function testDecimalComputesExactly(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 0.1 + 0.2, 1.5 + 2.25, 2.5 * 4, 7.5 % 2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0.3', '3.75', '10.0', '1.5']], $result->rows);
    }

    public function testDecimalComputesOverTableColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, c DECIMAL(10,2))');
        $session->query('INSERT INTO t VALUES (7, 12.34)');
        $result = $session->query('SELECT a * c, c / a, c % 5 FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['86.38', '1.762857', '2.34']], $result->rows);
    }

    public function testDecimalRaisesOutOfRangeBeyondSixtyFiveDigits(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("DECIMAL value is out of range in '(99999999999999999999999999999999999999999999999999999999999999999 * 10)'");

        $session->query('SELECT 99999999999999999999999999999999999999999999999999999999999999999 * 10');
    }

    public function testIntegerRaisesOutOfRangeAboveTheLargestBigint(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT value is out of range in '(9223372036854775807 + 1)'");

        $session->query('SELECT 9223372036854775807 + 1');
    }

    public function testIntegerRaisesOutOfRangeBelowTheSmallestBigint(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT value is out of range in '(-(9223372036854775807) - 2)'");

        $session->query('SELECT -9223372036854775807 - 2');
    }

    public function testIntegerRaisesOutOfRangeForANegativeUnsignedResult(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(1 - cast(2 as unsigned))'");

        $session->query('SELECT 1 - CAST(2 AS UNSIGNED)');
    }

    public function testIntegerComputesInTheUnsignedRange(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (b BIGINT UNSIGNED, i INT)');
        $session->query('INSERT INTO t VALUES (18446744073709551615, -3)');
        $result = $session->query('SELECT b - 1, b + 0, b + i, i * 2 FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551614', '18446744073709551615', '18446744073709551612', '-6']], $result->rows);
        self::assertTrue($result->columns[0]->unsigned());
        self::assertFalse($result->columns[3]->unsigned());
    }

    public function testIntegerDivideTruncatesTowardZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 7 DIV 2, -7 DIV 2, 5.5 DIV 2, '10' DIV '3'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '-3', '2', '3']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[2]->type);
    }

    public function testBoundedAnswersAnUnsignedResultAsItsBits(): void
    {
        $arithmetic = new Arithmetic(ArithmeticOperator::Plus, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), Domain::integer(unsigned: true), '(x + y)');

        self::assertSame([-1, 5], [$arithmetic->bounded('18446744073709551615'), $arithmetic->bounded('5')]);
    }

    public function testBoundedRaisesOutOfRangeBelowZeroForAnUnsignedResult(): void
    {
        $arithmetic = new Arithmetic(ArithmeticOperator::Plus, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 1), Domain::integer(unsigned: true), '(x + y)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1690);
        $this->expectExceptionMessage("BIGINT UNSIGNED value is out of range in '(x + y)'");

        $arithmetic->bounded('-1');
    }

    public function testByZeroWarnsUnderErrorForDivisionByZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 / 0, 1 DIV 0, 1 % 0, 1e0 / 0')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null, null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1365', 'Division by 0'], ['Warning', '1365', 'Division by 0'], ['Warning', '1365', 'Division by 0'], ['Warning', '1365', 'Division by 0']], $warnings->rows);
    }

    public function testByZeroReturnsNullSilentlyWithoutErrorForDivisionByZero(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = ''");
        $result = $session->query('SELECT 1 / 0, 5 % 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testByZeroRaisesAnErrorInAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1365);
        $this->expectExceptionMessage('Division by 0');

        $session->query('INSERT INTO t VALUES (1 / 0)');
    }

    public function testByZeroStoresNullInANonStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query("SET sql_mode = ''");
        $session->query('INSERT INTO t VALUES (1 / 0)');
        $result = $session->query('SELECT a FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
    }

    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::decimal(5, 2);
        $arithmetic = new Arithmetic(ArithmeticOperator::Plus, new Constant(Domain::integer(), 1), new Constant(Domain::decimal(3, 2), '1.50'), $domain, '(1 + 1.50)');

        self::assertSame($domain, $arithmetic->domain());
    }

    public function testEvaluateConvertsTheOtherOperandOfANullInDoublePrecision(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT NULL + 'a', 1/0 + 'b'")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'a'"], ['Warning', '1365', 'Division by 0'], ['Warning', '1292', "Truncated incorrect DOUBLE value: 'b'"]], $warnings->rows);
    }

    public function testOperandReadsBothIntegerOperandsBeforeANullDecides(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT CAST(NULL AS SIGNED) + (1 DIV 0), CAST(NULL AS DECIMAL) + (1.5 / 0)');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1365', 'Division by 0']], $warnings->rows);
    }

    public function testIntegerDivideReadsAStringOfAFunctionWithoutTruncationWarnings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONCAT('a') DIV 3, CONCAT('7x') DIV 3, 'a' DIV NULL, NULL DIV 'a'")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '2', null, null]], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1366', "Incorrect DECIMAL value: '0' for column '' at row -1"], ['Warning', '1292', "Truncated incorrect DECIMAL value: 'a'"]], $warnings->rows);
    }

}
