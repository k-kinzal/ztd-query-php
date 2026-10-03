<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\UtilityNoise;

#[CoversClass(UtilityNoise::class)]
#[Small]
final class UtilityNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], UtilityNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], UtilityNoise::synonyms());
    }
}
