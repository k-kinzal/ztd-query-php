<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Value\NumericString
 */
#[CoversClass(\Deriver\Internal\Value\NumericString::class)]
#[Small]
final class NumericStringTest extends TestCase
{
    public function testParsePreservesTheIntegerMaximumWithoutFloatingPointRounding(): void
    {
        $numbers = new \Deriver\Internal\Value\NumericString();
        self::assertSame(9223372036854775807, $numbers->parse('9223372036854775807'));
        self::assertSame(-9223372036854775807 - 1, $numbers->parse('-9223372036854775808'));
        self::assertSame(9223372036854775808.0, $numbers->parse('9223372036854775808'));
        self::assertSame(1.0, $numbers->parse('1.0'));
        self::assertSame(1000.0, $numbers->parse('1e3'));
        self::assertNull($numbers->parse('1tail'));
    }
    public function testIntegerChecksSyntaxSignAndTargetRange(): void
    {
        $numbers = new \Deriver\Internal\Value\NumericString();
        self::assertSame(1, $numbers->integer(' +0001 '));
        self::assertSame(0, $numbers->integer('-000'));
        self::assertNull($numbers->integer('1.0'));
        self::assertNull($numbers->integer('9223372036854775808'));
        self::assertNull($numbers->integer('-9223372036854775809'));
    }
}
