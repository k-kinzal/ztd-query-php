<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

#[CoversClass(WindowConstraint::class)]
#[Small]
final class WindowConstraintTest extends TestCase
{
    public function testMessageDescribesTheFixedConstraintOfTheRetainedSpecification(): void
    {
        $window = new WindowSpec(order: [new OrderItem(new NumberLiteral('1'))]);
        $problem = new WindowConstraint($window, true);
        self::assertSame($window, $problem->window);
        self::assertSame('ORDER BY or PARTITION BY uses legacy position indication which is not supported, use expression.', $problem->message());
    }
}
