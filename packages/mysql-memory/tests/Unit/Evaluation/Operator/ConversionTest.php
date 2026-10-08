<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Instance;
use MySqlMemory\Result\ColumnFlag;
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
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['é日']], $result->rows);
        self::assertSame(1, $result->warnings);
        self::assertSame(0, $result->columns[0]->flags & ColumnFlag::NotNull->value);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect CHAR(5) value: 'é日本語'"]], $warnings->rows);
    }

    public function testTextNamesANationalTargetCharByTheBytesItKeeps(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT CAST('日本語x' AS NCHAR(2))");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertContains(['Warning', '1292', "Truncated incorrect CHAR(6) value: '日本語x'"], $warnings->rows);
    }

    public function testTextPadsABinaryTargetWithZeroBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST('ab' AS BINARY(5)), CAST(1 AS CHAR(3) CHARSET binary)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([["ab\0\0\0", "1\0\0"]], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testQuotedWritesTheBytesOfABinaryValueAndTheCharactersOfAText(): void
    {
        $conversion = new Conversion(new Constant(Domain::integer(), 1), Domain::integer(), null, 'CHAR');

        self::assertSame(['\x00A\x0A\x7F\xC3\xA9', 'é?abc', str_repeat('a', 128)], [$conversion->quoted("\x00A\n\x7Fé", true), $conversion->quoted('é😀abc', false), $conversion->quoted(str_repeat('a', 300), false)]);
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

    public function testTranscodeConvertsATextIntoACharacterSet(): void
    {
        $instance = new Instance();
        $context = new \MySqlMemory\Evaluation\Context(new \MySqlMemory\Session\SqlModes([]), new \MySqlMemory\Session\Diagnostics(), new \MySqlMemory\Session\Variables($instance->catalog, $instance->globals), 0.0);
        $utf8 = Domain::string(4, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'));
        $binary = Domain::string(4, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary());

        self::assertSame(
            ["\xE9", "\x00\x41", "\x00\x41\x42\x43", 'é', "\x00\x31"],
            [
                Conversion::transcode('é', $utf8, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1'), $context),
                Conversion::transcode('A', $binary, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('ucs2'), $context),
                Conversion::transcode('ABC', $binary, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('ucs2'), $context),
                Conversion::transcode('é', $binary, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'), $context),
                Conversion::transcode('1', Domain::integer(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('ucs2'), $context),
            ],
        );
    }

    public function testTranscodeAnswersNullWithAWarningForBytesThatAreNoCharacter(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONVERT(X'4142FF434445464748494A4B4C' USING utf8mb4), HEX(CAST(X'41FF' AS CHAR CHARACTER SET ascii)), CONVERT(X'D800' USING utf16), HEX(CAST(X'41FF' AS CHAR CHARACTER SET latin1))")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[null, '41FF', null, '41FF']], $result->rows);
        self::assertSame([
            ['Warning', '1300', "Invalid utf8mb4 character string: 'FF4344'"],
            ['Warning', '1300', "Invalid ascii character string: 'FF'"],
            ['Warning', '1300', "Invalid utf16 character string: 'D800'"],
        ], $warnings->rows);
    }

    public function testEvaluateCastsIntoTheCharacterSetOfTheTarget(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(CAST('é' AS CHAR CHARACTER SET latin1)), HEX(CAST('é' AS CHAR CHARACTER SET binary)), HEX(CAST(CONVERT('é' USING latin1) AS CHAR))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['E9', 'C3A9', 'C3A9']], $result->rows);
    }

    public function testIntegerTakesTheComplementOfANegativeDecimalWithoutANote(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CAST(-1.5 AS UNSIGNED), CAST(CONCAT('-1') AS UNSIGNED)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551614', '18446744073709551615']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $warnings->rows);
    }

    public function testIntegerMakesANegativeIntegerUnsignedSilentlyIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('SELECT CAST(-1 AS UNSIGNED)');

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testMomentReadsAJsonValueAsAStringOrATemporalValue(): void
    {
        $result = (new Instance())->connect()->query("SELECT CAST(CAST('\"2020-01-02\"' AS JSON) AS DATE), CAST(CAST(DATE'2020-01-02' AS JSON) AS DATETIME), CAST(CAST('[1]' AS JSON) AS DATE)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2020-01-02', '2020-01-02 00:00:00', null]], $result->rows);
        self::assertSame(1, $result->warnings);
    }

    public function testEvaluateNormalizesACastToJson(): void
    {
        $result = (new Instance())->connect()->query("SELECT CAST('{\"b\":1,\"a\":2}' AS JSON), CAST(1.50 AS JSON), CAST(x'01' AS JSON), CAST(1=1 AS JSON), JSON_TYPE(CAST(TIMESTAMP'2020-01-01 00:00:00' AS JSON))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['{"a": 2, "b": 1}', '1.50', '"base64:type15:AQ=="', 'true', 'DATETIME']], $result->rows);
    }
}
