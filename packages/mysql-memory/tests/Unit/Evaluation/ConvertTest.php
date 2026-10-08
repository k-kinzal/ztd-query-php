<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Convert::class)]
#[Small]
final class ConvertTest extends TestCase
{
    public function testToDoubleReadsAnUnsignedIntegerAboveTheSignedRange(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(18446744073709551615.0, Convert::toDouble(-1, Domain::integer(Field::LongLong, 20, true), $context));
    }

    public function testToDoubleReadsTheNumberAtTheStartOfAStringAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(1.5, Convert::toDouble('1.5abc', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: '1.5abc'"]], $session->diagnostics->conditions);
    }

    public function testToDoubleReadsTheBytesOfABitValueAsAnUnsignedInteger(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(256.0, Convert::toDouble("\x01\x00", new Domain(Kind::Bit, Field::Bit, 16), $context));
    }

    public function testToDoubleReadsAHexadecimalLiteralAsTheIntegerOfItsBytes(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(65.0, Convert::toDouble('A', Domain::string(1, Collation::binary())->withNumericBytes(), $context));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testToDoubleReadsADateAsTheNumberOfItsDigits(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(20240229.0, Convert::toDouble('2024-02-29', new Domain(Kind::Date, Field::Date, 10), $context));
    }

    public function testToDoubleKeepsNull(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertNull(Convert::toDouble(null, Domain::integer(), $context));
    }

    public function testToIntegerRoundsADoubleHalfToEven(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([2, 4], [Convert::toInteger(2.5, Domain::double(), $context), Convert::toInteger(3.5, Domain::double(), $context)]);
    }

    public function testToIntegerRoundsADecimalHalfAwayFromZero(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([3, -3], [Convert::toInteger('2.5', Domain::decimal(2, 1), $context), Convert::toInteger('-2.5', Domain::decimal(2, 1), $context)]);
    }

    public function testToIntegerSaturatesANegativeDoubleAtZeroForAnUnsignedTarget(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(0, Convert::toInteger(-1.0, Domain::double(), $context, true));
    }

    public function testToIntegerReadsTheIntegerAtTheStartOfAStringAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(12, Convert::toInteger('12abc', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '12abc'"]], $session->diagnostics->conditions);
    }

    public function testToIntegerReadsATimeAsTheNumberOfItsDigits(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(103015, Convert::toInteger('10:30:15', new Domain(Kind::Time, Field::Time, 10), $context));
    }

    public function testToDecimalWritesAnUnsignedIntegerAboveTheSignedRange(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('18446744073709551615', Convert::toDecimal(-1, Domain::integer(Field::LongLong, 20, true), $context));
    }

    public function testToDecimalReadsADoubleAsItsShortestDecimal(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('0.1', Convert::toDecimal(0.1, Domain::double(), $context));
    }

    public function testToDecimalReadsTheNumberAtTheStartOfAStringAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('3.25', Convert::toDecimal('3.25x', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect DECIMAL value: '3.25x'"]], $session->diagnostics->conditions);
    }

    public function testToDecimalReadsADateTimeWithAFractionAsTheNumberOfItsDigits(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('20240229100000.5', Convert::toDecimal('2024-02-29 10:00:00.5', new Domain(Kind::DateTime, Field::DateTime, 21, 1), $context));
    }

    public function testToTextWritesAnUnsignedIntegerAboveTheSignedRange(): void
    {
        self::assertSame('18446744073709551615', Convert::toText(-1, Domain::integer(Field::LongLong, 20, true)));
    }

    public function testToTextWritesADoubleWithAFixedNumberOfDecimals(): void
    {
        self::assertSame('1.50', Convert::toText(1.5, Domain::double(22, 2)));
    }

    public function testToTextWritesALargeDoubleWithAnExponent(): void
    {
        self::assertSame(['1e20', '0.1'], [Convert::toText(1e20, Domain::double()), Convert::toText(0.1, Domain::double())]);
    }

    public function testToTextKeepsTheTextOfADecimalAndNull(): void
    {
        self::assertSame(['1.50', null], [Convert::toText('1.50', Domain::decimal(3, 2)), Convert::toText(null, Domain::decimal(3, 2))]);
    }

    public function testToBoolTellsWhetherANumberIsOtherThanZero(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(
            [true, false, false, false],
            [Convert::toBool(5, Domain::integer(), $context), Convert::toBool("\0", new Domain(Kind::Bit, Field::Bit, 1), $context), Convert::toBool(0.0, Domain::double(), $context), Convert::toBool('0.000', Domain::decimal(4, 3), $context)],
        );
    }

    public function testToBoolReadsAStringAsADoubleAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $domain = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([false, true, null], [Convert::toBool('abc', $domain, $context), Convert::toBool('1x', $domain, $context), Convert::toBool(null, $domain, $context)]);
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: 'abc'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: '1x'"]], $session->diagnostics->conditions);
    }

    public function testStringRealReadsAnOverflowAsTheLargestDoubleOfItsSignAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([PHP_FLOAT_MAX, -PHP_FLOAT_MAX, 12.5], [Convert::stringReal('1e400', $context), Convert::stringReal(' -1e400', $context), Convert::stringReal('12.5', $context)]);
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: '1e400'"], ['Warning', 1292, "Truncated incorrect DOUBLE value: ' -1e400'"]], $session->diagnostics->conditions);
    }

    public function testStringRealWarnsOnceForAnOverflowThatMoreFollows(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(PHP_FLOAT_MAX, Convert::stringReal('1e400x', $context));
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: '1e400x'"]], $session->diagnostics->conditions);
    }

    public function testStringNumberReadsTheNumberAtTheStartAndWarnsWithTheKindRead(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('12.5e1', Convert::stringNumber(' 12.5e1xyz', 'DOUBLE', $context, false));
        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: ' 12.5e1xyz'"]], $session->diagnostics->conditions);
    }

    public function testStringNumberDoesNotWarnForACompleteNumber(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('12.5', Convert::stringNumber('12.5', 'DECIMAL', $context, true));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testStringIntegerSaturatesANegativeOverflowAndWarns(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(PHP_INT_MIN, Convert::stringInteger('-99999999999999999999', $context, false));
        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '-99999999999999999999'"]], $session->diagnostics->conditions);
    }

    public function testStringIntegerHoldsTheLargestUnsignedValueInTheBitsOfAnInt(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(-1, Convert::stringInteger('18446744073709551615', $context, true));
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testExactIntegerSaturatesAtTheBoundsOfTheUnsignedRange(): void
    {
        self::assertSame(
            [0, -1, -1, 5],
            [Convert::exactInteger('-5', true), Convert::exactInteger('18446744073709551616', true), Convert::exactInteger('18446744073709551615', true), Convert::exactInteger('5', true)],
        );
    }

    public function testExactIntegerSaturatesAtTheBoundsOfTheSignedRange(): void
    {
        self::assertSame(
            [PHP_INT_MAX, PHP_INT_MIN, 42],
            [Convert::exactInteger('9223372036854775808', false), Convert::exactInteger('-9223372036854775809', false), Convert::exactInteger('42', false)],
        );
    }

    public function testBitsReadsTheBytesWithTheFirstTheMostSignificant(): void
    {
        self::assertSame([258, 0], [Convert::bits("\x01\x02"), Convert::bits('')]);
    }

    public function testBitsReadsOnlyTheLastEightBytes(): void
    {
        self::assertSame(1, Convert::bits("\x05\x00\x00\x00\x00\x00\x00\x00\x01"));
    }

    public function testShownQuotesABinaryStringByItsBytesAndAnotherInUtf8(): void
    {
        self::assertSame(['\x00\xFFa\x0A', 'é', 'é', "\xE9"], [Convert::shown("\x00\xFFa\n", Charset::binary()), Convert::shown("\xE9", Charset::known('latin1')), Convert::shown('é', Charset::known('utf8mb4')), Convert::shown("\xE9", null)]);
    }

    public function testToDoubleQuotesTheValueOfAStringInItsWarning(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT _binary X'41FF42' + 0, CONVERT('é' USING latin1) + 0, CAST(CONVERT('é12' USING utf16) AS UNSIGNED), CONVERT('12' USING utf16) + 0");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1292', "Truncated incorrect DOUBLE value: 'A\\xFFB'"],
            ['Warning', '1292', "Truncated incorrect DOUBLE value: 'é'"],
            ['Warning', '1292', "Truncated incorrect INTEGER value: 'é12'"],
        ], $warnings->rows);
    }

    public function testReadableReadsAWideCharacterSetInUtf8(): void
    {
        $ucs2 = Domain::string(2, Collation::known('ucs2_general_ci'));
        $latin1 = Domain::string(2, Collation::known('latin1_swedish_ci'));

        self::assertSame(['12', "\xE9"], [Convert::readable("\x001\x002", $ucs2), Convert::readable("\xE9", $latin1)]);
    }

    public function testReadableCharsetAnswersUtf8ForAWideCharacterSet(): void
    {
        self::assertSame(['utf8mb4', 'latin1', 'binary'], [Convert::readableCharset(Domain::string(2, Collation::known('utf16_general_ci')))->name, Convert::readableCharset(Domain::string(2, Collation::known('latin1_swedish_ci')))->name, Convert::readableCharset(Domain::integer())->name]);
    }

    public function testOperandDecimalWarnsForAColumnAsTheServerDoes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (s VARCHAR(10))');
        $session->query("INSERT INTO t VALUES ('a'), ('3x'), ('')");
        $result = $session->query('SELECT s DIV 3 FROM t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0'], ['1'], ['0']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '1366', "Incorrect DECIMAL value: '0' for column '' at row -1"],
            ['Warning', '1292', "Truncated incorrect DECIMAL value: 'a'"],
            ['Warning', '1292', "Truncated incorrect DECIMAL value: '3x'"],
            ['Warning', '1366', "Incorrect DECIMAL value: '0' for column '' at row -1"],
            ['Warning', '1292', "Truncated incorrect DECIMAL value: ''"],
        ], $warnings->rows);
    }

    public function testOperandDecimalReadsTheBinaryStringOfABitOperatorAsALiteral(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT ~BINARY('a') DIV 1");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame(['Warning', '1292', "Truncated incorrect DECIMAL value: '\\x9E'"], $warnings->rows[1]);
    }

    public function testStringIntegerWarnsForAnEmptyString(): void
    {
        $session = (new Instance())->connect();
        $session->query("SELECT '' & 1, ' ' + 0, CAST('' AS DECIMAL)");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect INTEGER value: ''"], ['Warning', '1292', "Truncated incorrect DECIMAL value: ''"]], $warnings->rows);
    }

    public function testDecimalIntegerSaturatesWithAWarning(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT CAST(18446744073709551616 AS SIGNED), CAST(-1.5 AS UNSIGNED), CAST(-18446744073709551616 AS UNSIGNED)')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['9223372036854775807', '18446744073709551614', '9223372036854775808']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect DECIMAL value: '18446744073709551616'"], ['Warning', '1292', "Truncated incorrect DECIMAL value: '-18446744073709551616'"]], $warnings->rows);
    }

    public function testToDoubleReadsAQuietStringWithoutWarning(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("SELECT CONCAT('1x') + 0, LOWER('ax') = 0");

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testOrdinalReadsEnumAndSetValuesAsNumbers(): void
    {
        $enum = new Domain(Kind::String, Field::Enum, 2, 31, false, Collation::known('utf8mb4_0900_ai_ci'), true, ['x', 'yy']);
        $set = new Domain(Kind::String, Field::Set, 5, 31, false, Collation::known('utf8mb4_0900_ai_ci'), true, ['a', 'b', 'c']);
        $text = new Domain(Kind::String, Field::VarString, 2, 31, false, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([2, 0, 5, null], [Convert::ordinal('yy', $enum), Convert::ordinal('', $enum), Convert::ordinal('a,c', $set), Convert::ordinal('yy', $text)]);
    }

    public function testToDoubleReadsAnEnumValueAsItsPosition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE TABLE t (e ENUM('x', 'yy'), s SET('a', 'b', 'c'))");
        $session->query("INSERT INTO t VALUES ('yy', 'a,c')");
        $result = $session->query('SELECT e + 0, CEILING(e), s + 0 FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2', '2', '5']], $result->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
