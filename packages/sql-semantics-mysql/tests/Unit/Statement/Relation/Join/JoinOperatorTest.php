<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Join;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;

#[CoversClass(JoinOperator::class)]
#[Small]
final class JoinOperatorTest extends TestCase
{
    public function testKeywordsSpellEveryOperator(): void
    {
        self::assertSame([['JOIN'], ['INNER', 'JOIN'], ['CROSS', 'JOIN'], ['STRAIGHT_JOIN'], ['LEFT', 'JOIN'], ['RIGHT', 'JOIN'], ['NATURAL', 'JOIN'], ['NATURAL', 'LEFT', 'JOIN'], ['NATURAL', 'RIGHT', 'JOIN']], array_map(static fn (JoinOperator $operator): array => $operator->keywords(), JoinOperator::cases()));
    }

    public function testNaturalTellsTheNaturalJoins(): void
    {
        self::assertSame([JoinOperator::Natural, JoinOperator::NaturalLeft, JoinOperator::NaturalRight], array_values(array_filter(JoinOperator::cases(), static fn (JoinOperator $operator): bool => $operator->natural())));
    }

    public function testKeepsLeftTellsTheLeftJoins(): void
    {
        self::assertSame([JoinOperator::Left, JoinOperator::NaturalLeft], array_values(array_filter(JoinOperator::cases(), static fn (JoinOperator $operator): bool => $operator->keepsLeft())));
    }

    public function testKeepsRightTellsTheRightJoins(): void
    {
        self::assertSame([JoinOperator::Right, JoinOperator::NaturalRight], array_values(array_filter(JoinOperator::cases(), static fn (JoinOperator $operator): bool => $operator->keepsRight())));
    }

    public function testConditionedTellsTheJoinsThatRequireACondition(): void
    {
        self::assertSame([JoinOperator::Left, JoinOperator::Right], array_values(array_filter(JoinOperator::cases(), static fn (JoinOperator $operator): bool => $operator->conditioned())));
    }
}
