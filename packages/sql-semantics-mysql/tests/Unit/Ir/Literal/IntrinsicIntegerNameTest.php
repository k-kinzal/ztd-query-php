<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName;

#[CoversClass(IntrinsicIntegerName::class)]
#[Small]
final class IntrinsicIntegerNameTest extends TestCase
{
    public function testKeyRetainsTheExactIntrinsicHeading(): void
    {
        $digits = str_repeat('0', 256) . '1';

        self::assertSame($digits, (new IntrinsicIntegerName($digits))->key());
    }
}
