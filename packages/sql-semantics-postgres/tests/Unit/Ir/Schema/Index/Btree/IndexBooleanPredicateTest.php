<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate;

#[CoversClass(IndexBooleanPredicate::class)]
#[Small]
final class IndexBooleanPredicateTest extends TestCase
{
    public function testDisjunctionRetainsOrderedTypedChildren(): void
    {
        $left = new NullIndexPredicate(0, true);
        $right = new IndexBooleanPredicate(LogicalOperator::Not, new NullIndexPredicate(1));
        $either = new IndexBooleanPredicate(LogicalOperator::Or, $left, $right);

        self::assertSame($left, $either->left);
        self::assertSame($right, $either->right);
    }
}
