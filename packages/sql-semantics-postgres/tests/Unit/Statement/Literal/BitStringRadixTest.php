<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringRadix;

#[CoversClass(BitStringRadix::class)]
#[Small]
final class BitStringRadixTest extends TestCase
{
    public function testCasesAreTheTwoNotations(): void
    {
        self::assertSame(['b', 'x'], array_map(static fn (BitStringRadix $radix): string => $radix->value, BitStringRadix::cases()));
    }
}
