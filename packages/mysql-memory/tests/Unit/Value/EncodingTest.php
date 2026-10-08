<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use MySqlMemory\Value\Encoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Encoding::class)]
#[Small]
final class EncodingTest extends TestCase
{
    public function testNameAnswersTheEncodingOfACharacterSet(): void
    {
        self::assertSame(['Windows-1252', 'UTF-8', 'UCS-2BE', null, 'ASCII'], [Encoding::name(Charset::known('latin1')), Encoding::name(Charset::known('utf8mb3')), Encoding::name(Charset::known('ucs2')), Encoding::name(Charset::binary()), Encoding::name(Charset::known('cp1250'))]);
    }

    public function testUtf8TellsWhetherACharacterSetHoldsUtf8(): void
    {
        self::assertSame([true, true, false], [Encoding::utf8(Charset::known('utf8mb4')), Encoding::utf8(Charset::known('utf8mb3')), Encoding::utf8(Charset::known('latin1'))]);
    }

    public function testConvertConvertsTheBytesOfAString(): void
    {
        $utf8 = Charset::known('utf8mb4');

        self::assertSame(
            ["\xE9", "\x80", '?', "\x00\xE9", "\x00?", '?', 'é', "\u{81}", 'é', "\xC3\xA9"],
            [
                Encoding::convert('é', $utf8, Charset::known('latin1')),
                Encoding::convert('€', $utf8, Charset::known('latin1')),
                Encoding::convert('中', $utf8, Charset::known('latin1')),
                Encoding::convert('é', $utf8, Charset::known('ucs2')),
                Encoding::convert('😀', $utf8, Charset::known('ucs2')),
                Encoding::convert('😀', $utf8, Charset::known('utf8mb3')),
                Encoding::convert("\xE9", Charset::known('latin1'), $utf8),
                Encoding::convert("\x81", Charset::known('latin1'), $utf8),
                Encoding::convert('é', Charset::binary(), $utf8),
                Encoding::convert('é', $utf8, Charset::binary()),
            ],
        );
    }

    public function testConvertReadsACharacterSetWithoutAConversionAsAscii(): void
    {
        self::assertSame(['a?', 'a?'], [Encoding::convert("a\xE9", Charset::known('cp1250'), Charset::known('utf8mb4')), Encoding::convert('aé', Charset::known('utf8mb4'), Charset::known('cp1250'))]);
    }

    public function testValidTellsWhetherBytesAreCharactersOfACharacterSet(): void
    {
        self::assertSame([true, false, true, false, false, true], [Encoding::valid('é', Charset::known('utf8mb4')), Encoding::valid("\xFF", Charset::known('utf8mb4')), Encoding::valid("\xFF", Charset::known('latin1')), Encoding::valid('😀', Charset::known('utf8mb3')), Encoding::valid("\xD8\x00", Charset::known('utf16')), Encoding::valid("\xFF", Charset::binary())]);
    }

    public function testPrefixAnswersTheBytesOfTheLongestRunOfWholeCharacters(): void
    {
        self::assertSame([1, 3, 0], [Encoding::prefix("A\xFFB", Charset::known('utf8mb4')), Encoding::prefix('aé', Charset::known('utf8mb4')), Encoding::prefix("\xC3", Charset::known('utf8mb4'))]);
    }

    public function testConvertibleAnswersTheBytesBeforeTheFirstCharacterAnotherSetCannotHold(): void
    {
        self::assertSame([2, 3], [Encoding::convertible('ab中c', Charset::known('utf8mb4'), Charset::known('latin1')), Encoding::convertible('aé', Charset::known('utf8mb4'), Charset::known('latin1'))]);
    }

    public function testCharactersSplitsAStringIntoTheCharactersOfItsSet(): void
    {
        self::assertSame([['a', 'é'], ["\x00a", "\x00\xE9"], ["\xC3", "\xA9"], [], ["\xFF", 'a']], [Encoding::characters('aé', Charset::known('utf8mb4')), Encoding::characters("\x00a\x00\xE9", Charset::known('ucs2')), Encoding::characters('é', Charset::known('latin1')), Encoding::characters('', Charset::known('utf8mb4')), Encoding::characters("\xFFa", Charset::known('utf8mb4'))]);
    }

    public function testLengthCountsTheCharactersOfAString(): void
    {
        self::assertSame([2, 1, 2, 1], [Encoding::length('aé', Charset::known('utf8mb4')), Encoding::length("\x00\xE9", Charset::known('ucs2')), Encoding::length('é', Charset::known('latin1')), Encoding::length("\xD8\x3D\xDE\x00", Charset::known('utf16'))]);
    }

    public function testSliceAnswersCharactersFromAPosition(): void
    {
        self::assertSame(['é', "\x00\xE9", "\xA9"], [Encoding::slice('aéb', 1, 1, Charset::known('utf8mb4')), Encoding::slice("\x00a\x00\xE9", 1, null, Charset::known('ucs2')), Encoding::slice('é', 1, null, Charset::known('latin1'))]);
    }
}
