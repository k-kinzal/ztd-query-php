<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\BinaryLiteral;

#[CoversClass(BinaryLiteral::class)]
#[Medium]
final class BinaryLiteralTest extends TestCase
{
    public function testValuePreservesItsExactScalar(): void
    {
        $literal = new BinaryLiteral("\0A");
        self::assertSame("\0A", $literal->value());
    }



}
