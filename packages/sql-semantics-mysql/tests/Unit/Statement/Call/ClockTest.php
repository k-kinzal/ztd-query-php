<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;

#[CoversClass(Clock::class)]
#[Small]
final class ClockTest extends TestCase
{
    public function testPreciseTellsWhetherAPrecisionIsAccepted(): void
    {
        self::assertTrue(Clock::SystemDate->precise());
        self::assertFalse(Clock::UtcDate->precise());
    }

    public function testResultAnswersTheTypeClass(): void
    {
        self::assertSame(TypeClass::Time, Clock::UtcTime->result());
        self::assertSame(TypeClass::Date, Clock::CurrentDate->result());
    }

    public function testBareAnswersTheKeywordWithoutParentheses(): void
    {
        self::assertSame(['CURRENT_TIMESTAMP', 'CURRENT_TIME', null, 'UTC_TIME', 'UTC_TIMESTAMP', 'CURRENT_DATE', 'UTC_DATE'], array_map(static fn (Clock $clock): ?string => $clock->bare(), Clock::cases()));
    }
}
