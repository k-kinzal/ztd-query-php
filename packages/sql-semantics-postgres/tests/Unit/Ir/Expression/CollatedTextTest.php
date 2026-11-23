<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;

#[CoversClass(CollatedText::class)]
#[Small]
final class CollatedTextTest extends TestCase
{
    public function testRetainsACollatedNativeNull(): void
    {
        $operand = new NullLiteral();
        $expression = new CollatedText($operand, TextCollation::C);

        self::assertSame($operand, $expression->operand);
        self::assertSame(TextCollation::C, $expression->collation);
    }
}
