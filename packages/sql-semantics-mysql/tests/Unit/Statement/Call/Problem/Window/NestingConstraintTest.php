<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint;

#[CoversClass(NestingConstraint::class)]
#[Small]
final class NestingConstraintTest extends TestCase
{
    public function testMessageRetainsTheOwningExpressionScope(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec());
        $window = new M\Call\Window\WindowSpec(order: [new M\Query\OrderItem($call)]);
        $problem = new NestingConstraint($window);
        self::assertSame($window, $problem->window);
        self::assertSame('A window specification cannot contain a window function in its own expression scope.', $problem->message());
    }
}
