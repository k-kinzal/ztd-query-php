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
    public function testPositionsListsTheOptionalWords(): void
    {
        self::assertSame(['opt_column: COLUMN_SYM', 'opt_to: TO_SYM', 'opt_to: EQ', 'opt_to: AS', 'opt_table_sym: TABLE_SYM'], array_keys(TableChangeNoise::positions()));
    }

    public function testSynonymsListsNothing(): void
    {
        self::assertSame([], TableChangeNoise::synonyms());
    }
}
