<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\CastResult;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CastResult::class)]
#[Small]
final class CastResultTest extends TestCase
{
    public function testTypeFollowsTheTarget(): void
    {
        $results = new CastResult();

        self::assertEquals(
            [new Known(new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])), new Known(new Decimal('5', '2')), new Known(new Floating(FloatingKind::Double)), new Known(new Floating(FloatingKind::Float)), new Known(new Spatial(SpatialKind::MultiPoint)), new Choice([new Floating(FloatingKind::Float), new Floating(FloatingKind::Double)])],
            [$results->type(new CastTarget(CastKind::Unsigned)), $results->type(new CastTarget(CastKind::Decimal, '5', '2')), $results->type(new CastTarget(CastKind::Float, '30')), $results->type(new CastTarget(CastKind::Float)), $results->type(new CastTarget(CastKind::MultiPoint)), $results->type(new CastTarget(CastKind::Real))],
        );
    }

    public function testNullabilityMakesATemporalCastNullable(): void
    {
        $results = new CastResult();

        self::assertSame([Nullability::Nullable, Nullability::NotNull], [$results->nullability(new CastTarget(CastKind::Year), Nullability::NotNull), $results->nullability(new CastTarget(CastKind::Signed), Nullability::NotNull)]);
    }
}
