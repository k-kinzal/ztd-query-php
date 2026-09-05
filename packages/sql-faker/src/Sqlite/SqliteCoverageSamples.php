<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

/**
 * The SQL that shows each of SQLite's character classes being lexed.
 *
 * SQLite decides what a character can start by looking it up in a table of
 * character classes, so a generator that never emits a character of some class
 * has never exercised that part of the lexer. Each sample here is one piece of
 * SQL, the tokens SQLite was observed to read it as, and the classes reading it
 * went through; together they are the evidence that the compiled profile covers
 * the tokenizer rather than only the parts of it a grammar happens to reach.
 *
 * They are written out rather than derived because the whole point of them is
 * to be independent of the code that consumes them.
 *
 * @phpstan-type CoverageSample array{string, list<string>, list<string>}
 *
 * @visibility root
 */
final class SqliteCoverageSamples
{
    /**
     * Answers every sample, keyed by the identifier its witness is filed under.
     *
     * @return array<string, CoverageSample> Sample identifier => its SQL, tokens and classes
     */
    public function all(): array
    {
        return [
            'cc-x' => ["X'00'", ['TK_BLOB'], ['CC_X']],
            'cc-keyword-start' => ['SELECT', ['TK_SELECT'], ['CC_KYWD0']],
            'cc-keyword' => ['z', ['TK_ID'], ['CC_KYWD']],
            'cc-digit' => ['1.5', ['TK_FLOAT'], ['CC_DIGIT']],
            'cc-dollar' => ['$name', ['TK_VARIABLE'], ['CC_DOLLAR']],
            'cc-variable-alpha' => [':name', ['TK_VARIABLE'], ['CC_VARALPHA']],
            'cc-variable-number' => ['?1', ['TK_VARIABLE'], ['CC_VARNUM']],
            'cc-space' => [" \t\n", ['TK_SPACE'], ['CC_SPACE']],
            'cc-quote' => ["'text' \"name\" `name`", ['TK_STRING', 'TK_SPACE', 'TK_ID', 'TK_SPACE', 'TK_ID'], ['CC_QUOTE', 'CC_SPACE']],
            'cc-bracket' => ['[name]', ['TK_ID'], ['CC_QUOTE2']],
            'cc-pipe' => ['| ||', ['TK_BITOR', 'TK_SPACE', 'TK_CONCAT'], ['CC_PIPE', 'CC_SPACE']],
            'cc-minus' => ["- -- comment\n", ['TK_MINUS', 'TK_SPACE', 'TK_SPACE', 'TK_SPACE'], ['CC_MINUS', 'CC_SPACE']],
            'cc-less' => ['< <= <> <<', ['TK_LT', 'TK_SPACE', 'TK_LE', 'TK_SPACE', 'TK_NE', 'TK_SPACE', 'TK_LSHIFT'], ['CC_LT', 'CC_SPACE']],
            'cc-greater' => ['> >= >>', ['TK_GT', 'TK_SPACE', 'TK_GE', 'TK_SPACE', 'TK_RSHIFT'], ['CC_GT', 'CC_SPACE']],
            'cc-equal' => ['= ==', ['TK_EQ', 'TK_SPACE', 'TK_EQ'], ['CC_EQ', 'CC_SPACE']],
            'cc-bang' => ['!=', ['TK_NE'], ['CC_BANG']],
            'cc-slash' => ['/ /* comment */', ['TK_SLASH', 'TK_SPACE', 'TK_SPACE'], ['CC_SLASH', 'CC_SPACE']],
            'cc-left-parenthesis' => ['(', ['TK_LP'], ['CC_LP']],
            'cc-right-parenthesis' => [')', ['TK_RP'], ['CC_RP']],
            'cc-semicolon' => [';', ['TK_SEMI'], ['CC_SEMI']],
            'cc-plus' => ['+', ['TK_PLUS'], ['CC_PLUS']],
            'cc-star' => ['*', ['TK_STAR'], ['CC_STAR']],
            'cc-percent' => ['%', ['TK_REM'], ['CC_PERCENT']],
            'cc-comma' => [',', ['TK_COMMA'], ['CC_COMMA']],
            'cc-and' => ['&', ['TK_BITAND'], ['CC_AND']],
            'cc-tilde' => ['~', ['TK_BITNOT'], ['CC_TILDA']],
            'cc-dot' => ['. .5', ['TK_DOT', 'TK_SPACE', 'TK_FLOAT'], ['CC_DOT', 'CC_SPACE']],
            'cc-id' => ['é', ['TK_ID'], ['CC_ID']],
            'cc-illegal' => ["\x01", ['TK_ILLEGAL'], ['CC_ILLEGAL']],
            'cc-bom' => ["\xef\xbb\xbf", ['TK_SPACE'], ['CC_BOM']],
        ];
    }

    /**
     * Answers the character classes no sample can show, and why.
     *
     * @return array<string, string> Class name => why no SQL can exercise it
     */
    public function unreachable(): array
    {
        return [
            'CC_NUL' => 'NUL terminates SQLite input and cannot be emitted inside a generated SQL statement.',
        ];
    }
}
