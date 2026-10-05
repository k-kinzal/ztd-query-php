<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Rules\Noise\TypeNoise;

#[CoversClass(TypeNoise::class)]
#[Medium]
final class TypeNoiseTest extends TestCase
{
    public function testPositionsDeclareTheRedundantTypeKeywordsAsNoise(): void
    {
        self::assertSame([1], TypeNoise::positions()['real_type: DOUBLE_SYM PRECISION']);
        self::assertSame([0], TypeNoise::positions()['opt_PRECISION: PRECISION']);
        self::assertSame([1], TypeNoise::positions()['nchar: NATIONAL_SYM CHAR_SYM']);
        self::assertSame([1], TypeNoise::positions()['nvarchar: NATIONAL_SYM VARCHAR']);
        self::assertSame([1], TypeNoise::positions()['nvarchar: NATIONAL_SYM VARCHAR_SYM']);
        self::assertSame([1], TypeNoise::positions()['nvarchar: NCHAR_SYM VARCHAR']);
        self::assertSame([1], TypeNoise::positions()['nvarchar: NCHAR_SYM VARCHAR_SYM']);
        self::assertSame([1, 2], TypeNoise::positions()['nvarchar: NATIONAL_SYM CHAR_SYM VARYING']);
        self::assertSame([1], TypeNoise::positions()['nvarchar: NCHAR_SYM VARYING']);
        self::assertCount(9, TypeNoise::positions());
        self::assertArrayNotHasKey('cast_type: SIGNED_SYM INT_SYM', TypeNoise::positions());
    }

    public function testPositionsNameOnlyTerminalPositionsOfProductionsOfTheGrammars(): void
    {
        $productions = array_merge(
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-5.7.44.php')->all(),
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-8.0.44.php')->all(),
        );
        $arity = array_map(static fn (string $signature): int => count(explode(' ', trim(substr($signature, (int) strpos($signature, ':') + 1)))), array_combine(array_keys(TypeNoise::positions()), array_keys(TypeNoise::positions())));

        self::assertSame([], array_values(array_diff(array_keys(TypeNoise::positions()), $productions)));
        self::assertSame([], array_filter(TypeNoise::positions(), static fn (array $positions, string $signature): bool => max(-1, ...$positions) >= $arity[$signature], ARRAY_FILTER_USE_BOTH));
    }

    public function testSynonymsMergeTheNationalDecimalAndBooleanSpellings(): void
    {
        self::assertSame([0 => 'NCHAR_SYM'], TypeNoise::synonyms()['nchar: NATIONAL_SYM CHAR_SYM']);
        self::assertSame([0 => 'NVARCHAR_SYM'], TypeNoise::synonyms()['nvarchar: NATIONAL_SYM VARCHAR']);
        self::assertSame([0 => 'NVARCHAR_SYM'], TypeNoise::synonyms()['nvarchar: NATIONAL_SYM VARCHAR_SYM']);
        self::assertSame([0 => 'NVARCHAR_SYM'], TypeNoise::synonyms()['nvarchar: NCHAR_SYM VARCHAR']);
        self::assertSame([0 => 'NVARCHAR_SYM'], TypeNoise::synonyms()['nvarchar: NCHAR_SYM VARCHAR_SYM']);
        self::assertSame([0 => 'NVARCHAR_SYM'], TypeNoise::synonyms()['nvarchar: NATIONAL_SYM CHAR_SYM VARYING']);
        self::assertSame([0 => 'NVARCHAR_SYM'], TypeNoise::synonyms()['nvarchar: NCHAR_SYM VARYING']);
        self::assertSame([0 => 'DECIMAL_SYM'], TypeNoise::synonyms()['type: NUMERIC_SYM float_options field_options']);
        self::assertSame([0 => 'DECIMAL_SYM'], TypeNoise::synonyms()['type: FIXED_SYM float_options field_options']);
        self::assertSame([0 => 'DECIMAL_SYM'], TypeNoise::synonyms()['numeric_type: NUMERIC_SYM']);
        self::assertSame([0 => 'DECIMAL_SYM'], TypeNoise::synonyms()['numeric_type: FIXED_SYM']);
        self::assertSame([0 => 'BOOL_SYM'], TypeNoise::synonyms()['type: BOOLEAN_SYM']);
        self::assertCount(12, TypeNoise::synonyms());
        self::assertArrayNotHasKey('numeric_type: DECIMAL_SYM', TypeNoise::synonyms());
    }

    public function testSynonymsNameOnlyTerminalPositionsOfProductionsOfTheGrammars(): void
    {
        $productions = array_merge(
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-5.7.44.php')->all(),
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-8.0.44.php')->all(),
        );
        $arity = array_map(static fn (string $signature): int => count(explode(' ', trim(substr($signature, (int) strpos($signature, ':') + 1)))), array_combine(array_keys(TypeNoise::synonyms()), array_keys(TypeNoise::synonyms())));

        self::assertSame([], array_values(array_diff(array_keys(TypeNoise::synonyms()), $productions)));
        self::assertSame([], array_filter(TypeNoise::synonyms(), static fn (array $keys, string $signature): bool => max(-1, ...array_keys($keys)) >= $arity[$signature], ARRAY_FILTER_USE_BOTH));
    }
}
