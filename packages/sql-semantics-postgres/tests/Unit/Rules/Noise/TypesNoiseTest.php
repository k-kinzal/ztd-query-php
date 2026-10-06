<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\TypesNoise;

#[CoversClass(TypesNoise::class)]
#[Small]
final class TypesNoiseTest extends TestCase
{
    public function testPositionsIsEmptyUntilTheFamilyDeclaresNoise(): void
    {
        self::assertSame([], TypesNoise::positions());
    }
}
