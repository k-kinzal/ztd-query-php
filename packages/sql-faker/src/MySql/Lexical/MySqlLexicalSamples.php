<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Lexical;

/**
 * The SQL that shows each MySQL terminal being lexed.
 *
 * A compiled profile is only as good as the examples it was built from: each
 * one is a piece of SQL, the tokens MySQL reads it as, and the lexer states
 * reading it went through. They are written out rather than derived, because
 * the whole point of them is to be independent of the code that consumes
 * them.
 */
final class MySqlLexicalSamples
{
    /**
     * Answers every sample, keyed by the terminal it stands for.
     *
     * @return array<string, list<array{string, list<string>, list<string>, 3?: string}>> Terminal => the samples that realize it
     */
    public function all(): array
    {
        return [
                '@TRIVIA' => [
                    [' ', [], ['MY_LEX_START', 'MY_LEX_SKIP']],
                    [" -- comment\n ", [], ['MY_LEX_START', 'MY_LEX_COMMENT']],
                    [" # comment\n ", [], ['MY_LEX_START', 'MY_LEX_COMMENT']],
                    [' /* comment */ ', [], ['MY_LEX_START', 'MY_LEX_LONG_COMMENT']],
                ],
                'IDENT' => [['_sqlfaker_identifier', ['IDENT'], ['MY_LEX_START', 'MY_LEX_IDENT']]],
                'IDENT_QUOTED' => [['`select`', ['IDENT_QUOTED'], ['MY_LEX_START', 'MY_LEX_USER_VARIABLE_DELIMITER']]],
                'TEXT_STRING' => [
                    ["'text'", ['TEXT_STRING'], ['MY_LEX_START', 'MY_LEX_STRING']],
                    ["'a''b'", ['TEXT_STRING'], ['MY_LEX_START', 'MY_LEX_STRING']],
                ],
                'NCHAR_STRING' => [["N'text'", ['NCHAR_STRING'], ['MY_LEX_START', 'MY_LEX_IDENT_OR_NCHAR']]],
                'NUM' => [['1', ['NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT', 'MY_LEX_INT_OR_REAL']]],
                'LONG_NUM' => [['2147483648', ['LONG_NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT', 'MY_LEX_INT_OR_REAL']]],
                'ULONGLONG_NUM' => [['18446744073709551615', ['ULONGLONG_NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT', 'MY_LEX_INT_OR_REAL']]],
                'DECIMAL_NUM' => [['1.5', ['DECIMAL_NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT', 'MY_LEX_REAL']]],
                'FLOAT_NUM' => [['1e2', ['FLOAT_NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT', 'MY_LEX_INT_OR_REAL']]],
                'HEX_NUM' => [
                    ['0x0f', ['HEX_NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT']],
                    ["X'0f'", ['HEX_NUM'], ['MY_LEX_START', 'MY_LEX_IDENT_OR_HEX', 'MY_LEX_HEX_NUMBER']],
                ],
                'BIN_NUM' => [
                    ['0b01', ['BIN_NUM'], ['MY_LEX_START', 'MY_LEX_NUMBER_IDENT']],
                    ["B'01'", ['BIN_NUM'], ['MY_LEX_START', 'MY_LEX_IDENT_OR_BIN', 'MY_LEX_BIN_NUMBER']],
                ],
                'LEX_HOSTNAME' => [['localhost', ['LEX_HOSTNAME'], ['MY_LEX_HOSTNAME'], '@localhost']],
                'PARAM_MARKER' => [['?', ['PARAM_MARKER'], ['MY_LEX_START', 'MY_LEX_CHAR']]],
                'OR2_SYM' => [['||', ['OR2_SYM'], ['MY_LEX_START', 'MY_LEX_BOOL']]],
                'WITH_ROLLUP_SYM' => [['WITH ROLLUP', ['WITH_ROLLUP_SYM'], ['MY_LEX_START', 'MY_LEX_IDENT']]],
                'UNDERSCORE_CHARSET' => [['_utf8mb4', ['UNDERSCORE_CHARSET'], ['MY_LEX_START', 'MY_LEX_IDENT']]],
                'SET_VAR' => [[':=', ['SET_VAR'], ['MY_LEX_START', 'MY_LEX_SET_VAR']]],
                'JSON_SEPARATOR_SYM' => [['->', ['JSON_SEPARATOR_SYM'], ['MY_LEX_START', 'MY_LEX_CHAR']]],
                'JSON_UNQUOTED_SEPARATOR_SYM' => [['->>', ['JSON_UNQUOTED_SEPARATOR_SYM'], ['MY_LEX_START', 'MY_LEX_CHAR']]],
        ];
    }
}
