<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\DateResults;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Seconds;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(DateResults::class)]
#[Small]
final class DateResultsTest extends TestCase
{
    public function testRulesTypeDatesYearsAndParts(): void
    {
        $rules = (new DateResults())->rules();

        self::assertEquals(new Domain(Kind::Date, Field::Date, 10), $rules['DATE'](new Invocation([Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(new Domain(Kind::Year, Field::Year, 4, 0, true), $rules['YEAR'](new Invocation([Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
        self::assertEquals(Domain::integer(Field::LongLong, 3), $rules['MONTH'](new Invocation([Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])))));
    }

    public function testRulesTypeTheFunctionsOfDatesAndTimes(): void
    {
        $rules = (new DateResults())->rules();
        $call = new Invocation([new Domain(Kind::DateTime, Field::DateTime, 23, 3), new Domain(Kind::Time, Field::Time, 17, 6)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 26, 6), $rules['TIMESTAMP']($call));
        self::assertEquals(new Domain(Kind::Time, Field::Time, 14, 3), $rules['TIME']($call));
        self::assertEquals(Domain::integer(Field::LongLong, 9), $rules['DATEDIFF']($call));
        self::assertEquals(Domain::string(17, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), $rules['GET_FORMAT']($call));
    }

    public function testLegacyTellsMySql56And57(): void
    {
        $context = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]);

        self::assertTrue((new DateResults())->legacy(new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation($context))));
    }

    public function testTimeCountsAPointAndTheFraction(): void
    {
        self::assertEquals(new Domain(Kind::Time, Field::Time, 12, 1), (new DateResults())->time(1));
    }

    public function testDateTimeCountsAPointAndTheFraction(): void
    {
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 19), (new DateResults())->dateTime(0));
    }

    public function testAddedKeepsADatetimeOrATimeAndWritesOtherValuesAsStrings(): void
    {
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));
        $call = new Invocation([new Domain(Kind::Date, Field::Date, 10), $string], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $text = new Invocation([$string, $string], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 26, 6), (new DateResults())->added($call, new Seconds()));
        self::assertEquals(Domain::string(29, Collation::known('utf8mb4_0900_ai_ci'), Field::String, Coercibility::Coercible), (new DateResults())->added($text, new Seconds()));
    }

    public function testUnixIsAnIntegerOrADecimalByTheFraction(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertEquals([Domain::integer(Field::LongLong, 21), Domain::decimal(14, 3)], [(new DateResults())->unix($call, 0), (new DateResults())->unix($call, 3)]);
    }

    public function testNameIsAsLongAsTheLongestNameOfTheLocale(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci'), timeNames: Locale::named('de_DE')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(10, (new DateResults())->name($call, $call->settings->locale()->longestDay())->length);
    }

    public function testWrittenReportsNoDecimalsInMySql57(): void
    {
        $context = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]);
        $call = new Invocation([], [], new Settings(Collation::known('latin1_swedish_ci')), new Derivation($context));

        self::assertSame(0, (new DateResults())->written($call, Domain::string(9, Collation::known('latin1_swedish_ci')))->decimals);
    }
}
