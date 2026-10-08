<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Function\Routine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Routine::class)]
#[Small]
final class RoutineTest extends TestCase
{
    public function testAcceptsCountsWithinTheBounds(): void
    {
        $routine = new Routine('ROUND', 1, 2, static fn (): int => 0);

        self::assertSame([false, true, true, false], [$routine->accepts(0), $routine->accepts(1), $routine->accepts(2), $routine->accepts(3)]);
    }

    public function testAcceptsAnyCountAboveTheMinimumWithoutAnUpperBound(): void
    {
        $routine = new Routine('COALESCE', 1, -1, static fn (): int => 0);

        self::assertSame([false, true, true], [$routine->accepts(0), $routine->accepts(1), $routine->accepts(100)]);
    }

    public function testAcceptsIgnoresWhetherSettledArgumentsCountAsKnown(): void
    {
        $routine = new Routine('RAND', 0, 1, static fn (): int => 0, 1, null, true);

        self::assertSame([true, true, true], [$routine->settled, $routine->accepts(0), $routine->accepts(1)]);
    }

    public function testAcceptsTakesTheArgumentsAfterTheFewestInSteps(): void
    {
        $routine = new Routine('JSON_OBJECT', 0, -1, static fn (): int => 0, 2);

        self::assertSame([true, false, true], [$routine->accepts(0), $routine->accepts(3), $routine->accepts(4)]);
    }
}
