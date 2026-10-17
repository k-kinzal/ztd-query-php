<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

#[CoversClass(Constraints::class)]
#[Small]
final class ConstraintsTest extends TestCase
{
    public function testPositionalSeparatesAnIntegerShorthandFromANumericExpression(): void
    {
        self::assertTrue((new Constraints())->positional(new WindowSpec(order: [new OrderItem(new NumberLiteral('1'))])));
        self::assertFalse((new Constraints())->positional(new WindowSpec(order: [new OrderItem(new NumberLiteral('1.0'))])));
    }

    public function testReversedRejectsBoundaryKindsWithoutRejectingAnEmptyOffsetRange(): void
    {
        $one = new FrameBound(FrameBoundKind::Following, new NumberLiteral('1'));
        $two = new FrameBound(FrameBoundKind::Following, new NumberLiteral('2'));
        self::assertTrue((new Constraints())->reversed(new WindowSpec(frame: new Frame(FrameUnit::Rows, $one, new FrameBound(FrameBoundKind::CurrentRow)))));
        self::assertFalse((new Constraints())->reversed(new WindowSpec(frame: new Frame(FrameUnit::Rows, $two, $one))));
        self::assertFalse((new Constraints())->reversed(new WindowSpec()));
    }
}
