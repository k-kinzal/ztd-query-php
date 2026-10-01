<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;

#[CoversClass(SqliteBinaryOperator::class)]
#[Small]
final class SqliteBinaryOperatorTest extends TestCase
{
    #[TestWith([SqliteBinaryOperator::Is, true])]
    #[TestWith([SqliteBinaryOperator::IsNot, true])]
    #[TestWith([SqliteBinaryOperator::DistinctFrom, true])]
    #[TestWith([SqliteBinaryOperator::NotDistinctFrom, true])]
    #[TestWith([SqliteBinaryOperator::Equal, false])]
    public function testNeverNullIdentifiesNullSafeComparisons(SqliteBinaryOperator $operator, bool $expected): void
    {
        self::assertSame($expected, $operator->neverNull());
    }

    public function testArithmeticKeepsRuntimeNumericBehaviorSeparateFromBitwiseConversion(): void
    {
        self::assertTrue(SqliteBinaryOperator::Add->arithmetic());
        self::assertFalse(SqliteBinaryOperator::BitwiseAnd->arithmetic());
    }

    public function testLogicalIdentifiesBothThreeValuedConnectives(): void
    {
        self::assertTrue(SqliteBinaryOperator::And->logical());
        self::assertTrue(SqliteBinaryOperator::Or->logical());
        self::assertFalse(SqliteBinaryOperator::Is->logical());
    }
}
