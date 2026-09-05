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
    private const RULE_WITNESSES = [
        1 => 'postgresql.lookahead.FORMAT_LA',
        2 => 'postgresql.family.@TRIVIA.3',
        3 => 'postgresql.family.@TRIVIA.3',
        4 => 'postgresql.family.@TRIVIA.3',
        5 => 'postgresql.family.@TRIVIA.3',
        6 => 'postgresql.family.@TRIVIA.4',
        7 => 'postgresql.family.@TRIVIA.4',
        8 => 'postgresql.family.BCONST.0',
        9 => 'postgresql.family.XCONST.0',
        10 => 'postgresql.family.BCONST.0',
        11 => 'postgresql.family.XCONST.0',
        12 => 'postgresql.coverage.national-string',
        13 => 'postgresql.family.SCONST.0',
        14 => 'postgresql.family.SCONST.4',
        15 => 'postgresql.family.SCONST.9',
        16 => 'postgresql.family.SCONST.0',
        17 => 'postgresql.family.SCONST.2',
        18 => 'postgresql.family.SCONST.3',
        19 => 'postgresql.coverage.quote-stop-other',
        20 => 'postgresql.family.SCONST.1',
        21 => 'postgresql.family.SCONST.0',
        22 => 'postgresql.family.SCONST.4',
        23 => 'postgresql.family.SCONST.5',
        24 => 'postgresql.family.SCONST.6',
        28 => 'postgresql.family.SCONST.4',
        29 => 'postgresql.family.SCONST.7',
        30 => 'postgresql.family.SCONST.8',
        32 => 'postgresql.family.SCONST.10',
        33 => 'postgresql.coverage.dollar-prefix-fallback',
        34 => 'postgresql.family.SCONST.10',
        35 => 'postgresql.family.SCONST.10',
        36 => 'postgresql.coverage.dollar-failed-inside',
        37 => 'postgresql.coverage.dollar-character-inside',
        38 => 'postgresql.family.IDENT.1',
        39 => 'postgresql.family.IDENT.2',
        40 => 'postgresql.family.IDENT.1',
        41 => 'postgresql.family.IDENT.2',
        42 => 'postgresql.family.IDENT.3',
        43 => 'postgresql.family.IDENT.1',
        44 => 'postgresql.coverage.unicode-prefix-fallback',
        45 => 'postgresql.family.TYPECAST.0',
        46 => 'postgresql.family.DOT_DOT.0',
        47 => 'postgresql.family.COLON_EQUALS.0',
        48 => 'postgresql.family.EQUALS_GREATER.0',
        49 => 'postgresql.family.LESS_EQUALS.0',
        50 => 'postgresql.family.GREATER_EQUALS.0',
        51 => 'postgresql.family.NOT_EQUALS.0',
        52 => 'postgresql.family.NOT_EQUALS.1',
        53 => 'postgresql.family.%.0',
        54 => 'postgresql.family.Op.0',
        55 => 'postgresql.family.PARAM.0',
        57 => 'postgresql.family.ICONST.0',
        58 => 'postgresql.family.ICONST.1',
        59 => 'postgresql.family.ICONST.2',
        60 => 'postgresql.family.ICONST.3',
        64 => 'postgresql.family.FCONST.0',
        65 => 'postgresql.coverage.numeric-range',
        66 => 'postgresql.family.FCONST.2',
        71 => 'postgresql.keyword.ABORT_P.0',
        72 => 'postgresql.coverage.other-character',
    ];

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

    /**
     * Maps PostgreSQL 17 scanner rules to successful lexical witnesses.
     *
     * @return array<int, string>
     */
    public function ruleWitnesses(): array
    {
        return self::RULE_WITNESSES;
    }
}
