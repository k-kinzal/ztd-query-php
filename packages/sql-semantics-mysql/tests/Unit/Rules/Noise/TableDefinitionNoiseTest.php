<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\TableDefinitionNoise;

#[CoversClass(TableDefinitionNoise::class)]
#[Small]
final class TableDefinitionNoiseTest extends TestCase
{
    public function testPositionsListsNothingYet(): void
    {
        self::assertSame([], TableDefinitionNoise::positions());
    }

    public function testSynonymsListsNothingYet(): void
    {
        self::assertSame([], TableDefinitionNoise::synonyms());
    }
}
