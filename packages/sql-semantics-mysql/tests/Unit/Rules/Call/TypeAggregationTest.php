<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\TypeAggregation;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(TypeAggregation::class)]
#[Small]
final class TypeAggregationTest extends TestCase
{
    public function testAggregateCombinesKnownTypesAndSkipsNull(): void
    {
        $int = new Known(new Integral(IntegralKind::Int));
        $aggregation = new TypeAggregation();

        self::assertSame($int, $aggregation->aggregate([new NullOnly(), $int, new Known(new Integral(IntegralKind::Int))]));
        self::assertEquals(new Known(TypeClass::Decimal->descriptor()), $aggregation->aggregate([$int, new Known(TypeClass::Decimal->descriptor())]));
        self::assertEquals(new NullOnly(), $aggregation->aggregate([new NullOnly()]));
    }

    public function testAggregatePropagatesMissingInputs(): void
    {
        $missing = new UndeclaredRoutine(new QualifiedName(new Name('f')));
        $type = (new TypeAggregation())->aggregate([new Known(TypeClass::Integer->descriptor()), new Dependent([$missing])]);

        self::assertInstanceOf(Dependent::class, $type);
        self::assertSame([$missing], $type->missing);
    }

    public function testCombineGivesEveryCombinationOfChoices(): void
    {
        $choice = new Choice([TypeClass::Integer->descriptor(), TypeClass::Character->descriptor()]);

        self::assertEquals(new Choice([TypeClass::Floating->descriptor(), TypeClass::Character->descriptor()]), (new TypeAggregation())->combine([$choice, new Known(TypeClass::Floating->descriptor())]));
    }

    public function testSameComparesDeclaredTypes(): void
    {
        $first = new Known(new Integral(IntegralKind::Int));

        self::assertTrue((new TypeAggregation())->same([$first, new Known(new Integral(IntegralKind::Int))], $first));
        self::assertFalse((new TypeAggregation())->same([$first, new Known(new Integral(IntegralKind::BigInt))], $first));
    }

    public function testClassesAnswersTheClassesOfAType(): void
    {
        self::assertSame([TypeClass::Integer], (new TypeAggregation())->classes(new Known(TypeClass::Integer->descriptor())));
        self::assertSame([], (new TypeAggregation())->classes(new NullOnly()));
    }

    public function testMergeFollowsTheConversionRules(): void
    {
        $aggregation = new TypeAggregation();

        self::assertSame(TypeClass::Floating, $aggregation->merge(TypeClass::Decimal, TypeClass::Floating));
        self::assertSame(TypeClass::Integer, $aggregation->merge(TypeClass::Unsigned, TypeClass::Integer));
        self::assertSame(TypeClass::DateTime, $aggregation->merge(TypeClass::Date, TypeClass::Time));
        self::assertSame(TypeClass::Character, $aggregation->merge(TypeClass::Integer, TypeClass::Date));
        self::assertSame(TypeClass::Binary, $aggregation->merge(TypeClass::Character, TypeClass::Spatial));
    }

    public function testFactAnswersAKnownTypeOrAChoice(): void
    {
        self::assertEquals(new Known(TypeClass::Date->descriptor()), (new TypeAggregation())->fact([TypeClass::Date, TypeClass::Date]));
        self::assertEquals(new Choice([TypeClass::Date->descriptor(), TypeClass::Time->descriptor()]), (new TypeAggregation())->fact([TypeClass::Date, TypeClass::Time]));
    }
}
