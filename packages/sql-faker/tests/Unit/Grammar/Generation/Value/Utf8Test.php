<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Value\Utf8;

#[CoversClass(Utf8::class)]
final class Utf8Test extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEncodings')]
    public function testValidRecognizesScalarBoundariesAndWidthLimits(string $bytes, int $width, bool $valid): void
    {
        self::assertSame($valid, (new Utf8())->valid($bytes, $width));
    }

    /**
     * @return iterable<array{string, int, bool}>
     */
    public static function providerEncodings(): iterable
    {
        foreach (['', "\0\x7f", 'é', '猫', '😀', "\xf4\x8f\xbf\xbf"] as $bytes) {
            yield [$bytes, 4, true];
        }
        foreach (["\x80", "\xc0\x80", "\xe0\x80\x80", "\xed\xa0\x80", "\xf0\x80\x80\x80", "\xf4\x90\x80\x80", "\xf5\x80\x80\x80", "\xc2", "\xe2\x82", "\xe2a\x80"] as $bytes) {
            yield [$bytes, 4, false];
        }
        yield ['ASCII', 1, true];
        yield ['é', 1, false];
        yield ['猫', 3, true];
        yield ['😀', 3, false];
    }
    public function testWidthClassifiesLeadingByteBoundaries(): void
    {
        $utf8 = new Utf8();
        self::assertSame(1, $utf8->width(127));
        self::assertSame(0, $utf8->width(128));
        self::assertSame(0, $utf8->width(193));
        self::assertSame(2, $utf8->width(194));
        self::assertSame(3, $utf8->width(224));
        self::assertSame(4, $utf8->width(240));
        self::assertSame(4, $utf8->width(244));
        self::assertSame(0, $utf8->width(245));
    }
}
