<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\Increment;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Value\Increment
 */
#[CoversClass(Increment::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\NumericString::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class IncrementTest extends TestCase
{
    public function testApplyPreservesNumericAndStringUpdateSemantics(): void
    {
        $increment = new Increment();
        self::assertSame('AA0', $increment->apply(Term::constant('Z9'), 1)->literal);
        self::assertSame(101.0, $increment->apply(Term::constant('1e2'), 1)->literal);
        self::assertNull($increment->apply(Term::constant(null), -1)->literal);
        self::assertTrue($increment->apply(Term::constant(true), 1)->literal);
        self::assertSame('TypeError', $increment->apply(Term::array([]), 1)->literal);
    }
    public function testWarningDescribesTheTargetProfileInsteadOfHostDeprecations(): void
    {
        $increment = new Increment();
        self::assertFalse($increment->warning(Term::constant('A9'), 1));
        self::assertTrue($increment->warning(Term::constant('A9'), -1));
        self::assertTrue($increment->warning(Term::constant(''), 1));
        self::assertTrue($increment->warning(Term::constant(false), 1));
        self::assertFalse($increment->warning(Term::constant(null), 1));
    }
    public function testStringCarriesAcrossLetterAndDigitBoundaries(): void
    {
        $increment = new Increment();
        self::assertSame('1aa', $increment->string('0zz'));
        self::assertSame('aa0', $increment->string('z9'));
        self::assertSame('a-0', $increment->string('a-9'));
        self::assertSame('foo!', $increment->string('foo!'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerScalarUpdates')]
    public function testApplyPreservesScalarTypesAndConfidentiality(int|float|string|bool|null $input, int $delta, int|float|string|bool|null $expected, bool $warning): void
    {
        $result = (new Increment())->apply(Term::constant($input, true), $delta);
        self::assertSame('constant', $result->kind);
        self::assertSame($expected, $result->literal);
        self::assertTrue($result->isSecret());
        self::assertSame($warning, (new Increment())->warning(Term::constant($input), $delta));
    }

    /**
     * @return iterable<string,array{int|float|string|bool|null,int,int|float|string|bool|null,bool}>
     */
    public static function providerScalarUpdates(): iterable
    {
        yield 'null increment' => [null, 1, 1, false];
        yield 'null decrement' => [null, -1, null, true];
        yield 'false increment' => [false, 1, false, true];
        yield 'true decrement' => [true, -1, true, true];
        yield 'integer increment' => [7, 1, 8, false];
        yield 'integer decrement' => [7, -1, 6, false];
        yield 'float increment' => [1.5, 1, 2.5, false];
        yield 'float decrement' => [1.5, -1, 0.5, false];
        yield 'numeric integer string' => ['19', 1, 20, false];
        yield 'numeric exponent string' => ['1e2', -1, 99.0, false];
        yield 'numeric whitespace string' => [' 9 ', 1, 10, false];
        yield 'numeric negative string' => ['-1', 1, 0, false];
        yield 'empty increment' => ['', 1, '1', true];
        yield 'empty decrement' => ['', -1, -1, true];
        yield 'lowercase' => ['abc', 1, 'abd', false];
        yield 'lowercase decrement unchanged' => ['abc', -1, 'abc', true];
        yield 'lowercase carry' => ['z', 1, 'aa', false];
        yield 'uppercase carry' => ['Z', 1, 'AA', false];
        yield 'mixed carry' => ['Z9', 1, 'AA0', false];
        yield 'punctuation stops carry' => ['a-9', 1, 'a-0', true];
        yield 'punctuation unchanged' => ['foo!', 1, 'foo!', true];
        yield 'integer upper overflow' => [9223372036854775807, 1, 9223372036854775808.0, false];
        yield 'integer lower overflow' => [-9223372036854775807 - 1, -1, -9223372036854775808.0, false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSymbolicUpdates')]
    public function testApplyRetainsSymbolicInputAndUpdateDirection(string $type, int $delta, string $kind): void
    {
        $input = new Term('parameter', 'value', attributes:['type' => $type], secret:true);
        $result = (new Increment())->apply($input, $delta);
        self::assertSame($kind, $result->kind);
        self::assertSame($kind === 'binary' ? '+' : $delta, $result->literal);
        self::assertSame($input, $result->operands[0]);
        self::assertSame($kind === 'binary' ? $delta : null, ($result->operands[1] ?? null)?->literal);
        self::assertTrue($result->isSecret());
        self::assertFalse((new Increment())->warning($input, $delta));
    }

    /**
     * @return iterable<string,array{string,int,string}>
     */
    public static function providerSymbolicUpdates(): iterable
    {
        foreach (['int', 'float', 'int|float'] as $type) {
            yield $type . ' increment' => [$type, 1, 'binary'];
            yield $type . ' decrement' => [$type, -1, 'binary'];
        }
        yield 'string' => ['string', 1, 'increment'];
        yield 'unknown' => ['mixed', -1, 'increment'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnsupportedKinds')]
    public function testApplyRejectsEveryConcreteNonScalarCategory(string $kind): void
    {
        $value = new Term($kind, 'identity');
        $result = (new Increment())->apply($value, 1);
        self::assertSame('throwable', $result->kind);
        self::assertSame('TypeError', $result->literal);
        self::assertFalse((new Increment())->warning($value, 1));
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerUnsupportedKinds(): iterable
    {
        yield 'array' => ['array'];
        yield 'object' => ['object'];
        yield 'closure' => ['closure'];
        yield 'enum' => ['enum'];
    }

    public function testApplyPreservesConfidentialAbsenceWhenIncrementInitializesStorage(): void
    {
        $result = (new Increment())->apply(new Term('uninitialized', secret:true), 1);
        self::assertSame(1, $result->literal);
        self::assertTrue($result->isSecret());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerAsciiCarry')]
    public function testStringUsesAsciiCarryAtEveryCharacterBoundary(string $input, string $expected): void
    {
        self::assertSame($expected, (new Increment())->string($input));
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerAsciiCarry(): iterable
    {
        yield 'empty' => ['', '1'];
        yield 'digit lower' => ['0', '1'];
        yield 'digit upper ordinary' => ['8', '9'];
        yield 'digit carry' => ['9', '10'];
        yield 'before digit' => ['/', '/'];
        yield 'after digit' => [':', ':'];
        yield 'before uppercase' => ['@', '@'];
        yield 'uppercase lower' => ['A', 'B'];
        yield 'uppercase upper ordinary' => ['Y', 'Z'];
        yield 'uppercase carry' => ['Z', 'AA'];
        yield 'after uppercase' => ['[', '['];
        yield 'before lowercase' => ['`', '`'];
        yield 'lowercase lower' => ['a', 'b'];
        yield 'lowercase upper ordinary' => ['y', 'z'];
        yield 'lowercase carry' => ['z', 'aa'];
        yield 'after lowercase' => ['{', '{'];
        yield 'null byte suffix' => ["a\0", "a\0"];
        yield 'nonascii suffix' => ["a\xff", "a\xff"];
        yield 'cross letter classes' => ['Zz', 'AAa'];
    }
}
