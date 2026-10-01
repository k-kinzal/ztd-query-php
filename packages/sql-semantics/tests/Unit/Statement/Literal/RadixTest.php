<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\Radix;
use SqlSemantics\Statement\Literal\UnsignedInteger;

#[CoversClass(Radix::class)]
#[Small]
final class RadixTest extends TestCase
{
    #[TestWith([Radix::Binary, '10', '2'])]
    #[TestWith([Radix::Octal, '10', '8'])]
    #[TestWith([Radix::Decimal, '10', '10'])]
    #[TestWith([Radix::Hexadecimal, '10', '16'])]
    public function testTheBaseDeterminesThePlaceValueOfEachDigit(Radix $radix, string $digits, string $decimal): void
    {
        self::assertSame($decimal, (new UnsignedInteger($digits, $radix))->decimal());
    }
}
