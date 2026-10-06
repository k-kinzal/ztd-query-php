<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Nullability::class)]
#[Small]
final class NullabilityTest extends TestCase
{
    public function testPropagateYieldsNullableWhenEitherOperandIsNullable(): void
    {
        self::assertSame(Nullability::Nullable, Nullability::NotNull->propagate(Nullability::Nullable));
        self::assertSame(Nullability::Nullable, Nullability::Nullable->propagate(Nullability::Dependent));
        self::assertSame(Nullability::Nullable, Nullability::Dependent->propagate(Nullability::Nullable));
    }

    public function testPropagateYieldsDependentWhenNoOperandIsNullableAndOneIsUndecided(): void
    {
        self::assertSame(Nullability::Dependent, Nullability::NotNull->propagate(Nullability::Dependent));
        self::assertSame(Nullability::Dependent, Nullability::Dependent->propagate(Nullability::NotNull));
        self::assertSame(Nullability::Dependent, Nullability::Dependent->propagate(Nullability::Dependent));
    }

    public function testPropagateYieldsNotNullOnlyForTwoNotNullOperands(): void
    {
        self::assertSame(Nullability::NotNull, Nullability::NotNull->propagate(Nullability::NotNull));
    }
}
