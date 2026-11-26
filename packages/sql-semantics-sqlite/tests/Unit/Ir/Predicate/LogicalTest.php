<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;

#[CoversClass(Logical::class)]
#[Small]
final class LogicalTest extends TestCase
{
    public function testKeepsTheComparisonAndTheNullPredicate(): void
    {
        $number = new IntegerLiteral('1');
        $comparison = new Comparison(ComparisonOperator::Equal, $number, $number);
        $null = new NullLiteral();
        $operation = new Logical(LogicalOperator::And, $comparison, $null);

        self::assertSame($comparison, $operation->left);
        self::assertSame($null, $operation->right);
        self::assertSame(LogicalOperator::And, $operation->operator);
    }
}
