<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\TableChangeNoise;

#[CoversClass(TableChangeNoise::class)]
#[Small]
final class TableChangeNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], TableChangeNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], TableChangeNoise::synonyms());
    }
}
