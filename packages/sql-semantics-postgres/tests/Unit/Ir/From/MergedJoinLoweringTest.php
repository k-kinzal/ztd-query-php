<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\MergedJoinLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(MergedJoinLowering::class)]
#[Medium]
final class MergedJoinLoweringTest extends TestCase
{
    public function testPredicateUsesOrderedInt4EqualityBetweenExactOccurrences(): void
    {
        $inspection = new Inspection();
        $left = $inspection->inspect('CREATE TABLE app.a (id INTEGER, lane INTEGER)');
        $right = $inspection->inspect('CREATE TABLE app.b (lane INTEGER, id INTEGER)');
        $query = $inspection->inspect('SELECT 1 FROM app.a JOIN app.b USING (lane, id)', [$left, $right]);
        self::assertInstanceOf(Select::class, $query->statement);
        $join = $query->statement->from;
        self::assertInstanceOf(Join::class, $join);
        $bound = $query->binding();
        $a = new SourceBinding($bound, $join->left);
        $b = new SourceBinding($bound, $join->right);

        $predicate = (new MergedJoinLowering())->predicate($join, $a, $b);

        self::assertInstanceOf(LogicalPredicate::class, $predicate);
        self::assertSame(LogicalOperator::And, $predicate->operator);
        self::assertEquals(new IntegerComparison(IntegerOperator::Equal, new ColumnReference(0), new ColumnReference(0, 1)), $predicate->left);
        self::assertEquals(new IntegerComparison(IntegerOperator::Equal, new ColumnReference(1), new ColumnReference(1, 1)), $predicate->right);
        self::assertSame('lane', $a->source()?->columns[0]->value);
        self::assertSame('id', $b->source()?->columns[1]->value);
    }
}
