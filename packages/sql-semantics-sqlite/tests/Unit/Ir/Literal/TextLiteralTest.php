<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;

#[CoversClass(TextLiteral::class)]
#[Small]
final class TextLiteralTest extends TestCase
{
    public function testKeepsQuotesAndUnicode(): void
    {
        self::assertSame("it's 雪", (new TextLiteral("it's 雪"))->value);
    }

    public function testRejectsNul(): void
    {
        $this->expectExceptionMessage('UTF-8 without NUL');

        new TextLiteral("a\0b");
    }
}
