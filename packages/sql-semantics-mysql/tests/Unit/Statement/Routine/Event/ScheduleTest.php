<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule;

#[CoversClass(Schedule::class)]
#[Small]
final class ScheduleTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function providerDeriveScheduleIsImplementedByTheOneTimeAndTheRecurringSchedule(): iterable
    {
        yield 'AT' => [OnceSchedule::class];
        yield 'EVERY' => [RecurringSchedule::class];
    }

    #[DataProvider('providerDeriveScheduleIsImplementedByTheOneTimeAndTheRecurringSchedule')]
    public function testDeriveScheduleIsImplementedByTheOneTimeAndTheRecurringSchedule(string $class): void
    {
        self::assertTrue(is_subclass_of($class, Schedule::class));
    }
}
