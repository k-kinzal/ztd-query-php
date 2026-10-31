<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reader::class)]
#[Small]
final class ReaderTest extends TestCase
{
    public function testBytesTracksRemainingInputAndRefusesTruncation(): void
    {
        $reader = new Reader('abc');
        self::assertSame('ab', $reader->bytes(2));
        self::assertSame(1, $reader->remaining());
        $this->expectExceptionCode(1149);
        $reader->bytes(2);
    }

    public function testRemainingTracksConsumedBytes(): void
    {
        $reader = new Reader('abc');
        $reader->bytes(1);
        self::assertSame(2, $reader->remaining());
    }

    public function testIntegerReadsBothByteOrders(): void
    {
        self::assertSame(513, (new Reader("\x01\x02"))->integer(2));
        self::assertSame(258, (new Reader("\x01\x02"))->integer(2, true));
    }

    public function testCountReadsAnExtendedLength(): void
    {
        self::assertSame(513, (new Reader("\xfc\x01\x02"))->count());
        $this->expectExceptionCode(1149);
        (new Reader("\xfb"))->count();
    }

    public function testNameRequiresItsTerminator(): void
    {
        self::assertSame('abc', (new Reader("\x03abc\0"))->name());
        $this->expectExceptionCode(1149);
        (new Reader("\x03abcd"))->name();
    }
}
