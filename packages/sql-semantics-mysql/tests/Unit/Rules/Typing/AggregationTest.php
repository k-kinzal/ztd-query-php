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
        self::assertEquals(Domain::integer(Field::LongLong, 2), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->of([Domain::null(), Domain::integer(Field::LongLong, 2)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(Domain::null(), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->of([Domain::null()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertEquals(Domain::double(23), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->of([Domain::integer(), Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }

    public function testIntegersWidenWhenSignedMeetsUnsigned(): void
    {
        self::assertEquals(Domain::integer(Field::Long, 11), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->integers([Domain::integer(Field::Tiny, 4), Domain::integer(Field::Long, 11)]));
        self::assertEquals(Domain::integer(Field::LongLong, 10), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->integers([Domain::integer(Field::Long, 10, true), Domain::integer(Field::Short, 6)]));
        self::assertEquals(Domain::decimal(20, 0), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->integers([Domain::integer(Field::LongLong, 20, true), Domain::integer(Field::Tiny, 4)]));
    }

    public function testDecimalsHoldTheIntegralAndFractionalDigitsOfEach(): void
    {
        self::assertEquals(Domain::decimal(2, 1), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->decimals([Domain::integer(Field::LongLong, 2), Domain::decimal(2, 1)]));
    }

    public function testTemporalsMakeADatetimeOfADateAndADatetime(): void
    {
        $date = new Domain(Kind::Date, Field::Date, 10);
        $datetime = new Domain(Kind::DateTime, Field::DateTime, 23, 3);

        self::assertEquals(new Domain(Kind::DateTime, Field::DateTime, 23, 3), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->temporals([$date, $datetime], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertSame(Kind::String, new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->temporals([$date, new Domain(Kind::Time, Field::Time, 10)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([])))?->kind);
    }

    public function testStringsTakeTheLongestTextInTheSettledCollation(): void
    {
        $literal = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals(Domain::string(22, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->strings([$literal, Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
        self::assertSame(23, new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->strings([$literal, new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED)], 'case', new Derivation((new Semantics(Dialect::MySql))->context([])))?->length);
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
}
