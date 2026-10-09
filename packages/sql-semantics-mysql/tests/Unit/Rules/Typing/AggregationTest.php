<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Aggregation::class)]
#[Small]
final class AggregationTest extends TestCase
{
    public function testOfSkipsNullBranches(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 2), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->of([Domain::null(), Domain::integer(Field::LongLong, 2)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(Domain::null(), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->of([Domain::null()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(Domain::double(23), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->of([Domain::integer(), Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testIntegersKeepTheWiderSignedType(): void
    {
        self::assertEquals(Domain::integer(Field::Short, 6), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::Tiny, 3, true), Domain::integer(Field::Short, 6)]));
    }

    public function testIntegersWidenWhenSignedMeetsUnsigned(): void
    {
        self::assertEquals(Domain::integer(Field::Long, 11), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::Tiny, 4), Domain::integer(Field::Long, 11)]));
        self::assertEquals(Domain::integer(Field::LongLong, 10), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::Long, 10, true), Domain::integer(Field::Short, 6)]));
        self::assertEquals(Domain::decimal(20, 0), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->integers([Domain::integer(Field::LongLong, 20, true), Domain::integer(Field::Tiny, 4)]));
    }

    public function testDecimalsHoldTheIntegralAndFractionalDigitsOfEach(): void
    {
        self::assertEquals(Domain::decimal(2, 1), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->decimals([Domain::integer(Field::LongLong, 2), Domain::decimal(2, 1)]));
    }

    public function testTemporalsMakeADatetimeOfADateAndADatetime(): void
    {
        $date = new Domain(Kind::Date, Field::Date, 10);
        $datetime = new Domain(Kind::DateTime, Field::DateTime, 23, 3);

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3, false, Collation::known('utf8mb4_0900_ai_ci')), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $datetime], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $datetime], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]))));
    }

    public function testTemporalsMakeADatetimeOfATimeFromMySql80(): void
    {
        $date = new Domain(Kind::Date, Field::Date, 10);
        $time = new Domain(Kind::Time, Field::Time, 12, 1);

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 21, 1, false, Collation::known('utf8mb4_0900_ai_ci')), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $time], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertSame(Kind::String, (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->temporals([$date, $time], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])))?->kind);
    }

    public function testStringsTakeTheLongestTextInTheSettledCollation(): void
    {
        $literal = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals(Domain::string(22, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->strings([$literal, Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertSame(12, (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->strings([$literal, new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([])))?->length);
        self::assertSame(23, (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->strings([$literal, new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED)], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([])))?->length);
    }

    public function testTextsCountTheBytesOfAStringInABinaryResult(): void
    {
        $text = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));
        $bit = new Domain(Kind::Bit, Field::Bit, 5, 0, true);

        self::assertEquals(Domain::string(40, Collation::binary()), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->texts([$text, $bit], 'ifnull', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testTextsCountTheBytesOfATextOutsideASetOperation(): void
    {
        $text = Domain::string(65535, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob);
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertSame([262140, 65535], [$aggregation->texts([$text, Domain::integer()], 'ifnull', $derivation)?->length, $aggregation->texts([$text, Domain::integer()], 'UNION', $derivation)?->length]);
    }

    public function testTextsMakeALongtextOfJsonAmongOtherValues(): void
    {
        $json = new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED);

        self::assertEquals(Domain::string(4294967295, Collation::known('utf8mb4_bin'), Field::LongBlob), (new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci'))))->texts([$json, Domain::integer()], 'coalesce', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testTextLengthCountsEachValueWrittenAsText(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $text = Domain::string(65535, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob);
        $string = Domain::string(10, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([262140, 65535, 40, 22, 12], [
            $aggregation->textLength([$text, Domain::integer()], Collation::known('utf8mb4_0900_ai_ci'), false),
            $aggregation->textLength([$text, Domain::integer()], Collation::known('utf8mb4_0900_ai_ci'), true),
            $aggregation->textLength([$string], Collation::binary(), false),
            $aggregation->textLength([Domain::double(), $string], Collation::known('utf8mb4_0900_ai_ci'), false),
            $aggregation->textLength([new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED), $string], Collation::known('utf8mb4_0900_ai_ci'), false),
        ]);
    }

    public function testOfSettlesAYearAndABitAsIntegers(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $year = new Domain(Kind::Year, Field::Year, 4, 0, true);
        $bit = new Domain(Kind::Bit, Field::Bit, 5, 0, true);

        self::assertEquals([Domain::integer(Field::Tiny, 4), Domain::decimal(10, 0), Domain::integer(Field::LongLong, 5, true)], [$aggregation->of([Domain::integer(Field::Tiny, 4), $year], 'ifnull', $derivation), $aggregation->of([Domain::integer(Field::Long, 11), $bit], 'ifnull', $derivation), $aggregation->of([$year, $bit], 'ifnull', $derivation)]);
    }

    public function testDoublesIsAsWideAsTheWidestValueBeforeMySql81(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $values = [Domain::double(3), Domain::integer(Field::Long, 11)];

        self::assertEquals(Domain::double(11), $aggregation->doubles($values, 'UNION', GrammarRelease::MySql8044));
        self::assertEquals(Domain::double(23), $aggregation->doubles($values, 'UNION', GrammarRelease::MySql847));
        self::assertEquals(Domain::double(23), $aggregation->doubles($values, 'UNION', GrammarRelease::MySql5744));
        self::assertEquals(Domain::double(11), $aggregation->doubles($values, 'if', GrammarRelease::MySql5744));
        self::assertEquals(Domain::double(22), $aggregation->of([Domain::double(3), Domain::double(22)], 'case', new Derivation((new Semantics(Dialect::MySql, 'mysql-8.0.44'))->context([]))));
    }

    public function testDoublesSettlesFloatsWithIntegersOnAFloat(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $float = new Domain(Kind::Double, Field::Float, 23, Domain::NOT_FIXED);

        self::assertSame([Field::Float, Field::Double, Field::Double], [$aggregation->doubles([$float, Domain::integer()], 'UNION', GrammarRelease::MySql847)->field, $aggregation->doubles([$float, Domain::decimal(2, 1)], 'UNION', GrammarRelease::MySql847)->field, $aggregation->doubles([$float, Domain::double()], 'UNION', GrammarRelease::MySql847)->field]);
    }

    public function testDoublesSettlesAFloatWithAnIntOrALeadingBigintOnADouble(): void
    {
        $aggregation = new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')));
        $float = new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED);

        self::assertSame([Field::Double, Field::Double, Field::Float], [$aggregation->doubles([$float, Domain::integer(Field::Long, 11)], 'ifnull', GrammarRelease::MySql847)->field, $aggregation->doubles([Domain::integer(Field::LongLong, 20), $float], 'ifnull', GrammarRelease::MySql847)->field, $aggregation->doubles([Domain::integer(Field::Int24, 9), $float], 'ifnull', GrammarRelease::MySql847)->field]);
    }
}
