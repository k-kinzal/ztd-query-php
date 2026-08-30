<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Lexical;

use RuntimeException;

/**
 * Writes the witnesses a PostgreSQL lexical catalogue is checked against.
 *
 * A witness is a scrap of SQL together with the tokens the server's own lexer
 * answers for it, so it is both an example of a terminal and a proof that the
 * model of the lexer reaches the branch that produces it. They come from
 * several places — the keyword list, the frontend's lookahead rewrites, the
 * lexeme families, samples written for branches nothing else reaches, and the
 * parser's alternative entry modes — and each of those is asked for separately.
 */
final class PgCatalogWitnesses
{
    /**
     * The parser's alternative entry modes, which no ordinary statement reaches.
     */
    public const PARSER_MODES = [
        'MODE_TYPE_NAME',
        'MODE_PLPGSQL_EXPR',
        'MODE_PLPGSQL_ASSIGN1',
        'MODE_PLPGSQL_ASSIGN2',
        'MODE_PLPGSQL_ASSIGN3',
    ];

    /**
     * Maps the PostgreSQL 17 scanner rule inventory to a successful witness.
     * Rules which can only report a lexical error are classified separately.
     */
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
     * Answers every witness the catalogue holds, by the terminal it stands for.
     *
     * @param array<string, list<string>> $keywords Lexemes each keyword terminal is written as
     * @param array<string, array{token: string, followed_by: list<string>}> $lookahead Rewrites the frontend makes by looking ahead
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> Every witness, by terminal
     */
    public function forProfile(array $keywords, array $lookahead): array
    {
        $terminals = [];
        foreach ([
            $this->fromKeywords($keywords),
            $this->fromLookahead($lookahead, $keywords),
            $this->fromSamples(),
            $this->coverageSamples(),
            $this->parserModes(),
        ] as $group) {
            foreach ($group as $terminal => $witnesses) {
                foreach ($witnesses as $witness) {
                    $terminals[$terminal][] = $witness;
                }
            }
        }

        return $terminals;
    }

    /**
     * Answers a witness for every lexeme a keyword terminal is written as.
     *
     * @param array<string, list<string>> $keywords Lexemes each keyword terminal is written as
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The keyword witnesses, by terminal
     */
    public function fromKeywords(array $keywords): array
    {
        $terminals = [];
        foreach ($keywords as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "postgresql.keyword.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                );
            }
        }

        return $terminals;
    }

    /**
     * Answers a witness for each token the frontend rewrites by looking ahead.
     *
     * Such a token is never written down: it is what the frontend answers when
     * one keyword is followed by another, so the witness carries the pair as
     * its context and the first keyword alone as its text.
     *
     * @param array<string, array{token: string, followed_by: list<string>}> $lookahead Rewrites the frontend makes by looking ahead
     * @param array<string, list<string>> $keywords Lexemes each keyword terminal is written as
     *
     * @return array<string, list<array{id: string, sql: string, context_sql: string, tokens: list<string>, units: list<string>}>> The lookahead witnesses, by terminal
     */
    public function fromLookahead(array $lookahead, array $keywords): array
    {
        $terminals = [];
        foreach ($lookahead as $baseTerminal => $rule) {
            $followingTerminal = $rule['followed_by'][0];
            $baseLexeme = $keywords[$baseTerminal][0];
            $followingLexeme = $keywords[$followingTerminal][0];
            $terminals[$rule['token']][] = [
                'id' => "postgresql.lookahead.{$rule['token']}",
                'sql' => $baseLexeme,
                'context_sql' => $baseLexeme . ' ' . $followingLexeme,
                'tokens' => [$rule['token']],
                'units' => [],
            ];
        }

        return $terminals;
    }

    /**
     * Answers a witness for every lexeme family and every punctuation mark.
     *
     * Trivia is expected to produce no token at all, which is what an empty
     * token list says here.
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The family witnesses, by terminal
     */
    public function fromSamples(): array
    {
        $samples = (new PgLexicalSamples())->all();
        foreach (str_split('%()*+,-./:;<=>[]^') as $punctuation) {
            $samples[$punctuation] = [$punctuation];
        }

        $terminals = [];
        foreach ($samples as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "postgresql.family.{$terminal}.{$index}",
                    $lexeme,
                    $terminal === '@TRIVIA' ? [] : [$terminal],
                );
            }
        }

        return $terminals;
    }

    /**
     * Answers the witnesses written for scanner branches nothing else reaches.
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The coverage witnesses, under one terminal
     */
    public function coverageSamples(): array
    {
        $samples = [
            'national-string' => ["N'text'", ['NCHAR', 'SCONST']],
            'dollar-prefix-fallback' => ['$tag', ['$', 'IDENT']],
            'quote-stop-other' => ["'text'x", ['SCONST', 'IDENT']],
            'dollar-delimiter-mismatch' => ['$tag$a$other$b$tag$', ['SCONST']],
            'dollar-failed-inside' => ['$tag$a$other b$tag$', ['SCONST']],
            'dollar-character-inside' => ['$tag$a$1b$tag$', ['SCONST']],
            'unicode-prefix-fallback' => ['U&x', ['IDENT', 'Op', 'IDENT']],
            'numeric-range' => ['1..2', ['ICONST', 'DOT_DOT', 'ICONST']],
            'other-character' => ['{', ['{']],
        ];

        $terminals = [];
        foreach ($samples as $name => [$sql, $expected]) {
            $terminals['@COVERAGE'][] = $this->witness('postgresql.coverage.' . $name, $sql, $expected);
        }

        return $terminals;
    }

    /**
     * Answers a witness for each alternative mode the parser can be entered in.
     *
     * A mode is entered rather than written, so its witness carries no text and
     * stands only for the unit it reaches.
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The mode witnesses, by terminal
     */
    public function parserModes(): array
    {
        $terminals = [];
        foreach (self::PARSER_MODES as $mode) {
            $terminals[$mode][] = [
                'id' => 'postgresql.mode.' . $mode,
                'sql' => '',
                'tokens' => [],
                'units' => ['parser-mode:' . $mode],
            ];
        }

        return $terminals;
    }

    /**
     * Answers which witness proves each scanner rule is reachable.
     *
     * @return array<int, string> Rule number => the id of the witness that reaches it
     */
    public function ruleWitnesses(): array
    {
        return self::RULE_WITNESSES;
    }

    /**
     * Writes one witness.
     *
     * @param string $id Name the catalogue knows this witness by
     * @param string $sql The scrap of SQL the witness is
     * @param list<string> $expectedTokens Tokens the server's lexer answers for it
     *
     * @return array{id: string, sql: string, tokens: list<string>, units: list<string>} The witness
     */
    public function witness(string $id, string $sql, array $expectedTokens): array
    {
        return [
            'id' => $id,
            'sql' => $sql,
            'tokens' => $expectedTokens,
            'units' => [],
        ];
    }

    /**
     * Records that one witness reaches a unit.
     *
     * @param array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> $terminals Every witness, by terminal
     * @param string $witnessId Witness the unit is attached to
     * @param string $unit Unit that witness reaches
     *
     * @throws RuntimeException When the witness the unit names is missing
     */
    public function attachUnit(array &$terminals, string $witnessId, string $unit): void
    {
        foreach ($terminals as &$witnesses) {
            foreach ($witnesses as &$witness) {
                if ($witness['id'] === $witnessId) {
                    $witness['units'][] = $unit;

                    return;
                }
            }
            unset($witness);
        }
        unset($witnesses);

        throw new RuntimeException("PostgreSQL scanner rule references an unknown witness: {$witnessId}");
    }
}
