<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Rules\LeafKeys;
use SqlSemantics\Platform\MySql\Rules\Noise\AccountNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\CallNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\DispatchNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\DmlNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\ExpressionNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\LeafNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\QueryNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\ReplicationNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\RoutineNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\ServerNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\TableChangeNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\TableDefinitionNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\TypeNoise;
use SqlSemantics\Platform\MySql\Rules\Noise\UtilityNoise;

#[CoversClass(LeafKeys::class)]
#[Small]
final class LeafKeysTest extends TestCase
{
    public function testKeyDecodesNamesAtNameProductions(): void
    {
        $keys = new LeafKeys(new LexicalSettings(''));

        self::assertSame('name:Users', $keys->key(new Token(1, 'IDENT', 'Users', 0), 'IDENT_sys: IDENT', 0));
        self::assertSame('name:order items', $keys->key(new Token(1, 'IDENT_QUOTED', '`order items`', 0), 'IDENT_sys: IDENT_QUOTED', 0));
        self::assertSame('name:a`b', $keys->key(new Token(1, 'IDENT_QUOTED', '`a``b`', 0), 'IDENT_sys: IDENT_QUOTED', 0));
        self::assertSame('name:action', $keys->key(new Token(1, 'ACTION', 'action', 0), 'ident_keywords_unambiguous: ACTION', 0));
        self::assertSame('name:action', $keys->key(new Token(1, 'ACTION', 'action', 0), 'keyword_sp: ACTION', 0));
        self::assertSame("name:a'b", $keys->key(new Token(1, 'TEXT_STRING', "'a''b'", 0), 'TEXT_STRING_sys: TEXT_STRING', 0));
        self::assertSame("name:a\nb", $keys->key(new Token(1, 'TEXT_STRING', "'a\\nb'", 0), 'TEXT_STRING_validated: TEXT_STRING', 0));
    }

    public function testKeyDecodesStringsUnderTheEscapeSetting(): void
    {
        self::assertSame("text:a\nb", (new LeafKeys(new LexicalSettings('')))->key(new Token(1, 'TEXT_STRING', "'a\\nb'", 0), 'text_literal: TEXT_STRING', 0));
        self::assertSame('text:a\\nb', (new LeafKeys(new LexicalSettings('NO_BACKSLASH_ESCAPES')))->key(new Token(1, 'TEXT_STRING', "'a\\nb'", 0), 'text_literal: TEXT_STRING', 0));
        self::assertSame("text:a'b", (new LeafKeys(new LexicalSettings('NO_BACKSLASH_ESCAPES')))->key(new Token(1, 'TEXT_STRING', "'a''b'", 0), 'text_literal: TEXT_STRING', 0));
        self::assertSame('national:x', (new LeafKeys(new LexicalSettings('')))->key(new Token(1, 'NCHAR_STRING', "N'x'", 0), 'text_literal: NCHAR_STRING', 0));
        self::assertSame('name:a\\nb', (new LeafKeys(new LexicalSettings('NO_BACKSLASH_ESCAPES')))->key(new Token(1, 'TEXT_STRING', "'a\\nb'", 0), 'TEXT_STRING_sys: TEXT_STRING', 0));
    }

    public function testKeyDecodesLiteralsByTheirExactValue(): void
    {
        $keys = new LeafKeys(new LexicalSettings(''));

        self::assertSame('charset:utf8mb4', $keys->key(new Token(1, 'UNDERSCORE_CHARSET', '_UTF8MB4', 0), 'text_literal: UNDERSCORE_CHARSET TEXT_STRING', 0));
        self::assertSame('hex:1f', $keys->key(new Token(1, 'HEX_NUM', "X'1f'", 0), 'literal: HEX_NUM', 0));
        self::assertSame('hex:1f', $keys->key(new Token(1, 'HEX_NUM', '0x1F', 0), 'literal: HEX_NUM', 0));
        self::assertSame('bit:101', $keys->key(new Token(1, 'BIN_NUM', "b'101'", 0), 'literal: BIN_NUM', 0));
        self::assertSame('bit:101', $keys->key(new Token(1, 'BIN_NUM', '0b101', 0), 'literal: BIN_NUM', 0));
        self::assertSame('number:007', $keys->key(new Token(1, 'NUM', '007', 0), 'NUM_literal: NUM', 0));
        self::assertSame('number:1.50', $keys->key(new Token(1, 'DECIMAL_NUM', '1.50', 0), 'NUM_literal: DECIMAL_NUM', 0));
        self::assertSame('number:1E3', $keys->key(new Token(1, 'FLOAT_NUM', '1E3', 0), 'NUM_literal: FLOAT_NUM', 0));
        self::assertSame('parameter:?', $keys->key(new Token(1, 'PARAM_MARKER', '?', 0), 'param_marker: PARAM_MARKER', 0));
    }

    public function testKeyKeysKeywordsAndPunctuationByTerminal(): void
    {
        $keys = new LeafKeys(new LexicalSettings(''));

        self::assertSame('NE', $keys->key(new Token(1, 'NE', '<>', 0), 'comp_op: NE', 0));
        self::assertSame('NE', $keys->key(new Token(1, 'NE', '!=', 0), 'comp_op: NE', 0));
        self::assertSame('DATABASE', $keys->key(new Token(1, 'DATABASE', 'database', 0), 'drop_database_stmt: DROP DATABASE if_exists ident', 1));
        self::assertSame('DATABASE', $keys->key(new Token(1, 'DATABASE', 'SCHEMA', 0), 'drop_database_stmt: DROP DATABASE if_exists ident', 1));
        self::assertSame('INT_SYM', $keys->key(new Token(1, 'INT_SYM', 'INTEGER', 0), 'int_type: INT_SYM', 0));
        self::assertSame('INT_SYM', $keys->key(new Token(1, 'INT_SYM', 'int', 0), 'int_type: INT_SYM', 0));
        self::assertSame('SELECT_SYM', $keys->key(new Token(1, 'SELECT_SYM', 'Select', 0), 'query_specification: SELECT_SYM select_options select_item_list', 0));
        self::assertSame('(', $keys->key(new Token(1, '(', '(', 0), 'simple_expr: ( expr )', 0));
    }

    public function testKeyIsNullAtNoisePositionsAndForTheEndMarker(): void
    {
        $keys = new LeafKeys(new LexicalSettings(''));

        self::assertNull($keys->key(new Token(1, 'AS', 'AS', 0), 'opt_as: AS', 0));
        self::assertSame('AS', $keys->key(new Token(1, 'AS', 'as', 0), 'select_alias: AS ident', 0));
        self::assertNull($keys->key(new Token(1, ';', ';', 0), 'sql_statement: simple_statement_or_begin ; opt_end_of_input', 1));
        self::assertNull($keys->key(new Token(1, 'PRECISION', 'PRECISION', 0), 'real_type: DOUBLE_SYM PRECISION', 1));
        self::assertNull($keys->key(new Token(1, 'SET', 'SET', 0), 'charset: CHAR_SYM SET', 1));
        self::assertNull($keys->key(new Token(0, 'END_OF_INPUT', '', 0), 'opt_end_of_input: END_OF_INPUT', 0));
        self::assertNull($keys->key(new Token(0, '$end', '', 0), 'start_entry: sql_statement', 1));
        self::assertSame('DOUBLE_SYM', $keys->key(new Token(1, 'DOUBLE_SYM', 'DOUBLE', 0), 'real_type: DOUBLE_SYM PRECISION', 0));
    }

    public function testKeyAnswersTheStatedKeyAtSynonymPositions(): void
    {
        $keys = new LeafKeys(new LexicalSettings(''));

        self::assertSame('CHARSET', $keys->key(new Token(1, 'CHAR_SYM', 'CHARACTER', 0), 'charset: CHAR_SYM SET', 0));
        self::assertSame('CHARSET', $keys->key(new Token(1, 'CHAR_SYM', 'character', 0), 'character_set: CHAR_SYM SET_SYM', 0));
        self::assertSame('INDEX_SYM', $keys->key(new Token(1, 'KEY_SYM', 'KEY', 0), 'key_or_index: KEY_SYM', 0));
        self::assertSame('INDEXES', $keys->key(new Token(1, 'KEYS', 'KEYS', 0), 'keys_or_index: KEYS', 0));
        self::assertSame('name:binary', $keys->key(new Token(1, 'BINARY', 'BINARY', 0), 'charset_name: BINARY', 0));
        self::assertSame('name:binary', $keys->key(new Token(1, 'BINARY_SYM', 'binary', 0), 'collation_name: BINARY_SYM', 0));
        self::assertSame('NCHAR_SYM', $keys->key(new Token(1, 'NATIONAL_SYM', 'NATIONAL', 0), 'nchar: NATIONAL_SYM CHAR_SYM', 0));
        self::assertSame('DECIMAL_SYM', $keys->key(new Token(1, 'NUMERIC_SYM', 'NUMERIC', 0), 'numeric_type: NUMERIC_SYM', 0));
        self::assertSame('SESSION_SYM', $keys->key(new Token(1, 'LOCAL_SYM', 'LOCAL', 0), 'opt_set_var_ident_type: LOCAL_SYM .', 0));
        self::assertSame('.', $keys->key(new Token(1, '.', '.', 0), 'opt_set_var_ident_type: LOCAL_SYM .', 1));
    }

    public function testNameDecodesIdentifiersStringsAndKeywordsAsNames(): void
    {
        self::assertSame('a`b', (new LeafKeys(new LexicalSettings('')))->name(new Token(1, 'IDENT_QUOTED', '`a``b`', 0)));
        self::assertSame('Users', (new LeafKeys(new LexicalSettings('')))->name(new Token(1, 'IDENT', 'Users', 0)));
        self::assertSame("a\tb", (new LeafKeys(new LexicalSettings('')))->name(new Token(1, 'TEXT_STRING', "'a\\tb'", 0)));
        self::assertSame('a\\tb', (new LeafKeys(new LexicalSettings('NO_BACKSLASH_ESCAPES')))->name(new Token(1, 'TEXT_STRING', "'a\\tb'", 0)));
        self::assertSame('Action', (new LeafKeys(new LexicalSettings('')))->name(new Token(1, 'ACTION', 'Action', 0)));
    }

    public function testTerminalKeysEachTerminalByWhatItDenotes(): void
    {
        $keys = new LeafKeys(new LexicalSettings(''));

        self::assertSame('name:x y', $keys->terminal(new Token(1, 'IDENT_QUOTED', '`x y`', 0)));
        self::assertSame('name:localhost', $keys->terminal(new Token(1, 'LEX_HOSTNAME', 'localhost', 0)));
        self::assertSame("text:a'b", $keys->terminal(new Token(1, 'TEXT_STRING', "'a\\'b'", 0)));
        self::assertSame("national:a'b", $keys->terminal(new Token(1, 'NCHAR_STRING', "N'a''b'", 0)));
        self::assertSame('charset:latin1', $keys->terminal(new Token(1, 'UNDERSCORE_CHARSET', '_Latin1', 0)));
        self::assertSame('hex:0abc', $keys->terminal(new Token(1, 'HEX_NUM', "x'0ABC'", 0)));
        self::assertSame('bit:0101', $keys->terminal(new Token(1, 'BIN_NUM', "B'0101'", 0)));
        self::assertSame('number:18446744073709551615', $keys->terminal(new Token(1, 'ULONGLONG_NUM', '18446744073709551615', 0)));
        self::assertSame('number:2147483648', $keys->terminal(new Token(1, 'LONG_NUM', '2147483648', 0)));
        self::assertSame('parameter:?', $keys->terminal(new Token(1, 'PARAM_MARKER', '?', 0)));
        self::assertSame('dollar:$$', $keys->terminal(new Token(1, 'DOLLAR_QUOTED_STRING_SYM', '$$', 0)));
        self::assertSame('SELECT_SYM', $keys->terminal(new Token(1, 'SELECT_SYM', 'select', 0)));
        self::assertSame(',', $keys->terminal(new Token(1, ',', ',', 0)));
    }

    public function testNoiseMergesEveryFamilyTableKeyedBySignature(): void
    {
        self::assertSame([1], LeafKeys::noise()['sql_statement: simple_statement_or_begin ; opt_end_of_input']);
        self::assertSame([0], LeafKeys::noise()['opt_as: AS']);
        self::assertSame([1], LeafKeys::noise()['real_type: DOUBLE_SYM PRECISION']);
        self::assertSame([1, 2], LeafKeys::noise()['nvarchar: NATIONAL_SYM CHAR_SYM VARYING']);
        self::assertArrayNotHasKey('select_alias: AS ident', LeafKeys::noise());
        self::assertSame([], array_filter(array_keys(LeafKeys::noise()), static fn (string $signature): bool => !str_contains($signature, ': ')));
        self::assertCount(
            count(DispatchNoise::positions()) + count(LeafNoise::positions()) + count(TypeNoise::positions()) + count(ExpressionNoise::positions())
            + count(CallNoise::positions()) + count(QueryNoise::positions()) + count(DmlNoise::positions()) + count(TableDefinitionNoise::positions())
            + count(TableChangeNoise::positions()) + count(RoutineNoise::positions()) + count(AccountNoise::positions()) + count(ReplicationNoise::positions())
            + count(ServerNoise::positions()) + count(UtilityNoise::positions()),
            LeafKeys::noise(),
        );
        self::assertSame(LeafKeys::noise(), LeafKeys::noise());
    }

    public function testSynonymsMergesEveryFamilyTableKeyedBySignature(): void
    {
        self::assertSame([0 => 'CHARSET'], LeafKeys::synonyms()['charset: CHAR_SYM SET']);
        self::assertSame([0 => 'INDEX_SYM'], LeafKeys::synonyms()['key_or_index: KEY_SYM']);
        self::assertSame([0 => 'name:binary'], LeafKeys::synonyms()['charset_name: BINARY']);
        self::assertSame([0 => 'NCHAR_SYM'], LeafKeys::synonyms()['nchar: NATIONAL_SYM CHAR_SYM']);
        self::assertSame([0 => 'BOOL_SYM'], LeafKeys::synonyms()['type: BOOLEAN_SYM']);
        self::assertSame([], array_filter(array_keys(LeafKeys::synonyms()), static fn (string $signature): bool => !str_contains($signature, ': ')));
        self::assertCount(
            count(DispatchNoise::synonyms()) + count(LeafNoise::synonyms()) + count(TypeNoise::synonyms()) + count(ExpressionNoise::synonyms())
            + count(CallNoise::synonyms()) + count(QueryNoise::synonyms()) + count(DmlNoise::synonyms()) + count(TableDefinitionNoise::synonyms())
            + count(TableChangeNoise::synonyms()) + count(RoutineNoise::synonyms()) + count(AccountNoise::synonyms()) + count(ReplicationNoise::synonyms())
            + count(ServerNoise::synonyms()) + count(UtilityNoise::synonyms()),
            LeafKeys::synonyms(),
        );
        self::assertSame(LeafKeys::synonyms(), LeafKeys::synonyms());
    }
}
