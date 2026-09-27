<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\IntervalFields;

#[CoversClass(IntervalFields::class)]
#[Small]
final class IntervalFieldsTest extends TestCase
{
    public function testListsEveryFieldRestrictionAnIntervalDeclarationAllows(): void
    {
        self::assertSame(['year', 'month', 'day', 'hour', 'minute', 'second', 'year to month', 'day to hour', 'day to minute', 'day to second', 'hour to minute', 'hour to second', 'minute to second'], array_column(IntervalFields::cases(), 'value'));
        self::assertSame(IntervalFields::DayToSecond, IntervalFields::from('day to second'));
    }
}
