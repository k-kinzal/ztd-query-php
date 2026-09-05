<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

/**
 * The SQL that shows each PostgreSQL terminal being lexed.
 *
 * A compiled profile is only as good as the examples it was built from. They
 * are written out rather than derived, because the whole point of them is to
 * be independent of the code that consumes them.
 *
 * @visibility root
 */
final class PgLexicalSamples
{
    /**
     * Answers every sample, keyed by the terminal it stands for.
     *
     * @return array<string, non-empty-list<string>> Terminal => the text that realizes it
     */
    public function all(): array
    {
        return [
                '@TRIVIA' => [' ', "\t\n", "-- comment\n", '/* outer /* inner */ outer */', '/* /+ ** */'],
                'IDENT' => ['_sqlfaker_identifier', '"select"', 'U&"select"', '"a""b"'],
                'SCONST' => [
                    "'text'",
                    "'a''b'",
                    "'first'\n'second'",
                    "'text' ",
                    "E'a\\\\b'",
                    "E'\\u0041'",
                    "E'\\uD800\\uDC00'",
                    "E'\\101'",
                    "E'\\x41'",
                    "U&'text'",
                    '$$text$$',
                    '$tag$text$tag$',
                ],
                'ICONST' => ['1', sprintf('0x%s', '10'), '0o10', '0b10'],
                'FCONST' => ['1.5', '.5', '1e2'],
                'BCONST' => ["B'01'"],
                'XCONST' => ["X'0f'"],
                'Op' => ['?', '?|', '?&', '@@'],
                'PARAM' => ['$1'],
                'TYPECAST' => ['::'],
                'COLON_EQUALS' => [':='],
                'EQUALS_GREATER' => ['=>'],
                'NOT_EQUALS' => ['<>', '!='],
                'LESS_EQUALS' => ['<='],
                'GREATER_EQUALS' => ['>='],
                'DOT_DOT' => ['..'],
        ];
    }
}
