<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;

#[CoversClass(BinaryOperator::class)]
#[Small]
final class BinaryOperatorTest extends TestCase
{
    public function testLevelOrdersTheGroupsFromWeakestToTightest(): void
    {
        self::assertSame(Precedence::DISJUNCTION, BinaryOperator::Or->level());
        self::assertSame(Precedence::CONJUNCTION, BinaryOperator::And->level());
        self::assertSame(Precedence::EQUALITY, BinaryOperator::Equal->level());
        self::assertSame(Precedence::COMPARISON, BinaryOperator::Less->level());
        self::assertSame(Precedence::BITWISE, BinaryOperator::BitAnd->level());
        self::assertSame(Precedence::ADDITIVE, BinaryOperator::Add->level());
        self::assertSame(Precedence::MULTIPLICATIVE, BinaryOperator::Multiply->level());
        self::assertSame(Precedence::CONCATENATION, BinaryOperator::Concat->level());
        self::assertLessThan(BinaryOperator::And->level(), BinaryOperator::Or->level());
        self::assertLessThan(BinaryOperator::Concat->level(), BinaryOperator::Multiply->level());
    }

    public function testLevelGivesEveryMemberOfAGroupTheSameLevel(): void
    {
        $levels = array_map(static fn (BinaryOperator $operator): int => $operator->level(), BinaryOperator::cases());

        self::assertSame([1, 2, 4, 4, 4, 4, 4, 4, 4, 4, 5, 5, 5, 5, 7, 7, 7, 7, 8, 8, 9, 9, 9, 10, 10, 10], $levels);
    }

    public function testWordedHoldsForTheKeywordOperatorsOnly(): void
    {
        $worded = array_filter(BinaryOperator::cases(), static fn (BinaryOperator $operator): bool => $operator->worded());

        self::assertSame([BinaryOperator::Or, BinaryOperator::And, BinaryOperator::Is, BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom, BinaryOperator::IsNotDistinctFrom], array_values($worded));
        self::assertFalse(BinaryOperator::Equal->worded());
        self::assertFalse(BinaryOperator::ExtractValue->worded());
    }

    public function testWordedOperatorsSpellTheirKeywordsSeparatedBySpaces(): void
    {
        self::assertSame('IS NOT DISTINCT FROM', BinaryOperator::IsNotDistinctFrom->value);
        self::assertSame('IS NOT', BinaryOperator::IsNot->value);
        self::assertSame('->>', BinaryOperator::ExtractValue->value);
        self::assertSame('||', BinaryOperator::Concat->value);
    }
}
