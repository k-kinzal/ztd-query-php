<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\ReplicationNoise;

#[CoversClass(ReplicationNoise::class)]
#[Small]
final class ReplicationNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], ReplicationNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], ReplicationNoise::synonyms());
    }
}
