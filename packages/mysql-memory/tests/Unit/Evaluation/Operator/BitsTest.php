<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Bits;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Bits::class)]
#[Small]
final class BitsTest extends TestCase
{
    public function testEvaluateComputesOnUnsignedIntegers(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 5 | 2, 6 & 3, 5 ^ 1, 1 << 3, 16 >> 2')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '2', '4', '8', '4']], $result->rows);
        self::assertSame(Field::LongLong, $result->columns[0]->type);
        self::assertTrue($result->columns[0]->unsigned());
    }

    public function testEvaluateWorksOnAllSixtyFourBits(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT ~0, ~1, -1 | 0, 1 << 63, -1 >> 60')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['18446744073709551615', '18446744073709551614', '18446744073709551615', '9223372036854775808', '15']], $result->rows);
    }

    public function testEvaluateShiftsBySixtyFourBitsOrMoreToZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 << 64, 255 >> 64, 3 >> -1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '0', '0']], $result->rows);
    }

    public function testEvaluateReturnsNullForANullOperand(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT 1 | NULL, ~NULL, NULL << 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }

    public function testEvaluateReadsOtherOperandsAsIntegers(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT X'0F' | 1, 1.6 | 0, 'a' | 1")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['15', '2', '1']], $result->rows);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1292', "Truncated incorrect INTEGER value: 'a'"]], $warnings->rows);
    }

    public function testBytesComputesByteByByteOnBinaryStrings(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE b (x VARBINARY(4), y VARBINARY(4))');
        $session->query("INSERT INTO b VALUES (X'00FF', X'0F0F')");
        $result = $session->query('SELECT HEX(x | y), HEX(x & y), HEX(x ^ y), HEX(~x) FROM b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0FFF', '000F', '0FF0', 'FF00']], $result->rows);
    }

    public function testBytesReturnsNullForANullOperand(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE b (x VARBINARY(4), y VARBINARY(4))');
        $session->query("INSERT INTO b VALUES (X'00FF', NULL)");
        $result = $session->query('SELECT x | y, y & x, ~y FROM b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[null, null, null]], $result->rows);
    }

    public function testBytesRaisesForOperandsOfDifferentLengths(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE b (x VARBINARY(4), y VARBINARY(4))');
        $session->query("INSERT INTO b VALUES (X'01', X'0102')");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3513);
        $this->expectExceptionMessage('Binary operands of bitwise operators must be of equal length');

        $session->query('SELECT x | y FROM b');
    }

    public function testShiftedKeepsTheLengthOfABinaryString(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE b (x VARBINARY(4))');
        $session->query("INSERT INTO b VALUES (X'00FF')");
        $result = $session->query('SELECT HEX(x << 8), HEX(x >> 4), HEX(x << 20), HEX(x >> 3) FROM b')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['FF00', '000F', '0000', '001F']], $result->rows);
    }

    public function testShiftedMovesBitsAcrossBytes(): void
    {
        $operand = new Constant(Domain::string(2, Collation::binary()), "\x00\xFF");
        $bits = new Bits(ArithmeticOperator::ShiftLeft, $operand, $operand, Domain::string(2, Collation::binary()), 'x << 4');

        self::assertSame(["\x0F\xF0", "\x40\x00"], [$bits->shifted("\x00\xFF", 4, true), $bits->shifted("\x80\x01", 1, false)]);
    }

    public function testShiftedClearsEveryBitForANegativeCount(): void
    {
        $operand = new Constant(Domain::string(2, Collation::binary()), "\xFF\xFF");
        $bits = new Bits(ArithmeticOperator::ShiftRight, $operand, $operand, Domain::string(2, Collation::binary()), 'x >> -1');

        self::assertSame("\x00\x00", $bits->shifted("\xFF\xFF", -1, false));
    }

    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::integer(unsigned: true);
        $bits = new Bits(ArithmeticOperator::BitOr, new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 2), $domain, '(1 | 2)');

        self::assertSame($domain, $bits->domain());
    }
}
