<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
                'SQL_TSI_SECOND',
                'SQL_TSI_MINUTE',
                'SQL_TSI_HOUR',
                'SQL_TSI_DAY',
                'SQL_TSI_WEEK',
                'SQL_TSI_MONTH',
                'SQL_TSI_QUARTER',
                'SQL_TSI_YEAR',
            ],
            array_column(IntervalUnit::cases(), 'value'),
        );
    }
    /**
     * @return iterable<array{IntervalUnit, IntervalUnit}>
     */
    public static function providerAliases(): iterable
    {
        return [
            [IntervalUnit::SqlTsiSecond, IntervalUnit::Second],
            [IntervalUnit::SqlTsiMinute, IntervalUnit::Minute],
            [IntervalUnit::SqlTsiHour, IntervalUnit::Hour],
            [IntervalUnit::SqlTsiDay, IntervalUnit::Day],
            [IntervalUnit::SqlTsiWeek, IntervalUnit::Week],
            [IntervalUnit::SqlTsiMonth, IntervalUnit::Month],
            [IntervalUnit::SqlTsiQuarter, IntervalUnit::Quarter],
            [IntervalUnit::SqlTsiYear, IntervalUnit::Year],
        ];
    }

    #[DataProvider('providerAliases')]
    public function testCanonicalResolvesEveryAlias(IntervalUnit $alias, IntervalUnit $unit): void
    {
        self::assertSame($unit, $alias->canonical());
    }

    public function testCanonicalPreservesUnitsWithoutAliases(): void
    {
        self::assertSame(IntervalUnit::DayMicrosecond, IntervalUnit::DayMicrosecond->canonical());
        self::assertSame(IntervalUnit::Microsecond, IntervalUnit::Microsecond->canonical());
    }
}
