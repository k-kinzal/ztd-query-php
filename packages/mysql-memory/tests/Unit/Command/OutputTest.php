<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\Output;
use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Output::class)]
#[Small]
final class OutputTest extends TestCase
{
    public function testResultRetainsFoundRowsThroughFinalWindowAssignments(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql; CREATE TEMPORARY TABLE t(n INT); INSERT INTO t VALUES(1),(2),(3); SET @x=0');
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS @x:=ROW_NUMBER() OVER () AS v FROM t ORDER BY v DESC LIMIT 1 OFFSET 1')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame([['2']], $result->rows);
        self::assertSame(3, $session->variables->foundRows);
        self::assertSame(2, $session->variables->user('x')[0]);
    }

    public function testCharactersDistinguishesJsonBytesFromStringCharacters(): void
    {
        $output = new Output();
        $collation = Collation::known('utf8mb4_bin');

        self::assertSame([3, 3, 12], [$output->characters(new Domain(Kind::Json, Field::Json, 12, collation: $collation), false), $output->characters(Domain::string(3, $collation), false), $output->characters(Domain::string(12, $collation, Field::Blob), true)]);
    }

    public function testColumnCountsJsonBytesBeforeResultCharsetConversion(): void
    {
        $domain = new Domain(Kind::Json, Field::Json, 4294967295, 31, false, Collation::known('utf8mb4_bin'));
        $output = new Output();

        self::assertSame([4294967292, 1073741823, 4294967295], [$output->column('v', $domain, null, Charset::known('utf8mb4'))->length, $output->column('v', $domain, null, Charset::known('latin1'))->length, $output->column('v', $domain, null)->length]);
    }

    public function testResultCountsAnOffsetRowWithoutEvaluatingItsWindowArguments(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'");
        $result = $session->query('SELECT SQL_CALC_FOUND_ROWS FIRST_VALUE(1/0) OVER () FROM DUAL LIMIT 1 OFFSET 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame(1, $session->variables->foundRows);
        self::assertSame([1287], array_column($session->diagnostics->conditions, 1));
    }

    public function testColumnReportsStringDecimalsWithoutChangingTemporalEvaluationPrecision(): void
    {
        $domain = new Domain(Kind::Time, Field::VarString, 14, 3, false, Collation::known('latin1_swedish_ci'), displayDecimals: Domain::NOT_FIXED);
        $column = (new Output())->column('v', $domain, null, Charset::known('utf8mb4'));

        self::assertSame([Field::VarString, 56, Domain::NOT_FIXED], [$column->type, $column->length, $column->decimals]);
        self::assertSame(3, $domain->decimals);
    }

    public function testTextSendsALegacyBitResultAsAnUnsignedInteger(): void
    {
        $domain = new Domain(Kind::Bit, Field::LongLong, 8, 0, true);

        self::assertSame('65', (new Output())->text('A', $domain));
    }

    public function testColumnConvertsAnExplicitBlobMetadataWidth(): void
    {
        $domain = new Domain(Kind::String, Field::LongBlob, 1024, Domain::NOT_FIXED, false, Collation::known('utf8mb4_general_ci'), display: 4096);

        self::assertSame(4096, (new Output())->column('x', $domain, null)->length);
        self::assertSame(16384, (new Output())->column('x', $domain, null, Charset::known('utf8mb4'))->length);
        self::assertSame(4096, (new Output())->column('x', $domain, null, Charset::known('latin1'))->length);
    }

    public function testColumnConvertsReportedTextWidthWithoutReducingTheValueBound(): void
    {
        $domain = new Domain(Kind::String, Field::VarString, 22, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), display: 12);
        $output = new Output();

        self::assertSame([22, 12, 12, 3], [$domain->length, $output->column('v', $domain, null)->length, $output->column('v', $domain, null, Charset::known('utf8mb4'))->length, $output->column('v', $domain, null, Charset::known('latin1'))->length]);
        self::assertSame('1.23456', $output->text('1.23456', $domain));
    }

    public function testColumnKeepsZeroTextMetadataDistinctFromTheValueLength(): void
    {
        $domain = new Domain(Kind::String, Field::VarString, 1, Domain::NOT_FIXED, false, Collation::known('latin1_bin'), display: 0);
        $column = (new Output())->column('v', $domain, null, Charset::known('utf8mb4'));

        self::assertSame([1, 0], [$domain->length, $column->length]);
    }

    public function testColumnConvertsWideCharacterBlobByteBounds(): void
    {
        $domain = new Domain(Kind::String, Field::Blob, 8192, 0, false, Collation::known('utf16_general_ci'));
        $output = new Output();

        self::assertSame(8192, $output->column('x', $domain, null)->length);
        self::assertSame(16384, $output->column('x', $domain, null, Charset::known('utf8mb4'))->length);
        self::assertSame(4096, $output->column('x', $domain, null, Charset::known('latin1'))->length);
    }

    public function testResultWritesEachValueInItsText(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query("SELECT 1, 'a', NULL, 1.50, 2e0")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'a', null, '1.50', '2']], $result->rows);
        self::assertSame(0, $result->warnings);
    }

    public function testResultCountsTheRowsItFound(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2), (3)');

        $result = $session->query('SELECT a FROM t WHERE a > 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2'], ['3']], $result->rows);
        self::assertSame(2, $session->variables->foundRows);
    }

    public function testColumnsDescribeTheColumnsOfATable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b VARCHAR(5))');

        $result = $session->query('SELECT a, b AS c FROM t AS x')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(
            [
                ['a', Field::Long, 11, 0, 32768, 63, 'a', 'x', 't', 'd'],
                ['c', Field::VarString, 20, 0, 0, 255, 'b', 'x', 't', 'd'],
            ],
            [
                [$result->columns[0]->name, $result->columns[0]->type, $result->columns[0]->length, $result->columns[0]->decimals, $result->columns[0]->flags, $result->columns[0]->charset, $result->columns[0]->originalName, $result->columns[0]->table, $result->columns[0]->originalTable, $result->columns[0]->schema],
                [$result->columns[1]->name, $result->columns[1]->type, $result->columns[1]->length, $result->columns[1]->decimals, $result->columns[1]->flags, $result->columns[1]->charset, $result->columns[1]->originalName, $result->columns[1]->table, $result->columns[1]->originalTable, $result->columns[1]->schema],
            ],
        );
    }

    public function testColumnsReportTheDisplayWidthOfADerivedColumnThatExpressionsDoNotSee(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query('SELECT a, -a, a + 0, CONCAT(a) FROM (SELECT 3 AS a) d')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[Field::Long, 2], [Field::LongLong, 11], [Field::LongLong, 12], [Field::VarString, 44]], array_map(static fn ($column): array => [$column->type, $column->length], $result->columns));
    }

    public function testColumnsReportTheDisplayWidthOfATableColumnThatExpressionsDoNotSee(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (i INT(5), b BIGINT(3)); INSERT INTO t VALUES (1, 1)');

        $result = $session->query('SELECT i, -i, b, -b, COALESCE(i) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[Field::Long, 5], [Field::LongLong, 11], [Field::LongLong, 3], [Field::LongLong, 20], [Field::Long, 11]], array_map(static fn ($column): array => [$column->type, $column->length], $result->columns));
    }

    public function testColumnKeepsTheCharacterSetOfAStringWithoutResultsCharacterSet(): void
    {
        $column = (new Output())->column('a', Domain::string(5, Collation::known('latin1_swedish_ci')), null);

        self::assertSame([Field::VarString, 5, 8, '', ''], [$column->type, $column->length, $column->charset, $column->originalName, $column->table]);
    }

    public function testColumnConvertsAStringToTheResultsCharacterSet(): void
    {
        $column = (new Output())->column('a', Domain::string(5, Collation::known('latin1_swedish_ci')), null, Charset::known('utf8mb4'));

        self::assertSame([20, 255], [$column->length, $column->charset]);
    }

    public function testColumnUsesTheReleaseWhenConvertingResultMetadata(): void
    {
        $column = (new Output())->column('a', Domain::string(5, Collation::known('utf8mb4_bin')), null, Charset::known('utf8mb4'), \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertSame([20, 45, 129], [$column->length, $column->charset, $column->flags]);
    }

    public function testColumnSendsABinaryStringAndANumberInTheBinaryCharacterSet(): void
    {
        $output = new Output();
        $binary = $output->column('a', Domain::string(5, Collation::binary()), null, Charset::known('utf8mb4'));
        $number = $output->column('b', Domain::integer(), null, Charset::known('utf8mb4'));

        self::assertSame([[5, 63], [21, 63]], [[$binary->length, $binary->charset], [$number->length, $number->charset]]);
    }

    public function testColumnSendsATemporalValueInACharacterSetAsAString(): void
    {
        $domain = new Domain(Kind::Date, Field::Date, 10, 0, false, Collation::known('utf8mb4_0900_ai_ci'));
        $output = new Output();

        self::assertSame([[40, 255], [10, 8]], [[$output->column('d', $domain, null, Charset::known('utf8mb4'))->length, $output->column('d', $domain, null, Charset::known('utf8mb4'))->charset], [$output->column('d', $domain, null, Charset::known('latin1'))->length, $output->column('d', $domain, null, Charset::known('latin1'))->charset]]);
    }

    public function testColumnSendsAnEnumAsAString(): void
    {
        $domain = new Domain(Kind::String, Field::Enum, 1, 0, false, Collation::known('utf8mb4_0900_ai_ci'), true, ['a', 'b']);

        self::assertSame(Field::String, (new Output())->column('a', $domain, null)->type);
    }

    public function testColumnTakesTheNamesAndFlagsOfItsOrigin(): void
    {
        $column = (new Output())->column('n', Domain::integer(Field::Long, 11), new ColumnOrigin('d', 'x', 't', 'c', 2));

        self::assertSame([3, 'c', 'x', 't', 'd'], [$column->flags & 3, $column->originalName, $column->table, $column->originalTable, $column->schema]);
    }

    public function testConvertedMultipliesTheCharactersByTheirWidth(): void
    {
        self::assertSame(20, (new Output())->converted(5, 4));
    }

    public function testConvertedHoldsTheLengthWithinALengthField(): void
    {
        self::assertSame(4294967295, (new Output())->converted(2000000000, 3));
        self::assertSame(4294967292, (new Output())->converted(4294967295, 4));
        self::assertSame(4294967295, (new Output())->converted(4294967295, 4, true));
    }

    public function testTextWritesNullAsNull(): void
    {
        self::assertNull((new Output())->text(null, Domain::integer()));
    }

    public function testTextWritesAFloatWithSixSignificantDigits(): void
    {
        $domain = new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED);

        self::assertSame(['123457000', '1.25'], [(new Output())->text(123456789.0, $domain), (new Output())->text(1.25, $domain)]);
    }

    public function testTextWritesTheBytesOfABitValue(): void
    {
        self::assertSame("\x05", (new Output())->text("\x05", new Domain(Kind::Bit, Field::Bit, 8)));
    }

    public function testTextWritesAnIntegerInDecimal(): void
    {
        self::assertSame('-42', (new Output())->text(-42, Domain::integer()));
    }

    public function testTextPadsZerofillColumnsToTheirReportedWidth(): void
    {
        $output = new Output();

        self::assertSame('02', $output->text(2, Domain::integer(Field::Long, 10, true)->withDisplay(2), true));
        self::assertSame('123', $output->text(123, Domain::integer(Field::Long, 10, true)->withDisplay(2), true));
        self::assertSame('00007.25', $output->text('7.25', Domain::decimal(7, 2, true), true));
        self::assertSame('0000000000.5', $output->text(0.5, new Domain(Kind::Double, Field::Float, 12, 31), true));
    }

    public function testColumnDistinguishesStoredNumericFieldsFromNumericExpressions(): void
    {
        $output = new Output();
        $origin = new ColumnOrigin('d', '', 't', 'n');
        $domains = [Domain::integer(Field::Long, 11), Domain::decimal(5, 2), Domain::double(), new Domain(Kind::Bit, Field::Bit, 1, unsigned: true)];

        self::assertSame([32769, 1, 32769, 32], array_map(static fn (Domain $domain): int => $output->column('n', $domain, $origin)->flags, $domains));
        self::assertSame(32897, $output->column('n', $domains[0], null)->flags);
    }

    public function testColumnDropsTheBinaryFlagOfAZerofillColumn(): void
    {
        $column = (new Output())->column('y', new Domain(Kind::Year, Field::Year, 4, 0, true), new ColumnOrigin('d', 't', 't', 'y', 64));

        self::assertSame([64, 0, 32], [$column->flags & 64, $column->flags & 128, $column->flags & 32]);
    }

    public function testTextWritesFourDigitsForAZerofillYear(): void
    {
        $domain = new Domain(Kind::Year, Field::Year, 4, 0, true);

        self::assertSame(['0000', '2024', '0'], [(new Output())->text(0, $domain, true), (new Output())->text(2024, $domain, true), (new Output())->text(0, $domain)]);
    }

    public function testResultWritesAYearColumnWithFourDigits(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (y YEAR)');
        $session->query("INSERT INTO t VALUES (0), ('0')");
        $result = $session->query('SELECT y, CAST(0 AS YEAR) FROM t UNION ALL SELECT y, 5 FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0000', '0'], ['2000', '0'], ['0000', '5'], ['2000', '5']], $result->rows);
    }

    public function testResultCountsTheRowsBeforeTheLimitForSqlCalcFoundRows(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); INSERT INTO t VALUES (1), (2), (3), (4), (5)');
        $session->query('SELECT SQL_CALC_FOUND_ROWS * FROM t WHERE a > 1 LIMIT 1, 2');
        $calculated = $session->query('SELECT FOUND_ROWS()')[0];
        $session->query('SELECT * FROM t LIMIT 2');
        $sent = $session->query('SELECT FOUND_ROWS()')[0];
        $session->query('SELECT SQL_CALC_FOUND_ROWS a FROM t UNION SELECT 9 LIMIT 1');
        $union = $session->query('SELECT FOUND_ROWS()')[0];

        self::assertInstanceOf(ResultSet::class, $calculated);
        self::assertInstanceOf(ResultSet::class, $sent);
        self::assertInstanceOf(ResultSet::class, $union);
        self::assertSame([[['4']], [['2']], [['6']]], [$calculated->rows, $sent->rows, $union->rows]);
    }

    public function testSentConvertsAStringIntoTheCharacterSetOfTheResults(): void
    {
        $latin1 = Domain::string(1, Collation::known('latin1_swedish_ci'));

        self::assertSame(['é', "\xE9", '5', null], [(new Output())->sent("\xE9", $latin1, Charset::known('utf8mb4')), (new Output())->sent("\xE9", $latin1, null), (new Output())->sent('5', Domain::integer(), Charset::known('latin1')), (new Output())->sent(null, $latin1, Charset::known('utf8mb4'))]);
    }

    public function testResultSendsAStringOfAnotherCharacterSetConverted(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONVERT('é' USING latin1), _latin1'é', CONVERT('é' USING ucs2)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['é', 'Ã©', 'é']], $result->rows);
    }

    public function testColumnsConvertTheNamesIntoTheCharacterSetOfTheResults(): void
    {
        $session = (new Instance('5.7.44', ['character_set_client' => 'latin1', 'character_set_connection' => 'latin1', 'character_set_results' => 'latin1', 'collation_connection' => 'latin1_swedish_ci']))->connect();

        $result = $session->query("SELECT '\xC3\xA9', 1 AS '\xC3\xA9'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(["\xC3\xA9", "\xC3\xA9"], array_map(static fn (ResultColumn $column): string => $column->name, $result->columns));
    }

    public function testColumnReportsTheExactFlagsOfASystemColumn(): void
    {
        $column = (new Output())->column('x', Domain::integer(), new ColumnOrigin('', 't', 't', 'x', 36864, true));

        self::assertSame(36865, $column->flags);
    }
}
