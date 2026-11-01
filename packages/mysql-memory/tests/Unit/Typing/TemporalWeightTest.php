<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\TemporalWeight;
use MySqlMemory\Value\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(TemporalWeight::class)]
#[Small]
final class TemporalWeightTest extends TestCase
{
    public function testBytesKeepSignedFractionalTimeOrderAcrossZero(): void
    {
        $weights = new TemporalWeight();
        $values = ['-01:02:03.123456', '-00:00:00.000001', '00:00:00', '00:00:00.000001', '838:59:59'];
        $bytes = array_map(static fn (string $value): string => $weights->bytes($value, Kind::Time), $values);
        $sorted = $bytes;
        sort($sorted, SORT_STRING);
        self::assertSame($bytes, $sorted);
        self::assertSame(['7fffffef7cfe1dc0', '7fffffffffffffff', '8000000000000000', '8000000000000001', '8000346efb000000'], array_map(bin2hex(...), $bytes));
    }

    public function testBytesUseTheTimestampInstantWhenAStorageZoneIsSupplied(): void
    {
        $weights = new TemporalWeight();
        self::assertSame('99b242108301e240', bin2hex($weights->bytes('2024-01-01 04:02:03.123456', Kind::DateTime, new Zone('+03:00', 10800))));
        self::assertSame('99b242408301e240', bin2hex($weights->bytes('2024-01-01 04:02:03.123456', Kind::DateTime)));
    }

    public function testStoredRetainsTheLegacyFieldWidthAndTimestampInstant(): void
    {
        $weights = new TemporalWeight();
        $zone = new Zone('+03:00', 10800);
        self::assertSame('0fd021', bin2hex($weights->stored('2024-01-01', new Domain(Kind::Date, Field::Date, 10), $zone)));
        self::assertSame('99b242108301e240', bin2hex($weights->stored('2024-01-01 01:02:03.123456', new Domain(Kind::DateTime, Field::DateTime, 26, 6), $zone)));
        self::assertSame('65920f0b01e240', bin2hex($weights->stored('2024-01-01 04:02:03.123456', new Domain(Kind::DateTime, Field::Timestamp, 26, 6), $zone)));
        self::assertSame('7fef7cfe1dc0', bin2hex($weights->stored('-01:02:03.123456', new Domain(Kind::Time, Field::Time, 17, 6), $zone)));
    }
}
