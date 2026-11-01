<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Function\Time\Weeks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Weeks::class)]
#[Small]
final class WeeksTest extends TestCase
{
    public function testWeekNumbersTheWeeksOfEachMode(): void
    {
        $weeks = new Weeks();

        self::assertSame([[0, 2024], [1, 2024], [53, 2023], [1, 2024], [53, 2024], [1, 2025], [613566752, 2024]], [$weeks->week(2024, 1, 1, 0), $weeks->week(2024, 1, 1, 1), $weeks->week(2024, 1, 1, 2), $weeks->week(2024, 1, 1, 3), $weeks->week(2024, 12, 31, 1), $weeks->week(2024, 12, 31, 3), $weeks->week(2024, 0, 0, 0)]);
    }

    public function testLengthCountsLeapYears(): void
    {
        self::assertSame([366, 365, 365, 366], [(new Weeks())->length(2024), (new Weeks())->length(1900), (new Weeks())->length(0), (new Weeks())->length(2000)]);
    }
}
