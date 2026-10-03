<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\ServerNoise;

#[CoversClass(ServerNoise::class)]
#[Small]
final class ServerNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], ServerNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], ServerNoise::synonyms());
    }
}
