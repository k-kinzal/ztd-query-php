<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Noise;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Rules\Noise\LeafNoise;

#[CoversClass(LeafNoise::class)]
#[Medium]
final class LeafNoiseTest extends TestCase
{
    public function testPositionsDeclareTheOptionalLeafKeywordsAsNoise(): void
    {
        self::assertSame([0], LeafNoise::positions()['opt_as: AS']);
        self::assertSame([0], LeafNoise::positions()['equal: EQ']);
        self::assertSame([0], LeafNoise::positions()['equal: SET_VAR']);
        self::assertSame([0], LeafNoise::positions()['opt_default: DEFAULT']);
        self::assertSame([0], LeafNoise::positions()['opt_default: DEFAULT_SYM']);
        self::assertSame([0, 1], LeafNoise::positions()['opt_wild: . *']);
        self::assertSame([0], LeafNoise::positions()['opt_comma: ,']);
        self::assertSame([0], LeafNoise::positions()['opt_storage: STORAGE_SYM']);
        self::assertSame([0], LeafNoise::positions()['opt_table: TABLE_SYM']);
        self::assertSame([0], LeafNoise::positions()['table_ident: . ident']);
        self::assertSame([0], LeafNoise::positions()['field_ident: . ident']);
        self::assertSame([1], LeafNoise::positions()['charset: CHAR_SYM SET']);
        self::assertSame([1], LeafNoise::positions()['character_set: CHAR_SYM SET_SYM']);
        self::assertCount(13, LeafNoise::positions());
        self::assertArrayNotHasKey('optional_braces: ( )', LeafNoise::positions());
        self::assertArrayNotHasKey('simple_ident_q: . ident . ident', LeafNoise::positions());
        self::assertArrayNotHasKey('table_ident: ident', LeafNoise::positions());
        self::assertArrayNotHasKey('charset: CHARSET', LeafNoise::positions());
    }

    public function testPositionsNameOnlyTerminalPositionsOfProductionsOfTheGrammars(): void
    {
        $productions = array_merge(
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-5.7.44.php')->all(),
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-8.0.44.php')->all(),
        );
        $arity = array_map(static fn (string $signature): int => count(explode(' ', trim(substr($signature, (int) strpos($signature, ':') + 1)))), array_combine(array_keys(LeafNoise::positions()), array_keys(LeafNoise::positions())));

        self::assertSame([], array_values(array_diff(array_keys(LeafNoise::positions()), $productions)));
        self::assertSame([], array_filter(LeafNoise::positions(), static fn (array $positions, string $signature): bool => max(-1, ...$positions) >= $arity[$signature], ARRAY_FILTER_USE_BOTH));
    }

    public function testSynonymsMergeTheTerminalsTheManualDefinesAsSynonyms(): void
    {
        self::assertSame([0 => 'CHARSET'], LeafNoise::synonyms()['charset: CHAR_SYM SET']);
        self::assertSame([0 => 'CHARSET'], LeafNoise::synonyms()['character_set: CHAR_SYM SET_SYM']);
        self::assertSame([0 => 'INDEX_SYM'], LeafNoise::synonyms()['key_or_index: KEY_SYM']);
        self::assertSame([0 => 'INDEXES'], LeafNoise::synonyms()['keys_or_index: KEYS']);
        self::assertSame([0 => 'INDEXES'], LeafNoise::synonyms()['keys_or_index: INDEX_SYM']);
        self::assertSame([0 => 'TABLE_SYM'], LeafNoise::synonyms()['table_or_tables: TABLES']);
        self::assertSame([0 => 'NOT_SYM'], LeafNoise::synonyms()['not: NOT2_SYM']);
        self::assertSame([0 => 'NO_WRITE_TO_BINLOG'], LeafNoise::synonyms()['opt_no_write_to_binlog: LOCAL_SYM']);
        self::assertSame([0 => 'SESSION_SYM'], LeafNoise::synonyms()['opt_var_ident_type: LOCAL_SYM .']);
        self::assertSame([0 => 'SESSION_SYM'], LeafNoise::synonyms()['opt_rvalue_system_variable_type: LOCAL_SYM .']);
        self::assertSame([0 => 'SESSION_SYM'], LeafNoise::synonyms()['opt_set_var_ident_type: LOCAL_SYM .']);
        self::assertSame([0 => 'name:default'], LeafNoise::synonyms()['lvalue_variable: DEFAULT_SYM . ident']);
        self::assertSame([0 => 'name:binary'], LeafNoise::synonyms()['charset_name: BINARY']);
        self::assertSame([0 => 'name:binary'], LeafNoise::synonyms()['charset_name: BINARY_SYM']);
        self::assertSame([0 => 'name:binary'], LeafNoise::synonyms()['old_or_new_charset_name: BINARY']);
        self::assertSame([0 => 'name:binary'], LeafNoise::synonyms()['old_or_new_charset_name: BINARY_SYM']);
        self::assertSame([0 => 'name:binary'], LeafNoise::synonyms()['collation_name: BINARY_SYM']);
        self::assertCount(17, LeafNoise::synonyms());
        self::assertArrayNotHasKey('key_or_index: INDEX_SYM', LeafNoise::synonyms());
        self::assertArrayNotHasKey('not: NOT_SYM', LeafNoise::synonyms());
    }

    public function testSynonymsNameOnlyTerminalPositionsOfProductionsOfTheGrammars(): void
    {
        $productions = array_merge(
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-5.7.44.php')->all(),
            Productions::load(dirname(__DIR__, 4) . '/resources/productions/mysql-8.0.44.php')->all(),
        );
        $arity = array_map(static fn (string $signature): int => count(explode(' ', trim(substr($signature, (int) strpos($signature, ':') + 1)))), array_combine(array_keys(LeafNoise::synonyms()), array_keys(LeafNoise::synonyms())));

        self::assertSame([], array_values(array_diff(array_keys(LeafNoise::synonyms()), $productions)));
        self::assertSame([], array_filter(LeafNoise::synonyms(), static fn (array $keys, string $signature): bool => max(-1, ...array_keys($keys)) >= $arity[$signature], ARRAY_FILTER_USE_BOTH));
    }
}
