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

    public function testQuotedHoldsForTemporalAndOpaqueValues(): void
    {
        self::assertSame([true, true, false, false], [JsonKind::Timestamp->quoted(), JsonKind::Opaque->quoted(), JsonKind::String->quoted(), JsonKind::Decimal->quoted()]);
    }

    public function testRankOrdersTheTypesAsTheServerDoes(): void
    {
        self::assertSame([0, 1, 1, 2, 3, 4, 5, 6, 7, 8, 8, 9], [JsonKind::Null->rank(), JsonKind::Integer->rank(), JsonKind::Double->rank(), JsonKind::String->rank(), JsonKind::Object->rank(), JsonKind::Array->rank(), JsonKind::Boolean->rank(), JsonKind::Date->rank(), JsonKind::Time->rank(), JsonKind::DateTime->rank(), JsonKind::Timestamp->rank(), JsonKind::Opaque->rank()]);
    }

    public function testMarkedReadsTheLetterOfEachType(): void
    {
        self::assertSame([JsonKind::Unsigned, JsonKind::Decimal, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque], array_map(static fn (string $letter): JsonKind => JsonKind::marked($letter), ['u', 'd', 'D', 'T', 'S', 'P', 'O']));
    }

    public function testMarkWritesALetterForTheTypesTheTextDoesNotTell(): void
    {
        self::assertSame(['u', 'd', 'P', 'O', '', '', ''], [JsonKind::Unsigned->mark(), JsonKind::Decimal->mark(), JsonKind::Timestamp->mark(), JsonKind::Opaque->mark(), JsonKind::Integer->mark(), JsonKind::Double->mark(), JsonKind::String->mark()]);
    }
}
