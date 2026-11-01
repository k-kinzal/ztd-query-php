<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Function\Time\Zeros;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Zeros::class)]
#[Small]
final class ZerosTest extends TestCase
{
    public function testCasesNameEachWayOfReadingZeroParts(): void
    {
        self::assertSame(['Modes', 'Dated', 'Refused', 'Months'], array_map(static fn (Zeros $zeros): string => $zeros->name, Zeros::cases()));
    }
}
