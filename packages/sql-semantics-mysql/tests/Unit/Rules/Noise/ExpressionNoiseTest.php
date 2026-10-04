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
    public function testPositionsListsTheOptionalWords(): void
    {
        self::assertSame([
            'simple_expr: ROW_SYM ( expr , expr_list )' => [0],
            'ident_list_arg: ( ident_list )' => [0, 2],
            'opt_natural_language_mode: IN_SYM NATURAL LANGUAGE_SYM MODE_SYM' => [0, 1, 2, 3],
            'opt_of: OF_SYM' => [0],
        ], ExpressionNoise::positions());
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
