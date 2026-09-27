<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\StringLiteral;

#[CoversClass(StringLiteral::class)]
#[Medium]
final class StringLiteralTest extends TestCase
{
    public function testValuePreservesItsExactScalar(): void
    {
        $literal = new StringLiteral('a', 'utf8mb4');
        self::assertSame('a', $literal->value());
        self::assertSame('utf8mb4', $literal->characterSet);
    }



}
