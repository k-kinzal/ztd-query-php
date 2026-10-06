<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;

#[CoversClass(IntervalFields::class)]
#[Small]
final class IntervalFieldsTest extends TestCase
{
    public function testSecondsTellsWhichRestrictionsEndInSeconds(): void
    {
        self::assertTrue(IntervalFields::Second->seconds());
        self::assertTrue(IntervalFields::MinuteToSecond->seconds());
        self::assertFalse(IntervalFields::DayToMinute->seconds());
    }
}
