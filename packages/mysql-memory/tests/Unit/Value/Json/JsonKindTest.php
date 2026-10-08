<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonKind::class)]
#[Small]
final class JsonKindTest extends TestCase
{
    public function testNumericHoldsForIntegersDoublesAndDecimals(): void
    {
        self::assertSame([true, true, true, false, false], [JsonKind::Integer->numeric(), JsonKind::Double->numeric(), JsonKind::Decimal->numeric(), JsonKind::String->numeric(), JsonKind::Boolean->numeric()]);
    }
}
