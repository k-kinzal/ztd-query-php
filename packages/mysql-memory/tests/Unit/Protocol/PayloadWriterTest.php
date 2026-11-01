<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use MySqlMemory\Protocol\PayloadWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadWriter::class)]
#[Small]
final class PayloadWriterTest extends TestCase
{
    public function testIntegerWritesLittleEndianBytes(): void
    {
        self::assertSame("\x02\x01", (new PayloadWriter())->integer(0x0102, 2)->payload());
        self::assertSame("\x04\x03\x02\x01", (new PayloadWriter())->integer(0x01020304, 4)->payload());
        self::assertSame("\x01", (new PayloadWriter())->integer(0x0201, 1)->payload());
    }

    public function testIntegerWritesTheLowBytesOfANegativeValue(): void
    {
        self::assertSame("\xFF\xFF", (new PayloadWriter())->integer(-1, 2)->payload());
    }

    public function testLengthEncodedWritesOneByteBelow251(): void
    {
        self::assertSame("\x00", (new PayloadWriter())->lengthEncoded(0)->payload());
        self::assertSame("\xFA", (new PayloadWriter())->lengthEncoded(250)->payload());
    }

    public function testLengthEncodedWritesTwoBytesAfter0xFC(): void
    {
        self::assertSame("\xFC\xFB\x00", (new PayloadWriter())->lengthEncoded(251)->payload());
        self::assertSame("\xFC\xFF\xFF", (new PayloadWriter())->lengthEncoded(65535)->payload());
    }

    public function testLengthEncodedWritesThreeBytesAfter0xFD(): void
    {
        self::assertSame("\xFD\x00\x00\x01", (new PayloadWriter())->lengthEncoded(65536)->payload());
        self::assertSame("\xFD\xFF\xFF\xFF", (new PayloadWriter())->lengthEncoded(16777215)->payload());
    }

    public function testLengthEncodedWritesEightBytesAfter0xFE(): void
    {
        self::assertSame("\xFE\x00\x00\x00\x01\x00\x00\x00\x00", (new PayloadWriter())->lengthEncoded(16777216)->payload());
        self::assertSame("\xFE\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF", (new PayloadWriter())->lengthEncoded(-1)->payload());
    }

    public function testLengthEncodedStringPrefixesTheLength(): void
    {
        self::assertSame("\x03abc", (new PayloadWriter())->lengthEncodedString('abc')->payload());
        self::assertSame("\x00", (new PayloadWriter())->lengthEncodedString('')->payload());
        self::assertSame("\xFC\x2C\x01" . str_repeat('x', 300), (new PayloadWriter())->lengthEncodedString(str_repeat('x', 300))->payload());
    }

    public function testNulTerminatedAppendsTheTerminator(): void
    {
        self::assertSame("root\x00", (new PayloadWriter())->nulTerminated('root')->payload());
        self::assertSame("\x00", (new PayloadWriter())->nulTerminated('')->payload());
    }

    public function testBytesAppendsTheBytesAsTheyAre(): void
    {
        self::assertSame("#42S02\x00x", (new PayloadWriter())->bytes('#42S02')->bytes("\x00x")->payload());
    }

    public function testPayloadAnswersTheFieldsInOrder(): void
    {
        $writer = new PayloadWriter();

        self::assertSame('', $writer->payload());
        self::assertSame("\x00\x02\x00a\x00\x01b", $writer->integer(0, 1)->integer(2, 2)->nulTerminated('a')->lengthEncodedString('b')->payload());
    }
}
