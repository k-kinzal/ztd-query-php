<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregates;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Aggregates::class)]
#[Small]
final class AggregatesTest extends TestCase
{
    public function testResultWidensSumsAndAveragesOfExactNumbers(): void
    {
        $aggregates = new Aggregates(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertEquals(Domain::decimal(32, 0), $aggregates->result(AggregateFunction::Sum, Domain::integer(Field::Long, 11)));
        self::assertEquals(Domain::decimal(12, 6), $aggregates->result(AggregateFunction::Average, Domain::decimal(8, 2)));
        self::assertEquals(Domain::double(23), $aggregates->result(AggregateFunction::Sum, Domain::double()));
        self::assertEquals(Domain::integer(Field::LongLong, 21), $aggregates->result(AggregateFunction::Count, null));
        self::assertEquals(Domain::decimal(8, 2), $aggregates->result(AggregateFunction::Maximum, Domain::decimal(8, 2)));
        self::assertNull($aggregates->result(AggregateFunction::Collect, Domain::integer()));
    }

    public function testConcatenatedFollowsGroupConcatMaxLen(): void
    {
        $text = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));

        self::assertEquals(Domain::string(100, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Implicit), (new Aggregates(new Settings(Collation::known('utf8mb4_0900_ai_ci'), 4, null, [], 100)))->concatenated([$text], $derivation));
        self::assertEquals(Domain::string(16384, Collation::known('utf8mb4_0900_ai_ci'), Field::LongBlob, Coercibility::Implicit), (new Aggregates(new Settings(Collation::known('utf8mb4_0900_ai_ci'))))->concatenated([$text], $derivation));
    }

    public function testResultTypesTheSumAndAverageOfNullAsShortDoubles(): void
    {
        $aggregates = new Aggregates(new Settings(Collation::known('utf8mb4_0900_ai_ci')));

        self::assertEquals([Domain::double(17, 0), Domain::double(21, 4)], [$aggregates->result(AggregateFunction::Sum, Domain::null()), $aggregates->result(AggregateFunction::Average, Domain::null())]);
    }

    public function testResultTypesJsonAggregatesAndExtremesOfJsonByRelease(): void
    {
        $aggregates = new Aggregates(new Settings(Collation::known('utf8mb4_0900_ai_ci')));
        $json = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json, Field::Json, 4294967295, 0, false, Collation::known('utf8mb4_bin'));

        self::assertSame(Domain::NOT_FIXED, $aggregates->result(AggregateFunction::Maximum, $json)?->decimals);
        self::assertSame(16777216, $aggregates->result(AggregateFunction::JsonArray, Domain::integer(), \SqlSemantics\Contract\GrammarRelease::MySql5744)?->length);
        self::assertSame(4294967295, $aggregates->result(AggregateFunction::JsonArray, Domain::integer())?->length);
    }
}
