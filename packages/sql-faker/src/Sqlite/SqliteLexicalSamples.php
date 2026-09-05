<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

/**
 * The SQL that shows each SQLite terminal being lexed.
 *
 * A compiled profile is only as good as the examples it was built from: each
 * one is a piece of SQL, the tokens SQLite reads it as, and the character
 * classes reading it went through. They are written out rather than derived,
 * because the whole point of them is to be independent of the code that
 * consumes them.
 *
 * @visibility root
 */
final class SqliteLexicalSamples
{
    /**
     * Answers every sample, keyed by the terminal it stands for.
     *
     * @return array<string, list<array{string, list<string>, list<string>}>> Terminal => the samples that realize it
     */
    public function all(): array
    {
        return [
                '@TRIVIA' => [
                    [' ', ['TK_SPACE'], ['CC_SPACE']],
                    ['/* comment */', ['TK_SPACE'], ['CC_SLASH']],
                    ["-- comment\n", ['TK_SPACE', 'TK_SPACE'], ['CC_MINUS', 'CC_SPACE']],
                ],
                'ID' => $this->identifierSamples(),
                'id' => $this->identifierSamples(),
                'idj' => $this->identifierSamples(),
                'ids' => [["'text'", ['TK_STRING'], ['CC_QUOTE']], ["'a''b'", ['TK_STRING'], ['CC_QUOTE']]],
                'STRING' => [["'text'", ['TK_STRING'], ['CC_QUOTE']], ["'/* not a comment */'", ['TK_STRING'], ['CC_QUOTE']], ["'a''b'", ['TK_STRING'], ['CC_QUOTE']]],
                'BLOB' => [["X'00ff'", ['TK_BLOB'], ['CC_X']]],
                'number' => [['1', ['TK_INTEGER'], ['CC_DIGIT']], ['1.5', ['TK_FLOAT'], ['CC_DIGIT']], ['.5', ['TK_FLOAT'], ['CC_DOT']], ['1e2', ['TK_FLOAT'], ['CC_DIGIT']]],
                'INTEGER' => [['1', ['TK_INTEGER'], ['CC_DIGIT']], [sprintf('0x%s', '10'), ['TK_INTEGER'], ['CC_DIGIT']]],
                'QNUMBER' => [['1_0', ['TK_QNUMBER'], ['CC_DIGIT']]],
                'VARIABLE' => [['?', ['TK_VARIABLE'], ['CC_VARNUM']], ['?1', ['TK_VARIABLE'], ['CC_VARNUM']], [':name', ['TK_VARIABLE'], ['CC_VARALPHA']], ['@name', ['TK_VARIABLE'], ['CC_VARALPHA']], ['$name', ['TK_VARIABLE'], ['CC_DOLLAR']]],
                'ANY' => [['name', ['TK_ID'], ['CC_KYWD0']]],
                'LP' => [['(', ['TK_LP'], ['CC_LP']]],
                'RP' => [[')', ['TK_RP'], ['CC_RP']]],
                'SEMI' => [[';', ['TK_SEMI'], ['CC_SEMI']]],
                'COMMA' => [[',', ['TK_COMMA'], ['CC_COMMA']]],
                'DOT' => [['.', ['TK_DOT'], ['CC_DOT']]],
                'EQ' => [['=', ['TK_EQ'], ['CC_EQ']], ['==', ['TK_EQ'], ['CC_EQ']]],
                'LT' => [['<', ['TK_LT'], ['CC_LT']]],
                'LE' => [['<=', ['TK_LE'], ['CC_LT']]],
                'GT' => [['>', ['TK_GT'], ['CC_GT']]],
                'GE' => [['>=', ['TK_GE'], ['CC_GT']]],
                'NE' => [['<>', ['TK_NE'], ['CC_LT']], ['!=', ['TK_NE'], ['CC_BANG']]],
                'PLUS' => [['+', ['TK_PLUS'], ['CC_PLUS']]],
                'MINUS' => [['-', ['TK_MINUS'], ['CC_MINUS']]],
                'STAR' => [['*', ['TK_STAR'], ['CC_STAR']]],
                'SLASH' => [['/', ['TK_SLASH'], ['CC_SLASH']]],
                'REM' => [['%', ['TK_REM'], ['CC_PERCENT']]],
                'BITAND' => [['&', ['TK_BITAND'], ['CC_AND']]],
                'BITOR' => [['|', ['TK_BITOR'], ['CC_PIPE']]],
                'BITNOT' => [['~', ['TK_BITNOT'], ['CC_TILDA']]],
                'LSHIFT' => [['<<', ['TK_LSHIFT'], ['CC_LT']]],
                'RSHIFT' => [['>>', ['TK_RSHIFT'], ['CC_GT']]],
                'CONCAT' => [['||', ['TK_CONCAT'], ['CC_PIPE']]],
                'PTR' => [['->', ['TK_PTR'], ['CC_MINUS']], ['->>', ['TK_PTR'], ['CC_MINUS']]],
                'FLOAT' => [['1.5', ['TK_FLOAT'], ['CC_DIGIT']], ['.5', ['TK_FLOAT'], ['CC_DOT']]],
        ];
    }

    /**
     * Answers the ways an identifier can be written.
     *
     * SQLite accepts three quoting styles as well as a bare name, and a
     * generator that only ever wrote one of them would leave the other three
     * unexercised.
     *
     * @return list<array{string, list<string>, list<string>}> The samples
     */
    public function identifierSamples(): array
    {
        return [
            ['name', ['TK_ID'], ['CC_KYWD0']],
            ['"select"', ['TK_ID'], ['CC_QUOTE']],
            ['`select`', ['TK_ID'], ['CC_QUOTE']],
            ['[select]', ['TK_ID'], ['CC_QUOTE2']],
        ];
    }
}
