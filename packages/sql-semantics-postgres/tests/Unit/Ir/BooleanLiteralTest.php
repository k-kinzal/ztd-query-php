<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;

#[CoversClass(BooleanLiteral::class)]
#[Small]
final class BooleanLiteralTest extends TestCase
{
    public function testBothBooleanAlternativesAreValues(): void
    {
        self::assertTrue((new BooleanLiteral(true))->value);
        self::assertFalse((new BooleanLiteral(false))->value);
    }
}
