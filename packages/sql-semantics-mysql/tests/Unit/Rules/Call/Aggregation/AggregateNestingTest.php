<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Aggregation\AggregateNesting;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(AggregateNesting::class)]
#[Medium]
final class AggregateNestingTest extends TestCase
{
    public function testProblemRespectsQueryAndWindowScopes(): void
    {
        $rank = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::Rank, [], new M\Call\Window\WindowSpec());
        $rule = new AggregateNesting();
        self::assertSame('rank', $rule->problem([$rank])?->windowName());
        $count = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Count, []);
        self::assertInstanceOf(M\Call\Problem\Aggregate\AggregateNestingConstraint::class, $rule->problem([$count]));
        self::assertNull($rule->problem([$count], true));
        self::assertNull($rule->problem([new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($rank)]))]));
    }

    public function testColumnsLeavesOwnershipOfColumnDependenciesOpen(): void
    {
        $rule = new AggregateNesting();
        $call = new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a'))]);
        self::assertTrue($rule->columns($call));
        self::assertNull($rule->problem([$call]));
        self::assertFalse($rule->columns(new M\Literal\NumberLiteral('1')));
    }
}
