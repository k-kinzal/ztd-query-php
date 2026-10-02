<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\ExpressionNoise;

#[CoversClass(ExpressionNoise::class)]
#[Small]
final class ExpressionNoiseTest extends TestCase
{
    public function testPositionsIsEmptyUntilTheFamilyDeclaresNoise(): void
    {
        self::assertSame([], ExpressionNoise::positions());
    }
}
