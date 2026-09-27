<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\BitLiteral;

#[CoversClass(BitLiteral::class)]
#[Medium]
final class BitLiteralTest extends TestCase
{
    public function testValuePreservesItsExactScalar(): void
    {
        $literal = new BitLiteral('001');
        self::assertSame('001', $literal->value());
    }





}
