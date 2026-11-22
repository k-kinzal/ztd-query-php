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

    public function testRejectsIllFormedUtf8(): void
    {
        $this->expectExceptionMessage('TEXT literal requires valid UTF-8');

        new TextLiteral("\xff");
    }

    public function testToStringUsesATextExpressionWithoutAffinity(): void
    {
        self::assertSame("(+CAST(X'610062' AS TEXT))", (new TextLiteral("a\0b"))->toString());
        self::assertSame("'it''s'", (new TextLiteral("it's"))->toString());
    }

    public function testPayloadBytesCountsTheEscapedOrHexadecimalPayload(): void
    {
        self::assertSame(6, (new TextLiteral("a\0b"))->payloadBytes());
        self::assertSame(5, (new TextLiteral("it's"))->payloadBytes());
    }
}
