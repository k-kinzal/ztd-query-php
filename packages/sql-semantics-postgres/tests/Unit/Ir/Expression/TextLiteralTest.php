<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;

#[CoversClass(TextLiteral::class)]
#[Small]
final class TextLiteralTest extends TestCase
{
    public function testToStringUsesTheNativeTextTypeAndDoublesApostrophes(): void
    {
        $literal = new TextLiteral("值\\'x");

        self::assertSame("值\\'x", $literal->value);
        self::assertSame("CAST('值\\''x' AS pg_catalog.text)", $literal->toString());
    }

    public function testRejectsAnEmbeddedZeroByte(): void
    {
        $this->expectExceptionMessage('requires valid UTF-8 without a zero byte');

        new TextLiteral("a\0b");
    }

    public function testRejectsMalformedUtf8(): void
    {
        $this->expectExceptionMessage('requires valid UTF-8 without a zero byte');

        new TextLiteral("\xff");
    }
}
