<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

#[CoversClass(IntervalUnit::class)]
#[Small]
final class IntervalUnitTest extends TestCase
{
    public function testCasesSpellEverySimpleAndCompoundUnit(): void
    {
        self::assertSame(
            [
                'MICROSECOND',
                'SECOND',
                'MINUTE',
                'HOUR',
                'DAY',
                'WEEK',
                'MONTH',
                'QUARTER',
                'YEAR',
                'SECOND_MICROSECOND',
                'MINUTE_MICROSECOND',
                'MINUTE_SECOND',
                'HOUR_MICROSECOND',
                'HOUR_SECOND',
                'HOUR_MINUTE',
                'DAY_MICROSECOND',
                'DAY_SECOND',
                'DAY_MINUTE',
                'DAY_HOUR',
                'YEAR_MONTH',
            ],
            array_column(IntervalUnit::cases(), 'value'),
        );
    }
}
