<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Nesting;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(Nesting::class)]
#[Small]
final class NestingTest extends TestCase
{
    public function testContainsRejectsCallsInTheSameSpecificationScope(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec());
        $specification = new M\Call\Window\WindowSpec(partition: [new M\Query\OrderItem($call)]);
        self::assertTrue((new Nesting())->contains($specification));
        self::assertFalse((new Nesting())->contains(new M\Call\Window\WindowSpec()));
        self::assertInstanceOf(M\Call\Problem\Window\NestingConstraint::class, (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($specification));
    }

    public function testCallsDoesNotCrossAScalarSubqueryBoundary(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec());
        $subquery = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($call)]));
        $window = new M\Call\Window\WindowSpec(order: [new M\Query\OrderItem($subquery)]);
        self::assertFalse((new Nesting())->calls($subquery));
        self::assertSame($window, (new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], $window))->over);
    }

    public function testCallsIncludesDeferredWindowInvocations(): void
    {
        $window = new M\Call\Window\WindowSpec(order: [new M\Query\OrderItem(new M\Literal\NumberLiteral('1'))]);
        $call = new M\Routine\Program\Expression\Call\DeferredWindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], $window);
        self::assertTrue((new Nesting())->calls($call));
        self::assertFalse((new Nesting())->calls(new M\Literal\NumberLiteral('1')));
    }
}
