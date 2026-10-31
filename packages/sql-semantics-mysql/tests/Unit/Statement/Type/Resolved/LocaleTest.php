<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;

#[CoversClass(Locale::class)]
#[Small]
final class LocaleTest extends TestCase
{
    public function testAllListsTheLocalesOfTheServer(): void
    {
        self::assertCount(111, Locale::all());
        self::assertSame('en_GB', Locale::all()[1]->name);
    }

    public function testNamedFindsALocaleWithoutRegardToCase(): void
    {
        self::assertSame(['de_DE', 'Februar', 'Donnerstag'], [Locale::named('de_de')?->name, Locale::named('de_de')?->months[1], Locale::named('de_de')?->days[3]]);
        self::assertNull(Locale::named('xx'));
    }

    public function testNumberedFindsALocaleByItsNumber(): void
    {
        self::assertSame('fr_FR', Locale::numbered(5)?->name);
        self::assertNull(Locale::numbered(111));
    }

    public function testDefaultIsEnglish(): void
    {
        self::assertSame(['en_US', 'Jan', 'Mon'], [Locale::default()->name, Locale::default()->shortMonths[0], Locale::default()->shortDays[0]]);
    }

    public function testLongestMonthCountsCharacters(): void
    {
        self::assertSame([9, 12], [Locale::default()->longestMonth(), Locale::named('ar_SA')?->longestMonth()]);
    }

    public function testLongestDayCountsCharacters(): void
    {
        self::assertSame([9, 10, 3], [Locale::default()->longestDay(), Locale::named('de_DE')?->longestDay(), Locale::named('ja_JP')?->longestDay()]);
    }
}
