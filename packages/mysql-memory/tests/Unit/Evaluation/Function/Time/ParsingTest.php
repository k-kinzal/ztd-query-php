<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Time;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Function\Time\Parsing;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Parsing::class)]
#[Small]
final class ParsingTest extends TestCase
{
    public function testRoutinesNamesStrToDate(): void
    {
        self::assertSame(['STR_TO_DATE'], array_map(static fn ($routine): string => $routine->name, (new Parsing())->routines()));
    }

    public function testReadParsesByTheFormatAndWarnsOfTheRest(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query("SELECT STR_TO_DATE('2024-01-05 extra', '%Y-%m-%d'), STR_TO_DATE('2024105', '%Y%m%d'), STR_TO_DATE('10:30:15 pm', '%r'), STR_TO_DATE('2024/01/05', '%Y-%m-%d'), STR_TO_DATE('2024 10 1', '%x %v %w')")[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['2024-01-05', '2024-10-05', '22:30:15', null, '2024-03-04']], $reply->rows);
        $reply = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['Warning', '1292', "Truncated incorrect date value: '2024-01-05 extra'"], ['Warning', '1411', "Incorrect datetime value: '2024/01/05' for function str_to_date"]], $reply->rows);
    }

    public function testScanStopsWhereTheStringEnds(): void
    {
        self::assertSame(['year' => 2024, 'month' => 1], (new Parsing())->scan('2024-1', '%Y-%m-%d %H'));
        self::assertSame(['failed' => 1], (new Parsing())->scan('2024x', '%YX'));
    }

    public function testFieldReadsAWholeEnglishName(): void
    {
        self::assertSame([['month' => 1], 7], (new Parsing())->field('M', 'january', 0, []));
        self::assertNull((new Parsing())->field('b', 'Janu', 0, []));
    }

    public function testDigitsReadsUpToTheDigitsOfTheSpecifier(): void
    {
        self::assertSame([['day' => 5], 4], (new Parsing())->digits('D', '+5th', 0, []));
        self::assertSame([['micro' => 123400], 4], (new Parsing())->digits('f', '1234x', 0, []));
        self::assertNull((new Parsing())->digits('d', 'x', 0, []));
    }

    public function testWordReadsANameOrTheCharacterOfTheSpecifier(): void
    {
        self::assertSame([['weekday' => 5], 3], (new Parsing())->word('a', 'FRI', 0, []));
        self::assertSame([['twelve' => 1, 'meridian' => 'pm'], 2], (new Parsing())->word('p', 'pm', 0, ['twelve' => 1]));
        self::assertSame([[], 1], (new Parsing())->word('q', 'q', 0, []));
        self::assertNull((new Parsing())->word('p', 'pm', 0, []));
    }

    public function testNumberRefusesValuesOutOfRange(): void
    {
        self::assertSame(['year' => 2024], (new Parsing())->number('Y', '24', []));
        self::assertNull((new Parsing())->number('h', '13', []));
        self::assertNull((new Parsing())->number('j', '0', []));
        self::assertSame(['hour' => 0, 'twelve' => 1], (new Parsing())->number('I', '12', ['hour' => 5]));
    }

    public function testStoredAnswersTheFieldsOfTheSpecifier(): void
    {
        self::assertSame([['week' => 3, 'monday' => 1, 'strict' => 1], ['year' => 1999], ['year' => 24], ['weekYear' => 2024]], [(new Parsing())->stored('v', '3', 3), (new Parsing())->stored('y', '99', 99), (new Parsing())->stored('Y', '024', 24), (new Parsing())->stored('X', '2024', 2024)]);
    }

    public function testCenturyReadsTwoDigitsAsAYearOf1970To2069(): void
    {
        self::assertSame([2069, 1970, 2000], [(new Parsing())->century(69), (new Parsing())->century(70), (new Parsing())->century(0)]);
    }

    public function testWeeklyAnswersTheDayNumberOfTheDayOfTheWeek(): void
    {
        self::assertSame(739251, (new Parsing())->weekly(['week' => 1, 'weekday' => 1, 'monday' => 1, 'strict' => 1, 'weekYear' => 2024], 0));
    }

    public function testResolveFindsTheDayOfAWeek(): void
    {
        self::assertSame([2024, 1, 2, 0, 0, 0, 0], (new Parsing())->resolve(['year' => 2024, 'week' => 0, 'weekday' => 2, 'monday' => 0, 'strict' => 0]));
    }

    public function testAcceptedRefusesAZeroYearUnderNoZeroDate(): void
    {
        $instance = new Instance();
        $context = new Context(new SqlModes(['NO_ZERO_DATE']), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0);

        self::assertFalse((new Parsing())->accepted([0, 1, 1, 0, 0, 0, 0], new Domain(Kind::Date, Field::Date, 10), $context));
        self::assertTrue((new Parsing())->accepted([0, 0, 0, 10, 0, 0, 0], new Domain(Kind::Time, Field::Time, 10), $context));
    }

    public function testWrittenWritesTheTypeOfTheResult(): void
    {
        self::assertSame(['2024-01-05', '58:00:00', '2024-01-05 10:00:00.000000'], [(new Parsing())->written([2024, 1, 5, 10, 0, 0, 0], new Domain(Kind::Date, Field::Date, 10)), (new Parsing())->written([0, 0, 2, 10, 0, 0, 0], new Domain(Kind::Time, Field::Time, 10)), (new Parsing())->written([2024, 1, 5, 10, 0, 0, 0], new Domain(Kind::DateTime, Field::DateTime, 26, 6))]);
    }
}
