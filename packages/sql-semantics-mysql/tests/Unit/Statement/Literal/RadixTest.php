<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;

#[CoversClass(Radix::class)]
#[Small]
final class RadixTest extends TestCase
{
    public function testCasesNameTheTwoDigitSystems(): void
    {
        self::assertSame(['Hexadecimal', 'Bit'], array_column(Radix::cases(), 'name'));
    }
}
