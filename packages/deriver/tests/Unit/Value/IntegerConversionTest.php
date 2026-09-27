<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\IntegerConversion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Value\IntegerConversion
 */
#[CoversClass(IntegerConversion::class)]
#[Small]
final class IntegerConversionTest extends TestCase
{
    public function testApplyWrapsBothSignsAtTheTargetIntegerBoundary(): void
    {
        $conversion = new IntegerConversion();
        self::assertSame(-9223372036854775807 - 1, $conversion->apply(9223372036854775808.0));
        self::assertSame(0, $conversion->apply(18446744073709551616.0));
        self::assertSame(7766279631452241920, $conversion->apply(1e20));
        self::assertSame(-7766279631452241920, $conversion->apply(-1e20));
        self::assertSame(0, $conversion->apply(INF));
        self::assertSame(0, $conversion->apply(NAN));
        self::assertSame(1, $conversion->apply(1.5));
        self::assertSame(0, $conversion->apply(-0.5));
    }
    public function testWarningDistinguishesImplicitLossFromExactConversion(): void
    {
        $conversion = new IntegerConversion();
        self::assertFalse($conversion->warning(1));
        self::assertFalse($conversion->warning(1.0));
        self::assertTrue($conversion->warning(1.5));
        self::assertTrue($conversion->warning(1e30));
        self::assertTrue($conversion->warning(INF));
    }
}
