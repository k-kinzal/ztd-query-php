<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Time\Formatting;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

#[CoversClass(Formatting::class)]
#[Small]
final class FormattingTest extends TestCase
{
    public function testRoutinesNamesTheFormattingFunctions(): void
    {
        self::assertSame(['DATE_FORMAT', 'TIME_FORMAT'], array_map(static fn ($routine): string => $routine->name, (new Formatting())->routines()));
    }

    public function testDateWritesEverySpecifier(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT DATE_FORMAT('2024-01-05 13:04:05.123456', '%a %b %c %D %d %e %f %H %h %I %i %j %k %l %M %m %p %r %S %s %T %U %u %V %v %W %w %X %x %Y %y %% %q'), DATE_FORMAT('2024-01-05', '')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['Fri Jan 1 5th 05 5 123456 13 01 01 04 005 13 1 January 01 PM 01:04:05 PM 05 05 13:04:05 00 01 53 01 Friday 5 2023 2024 2024 24 % q', null]], $reply->rows);
    }

    public function testTimeWritesHoursPast23AndASign(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT TIME_FORMAT('-25:04:05.5', '%H %k %h %f %p %r %T'), TIME_FORMAT('10:00:00', '%a'), TIME_FORMAT('-12:13:14', '%Y %m %d')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['-25 25 01 500000 AM 01:04:05 AM 25:04:05', null, '-0000 00 00']], $reply->rows);
    }

    public function testStandardAnswersTheFormatOfAStandard(): void
    {
        self::assertSame(['%d.%m.%Y', '%Y-%m-%d %H:%i:%s', null, null], [(new Formatting())->standard(TemporalFormat::Date, 'eur'), (new Formatting())->standard(TemporalFormat::Timestamp, 'JIS'), (new Formatting())->standard(TemporalFormat::Time, 'EUR '), (new Formatting())->standard(TemporalFormat::Time, null)]);
    }

    public function testWriteIsNullForANameOfAZeroMonth(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame([null, 'Tue -26 613566752'], [(new Formatting())->write([2024, 0, 5, 0, 0, 0, 0], '%M', false, $frame), (new Formatting())->write([2024, 0, 5, 0, 0, 0, 0], '%a %j %U', false, $frame)]);
    }

    public function testSpecifierWritesOnePart(): void
    {
        self::assertSame(['Februar', '29th', null], [(new Formatting())->specifier('M', [2024, 2, 29, 0, 0, 0, 0], Locale::named('de_DE') ?? Locale::default()), (new Formatting())->specifier('D', [2024, 2, 29, 0, 0, 0, 0], Locale::default()), (new Formatting())->specifier('W', [0, 0, 0, 0, 0, 0, 0], Locale::default())]);
    }

    public function testNameWritesTheNamesOfTheLocale(): void
    {
        self::assertSame(['Fri', 'Jan', '5', null, null], [(new Formatting())->name('a', [2024, 1, 5, 0, 0, 0, 0], Locale::default()), (new Formatting())->name('b', [2024, 1, 5, 0, 0, 0, 0], Locale::default()), (new Formatting())->name('w', [2024, 1, 5, 0, 0, 0, 0], Locale::default()), (new Formatting())->name('w', [0, 0, 5, 0, 0, 0, 0], Locale::default()), (new Formatting())->name('M', [2024, 0, 5, 0, 0, 0, 0], Locale::default())]);
    }

    public function testWeekCountsWeeksAsWeekCountsThem(): void
    {
        self::assertSame(['00', '01', '53', '01', '2023', '2024'], array_map(static fn (string $specifier): string => (new Formatting())->week($specifier, [2024, 1, 5, 0, 0, 0, 0]), ['U', 'u', 'V', 'v', 'X', 'x']));
    }

    public function testClockWritesTheTimeOfDay(): void
    {
        self::assertSame(['123456', '13', '01', '1', 'PM', '01:04:05 PM', '13:04:05'], array_map(static fn (string $specifier): string => (new Formatting())->clock($specifier, [2024, 1, 5, 13, 4, 5, 123456]), ['f', 'H', 'h', 'l', 'p', 'r', 'T']));
    }

    public function testCalendarWritesTheDateInNumbers(): void
    {
        self::assertSame(['1', '5th', '005', '01', '2024', '24', 'q'], array_map(static fn (string $specifier): string => (new Formatting())->calendar($specifier, [2024, 1, 5, 13, 4, 5, 0]), ['c', 'D', 'j', 'm', 'Y', 'y', 'q']));
    }

    public function testSuffixFollowsEnglish(): void
    {
        self::assertSame(['st', 'nd', 'rd', 'th', 'th', 'st'], array_map(static fn (int $day): string => (new Formatting())->suffix($day), [1, 2, 3, 11, 13, 21]));
    }

    public function testYearWritesAYearBeforeZeroAsUnsigned(): void
    {
        self::assertSame(['0024', '4294967295'], [(new Formatting())->year(24), (new Formatting())->year(-1)]);
    }
}
