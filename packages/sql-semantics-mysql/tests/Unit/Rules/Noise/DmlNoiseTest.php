<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\DmlNoise;

#[CoversClass(DmlNoise::class)]
#[Small]
final class DmlNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], DmlNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], DmlNoise::synonyms());
    }
}
