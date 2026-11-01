<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal\Quoted;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Quoted\Escape;

#[CoversClass(Escape::class)]
#[Small]
final class EscapeTest extends TestCase
{
    public function testBytesDistinguishesSingleByteEscapesFromPatternEscapes(): void
    {
        self::assertSame("\x1a", Escape::ControlZ->bytes());
        self::assertSame('\\%', Escape::Percent->bytes());
        self::assertSame('\\_', Escape::Underscore->bytes());
        self::assertSame("'", Escape::SingleQuote->bytes());
        self::assertNull(Escape::Discarded->bytes());
    }
}
