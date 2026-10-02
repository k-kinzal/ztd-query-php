<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;

#[CoversClass(Identifiers::class)]
#[Small]
final class IdentifiersTest extends TestCase
{
    public function testDecodeFoldsAnUnquotedIdentifierToLowerCase(): void
    {
        self::assertSame('foo_bar$1', (new Identifiers())->decode('Foo_BAR$1'));
    }

    public function testDecodeKeepsNonAsciiLettersOfAnUnquotedIdentifier(): void
    {
        self::assertSame('École', (new Identifiers())->decode('ÉCOLE'));
    }

    public function testDecodeReadsAQuotedIdentifierLiterally(): void
    {
        self::assertSame('Foo "Bar"', (new Identifiers())->decode('"Foo ""Bar"""'));
    }

    public function testDecodeReplacesTheEscapesOfAUnicodeIdentifier(): void
    {
        self::assertSame('data', (new Identifiers())->decode('U&"d\\0061t\\+000061"'));
    }

    public function testDecodeHonoursTheEscapeCharacterOfAUescapeClause(): void
    {
        self::assertSame('da"ta', (new Identifiers())->decode('u&"d!0061""t!+000061" UESCAPE \'!\''));
    }

    public function testDecodeCutsANameToSixtyThreeBytes(): void
    {
        self::assertSame(str_repeat('a', 63), (new Identifiers())->decode(str_repeat('A', 70)));
    }

    public function testFoldLowersAsciiLettersOnly(): void
    {
        self::assertSame('select É', (new Identifiers())->fold('SELECT É'));
    }

    public function testClipDoesNotSplitACharacter(): void
    {
        self::assertSame(str_repeat('a', 62), (new Identifiers())->clip(str_repeat('a', 62) . 'é'));
        self::assertSame(str_repeat('a', 61) . 'é', (new Identifiers())->clip(str_repeat('a', 61) . 'éé'));
    }
}
