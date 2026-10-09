<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Literals;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Literals::class)]
#[Small]
final class LiteralsTest extends TestCase
{
    public function testRulesTypeTheLiteralsOfAColumnDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE TABLE t (a DECIMAL(5,2) DEFAULT 1.5, c VARCHAR(3) DEFAULT 'x')");
        $session->query('INSERT INTO t VALUES ()');
        $result = $session->query('SELECT a, c FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1.50', 'x']], $result->rows);
    }

    public function testTypedGivesALiteralTheTypeSqlSemanticsResolved(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1.50, 18446744073709551615, 1e2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['NewDecimal', 'LongLong', 'Double'], [$result->columns[0]->type->name, $result->columns[1]->type->name, $result->columns[2]->type->name]);
        self::assertSame([false, true, false], [$result->columns[0]->unsigned(), $result->columns[1]->unsigned(), $result->columns[2]->unsigned()]);
    }

    public function testNumberCompilesIntegersDecimalsAndDoubles(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 42, 18446744073709551615, -9223372036854775808, 1.50, 1e2, 007')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['42', '18446744073709551615', '-9223372036854775808', '1.50', '100', '7']], $result->rows);
    }

    public function testSignedCompilesANegativeNumberOfADefaultClause(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT DEFAULT -5, b DOUBLE DEFAULT -1.5)');
        $session->query('INSERT INTO t VALUES ()');
        $result = $session->query('SELECT a, b FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['-5', '-1.5']], $result->rows);
    }

    public function testStringCompilesTheValueOfAdjacentQuotedStrings(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT 'a' 'b' 'c', 'it''s', 'tab\\there'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', "it's", "tab\there"]], $result->rows);
    }

    public function testRadixCompilesTheBytesOfAHexadecimalOrBitLiteral(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT X'41', 0x4142, b'1000001', 0x4, b'1'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['A', 'AB', 'A', "\x04", "\x01"]], $result->rows);
    }

    public function testRadixReadsTheBytesAsAnUnsignedIntegerInANumericContext(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT X'41' + 0, b'1000001' + 0, 0xFFFFFFFFFFFFFFFF + 0")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['65', '65', '18446744073709551615']], $result->rows);
    }

    public function testTemporalCompilesDateTimeAndTimestampLiterals(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT DATE '2024-02-29', TIME '-10:11:12', TIMESTAMP '2024-02-29 10:00:00.123'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-02-29', '-10:11:12', '2024-02-29 10:00:00.123']], $result->rows);
        self::assertSame(3, $result->columns[2]->decimals);
    }

    public function testTemporalRefusesADateThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect DATE value: '2024-02-30'");

        $session->query("SELECT DATE '2024-02-30'");
    }

    public function testBooleanCompilesTrueAndFalseAsOneAndZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT TRUE, FALSE, TRUE + TRUE')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '2']], $result->rows);
    }

    public function testNullCompilesTheNullLiteral(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT NULL')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        self::assertSame('Null', $result->columns[0]->type->name);
    }

    public function testTemporalRoundsTheSeventhFractionalDigitAndRefusesValuesOutOfRange(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT TIME '10:00:00.1234567', TIMESTAMP '2024-12-31 23:59:59.9999995', TIME '-0:00:00', DATE '5-1-1'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['10:00:00.123457', '2025-01-01 00:00:00.000000', '-00:00:00', '0005-01-01']], $result->rows);
    }

    public function testTemporalAcceptsZeroDatesWhereTheModeAllowsThem(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = ''");
        $result = $session->query("SELECT DATE '0000-00-00', DATE '2024-00-00', TIMESTAMP '2024-01-00 00:00:00'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0000-00-00', '2024-00-00', '2024-01-00 00:00:00']], $result->rows);
    }

    public function testOdbcReadsATemporalLiteralOrStandsForTheOperand(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT { d '2020-1-1' }, { ts '2020-01-01 10:00:00.5' }, { t '1 10:00:00' }, { foo 1 + 1 }, { D '2020-01-01' }")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2020-01-01', '2020-01-01 10:00:00.5', '34:00:00', '2', '2020-01-01']], $result->rows);
        self::assertSame(['{ d \'2020-1-1\' }', '{ ts \'2020-01-01 10:00:00.5\' }', '{ t \'1 10:00:00\' }', '{ foo 1 + 1 }', '2020-01-01'], array_map(static fn ($column): string => $column->name, $result->columns));
    }

    public function testStringPadsALiteralOfWideUnitsWithLeadingZeroBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(_utf16'a'), HEX(_utf32'a'), HEX(_utf16'ab'), HEX(_latin1'a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0061', '00000061', '6162', '61']], $result->rows);
    }
}
