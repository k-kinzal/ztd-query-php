<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClasses;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(TypeClasses::class)]
#[Small]
final class TypeClassesTest extends TestCase
{
    public function testClassesAnswersTheClassesOfAType(): void
    {
        self::assertSame([TypeClass::Integer], (new TypeClasses())->classes(new Known(TypeClass::Integer->descriptor())));
        self::assertSame([], (new TypeClasses())->classes(new NullOnly()));
    }

    public function testMergeFollowsTheConversionRules(): void
    {
        $aggregation = new TypeClasses();

        self::assertSame(TypeClass::Floating, $aggregation->merge(TypeClass::Decimal, TypeClass::Floating));
        self::assertSame(TypeClass::Integer, $aggregation->merge(TypeClass::Unsigned, TypeClass::Integer));
        self::assertSame(TypeClass::DateTime, $aggregation->merge(TypeClass::Date, TypeClass::Time));
        self::assertSame(TypeClass::Character, $aggregation->merge(TypeClass::Integer, TypeClass::Date));
        self::assertSame(TypeClass::Binary, $aggregation->merge(TypeClass::Character, TypeClass::Spatial));
    }

    public function testFactAnswersAKnownTypeOrAChoice(): void
    {
        self::assertEquals(new Known(TypeClass::Date->descriptor()), (new TypeClasses())->fact([TypeClass::Date, TypeClass::Date]));
        self::assertEquals(new Choice([TypeClass::Date->descriptor(), TypeClass::Time->descriptor()]), (new TypeClasses())->fact([TypeClass::Date, TypeClass::Time]));
    }
}
