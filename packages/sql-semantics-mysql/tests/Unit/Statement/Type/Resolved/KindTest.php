<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Kind::class)]
#[Small]
final class KindTest extends TestCase
{
    public function testNumericCoversNumbersYearsAndBits(): void
    {
        self::assertSame([Kind::Integer, Kind::Decimal, Kind::Double, Kind::Year, Kind::Bit], array_values(array_filter(Kind::cases(), static fn (Kind $kind): bool => $kind->numeric())));
    }

    public function testTemporalCoversDatesTimesAndDatetimes(): void
    {
        self::assertSame([Kind::Date, Kind::Time, Kind::DateTime], array_values(array_filter(Kind::cases(), static fn (Kind $kind): bool => $kind->temporal())));
    }
}
