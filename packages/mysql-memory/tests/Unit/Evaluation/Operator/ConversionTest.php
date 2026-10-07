<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Conversion::class)]
#[Small]
final class ConversionTest extends TestCase
{
    public function testEvaluateReturnsNullForNull(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(NULL AS SIGNED), CAST(NULL AS DATE), CAST(NULL AS CHAR(2))')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }

    public function testEvaluateConvertsToDouble(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(1.5 AS DOUBLE), CAST('2.25' AS DOUBLE)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1.5', '2.25']], $result->rows);
        self::assertSame(Field::Double, $result->columns[0]->type);
    }

    public function testEvaluateConvertsToTemporalTypes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(20240115 AS DATE), CAST('10:20:30' AS TIME), CAST(20240115102030 AS DATETIME), CAST(24 AS YEAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-15', '10:20:30', '2024-01-15 10:20:30', '2024']], $result->rows);
        self::assertSame([Field::Date, Field::Time, Field::DateTime, Field::Year], [$result->columns[0]->type, $result->columns[1]->type, $result->columns[2]->type, $result->columns[3]->type]);
    }

    public function testIntegerRoundsNumbersAndReadsStrings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('12' AS SIGNED), CAST(1.5 AS SIGNED), CAST(-2.5 AS SIGNED), CAST(CAST(5 AS UNSIGNED) AS SIGNED)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12', '2', '-3', '5']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
        self::assertFalse($result->columns[0]->unsigned());
    }

    public function testIntegerWarnsForAStringThatIsNoNumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('abc' AS SIGNED)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect INTEGER value: 'abc'"]], $warnings->rows);
    }

    public function testIntegerTakesTheComplementOfANegativeNumberCastToUnsigned(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(-5 AS UNSIGNED)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551611']], $result->rows);
        self::assertTrue($result->columns[0]->unsigned());
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(['Warning', '1105'], [$warnings->rows[0][0], $warnings->rows[0][1]]);
    }

    public function testIntegerConvertsAColumnToUnsigned(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (i INT)');
        $session->query('INSERT INTO t VALUES (-3), (7)');
        $result = $session->query('SELECT CAST(i AS UNSIGNED) FROM t ORDER BY i')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551613'], ['7']], $result->rows);
        self::assertSame(1, $result->warnings);
    }

    public function testDecimalRoundsToTheScaleOfTheTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(123.456 AS DECIMAL(5,2)), CAST('1.005' AS DECIMAL(4,2)), CAST(1 AS DECIMAL(5,2)), CAST(0.001 AS DECIMAL(3,2)), CAST(1.5 AS DECIMAL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['123.46', '1.01', '1.00', '0.00', '2']], $result->rows);
        self::assertSame([Field::NewDecimal, 2], [$result->columns[0]->type, $result->columns[0]->decimals]);
        self::assertSame(0, $result->warnings);
    }

    public function testDecimalClampsToTheLargestValueOfThePrecision(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(12345.6 AS DECIMAL(5,2)), CAST(-12345.6 AS DECIMAL(5,2)), CAST(12345678901 AS DECIMAL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['999.99', '-999.99', '9999999999']], $result->rows);
        self::assertSame(3, $result->warnings);
    }

    public function testTextCutsAStringLongerThanACharTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('abcdef' AS CHAR(3))")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect CHAR(3) value: 'abcdef'"]], $warnings->rows);
    }

    public function testTextCutsABinaryTargetToBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('abcdef' AS BINARY(2))")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ab']], $result->rows);
        self::assertTrue($result->columns[0]->binary());
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect BINARY(2) value: 'abcdef'"]], $warnings->rows);
    }

    public function testTextCountsTheCharactersOfAMultibyteString(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('é日本語' AS CHAR(2))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['é日']], $result->rows);
        self::assertSame(1, $result->warnings);
    }

    public function testTextKeepsAStringWithinTheTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('ab' AS CHAR(5)), CAST(12 AS CHAR), CAST(1.50 AS CHAR)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['ab', '12', '1.50']], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testDomainAnswersTheDomainOfTheTarget(): void
    {
        $domain = Domain::integer(unsigned: true);
        $conversion = new Conversion(new Constant(Domain::integer(), 1), $domain, null, 'UNSIGNED');

        self::assertSame($domain, $conversion->domain());
    }
}
