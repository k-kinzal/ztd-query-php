<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Times;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Times::class)]
#[Small]
final class TimesTest extends TestCase
{
    public function testValueWritesADateFromAString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        self::assertSame(['2024-03-01', '1999-01-02'], [(new Times(new Store($context)))->value('2024-3-1', Domain::string(8, Collation::known('utf8mb4_0900_ai_ci')), $column), (new Times(new Store($context)))->value('99-01-02', Domain::string(8, Collation::known('utf8mb4_0900_ai_ci')), $column)]);
    }

    public function testValueReadsANumberAsADate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        self::assertSame('2024-03-01', (new Times(new Store($context)))->value(20240301, Domain::integer(), $column));
    }

    public function testValueDropsTheTimeOfADatetimeStoredIntoADate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        self::assertSame('2024-03-01', (new Times(new Store($context)))->value('2024-03-01 12:00:00', Domain::string(19, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testValueRoundsFractionalSecondsIntoTheNextDay(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::DateTime, Field::DateTime, 22, 2), Fill::none());

        self::assertSame('2024-03-02 00:00:00.00', (new Times(new Store($context)))->value('2024-03-01 23:59:59.996', Domain::string(23, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testValueStoresTheZeroDateForAnInvalidDate(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none());

        $value = (new Times(new Store($context, 3)))->value('2024-02-30', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $column);

        self::assertSame(['0000-00-00', 'Warning', "Incorrect date value: '2024-02-30' for column 'c' at row 3"], [$value, $context->diagnostics->conditions[0][0], $context->diagnostics->conditions[0][2]]);
    }

    public function testValueWritesATimeFromAString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none());

        self::assertSame('26:03:04', (new Times(new Store($context)))->value('1 02:03:04', Domain::string(10, Collation::known('utf8mb4_0900_ai_ci')), $column));
    }

    public function testRoundRoundsMicrosecondsToTheDecimals(): void
    {
        $times = new Times(new Store(new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0)));

        self::assertSame([123000, 123500, 1000000, 5], [$times->round(123456, 3), $times->round(123456, 4), $times->round(999999, 0), $times->round(5, 6)]);
    }

    public function testTimeTakesTheTimeOfADatetime(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 12, 1), Fill::none());

        self::assertSame('12:34:56.5', (new Times(new Store($context)))->time('2024-03-01 12:34:56.5', new Domain(Kind::DateTime, Field::DateTime, 21, 1), $column));
    }

    public function testTimeReadsHoursMinutesAndSeconds(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 13, 2), Fill::none());
        $from = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(['-12:34:00.00', '12:34:56.70'], [(new Times(new Store($context)))->time('-12:34', $from, $column), (new Times(new Store($context)))->time('123456.7', $from, $column)]);
    }

    public function testTimeClipsHoursAboveTheRange(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none());

        $value = (new Times(new Store($context)))->time('900:00:00', Domain::string(9, Collation::known('utf8mb4_0900_ai_ci')), $column);

        self::assertSame(['838:59:59', 1], [$value, count($context->diagnostics->conditions)]);
    }

    public function testInvalidStoresTheZeroValueOfTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $store = new Store($context);

        self::assertSame(['0000-00-00', '00:00:00.0', '0000-00-00 00:00:00.000'], [(new Times($store))->invalid('x', new ColumnDefinition('c', new Domain(Kind::Date, Field::Date, 10), Fill::none())), (new Times($store))->invalid('x', new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 12, 1), Fill::none())), (new Times($store))->invalid('x', new ColumnDefinition('c', new Domain(Kind::DateTime, Field::DateTime, 23, 3), Fill::none()))]);
    }

    public function testInvalidNamesTheTypeOfTheColumnInTheWarning(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);

        (new Times(new Store($context, 2)))->invalid('nope', new ColumnDefinition('c', new Domain(Kind::DateTime, Field::Timestamp, 19), Fill::none()));

        self::assertSame(['Warning', "Incorrect datetime value: 'nope' for column 'c' at row 2"], [$context->diagnostics->conditions[0][0], $context->diagnostics->conditions[0][2]]);
    }

    public function testInvalidRaisesAnErrorUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect time value: '12:61:00' for column 'c' at row 1");

        (new Times(new Store($context)))->invalid('12:61:00', new ColumnDefinition('c', new Domain(Kind::Time, Field::Time, 10), Fill::none()));
    }

    public function testYearReadsTwoDigitYears(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context));

        self::assertSame([2005, 2069, 1970, 1999], [$times->year(5, Domain::integer(), $column), $times->year(69, Domain::integer(), $column), $times->year(70, Domain::integer(), $column), $times->year(99, Domain::integer(), $column)]);
    }

    public function testYearReadsZeroAsTheZeroYearAndTheStringZeroZeroAs2000(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context));

        self::assertSame([0, 2000, 2024], [$times->year(0, Domain::integer(), $column), $times->year('00', Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')), $column), $times->year('2024', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), $column)]);
    }

    public function testYearStoresZeroForAYearOutsideTheRange(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Year, Field::Year, 4, 0, true), Fill::none());
        $times = new Times(new Store($context, 3));

        self::assertSame([0, 0, [['Warning', 1264, "Out of range value for column 'c' at row 3"], ['Warning', 1264, "Out of range value for column 'c' at row 3"]]], [$times->year(1900, Domain::integer(), $column), $times->year(2156, Domain::integer(), $column), $context->diagnostics->conditions]);
    }

    public function testJsonWritesTheTextOfANumber(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        self::assertSame('5', (new Times(new Store($context)))->json(5, Domain::integer(), $column));
    }

    public function testJsonRefusesATextThatIsNoDocument(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3140);

        (new Times(new Store($context)))->json('{bad', Domain::string(4, Collation::known('utf8mb4_0900_ai_ci')), $column);
    }
}
