<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal\Quoted;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Escape;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Notation;

#[CoversClass(Notation::class)]
#[Small]
final class NotationTest extends TestCase
{
    public function testSpellPreservesIndependentChoicesForTheSameDecodedByte(): void
    {
        self::assertSame("'\x1a\\Z'", (new Notation(false, [1 => Escape::ControlZ]))->spell("\x1a\x1a"));
        self::assertSame('"a\\"b"', (new Notation(true, [1 => Escape::DoubleQuote]))->spell('a"b'));
        self::assertSame("'\\%\\\\%'", (new Notation(false, [0 => Escape::Percent, 2 => Escape::Backslash]))->spell('\\%\\%'));
        self::assertSame("'\\q'", (new Notation(false, [0 => Escape::Discarded]))->spell('q'));
        self::assertSame("'\\\\'", (new Notation())->spell('\\'));
        self::assertSame("'\\'", (new Notation(backslashEscapes: false))->spell('\\'));
    }

    public function testValidateAcceptsEscapesAtDecodedByteOffsets(): void
    {
        $notation = new Notation(false, [0 => Escape::Percent, 2 => Escape::ControlZ]);
        $value = "\\%\x1a";
        $notation->validate($value);
        self::assertSame("'\\%\\Z'", $notation->spell($value));
    }
}
