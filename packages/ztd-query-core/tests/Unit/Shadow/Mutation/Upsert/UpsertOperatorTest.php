<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertComparison;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertOperator;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertTruth;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

#[CoversClass(UpsertOperator::class)]
#[UsesClass(UpsertNumber::class)]
#[UsesClass(UpsertComparison::class)]
#[UsesClass(UpsertTruth::class)]
final class UpsertOperatorTest extends TestCase
{
    public function testApplyRefusesAKindThatNamesNoOperator(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Expected an UPSERT expression kind naming an operator');

        (new UpsertOperator())->apply(UpsertExpressionKind::Literal, 1, null);
    }

    public function testApplyDoesTheArithmeticEachOperatorStandsFor(): void
    {
        $operator = new UpsertOperator();

        self::assertSame(
            [2, -2, 5, 1, 6, 3, 1],
            [
                $operator->apply(UpsertExpressionKind::UnaryPlus, 2, null),
                $operator->apply(UpsertExpressionKind::UnaryMinus, 2, null),
                $operator->apply(UpsertExpressionKind::Add, 2, 3),
                $operator->apply(UpsertExpressionKind::Subtract, 3, 2),
                $operator->apply(UpsertExpressionKind::Multiply, 2, 3),
                $operator->apply(UpsertExpressionKind::Divide, 6, 2),
                $operator->apply(UpsertExpressionKind::Modulo, 7, 3),
            ],
        );
    }

    public function testApplyDoesTheComparingEachOperatorStandsFor(): void
    {
        $operator = new UpsertOperator();

        self::assertSame(
            [true, true, true, true, true, true],
            [
                $operator->apply(UpsertExpressionKind::Equal, 1, 1),
                $operator->apply(UpsertExpressionKind::NotEqual, 1, 2),
                $operator->apply(UpsertExpressionKind::Less, 1, 2),
                $operator->apply(UpsertExpressionKind::LessOrEqual, 2, 2),
                $operator->apply(UpsertExpressionKind::Greater, 2, 1),
                $operator->apply(UpsertExpressionKind::GreaterOrEqual, 2, 2),
            ],
        );
    }

    public function testApplyAnswersWhatIsTrueOfEachLogicalOperator(): void
    {
        $operator = new UpsertOperator();

        self::assertSame(
            [false, true, true],
            [
                $operator->apply(UpsertExpressionKind::Not, 1, null),
                $operator->apply(UpsertExpressionKind::And, 1, 1),
                $operator->apply(UpsertExpressionKind::Or, 0, 1),
            ],
        );
    }
}
