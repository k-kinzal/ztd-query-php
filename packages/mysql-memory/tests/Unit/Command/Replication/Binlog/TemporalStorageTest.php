<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Reader;
use MySqlMemory\Command\Replication\Binlog\TemporalStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemporalStorage::class)]
#[Small]
final class TemporalStorageTest extends TestCase
{
    public function testDatePreservesZeroComponents(): void
    {
        self::assertSame('0000-00-00', (new TemporalStorage())->date(new Reader("\0\0\0")));
        self::assertSame('2024-02-03', (new TemporalStorage())->date(new Reader(substr(pack('V', (2024 << 9) | (2 << 5) | 3), 0, 3))));
    }

    public function testReadConsumesSignedFractionalTimes(): void
    {
        self::assertSame('-00:00:00.01', (new TemporalStorage())->read(new Reader("\x7f\xff\xff\xff"), 19, "\x02"));
        self::assertSame('00:00:01.120', (new TemporalStorage())->read(new Reader("\x80\0\x01\x04\xb0"), 19, "\x03"));
        self::assertSame('1970-01-01 00:00:01.123456', (new TemporalStorage())->read(new Reader("\0\0\0\x01\x01\xe2\x40"), 17, "\x06"));
    }

    public function testDateTimeDecodesCalendarAndClockFields(): void
    {
        self::assertSame('2024-02-03 04:05:06', (new TemporalStorage())->dateTime((((2024 * 13 + 2) << 5 | 3) << 17) | (4 << 12) | (5 << 6) | 6));
    }

    public function testLegacyReadsDecimalClockDigitsAndTimestampSeconds(): void
    {
        self::assertSame('2024-02-03 04:05:06', (new TemporalStorage())->legacy(new Reader(pack('P', 20240203040506)), 12));
        self::assertSame('-12:34:56', (new TemporalStorage())->legacy(new Reader(substr(pack('V', -123456), 0, 3)), 11));
        self::assertSame('1970-01-01 00:00:01', (new TemporalStorage())->legacy(new Reader(pack('V', 1)), 7));
    }
}
