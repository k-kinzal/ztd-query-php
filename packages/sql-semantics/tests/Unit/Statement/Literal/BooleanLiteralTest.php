<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\BooleanLiteral;

#[CoversClass(BooleanLiteral::class)]
#[Medium]
final class BooleanLiteralTest extends TestCase
{
    public function testValuePreservesItsExactScalar(): void
    {
        $literal = new BooleanLiteral(false);
        self::assertSame(false, $literal->value());
    }



}
