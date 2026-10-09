<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Charset::class)]
#[Small]
final class CharsetTest extends TestCase
{
    public function testNamedFindsCaseInsensitivelyAndReadsUtf8AsUtf8mb3(): void
    {
        self::assertSame(Charset::named('utf8mb3'), Charset::named('UTF8'));
        self::assertSame(4, Charset::named('utf8mb4')?->maxLength);
        self::assertNull(Charset::named('klingon'));
    }

    public function testKnownFindsANameTheCatalogHas(): void
    {
        self::assertSame('latin1', Charset::known('latin1')->name);
    }

    public function testBinaryIsTheBinaryCharacterSet(): void
    {
        self::assertSame('binary', Charset::binary()->name);
        self::assertSame(1, Charset::binary()->maxLength);
    }

    public function testDefaultCollationDependsOnTheRelease(): void
    {
        self::assertSame('utf8mb4_general_ci', Charset::known('utf8mb4')->defaultCollation(GrammarRelease::MySql5744)->name);
        self::assertSame('utf8mb4_0900_ai_ci', Charset::known('utf8mb4')->defaultCollation(GrammarRelease::MySql847)->name);
    }

    public function testNameInSpellsUtf8mb3AsUtf8InFiveReleases(): void
    {
        self::assertSame('utf8', Charset::known('utf8mb3')->nameIn(GrammarRelease::MySql5651));
        self::assertSame('utf8mb3', Charset::known('utf8mb3')->nameIn(GrammarRelease::MySql8044));
        self::assertSame('latin1', Charset::known('latin1')->nameIn(GrammarRelease::MySql5744));
    }

    public function testLengthCountsCharacters(): void
    {
        self::assertSame(3, Charset::known('utf8mb4')->length("a\u{e9}\u{1F600}"));
        self::assertSame(2, Charset::known('utf8mb3')->length("\u{e9}\u{e9}"));
        self::assertSame(2, Charset::known('utf16')->length("\0a\0b"));
        self::assertSame(1, Charset::known('utf32')->length("\0\0\0a"));
        self::assertSame(2, Charset::known('latin1')->length("\xe9\xe9"));
    }

    public function testMinLengthIsTheFewestBytesOfACharacter(): void
    {
        self::assertSame([2, 4, 1], [Charset::known('ucs2')->minLength(), Charset::known('utf32')->minLength(), Charset::known('utf8mb4')->minLength()]);
    }
}
