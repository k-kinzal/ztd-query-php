<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ArithmeticOperator;
use SqlSemantics\Platform\Sqlite\Ir\Expression\BinaryOperatorLowering;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;

#[CoversClass(BinaryOperatorLowering::class)]
#[Small]
final class BinaryOperatorLoweringTest extends TestCase
{
    public function testLowerChoosesTheOperationAndResolvesSynonyms(): void
    {
        $operators = new BinaryOperatorLowering();

        self::assertSame(ComparisonOperator::Equal, $operators->lower(BinaryOperator::DoubleEqual));
        self::assertSame(ComparisonOperator::IsNot, $operators->lower(BinaryOperator::IsDistinctFrom));
        self::assertSame(ArithmeticOperator::Multiply, $operators->lower(BinaryOperator::Multiply));
        self::assertSame(LogicalOperator::Or, $operators->lower(BinaryOperator::Or));
    }

    public function testLowerRejectsAnUnregisteredOperator(): void
    {
        $this->expectExceptionMessage('no complete SQLite scalar representation');

        (new BinaryOperatorLowering())->lower(BinaryOperator::Extract);
    }
}
