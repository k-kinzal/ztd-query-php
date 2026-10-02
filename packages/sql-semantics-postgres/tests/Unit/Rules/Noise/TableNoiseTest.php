<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Noise\TableNoise;

#[CoversClass(TableNoise::class)]
#[Small]
final class TableNoiseTest extends TestCase
{
    public function testPositionsIsEmptyUntilTheFamilyDeclaresNoise(): void
    {
        self::assertSame([], TableNoise::positions());
    }
}
