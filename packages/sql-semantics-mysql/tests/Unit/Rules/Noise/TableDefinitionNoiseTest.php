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
    public function testPositionsListsTheOptionalWords(): void
    {
        self::assertSame([1], TableDefinitionNoise::positions()['column_attribute: UNIQUE_SYM KEY_SYM']);
        self::assertSame([0, 1], TableDefinitionNoise::positions()['opt_generated_always: GENERATED ALWAYS_SYM']);
    }

    public function testSynonymsMapsTypeToUsing(): void
    {
        self::assertSame([0 => 'USING'], TableDefinitionNoise::synonyms()['index_type_clause: TYPE_SYM index_type']);
    }
}
