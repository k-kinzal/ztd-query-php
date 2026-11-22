<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;

#[CoversClass(LogicalPredicate::class)]
#[Small]
final class LogicalPredicateTest extends TestCase
{
    public function testPreservesOrderedOperandsAndUnaryNegation(): void
    {
        $left = new BooleanLiteral(false);
        $right = new NullLiteral();
        $and = new LogicalPredicate(LogicalOperator::And, $left, $right);
        $not = new LogicalPredicate(LogicalOperator::Not, $and);

        self::assertSame([$left, $right, $and, null], [$and->left, $and->right, $not->left, $not->right]);
    }
}
