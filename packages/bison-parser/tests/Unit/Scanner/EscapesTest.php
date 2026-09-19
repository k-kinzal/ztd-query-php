<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Ast\Location;
use BisonParser\Scanner\Escapes;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Escapes::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[Small]
final class EscapesTest extends TestCase
{
    public function testDecode(): void
    {
        $escapes = new Escapes();

        self::assertSame("a\nb\t\\\"'?", $escapes->decode('a\nb\t\\\\\"\'\?', new Location(1, 1)));
        self::assertSame("\x07\x08\f\r\v", $escapes->decode('\a\b\f\r\v', new Location(1, 1)));
        self::assertSame('plain', $escapes->decode('plain', new Location(1, 1)));
    }

    public function testDecodeRejectsAnUnknownEscape(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid character after \-escape: q at 2:4');

        (new Escapes())->decode('a\q', new Location(2, 4));
    }

    public function testDecodeRejectsATrailingBackslash(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid character after \-escape: end of literal at 2:4');

        (new Escapes())->decode('ab\\', new Location(2, 4));
    }

    public function testEscape(): void
    {
        $escapes = new Escapes();

        self::assertSame(["\x01", 1], $escapes->escape('1', new Location(1, 1)));
        self::assertSame(["\x0A", 2], $escapes->escape('12x', new Location(1, 1)));
        self::assertSame(["\xFF", 3], $escapes->escape('3778', new Location(1, 1)));
        self::assertSame(["\x02", 21], $escapes->escape('x00000000000000000002', new Location(1, 1)));
        self::assertSame(["\x41", 3], $escapes->escape('x41g', new Location(1, 1)));
        self::assertSame(["\xE9", 5], $escapes->escape('u00E9', new Location(1, 1)));
        self::assertSame(["\x7E", 9], $escapes->escape('U0000007E', new Location(1, 1)));
        self::assertSame(["\xFF", 5], $escapes->escape('u00FF', new Location(1, 1)));
        self::assertSame(["\n", 1], $escapes->escape('n', new Location(1, 1)));
        self::assertSame(["\n", 1], $escapes->escape('nx41', new Location(1, 1)));
        self::assertSame(["\t", 1], $escapes->escape('tu00E9', new Location(1, 1)));
    }

    public function testEscapeRejectsANumberAboveOneByte(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\x100 at 1:1');

        (new Escapes())->escape('x100', new Location(1, 1));
    }

    public function testByte(): void
    {
        $escapes = new Escapes();

        self::assertSame("\xFF", $escapes->byte(255, '\\377', new Location(1, 1)));
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\777 at 2:3');
        $escapes->byte(511, '\\777', new Location(2, 3));
    }

    public function testEscapeRejectsAWideUniversalCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\u3042 at 1:1');

        (new Escapes())->escape('u3042', new Location(1, 1));
    }

    public function testEncode(): void
    {
        $escapes = new Escapes();

        self::assertSame('a\\nb\\t\\r\\\\\\"\'', $escapes->encode("a\nb\t\r\\\"'", '"'));
        self::assertSame('\\\'"', $escapes->encode("'\"", "'"));
        self::assertSame('\\001\\177 ~', $escapes->encode("\x01\x7F ~", '"'));
        self::assertSame("\xC3\xA9", $escapes->encode("\xC3\xA9", '"'));
    }

    public function testEscapeRejectsANullOctalEscape(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\0 at 3:4');

        (new Escapes())->escape('0', new Location(3, 4));
    }

    public function testEscapeRejectsANullHexadecimalEscape(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\x000 at 3:4');

        (new Escapes())->escape('x000', new Location(3, 4));
    }

    public function testEscapeRejectsANullUniversalCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\u0000 at 3:4');

        (new Escapes())->escape('u0000', new Location(3, 4));
    }

    public function testEscapeRejectsANullWideUniversalCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\U00000000 at 3:4');

        (new Escapes())->escape('U00000000', new Location(3, 4));
    }

    public function testByteRejectsZero(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid number after \\-escape: \\0 at 1:1');

        (new Escapes())->byte(0, '\\0', new Location(1, 1));
    }
}
