<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Argument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;

#[CoversClass(NativePragmaInteger::class)]
#[Small]
final class NativePragmaIntegerTest extends TestCase
{
    public function testParseAppliesTheNativePrefixAndSignedLimits(): void
    {
        $integer = new NativePragmaInteger();

        self::assertSame(-2147483648, $integer->parse('-0002147483648tail'));
        self::assertSame(127, $integer->parse('0x0007fTAIL'));
        self::assertSame(2, $integer->parse('+2.8'));
        self::assertSame(0, $integer->parse('-0x7f'));
        self::assertNull($integer->parse('2147483648'));
        self::assertNull($integer->parse(' 42'));
    }
}
