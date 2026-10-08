<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\Output;
use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
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
                ['a', Field::Long, 11, 0, 32896, 63, 'a', 'x', 't', 'd'],
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
}
