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
}
