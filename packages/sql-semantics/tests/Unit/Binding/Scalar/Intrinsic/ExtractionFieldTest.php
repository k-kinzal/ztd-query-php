<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Scalar\Intrinsic\ExtractionField;
use SqlSemantics\Model\Scalar\Temporal\PostgreSqlField;

#[CoversClass(ExtractionField::class)]
final class ExtractionFieldTest extends TestCase
{
    #[TestWith(['centuries', PostgreSqlField::Century])]
    #[TestWith(['y', PostgreSqlField::Year])]
    #[TestWith(['microsecond', PostgreSqlField::Microseconds])]
    #[TestWith(['msec', PostgreSqlField::Milliseconds])]
    #[TestWith(['j', PostgreSqlField::Julian])]
    #[TestWith(['timezone_h', PostgreSqlField::TimezoneHour])]
    #[TestWith(['timezone_m', PostgreSqlField::TimezoneMinute])]
    #[TestWith(['mins', PostgreSqlField::Minute])]
    #[TestWith(['isoyear', PostgreSqlField::Isoyear])]
    #[TestWith(['unknown', null])]
    public function testPostgresResolvesUnitAliases(string $name, ?PostgreSqlField $expected): void
    {
        self::assertSame($expected, ExtractionField::postgres($name));
    }

    /**
     * @return iterable<string, array{string, PostgreSqlField}>
     */
    public static function providerPostgresResolvesEveryAlias(): iterable
    {
        return [
            'c' => ['c', PostgreSqlField::Century],
            'cent' => ['cent', PostgreSqlField::Century],
            'centuries' => ['centuries', PostgreSqlField::Century],
            'd' => ['d', PostgreSqlField::Day],
            'days' => ['days', PostgreSqlField::Day],
            'dec' => ['dec', PostgreSqlField::Decade],
            'decades' => ['decades', PostgreSqlField::Decade],
            'decs' => ['decs', PostgreSqlField::Decade],
            'h' => ['h', PostgreSqlField::Hour],
            'hours' => ['hours', PostgreSqlField::Hour],
            'hr' => ['hr', PostgreSqlField::Hour],
            'hrs' => ['hrs', PostgreSqlField::Hour],
            'j' => ['j', PostgreSqlField::Julian],
            'jd' => ['jd', PostgreSqlField::Julian],
            'm' => ['m', PostgreSqlField::Minute],
            'min' => ['min', PostgreSqlField::Minute],
            'mins' => ['mins', PostgreSqlField::Minute],
            'minutes' => ['minutes', PostgreSqlField::Minute],
            'microsecond' => ['microsecond', PostgreSqlField::Microseconds],
            'us' => ['us', PostgreSqlField::Microseconds],
            'usec' => ['usec', PostgreSqlField::Microseconds],
            'usecs' => ['usecs', PostgreSqlField::Microseconds],
            'usecond' => ['usecond', PostgreSqlField::Microseconds],
            'useconds' => ['useconds', PostgreSqlField::Microseconds],
            'mil' => ['mil', PostgreSqlField::Millennium],
            'millennia' => ['millennia', PostgreSqlField::Millennium],
            'mils' => ['mils', PostgreSqlField::Millennium],
            'millisecond' => ['millisecond', PostgreSqlField::Milliseconds],
            'ms' => ['ms', PostgreSqlField::Milliseconds],
            'msec' => ['msec', PostgreSqlField::Milliseconds],
            'msecs' => ['msecs', PostgreSqlField::Milliseconds],
            'msecond' => ['msecond', PostgreSqlField::Milliseconds],
            'mseconds' => ['mseconds', PostgreSqlField::Milliseconds],
            'mon' => ['mon', PostgreSqlField::Month],
            'mons' => ['mons', PostgreSqlField::Month],
            'months' => ['months', PostgreSqlField::Month],
            'qtr' => ['qtr', PostgreSqlField::Quarter],
            's' => ['s', PostgreSqlField::Second],
            'sec' => ['sec', PostgreSqlField::Second],
            'secs' => ['secs', PostgreSqlField::Second],
            'seconds' => ['seconds', PostgreSqlField::Second],
            'timezone_hr' => ['timezone_hr', PostgreSqlField::TimezoneHour],
            'timezone_min' => ['timezone_min', PostgreSqlField::TimezoneMinute],
            'w' => ['w', PostgreSqlField::Week],
            'weeks' => ['weeks', PostgreSqlField::Week],
            'y' => ['y', PostgreSqlField::Year],
            'yr' => ['yr', PostgreSqlField::Year],
            'yrs' => ['yrs', PostgreSqlField::Year],
            'years' => ['years', PostgreSqlField::Year],
        ];
    }

    #[DataProvider('providerPostgresResolvesEveryAlias')]
    public function testPostgresResolvesEveryAlias(string $name, PostgreSqlField $expected): void
    {
        self::assertSame($expected, ExtractionField::postgres($name));
    }
}
