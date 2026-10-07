<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use MySqlMemory\Protocol\Binary;
use MySqlMemory\Protocol\MalformedPacket;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Protocol\PayloadWriter;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Binary::class)]
#[Small]
final class BinaryTest extends TestCase
{
    public function testRowWritesTheHeaderTheNullBitmapAndTheValues(): void
    {
        $columns = [new ResultColumn('id', Field::Long, 11, 0, 0, 63), new ResultColumn('name', Field::VarString, 80, 0, 0, 255)];

        self::assertSame("\x00\x00\x07\x00\x00\x00\x03ink", (new Binary())->row($columns, ['7', 'ink']));
    }

    public function testRowMarksNullValuesFromTheThirdBit(): void
    {
        $columns = [new ResultColumn('id', Field::Long, 11, 0, 0, 63), new ResultColumn('name', Field::VarString, 80, 0, 0, 255)];

        self::assertSame("\x00\x08\x07\x00\x00\x00", (new Binary())->row($columns, ['7', null]));
        self::assertSame("\x00\x0C", (new Binary())->row($columns, [null, null]));
    }

    public function testRowWritesASecondBitmapByteFromTheSeventhColumn(): void
    {
        $column = new ResultColumn('a', Field::Tiny, 4, 0, 0, 63);

        self::assertSame("\x00\xFC\x00\x01", (new Binary())->row([$column, $column, $column, $column, $column, $column, $column], [null, null, null, null, null, null, '1']));
    }

    public function testValueWritesIntegersInTheWidthOfTheirType(): void
    {
        $writer = new PayloadWriter();
        $binary = new Binary();
        $binary->value($writer, new ResultColumn('a', Field::Tiny, 4, 0, 0, 63), '-1');
        $binary->value($writer, new ResultColumn('a', Field::Short, 6, 0, 0, 63), '258');
        $binary->value($writer, new ResultColumn('a', Field::Year, 4, 0, 0, 63), '2024');
        $binary->value($writer, new ResultColumn('a', Field::Int24, 9, 0, 0, 63), '-2');
        $binary->value($writer, new ResultColumn('a', Field::Long, 11, 0, 0, 63), '65536');

        self::assertSame("\xFF\x02\x01\xE8\x07\xFE\xFF\xFF\xFF\x00\x00\x01\x00", $writer->payload());
    }

    public function testValueWritesABigintInEightBytes(): void
    {
        $writer = new PayloadWriter();
        $binary = new Binary();
        $binary->value($writer, new ResultColumn('a', Field::LongLong, 20, 0, 0, 63), '-2');
        $binary->value($writer, new ResultColumn('a', Field::LongLong, 20, 0, ColumnFlag::Unsigned->value, 63), '18446744073709551615');

        self::assertSame("\xFE\xFF\xFF\xFF\xFF\xFF\xFF\xFF" . "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF", $writer->payload());
    }

    public function testValueWritesFloatingPointNumbersLittleEndian(): void
    {
        $writer = new PayloadWriter();
        $binary = new Binary();
        $binary->value($writer, new ResultColumn('a', Field::Float, 12, 31, 0, 63), '1.5');
        $binary->value($writer, new ResultColumn('a', Field::Double, 22, 31, 0, 63), '-2');

        self::assertSame("\x00\x00\xC0\x3F" . "\x00\x00\x00\x00\x00\x00\x00\xC0", $writer->payload());
    }

    public function testValueWritesTemporalValuesInTheirBinaryForm(): void
    {
        $writer = new PayloadWriter();
        $binary = new Binary();
        $binary->value($writer, new ResultColumn('a', Field::Date, 10, 0, 0, 63), '2024-03-05');
        $binary->value($writer, new ResultColumn('a', Field::Timestamp, 19, 0, 0, 63), '2024-03-05 10:20:30');
        $binary->value($writer, new ResultColumn('a', Field::Time, 10, 0, 0, 63), '10:20:30');

        self::assertSame("\x04\xE8\x07\x03\x05" . "\x07\xE8\x07\x03\x05\x0A\x14\x1E" . "\x08\x00\x00\x00\x00\x00\x0A\x14\x1E", $writer->payload());
    }

    public function testValueWritesOtherTypesAsLengthEncodedStrings(): void
    {
        $writer = new PayloadWriter();
        $binary = new Binary();
        $binary->value($writer, new ResultColumn('a', Field::NewDecimal, 5, 2, 0, 63), '-1.50');
        $binary->value($writer, new ResultColumn('a', Field::VarString, 80, 0, 0, 255), 'ink');
        $binary->value($writer, new ResultColumn('a', Field::Blob, 65535, 0, 144, 63), '');
        $binary->value($writer, new ResultColumn('a', Field::Json, 4294967295, 0, 144, 63), '[1]');

        self::assertSame("\x05-1.50\x03ink\x00\x03[1]", $writer->payload());
    }

    public function testDateTimeWritesFourBytesForADate(): void
    {
        self::assertSame("\x04\xE8\x07\x03\x05", (new Binary())->dateTime('2024-03-05'));
        self::assertSame("\x04\xE8\x07\x03\x05", (new Binary())->dateTime('2024-03-05 00:00:00'));
    }

    public function testDateTimeWritesSevenBytesWithATime(): void
    {
        self::assertSame("\x07\xE8\x07\x0C\x1F\x17\x3B\x3B", (new Binary())->dateTime('2024-12-31 23:59:59'));
    }

    public function testDateTimeWritesElevenBytesWithMicroseconds(): void
    {
        self::assertSame("\x0B\xE8\x07\x03\x05\x00\x00\x00\x7B\x00\x00\x00", (new Binary())->dateTime('2024-03-05 00:00:00.000123'));
    }

    public function testDateTimeWritesNoBytesForTheZeroDate(): void
    {
        self::assertSame("\x00", (new Binary())->dateTime('0000-00-00'));
        self::assertSame("\x00", (new Binary())->dateTime('0000-00-00 00:00:00'));
    }

    public function testTimeWritesNoBytesForZero(): void
    {
        self::assertSame("\x00", (new Binary())->time('00:00:00'));
    }

    public function testTimeWritesTheDaysOfMoreThan24Hours(): void
    {
        self::assertSame("\x08\x01\x01\x00\x00\x00\x01\x00\x01", (new Binary())->time('-25:00:01'));
    }

    public function testTimeWritesTwelveBytesWithMicroseconds(): void
    {
        self::assertSame("\x0C\x00\x00\x00\x00\x00\x01\x02\x03\x20\xA1\x07\x00", (new Binary())->time('01:02:03.500000'));
    }

    public function testParametersAnswersNoValuesWithoutParameters(): void
    {
        $reader = new PayloadReader('');

        self::assertSame([[], [8]], (new Binary())->parameters($reader, 0, [8], Collation::known('utf8mb4_0900_ai_ci')));
    }

    public function testParametersReadsTheTypesSentWithTheValues(): void
    {
        $reader = new PayloadReader("\x02\x01\x08\x00\xFD\x00\x2A\x00\x00\x00\x00\x00\x00\x00");

        self::assertEquals(
            [[[42, Domain::integer(Field::LongLong, 20)->withNullable(false)], [null, Domain::null()]], [8, 253]],
            (new Binary())->parameters($reader, 2, [], Collation::known('utf8mb4_0900_ai_ci')),
        );
    }

    public function testParametersReadsTheValuesWithTheTypesLastBound(): void
    {
        $reader = new PayloadReader("\x00\x00\x2A\x02ab");
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertEquals(
            [[[42, Domain::integer(Field::LongLong, 4)->withNullable(false)], ['ab', new Domain(Kind::String, Field::VarString, 2, Domain::NOT_FIXED, false, $collation, false)]], [1, 253]],
            (new Binary())->parameters($reader, 2, [1, 253], $collation),
        );
    }

    public function testParametersRefusesAPayloadThatEndsBeforeAValue(): void
    {
        $reader = new PayloadReader("\x00\x01\x08\x00\x2A");

        $this->expectException(MalformedPacket::class);

        (new Binary())->parameters($reader, 1, [], Collation::known('utf8mb4_0900_ai_ci'));
    }

    public function testParameterReadsSignedIntegers(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $binary = new Binary();

        self::assertEquals([-1, Domain::integer(Field::LongLong, 4)->withNullable(false)], $binary->parameter(new PayloadReader("\xFF"), 1, $collation));
        self::assertEquals([-2, Domain::integer(Field::LongLong, 6)->withNullable(false)], $binary->parameter(new PayloadReader("\xFE\xFF"), 2, $collation));
        self::assertEquals([-3, Domain::integer(Field::LongLong, 11)->withNullable(false)], $binary->parameter(new PayloadReader("\xFD\xFF\xFF\xFF"), 3, $collation));
        self::assertEquals([-4, Domain::integer(Field::LongLong, 20)->withNullable(false)], $binary->parameter(new PayloadReader("\xFC\xFF\xFF\xFF\xFF\xFF\xFF\xFF"), 8, $collation));
    }

    public function testParameterReadsUnsignedIntegers(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $binary = new Binary();

        self::assertEquals([255, Domain::integer(Field::LongLong, 4, true)->withNullable(false)], $binary->parameter(new PayloadReader("\xFF"), 0x8001, $collation));
        self::assertEquals([4294967295, Domain::integer(Field::LongLong, 11, true)->withNullable(false)], $binary->parameter(new PayloadReader("\xFF\xFF\xFF\xFF"), 0x8003, $collation));
    }

    public function testParameterReadsFloatingPointNumbers(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $binary = new Binary();

        self::assertEquals([1.5, Domain::double()->withNullable(false)], $binary->parameter(new PayloadReader("\x00\x00\xC0\x3F"), 4, $collation));
        self::assertEquals([-2.0, Domain::double()->withNullable(false)], $binary->parameter(new PayloadReader("\x00\x00\x00\x00\x00\x00\x00\xC0"), 5, $collation));
    }

    public function testParameterReadsANullTypeAsNull(): void
    {
        self::assertEquals([null, Domain::null()], (new Binary())->parameter(new PayloadReader(''), 6, Collation::known('utf8mb4_0900_ai_ci')));
    }

    public function testParameterReadsOtherTypesAsText(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertEquals(['2024-03-05', new Domain(Kind::String, Field::VarString, 10, Domain::NOT_FIXED, false, $collation, false)], (new Binary())->parameter(new PayloadReader("\x0A2024-03-05"), 10, $collation));
        self::assertEquals(['x', new Domain(Kind::String, Field::VarString, 1, Domain::NOT_FIXED, false, $collation, false)], (new Binary())->parameter(new PayloadReader("\x01x"), 0x77, $collation));
    }

    public function testTextTypesADecimalByItsDigits(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertEquals(['-12.345', Domain::decimal(5, 3)->withNullable(false)], (new Binary())->text('-12.345', Field::NewDecimal, $collation));
        self::assertEquals(['12', Domain::decimal(2, 0)->withNullable(false)], (new Binary())->text('12', Field::Decimal, $collation));
        self::assertEquals(['', Domain::decimal(1, 0)->withNullable(false)], (new Binary())->text('', Field::NewDecimal, $collation));
    }

    public function testTextTypesAStringInTheConnectionCollation(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertEquals(['日本', new Domain(Kind::String, Field::VarString, 2, Domain::NOT_FIXED, false, $collation, false)], (new Binary())->text('日本', Field::VarString, $collation));
    }

    public function testTextTypesABlobAsBinary(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertEquals(['ab', new Domain(Kind::String, Field::VarString, 2, Domain::NOT_FIXED, false, Collation::binary(), false)], (new Binary())->text('ab', Field::Blob, $collation));
        self::assertEquals(['ab', new Domain(Kind::String, Field::VarString, 2, Domain::NOT_FIXED, false, Collation::binary(), false)], (new Binary())->text('ab', Field::TinyBlob, $collation));
        self::assertEquals(['ab', new Domain(Kind::String, Field::VarString, 2, Domain::NOT_FIXED, false, Collation::binary(), false)], (new Binary())->text('ab', Field::MediumBlob, $collation));
        self::assertEquals(['ab', new Domain(Kind::String, Field::VarString, 2, Domain::NOT_FIXED, false, Collation::binary(), false)], (new Binary())->text('ab', Field::LongBlob, $collation));
    }

    public function testFloatReadsAFloat(): void
    {
        self::assertSame(0.25, (new Binary())->float(new PayloadReader("\x00\x00\x80\x3E"), 'g', 4));
    }

    public function testFloatReadsADouble(): void
    {
        self::assertSame(0.1, (new Binary())->float(new PayloadReader("\x9A\x99\x99\x99\x99\x99\xB9\x3F"), 'e', 8));
    }

    public function testFloatRefusesAPayloadThatEndsBeforeTheNumber(): void
    {
        $this->expectException(MalformedPacket::class);

        (new Binary())->float(new PayloadReader("\x00\x00"), 'g', 4);
    }
}
