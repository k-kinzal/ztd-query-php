<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Operator\CastMode;

#[CoversClass(CastMode::class)]
final class CastModeTest extends TestCase
{
    public function testRepresentsBothConversionModes(): void
    {
        self::assertSame(['explicit', 'implicit'], array_column(CastMode::cases(), 'value'));
    }

    public function testResolvesAModeFromItsName(): void
    {
        self::assertSame(CastMode::Implicit, CastMode::from('implicit'));
    }

    #[TestWith(['coercion'])]
    #[TestWith(['Implicit'])]
    public function testLeavesOtherNamesUnclassified(string $name): void
    {
        self::assertNull(CastMode::tryFrom($name));
    }
}
