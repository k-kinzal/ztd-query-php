<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\RoutineNoise;

#[CoversClass(RoutineNoise::class)]
#[Small]
final class RoutineNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], RoutineNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], RoutineNoise::synonyms());
    }
}
