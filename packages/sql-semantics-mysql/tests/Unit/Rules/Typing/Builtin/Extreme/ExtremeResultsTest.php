<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin\Extreme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Extreme\ExtremeResults;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(ExtremeResults::class)]
#[Small]
final class ExtremeResultsTest extends TestCase
{
    public function testRulesTypeGreatestAndLeast(): void
    {
        $rules = (new ExtremeResults())->rules();
        $call = new Invocation([Domain::integer(Field::Long, 11), Domain::decimal(10, 3)], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));

        self::assertSame(['GREATEST', 'LEAST'], array_keys($rules));
        self::assertEquals([Domain::decimal(13, 3), Domain::decimal(13, 3)], [$rules['GREATEST']($call), $rules['LEAST']($call)]);
    }

    public function testLegacyTellsMySql57FromMySql84(): void
    {
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([true, false], [
            (new ExtremeResults())->legacy(new Invocation([], [], $settings, new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])))),
            (new ExtremeResults())->legacy(new Invocation([], [], $settings, new Derivation((new Semantics(Dialect::MySql))->context([])))),
        ]);
    }

    public function testExtremeTypesGreatestAndLeast(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT GREATEST(1, 2.5), GREATEST(1, 'a'), LEAST(NULL, NULL), GREATEST(DATE'2020-01-01', TIMESTAMP'2020-01-01 10:00:00')");
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $items = array_map(static fn ($item) => $item instanceof SelectExpression ? $operation->facts->scalar($item->expression)->type : null, $statement->items);

        self::assertEquals([
            new Known(Domain::decimal(2, 1)),
            new Known(Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)),
            new Known(Domain::null()),
            new Known(new Domain(Kind::DateTime, Field::DateTime, 19, 0, false, Collation::known('utf8mb4_0900_ai_ci'), [], Coercibility::Numeric)),
        ], $items);
    }

    public function testJsonExtremeMakesALongtext(): void
    {
        $json = new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'));

        self::assertEquals([
            new Domain(Kind::String, Field::LongBlob, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), [], Coercibility::Implicit),
            new Domain(Kind::String, Field::VarString, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), [], Coercibility::Implicit),
            new Domain(Kind::String, Field::VarString, 4294967295, Domain::NOT_FIXED, false, Collation::binary(), [], Coercibility::Implicit),
        ], [(new ExtremeResults())->jsonExtreme([$json, Domain::integer()], true), (new ExtremeResults())->jsonExtreme([$json, $json], true), (new ExtremeResults())->jsonExtreme([$json, Domain::string(3, Collation::binary())], false)]);
    }

    public function testTemporalExtremeSettlesTemporalValues(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $date = new Domain(Kind::Date, Field::Date, 10);
        $time = new Domain(Kind::Time, Field::Time, 13, 2);

        self::assertEquals([
            new Domain(Kind::Date, Field::Date, 10, 0, false, Collation::known('utf8mb4_0900_ai_ci'), [], Coercibility::Numeric),
            new Domain(Kind::DateTime, Field::DateTime, 22, 2, false, Collation::known('utf8mb4_0900_ai_ci'), [], Coercibility::Numeric),
        ], [(new ExtremeResults())->temporalExtreme([$date, $date], $call), (new ExtremeResults())->temporalExtreme([$date, $time], $call)]);
    }

    public function testNumericExtremeSettlesNumbers(): void
    {
        $year = new Domain(Kind::Year, Field::Year, 4, 0, true);

        self::assertEquals([$year, Domain::decimal(13, 3), Domain::integer(Field::Tiny, 4)], [(new ExtremeResults())->numericExtreme([$year, $year], true), (new ExtremeResults())->numericExtreme([Domain::integer(Field::Long, 11), Domain::decimal(10, 3)], true), (new ExtremeResults())->numericExtreme([Domain::integer(Field::Tiny, 4), $year], true)]);
    }

    public function testIntegersWidenMixedSignsAndMakeADecimalWithAnUnsignedBigint(): void
    {
        self::assertEquals([Domain::integer(Field::LongLong, 10), Domain::decimal(20, 0), Domain::integer(Field::LongLong, 20, true)], [
            (new ExtremeResults())->integers([Domain::integer(Field::Tiny, 4), Domain::integer(Field::Long, 10, true)]),
            (new ExtremeResults())->integers([Domain::integer(Field::Tiny, 4), Domain::integer(Field::LongLong, 20, true)]),
            (new ExtremeResults())->integers([new Domain(Kind::Bit, Field::Bit, 4, 0, true), Domain::integer(Field::LongLong, 20, true)]),
        ]);
    }

    public function testRealsSettleOnAFloatOrADouble(): void
    {
        $float = new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED);
        $fixed = new Domain(Kind::Double, Field::Float, 7, 2);

        self::assertEquals([
            new Domain(Kind::Double, Field::Float, 23, Domain::NOT_FIXED, false, null, [], Coercibility::Numeric),
            new Domain(Kind::Double, Field::Double, 23, Domain::NOT_FIXED, false, null, [], Coercibility::Numeric),
            new Domain(Kind::Double, Field::Float, 10, 2, false, null, [], Coercibility::Numeric),
            new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED, false, null, [], Coercibility::Numeric),
        ], [
            (new ExtremeResults())->reals([$float, Domain::integer(Field::Tiny, 4)], true),
            (new ExtremeResults())->reals([$float, Domain::integer(Field::Long, 11)], true),
            (new ExtremeResults())->reals([$fixed, Domain::integer(Field::LongLong, 8)], true),
            (new ExtremeResults())->reals([$float, Domain::integer(Field::Tiny, 4)], false),
        ]);
    }

    public function testTextExtremeWritesEveryArgumentAsText(): void
    {
        $call = new Invocation([], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), new Derivation((new Semantics(Dialect::MySql))->context([])));
        $text = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertEquals([
            new Domain(Kind::String, Field::VarString, 17, Domain::NOT_FIXED, false, Collation::known('utf8mb4_0900_ai_ci'), [], Coercibility::Implicit),
            new Domain(Kind::String, Field::VarString, 20, Domain::NOT_FIXED, false, Collation::binary(), [], Coercibility::Implicit),
        ], [
            (new ExtremeResults())->textExtreme([$text, new Domain(Kind::Time, Field::Time, 10)], 'greatest', true, $call),
            (new ExtremeResults())->textExtreme([$text, Domain::string(3, Collation::binary())], 'greatest', true, $call),
        ]);
    }

    public function testFractionCountsSixForAString(): void
    {
        self::assertSame([6, 3, 0], [(new ExtremeResults())->fraction([Domain::string(1, Collation::binary())]), (new ExtremeResults())->fraction([Domain::decimal(10, 3)]), (new ExtremeResults())->fraction([Domain::integer()])]);
    }

    public function testTextDecimalsAnswersTheFractionWithATemporalValueIn80(): void
    {
        self::assertSame([Domain::NOT_FIXED, 0, 3], [
            (new ExtremeResults())->textDecimals([Domain::integer()]),
            (new ExtremeResults())->textDecimals([new Domain(Kind::Date, Field::Date, 10), Domain::decimal(10, 3)]),
            (new ExtremeResults())->textDecimals([new Domain(Kind::Time, Field::Time, 10), Domain::decimal(10, 3)]),
        ]);
    }

    public function testLegacyExtremeSizesTheComparisonOfMySql57(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $settings = new Settings(Collation::known('latin1_swedish_ci'));
        $literal = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('12');

        self::assertEquals(Domain::integer(Field::LongLong, 3), (new ExtremeResults())->legacyExtreme(new Invocation([Domain::integer(Field::LongLong, 2), Domain::integer(Field::LongLong, 1)], [$literal, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')], $settings, $derivation), 'greatest'));
        self::assertEquals(new Domain(Kind::Decimal, Field::NewDecimal, 19, 2, false, null, [], Coercibility::Numeric), (new ExtremeResults())->legacyExtreme(new Invocation([Domain::null(), Domain::decimal(8, 2)], [], $settings, $derivation), 'greatest'));
        self::assertSame([23, Domain::NOT_FIXED], [(new ExtremeResults())->legacyExtreme(new Invocation([Domain::integer(Field::Long, 11), Domain::string(10, Collation::known('latin1_swedish_ci'))], [], $settings, $derivation), 'greatest')?->length, Domain::NOT_FIXED]);
    }

    public function testComparedSizesTheArgumentsAsDecimals(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]));
        $settings = new Settings(Collation::known('latin1_swedish_ci'));

        self::assertSame([3, 0, true], (new ExtremeResults())->compared(new Invocation([Domain::integer(Field::LongLong, 2), Domain::integer(Field::LongLong, 1)], [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('12')], $settings, $derivation)));
        self::assertSame([19, 2, true], (new ExtremeResults())->compared(new Invocation([Domain::null(), Domain::decimal(8, 2)], [], $settings, $derivation)));
        self::assertSame([3, 0, false], (new ExtremeResults())->compared(new Invocation([Domain::integer(Field::Tiny, 3, true)], [], $settings, $derivation)));
    }

    public function testWrittenCountsTheIntegerDigitsOfANumberLiteral(): void
    {
        self::assertSame([3, 1, null], [(new ExtremeResults())->written(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('123.45')), (new ExtremeResults())->written(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0.5')), (new ExtremeResults())->written(null)]);
    }
}
