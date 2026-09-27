<?php

declare(strict_types=1);

namespace Tests\Unit\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Platform\PostgreSql\Literal\Escapes;

#[CoversClass(Escapes::class)]
#[Medium]
final class EscapesTest extends TestCase
{
    public function testCStyleKeepsByteEscapesSeparateFromUnicode(): void
    {
        $escapes = new Escapes();
        self::assertSame("\xc3\xa9", $escapes->cStyle('\\xC3\\xA9'));
        self::assertSame('é', $escapes->cStyle('\\u00e9'));
        self::assertSame('😀', $escapes->cStyle('\\uD83D\\uDE00'));
    }
    public function testUnicodeSupportsCustomEscapeAndPairs(): void
    {
        self::assertSame('a!😀', (new Escapes())->unicode('!0061!!!D83D!DE00', '!'));
    }
    #[TestWith(['\\u0000'])]
    #[TestWith(['\\uD800'])]
    #[TestWith(['\\uDC00'])]
    #[TestWith(['\\U00110000'])]
    #[TestWith(['\\u12'])]
    public function testCStyleRejectsInvalidUnicode(string $text): void
    {
        $this->expectException(DecodingException::class);
        (new Escapes())->cStyle($text);
    }
    public function testCodepointRequiresExactlyTheDeclaredDigits(): void
    {
        self::assertSame(65, (new Escapes())->codepoint('0041', 4));
        $this->expectException(DecodingException::class);
        (new Escapes())->codepoint('041', 4);
    }
    public function testAssembleRequiresAdjacentSurrogatePairs(): void
    {
        self::assertSame('😀', (new Escapes())->assemble([0xd83d, 0xde00]));
        $this->expectException(DecodingException::class);
        (new Escapes())->assemble([0xd83d, 'x', 0xde00]);
    }
    public function testUtf8EncodesAllWidths(): void
    {
        self::assertSame('Aé€😀', implode('', array_map((new Escapes())->utf8(...), [65, 233, 8364, 128512])));
    }

}
