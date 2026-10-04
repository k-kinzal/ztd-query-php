<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\AggregateResults;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(AggregateResults::class)]
#[Small]
final class AggregateResultsTest extends TestCase
{
    public function testAggregateTypesEachFunction(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = [new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull)];
        $results = new AggregateResults();
        $count = $results->aggregate(new Aggregate(AggregateFunction::Count, []), [], $derivation);
        $sum = $results->aggregate(new Aggregate(AggregateFunction::Sum, [new NumberLiteral('1')]), $int, $derivation);
        $maximum = $results->aggregate(new Aggregate(AggregateFunction::Maximum, [new NumberLiteral('1')]), $int, $derivation);
        $bits = $results->aggregate(new Aggregate(AggregateFunction::BitOr, [new NumberLiteral('1')]), $int, $derivation);

        self::assertEquals(new Known(TypeClass::Integer->descriptor()), $count->type);
        self::assertSame(Nullability::NotNull, $count->nullability);
        self::assertEquals(new Known(TypeClass::Decimal->descriptor()), $sum->type);
        self::assertSame(Nullability::Nullable, $sum->nullability);
        self::assertSame($int[0]->type, $maximum->type);
        self::assertEquals(new Known(TypeClass::Unsigned->descriptor()), $bits->type);
        self::assertSame(Nullability::NotNull, $bits->nullability);
    }

    public function testAggregateReportsDistinctInAWindow(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], false));
        $int = [new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull)];
        (new AggregateResults())->aggregate(new Aggregate(AggregateFunction::Sum, [new NumberLiteral('1')], true, false, new Name('w')), $int, $derivation);

        self::assertInstanceOf(UnsupportedWindowing::class, $derivation->facts()->diagnostics[0]);
    }

    public function testSumIsDecimalForExactNumbersAndDoubleOtherwise(): void
    {
        self::assertEquals(new Known(TypeClass::Floating->descriptor()), (new AggregateResults())->sum(new Known(TypeClass::Character->descriptor())));
        self::assertEquals(new Choice([TypeClass::Decimal->descriptor(), TypeClass::Floating->descriptor()]), (new AggregateResults())->sum(new NullOnly()));
    }

    public function testBitsIsBinaryForABinaryString(): void
    {
        self::assertEquals(new Known(TypeClass::Binary->descriptor()), (new AggregateResults())->bits(new Known(TypeClass::Binary->descriptor())));
        self::assertEquals(new Known(TypeClass::Unsigned->descriptor()), (new AggregateResults())->bits(new NullOnly()));
    }
}
