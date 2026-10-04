<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericMerge;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;

#[CoversClass(NumericMerge::class)]
#[Small]
final class NumericMergeTest extends TestCase
{
    public function testMergeWidensNumbers(): void
    {
        $merge = new NumericMerge();

        self::assertEquals(
            [new Floating(FloatingKind::Float), new Floating(FloatingKind::Double), new Decimal()],
            [$merge->merge(new Floating(FloatingKind::Float), new Floating(FloatingKind::Float)), $merge->merge(new Floating(FloatingKind::Float), new Integral(IntegralKind::Int)), $merge->merge(new Decimal('5'), new Integral(IntegralKind::Int))],
        );
    }

    public function testIntegralMapsBoolAndBitToIntegers(): void
    {
        $merge = new NumericMerge();

        self::assertEquals([new Integral(IntegralKind::TinyInt), new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned]), new Decimal()], [$merge->integral(new Elementary(ElementaryKind::Boolean)), $merge->integral(new Elementary(ElementaryKind::Bit)), $merge->integral(new Decimal())]);
    }

    public function testIntegersWidenForMixedSignedness(): void
    {
        $merge = new NumericMerge();
        $unsigned = [NumericModifier::Unsigned];

        self::assertEquals(
            [new Integral(IntegralKind::Int, null, $unsigned), new Integral(IntegralKind::BigInt), new Decimal()],
            [$merge->integers(new Integral(IntegralKind::TinyInt, null, $unsigned), new Integral(IntegralKind::Int, null, $unsigned)), $merge->integers(new Integral(IntegralKind::Int, null, $unsigned), new Integral(IntegralKind::SmallInt)), $merge->integers(new Integral(IntegralKind::BigInt), new Integral(IntegralKind::BigInt, null, $unsigned))],
        );
    }
}
