<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint;

#[CoversClass(FrameConstraint::class)]
#[Small]
final class FrameConstraintTest extends TestCase
{
    public function testMessagePreservesTheWindowAndItsOffsetCondition(): void
    {
        $window = new M\Call\Window\WindowSpec(frame: new M\Call\Window\Frame(M\Call\Window\FrameUnit::Rows, new M\Call\Window\FrameBound(M\Call\Window\FrameBoundKind::Preceding, new M\Literal\NumberLiteral('1.5'))));
        $problem = new FrameConstraint($window, M\Call\Problem\FrameCondition::Offset);
        self::assertSame($window, $problem->window);
        self::assertSame('a ROWS offset literal must be an unsigned 64-bit integer', $problem->message());
    }
}
