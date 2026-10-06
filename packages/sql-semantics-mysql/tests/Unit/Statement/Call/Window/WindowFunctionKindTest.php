<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(WindowFunctionKind::class)]
#[Small]
final class WindowFunctionKindTest extends TestCase
{
    public function testArityAnswersTheArgumentCounts(): void
    {
        self::assertSame([0, 0], WindowFunctionKind::PercentRank->arity());
        self::assertSame([2, 2], WindowFunctionKind::NthValue->arity());
    }

    public function testTreatsNullsTellsTheValueFunctions(): void
    {
        self::assertTrue(WindowFunctionKind::LastValue->treatsNulls());
        self::assertFalse(WindowFunctionKind::Tile->treatsNulls());
    }

    public function testCountedAnswersThePositionOfTheStableInteger(): void
    {
        self::assertSame(0, WindowFunctionKind::Tile->counted());
        self::assertSame(1, WindowFunctionKind::Lag->counted());
        self::assertNull(WindowFunctionKind::Rank->counted());
    }
}
