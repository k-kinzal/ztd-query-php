<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Syntax\CodePoint as Subject;

#[CoversClass(Subject::class)]
final class CodePointTest extends TestCase
{
    #[DataProvider('providerCodePoints')]
    public function testUtf8WritesTheBytesOfACodePoint(int $code, string $expected): void
    {
        self::assertSame($expected, (new Subject())->utf8($code));
    }

    /**
     * @return list<array{int, string}>
     */
    public static function providerCodePoints(): array
    {
        return [
            [0x41, 'A'],
            [0x00, "\0"],
            [0x7F, "\x7f"],
            [0x80, "\u{0080}"],
            [0xE9, 'é'],
            [0x7FF, "\u{07ff}"],
            [0x800, "\u{0800}"],
            [0x732B, '猫'],
            [0xFFFF, "\u{ffff}"],
            [0x10000, "\u{10000}"],
            [0x1F600, '😀'],
        ];
    }
}
