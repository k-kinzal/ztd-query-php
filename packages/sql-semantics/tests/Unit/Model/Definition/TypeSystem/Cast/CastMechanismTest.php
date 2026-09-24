<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism;

#[CoversClass(CastMechanism::class)]
final class CastMechanismTest extends TestCase
{
    public function testSpellsTheSqlClauses(): void
    {
        self::assertSame(['WITHOUT FUNCTION', 'WITH INOUT'], array_map(static fn (CastMechanism $mechanism): string => $mechanism->value, CastMechanism::cases()));
    }
}
