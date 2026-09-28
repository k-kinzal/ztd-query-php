<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RangeException;
use SqlSemantics\Statement\Literal\NumberLiteral;

#[CoversClass(NumberLiteral::class)]
#[Medium]
final class NumberLiteralTest extends TestCase
{
    public function testValueKeepsNumbersBeyondMachinePrecision(): void
    {
        $literal = new NumberLiteral('18446744073709551616.000000000000000001');
        self::assertSame('18446744073709551616.000000000000000001', $literal->value());
    }

    #[TestWith(['00042', 42])]
    #[TestWith(['-0', 0])]
    public function testToIntConvertsOnlyExactlyRepresentableIntegers(string $text, int $expected): void
    {
        self::assertSame($expected, (new NumberLiteral($text))->toInt());
        self::assertSame(PHP_INT_MIN, (new NumberLiteral((string) PHP_INT_MIN))->toInt());
        self::assertSame(PHP_INT_MAX, (new NumberLiteral((string) PHP_INT_MAX))->toInt());
    }

    #[TestWith(['9223372036854775808'])]
    #[TestWith(['-9223372036854775809'])]
    #[TestWith(['1.5'])]
    #[TestWith(['1e2'])]
    public function testToIntRejectsLossyConversions(string $text): void
    {
        $this->expectException(RangeException::class);
        (new NumberLiteral($text))->toInt();
    }

}
