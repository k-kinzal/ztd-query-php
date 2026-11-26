<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;

#[CoversClass(HexIntegerLiteral::class)]
#[Small]
final class HexIntegerLiteralTest extends TestCase
{
    public function testAllowsTheSignedMinimumWithoutNegation(): void
    {
        $value = new HexIntegerLiteral('8000000000000000');

        self::assertSame('8000000000000000', $value->digits);
        self::assertFalse($value->negated);
    }

    public function testRejectsNegationOfTheMinimum(): void
    {
        $this->expectExceptionMessage('cannot be negated');

        new HexIntegerLiteral('8000000000000000', true);
    }

    public function testToStringKeepsTheSignAndDigits(): void
    {
        self::assertSame('-0xFF', (new HexIntegerLiteral('FF', true))->toString());
    }
}
