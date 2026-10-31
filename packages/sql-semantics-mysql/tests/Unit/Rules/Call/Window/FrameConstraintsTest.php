<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\FrameConstraints;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FrameConstraints::class)]
#[Small]
final class FrameConstraintsTest extends TestCase
{
    public function testConditionKeepsAnUnusedLiteralOffsetOutsideValueValidation(): void
    {
        $window = new M\Call\Window\WindowSpec(frame: new M\Call\Window\Frame(M\Call\Window\FrameUnit::Rows, new M\Call\Window\FrameBound(M\Call\Window\FrameBoundKind::Preceding, new M\Literal\NumberLiteral('1.5'))));
        self::assertSame(M\Call\Problem\FrameCondition::Offset, (new FrameConstraints())->condition($window));
        self::assertNull((new FrameConstraints())->condition($window, false));
    }

    public function testConditionLeavesAnInheritedOrderingOpen(): void
    {
        $frame = new M\Call\Window\Frame(M\Call\Window\FrameUnit::Range, new M\Call\Window\FrameBound(M\Call\Window\FrameBoundKind::Preceding, new M\Literal\NumberLiteral('1')));
        self::assertSame(M\Call\Problem\FrameCondition::RangeOrder, (new FrameConstraints())->condition(new M\Call\Window\WindowSpec(frame: $frame)));
        self::assertNull((new FrameConstraints())->condition(new M\Call\Window\WindowSpec(new Name('base'), frame: $frame)));
    }

    public function testConditionRejectsAnIntervalInRowsEvenForAnUnusedWindow(): void
    {
        $window = new M\Call\Window\WindowSpec(frame: new M\Call\Window\Frame(M\Call\Window\FrameUnit::Rows, new M\Call\Window\FrameBound(M\Call\Window\FrameBoundKind::Preceding, new M\Literal\NumberLiteral('1'), M\Expression\IntervalUnit::Day)));
        self::assertSame(M\Call\Problem\FrameCondition::RowsInterval, (new FrameConstraints())->condition($window, false));
    }
}
