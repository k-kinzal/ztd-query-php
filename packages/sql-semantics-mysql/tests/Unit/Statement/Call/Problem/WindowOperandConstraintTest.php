<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint;

#[CoversClass(WindowOperandConstraint::class)]
#[Small]
final class WindowOperandConstraintTest extends TestCase
{
    public function testMessageDescribesTheRetainedOperandWidth(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $window = new M\Routine\Program\Selection\DeferredWindowSpec(order: [new M\Routine\Program\Selection\DeferredOrderItem($row)]);
        $problem = new WindowOperandConstraint($window);
        self::assertSame($window, $problem->window);
        self::assertSame('Operand should contain 1 column(s), not 2.', $problem->message());
    }
}
