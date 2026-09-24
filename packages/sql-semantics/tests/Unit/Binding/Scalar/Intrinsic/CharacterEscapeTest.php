<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Scalar\Intrinsic\CharacterEscape;

#[CoversClass(CharacterEscape::class)]
final class CharacterEscapeTest extends TestCase
{
    #[TestWith(['y\\x65ar', 'year'])]
    #[TestWith(['y\\145ar', 'year'])]
    #[TestWith(['y\\u0065ar', 'year'])]
    #[TestWith(['y\\U00000065ar', 'year'])]
    #[TestWith(['y\\ear', 'year'])]
    #[TestWith(['y\\nar', "y\nar"])]
    public function testPostgresDecodesCharacterEscapes(string $input, string $expected): void
    {
        self::assertSame($expected, CharacterEscape::postgres($input));
    }

    #[TestWith([101, 'e'])]
    #[TestWith([233, 'é'])]
    #[TestWith([24180, '年'])]
    #[TestWith([128197, '📅'])]
    public function testUnicodeEncodesCodePoints(int $point, string $expected): void
    {
        self::assertSame($expected, CharacterEscape::unicode($point));
    }

    #[TestWith(['\\b\\f\\r\\t', "\x08\x0c\r\t"])]
    #[TestWith(['\\101\\7', "A\x07"])]
    #[TestWith(['\\777', "\xff"])]
    #[TestWith(['\\8\\9', '89'])]
    #[TestWith(['\\x\\x4', "x\x04"])]
    #[TestWith(['\\u4e2d\\U0001F600', "\u{4e2d}\u{1F600}"])]
    public function testPostgresDecodesEveryEscapeForm(string $input, string $expected): void
    {
        self::assertSame($expected, CharacterEscape::postgres($input));
    }

    #[TestWith([0x7f, "\u{7f}"])]
    #[TestWith([0x80, "\u{80}"])]
    #[TestWith([0x7ff, "\u{7ff}"])]
    #[TestWith([0x800, "\u{800}"])]
    #[TestWith([0xffff, "\u{ffff}"])]
    #[TestWith([0x10000, "\u{10000}"])]
    #[TestWith([0x10ffff, "\u{10ffff}"])]
    #[TestWith([0x10fabc, "\u{10fabc}"])]
    public function testUnicodeEncodesTheBoundariesOfEachWidth(int $point, string $expected): void
    {
        self::assertSame($expected, CharacterEscape::unicode($point));
    }
}
