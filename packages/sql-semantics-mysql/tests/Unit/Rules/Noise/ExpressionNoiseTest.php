<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Noise\ExpressionNoise;

#[CoversClass(ExpressionNoise::class)]
#[Small]
final class ExpressionNoiseTest extends TestCase
{
    public function testPositionsListsNoOptionalWordSinceTheModelKeepsThem(): void
    {
        self::assertSame([], ExpressionNoise::positions());
    }

    public function testSynonymsKeyTheSynonymTerminalsAsTheWrittenOnes(): void
    {
        self::assertSame([
            'and: AND_AND_SYM' => [0 => 'AND_SYM'],
            'or: OR2_SYM' => [0 => 'OR_SYM'],
            'not2: NOT2_SYM' => [0 => '!'],
            'bit_expr: bit_expr MOD_SYM bit_expr' => [1 => '%'],
            'simple_expr: CONVERT_SYM ( expr , cast_type )' => [0 => 'CAST_SYM', 3 => 'AS'],
        ], ExpressionNoise::synonyms());
    }
}
