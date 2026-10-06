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
    public function testPositionsListsTheOptionalWords(): void
    {
        self::assertSame([0], DmlNoise::positions()['opt_INTO: INTO']);
        self::assertSame([0, 2], DmlNoise::positions()['opt_paren_expr_list: ( opt_expr_list )']);
    }

    public function testSynonymsMapValueToValues(): void
    {
        self::assertSame([0 => 'VALUES'], DmlNoise::synonyms()['value_or_values: VALUE_SYM']);
        self::assertSame([0 => 'DEALLOCATE_SYM'], DmlNoise::synonyms()['deallocate_or_drop: DROP']);
    }
}
