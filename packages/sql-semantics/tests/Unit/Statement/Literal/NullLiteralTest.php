<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\NullLiteral;

#[CoversClass(NullLiteral::class)]
#[Medium]
final class NullLiteralTest extends TestCase
{
    public function testValuePreservesItsExactScalar(): void
    {
        $literal = NullLiteral::Null;
        self::assertSame(null, $literal->value());
    }

}
