<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;

#[CoversClass(CollatedText::class)]
#[Small]
final class CollatedTextTest extends TestCase
{
    public function testKeepsTheExactOperandAndBuiltinCollation(): void
    {
        $text = new TextLiteral("é\0 ");
        $expression = new CollatedText($text);

        self::assertSame($text, $expression->operand);
        self::assertSame(TextCollation::Utf8mb4Bin, $expression->collation);
    }
}
