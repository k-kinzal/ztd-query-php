<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use MySqlMemory\Protocol\MalformedPacket;
use MySqlMemory\Protocol\PayloadReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayloadReader::class)]
#[Small]
final class PayloadReaderTest extends TestCase
{
    public function testRemainingCountsTheBytesNotRead(): void
    {
        $reader = new PayloadReader('abc');

        self::assertSame(3, $reader->remaining());
        $reader->bytes(1);
        self::assertSame(2, $reader->remaining());
    }

    public function testIntegerReadsLittleEndianBytes(): void
    {
        $reader = new PayloadReader("\x01\x02\x03\xFF\xFF\xFF\xFF\x00\x00\x00\x80");

        self::assertSame(0x030201, $reader->integer(3));
        self::assertSame(0xFFFFFFFF, $reader->integer(4));
        self::assertSame(0x80000000, $reader->integer(4));
        self::assertSame(0, $reader->remaining());
    }

    public function testIntegerRefusesToReadPastTheEnd(): void
    {
        $reader = new PayloadReader("\x01\x02");

        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('The packet ends before the field.');

        $reader->integer(4);
    }

    public function testLengthEncodedReadsEachForm(): void
    {
        $reader = new PayloadReader("\xFA\xFC\xFB\x00\xFD\x00\x00\x01\xFE\x00\x00\x00\x01\x00\x00\x00\x00");

        self::assertSame(250, $reader->lengthEncoded());
        self::assertSame(251, $reader->lengthEncoded());
        self::assertSame(65536, $reader->lengthEncoded());
        self::assertSame(16777216, $reader->lengthEncoded());
    }

    public function testLengthEncodedAnswersNullForTheNullMarker(): void
    {
        $reader = new PayloadReader("\xFB\x05");

        self::assertNull($reader->lengthEncoded());
        self::assertSame(5, $reader->lengthEncoded());
    }

    public function testLengthEncodedRefusesTheErrorMarker(): void
    {
        $reader = new PayloadReader("\xFF\x00\x00");

        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('An integer cannot start with 0xFF.');

        $reader->lengthEncoded();
    }

    public function testLengthEncodedStringReadsTheLengthThenTheBytes(): void
    {
        $reader = new PayloadReader("\x03abcd");

        self::assertSame('abc', $reader->lengthEncodedString());
        self::assertSame('d', $reader->rest());
    }

    public function testLengthEncodedStringReadsTheNullMarkerAsEmpty(): void
    {
        $reader = new PayloadReader("\xFBx");

        self::assertSame('', $reader->lengthEncodedString());
        self::assertSame(1, $reader->remaining());
    }

    public function testNulTerminatedConsumesTheTerminator(): void
    {
        $reader = new PayloadReader("root\x00\x00rest");

        self::assertSame('root', $reader->nulTerminated());
        self::assertSame('', $reader->nulTerminated());
        self::assertSame('rest', $reader->rest());
    }

    public function testNulTerminatedRefusesAStringWithoutTerminator(): void
    {
        $reader = new PayloadReader('root');

        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('A NUL-terminated string has no terminator.');

        $reader->nulTerminated();
    }

    public function testBytesReadsAFixedNumberOfBytes(): void
    {
        $reader = new PayloadReader('abcdef');

        self::assertSame('ab', $reader->bytes(2));
        self::assertSame('', $reader->bytes(0));
        self::assertSame('cdef', $reader->bytes(4));
    }

    public function testBytesRefusesANegativeLength(): void
    {
        $reader = new PayloadReader('abc');

        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('The packet ends before the field.');

        $reader->bytes(-1);
    }

    public function testBytesRefusesToReadPastTheEnd(): void
    {
        $reader = new PayloadReader('abc');

        $this->expectException(MalformedPacket::class);
        $this->expectExceptionMessage('The packet ends before the field.');

        $reader->bytes(4);
    }

    public function testRestReadsEveryByteThatRemains(): void
    {
        $reader = new PayloadReader("\x03SELECT 1");

        self::assertSame(3, $reader->integer(1));
        self::assertSame('SELECT 1', $reader->rest());
        self::assertSame('', $reader->rest());
        self::assertSame("\x03SELECT 1", $reader->payload);
    }
}
