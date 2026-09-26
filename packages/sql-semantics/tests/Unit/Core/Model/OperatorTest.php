<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Model\Operator;

#[CoversClass(Operator::class)]
#[Small]
final class OperatorTest extends TestCase
{
    #[DataProvider('providerSpellings')]
    public function testFromSpellingAcceptsSynonymsAndAnyLetterCase(string $spelling, ?Operator $expected): void
    {
        self::assertSame($expected, Operator::fromSpelling($spelling));
    }

    /**
     * @return iterable<string, array{string, ?Operator}>
     */
    public static function providerSpellings(): iterable
    {
        yield 'plus' => ['+', Operator::Plus];
        yield 'not equal' => ['<>', Operator::NotEqual];
        yield 'not equal synonym' => ['!=', Operator::NotEqual];
        yield 'null safe' => ['<=>', Operator::NullSafeEqual];
        yield 'lower and' => ['and', Operator::And];
        yield 'spaced null test' => ['is   not  null', Operator::IsNotNull];
        yield 'unknown' => ['||', null];
    }

    public function testIsBinaryExcludesNegationAndNullTests(): void
    {
        self::assertSame([Operator::IsNull, Operator::IsNotNull, Operator::Not], array_values(array_filter(Operator::cases(), static fn (Operator $operator): bool => !$operator->isBinary())));
    }

    public function testIsArithmeticCoversNumericOperators(): void
    {
        self::assertSame([Operator::Plus, Operator::Minus, Operator::Multiply], array_values(array_filter(Operator::cases(), static fn (Operator $operator): bool => $operator->isArithmetic())));
    }

    public function testIsComparisonCoversComparisonsIncludingNullSafeForms(): void
    {
        self::assertSame([Operator::Equal, Operator::NotEqual, Operator::Less, Operator::Greater, Operator::LessOrEqual, Operator::GreaterOrEqual, Operator::NullSafeEqual, Operator::Is], array_values(array_filter(Operator::cases(), static fn (Operator $operator): bool => $operator->isComparison())));
    }

    public function testIsLogicalCoversTruthValueOperators(): void
    {
        self::assertSame([Operator::And, Operator::Or, Operator::Not], array_values(array_filter(Operator::cases(), static fn (Operator $operator): bool => $operator->isLogical())));
    }

    public function testIsNullTestCoversBothNullTests(): void
    {
        self::assertSame([Operator::IsNull, Operator::IsNotNull], array_values(array_filter(Operator::cases(), static fn (Operator $operator): bool => $operator->isNullTest())));
    }

    public function testEveryOperatorBelongsToExactlyOneCategory(): void
    {
        $counts = array_map(static fn (Operator $operator): int => (int) $operator->isArithmetic() + (int) $operator->isComparison() + (int) $operator->isLogical() + (int) $operator->isNullTest(), Operator::cases());
        self::assertSame(array_fill(0, count(Operator::cases()), 1), $counts);
    }
}
