<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
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
        self::assertEquals(Domain::integer(Field::LongLong, 11), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->integers([Domain::integer(Field::Long, 10, true), Domain::integer(Field::Short, 6)]));
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

        self::assertEquals(Domain::string(23, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible), new Aggregation(new Collations(Collation::known('utf8mb4_0900_ai_ci')))->strings([$literal, Domain::double()], 'case', new Derivation((new Semantics(Dialect::MySql))->context([]))));
    }
}
