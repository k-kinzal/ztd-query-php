<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Value\Increment
 */
#[CoversClass(\Deriver\Internal\Value\Increment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\NumericString::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class IncrementTest extends TestCase
{
    public function testApplyPreservesNumericAndStringUpdateSemantics(): void
    {
        $increment = new \Deriver\Internal\Value\Increment();
        self::assertSame('AA0', $increment->apply(\Deriver\Value\Term::constant('Z9'), 1)->literal);
        self::assertSame(101.0, $increment->apply(\Deriver\Value\Term::constant('1e2'), 1)->literal);
        self::assertNull($increment->apply(\Deriver\Value\Term::constant(null), -1)->literal);
        self::assertTrue($increment->apply(\Deriver\Value\Term::constant(true), 1)->literal);
        self::assertSame('TypeError', $increment->apply(\Deriver\Value\Term::array([]), 1)->literal);
    }
    public function testWarningDescribesTheTargetProfileInsteadOfHostDeprecations(): void
    {
        $increment = new \Deriver\Internal\Value\Increment();
        self::assertFalse($increment->warning(\Deriver\Value\Term::constant('A9'), 1));
        self::assertTrue($increment->warning(\Deriver\Value\Term::constant('A9'), -1));
        self::assertTrue($increment->warning(\Deriver\Value\Term::constant(''), 1));
        self::assertTrue($increment->warning(\Deriver\Value\Term::constant(false), 1));
        self::assertFalse($increment->warning(\Deriver\Value\Term::constant(null), 1));
    }
    public function testStringCarriesAcrossLetterAndDigitBoundaries(): void
    {
        $increment = new \Deriver\Internal\Value\Increment();
        self::assertSame('1aa', $increment->string('0zz'));
        self::assertSame('aa0', $increment->string('z9'));
        self::assertSame('a-0', $increment->string('a-9'));
        self::assertSame('foo!', $increment->string('foo!'));
    }
}
