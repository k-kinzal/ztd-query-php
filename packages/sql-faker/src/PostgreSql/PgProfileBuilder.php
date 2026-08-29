<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;
use SqlFaker\Grammar\Source\UpstreamLexerSource;
use SqlFaker\PostgreSql\LexicalProfileCompiler as PostgreSqlCompiler;
use SqlFaker\PostgreSql\LexicalSourceParser as PostgreSqlSourceParser;

/**
 * Builds a PostgreSQL lexical profile from the server's own lexer source.
 *
 * PostgreSQL splits what one release calls a keyword across a keyword list and
 * a scanner, and its parser frontend rewrites some tokens by looking ahead, so
 * the profile records both and is bound to one exact version.
 */
final class PgProfileBuilder
{
    /** @readonly */
    private LexerSource $source;

    /**
     * @param LexerSource|null $source Reads the upstream lexer files
     */
    public function __construct(?LexerSource $source = null)
    {
        $this->source = $source ?? new UpstreamLexerSource();
    }

    /**
     * Reports the upstream files a release keeps its lexer in.
     *
     * PostgreSQL tags its releases with underscores rather than dots, so the
     * version this package uses has to be spelled back into a tag name.
     *
     * @param string $version Release tag, e.g. "pg-17.2"
     *
     * @return array{keywords: string, scanner: string, parser: string} URLs of the three files
     */
    public function sourceUrls(string $version): array
    {
        $release = strtoupper(str_replace(['pg-', '.'], ['REL_', '_'], $version));
        $base = "https://raw.githubusercontent.com/postgres/postgres/refs/tags/{$release}";

        return [
            'keywords' => $base . '/src/include/parser/kwlist.h',
            'scanner' => $base . '/src/backend/parser/scan.l',
            'parser' => $base . '/src/backend/parser/parser.c',
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the upstream source does not describe the lexer
     */
    public function build(string $version): array
    {
        ['keywords' => $keywordUrl, 'scanner' => $scannerUrl, 'parser' => $parserUrl]
            = $this->sourceUrls($version);
        $keywords = $this->source->fetch($keywordUrl);
        $scanner = $this->source->fetch($scannerUrl);
        $parser = $this->source->fetch($parserUrl);

        $profile = [
            'dialect' => 'postgresql',
            'version' => $version,
            'sources' => [
                $keywordUrl => hash('sha256', $keywords),
                $scannerUrl => hash('sha256', $scanner),
                $parserUrl => hash('sha256', $parser),
            ],
            'keywords' => (new PostgreSqlCompiler())->compile($keywords),
            'lookahead' => [
                'FORMAT' => ['token' => 'FORMAT_LA', 'followed_by' => ['JSON']],
                'NOT' => ['token' => 'NOT_LA', 'followed_by' => ['BETWEEN', 'IN_P', 'LIKE', 'ILIKE', 'SIMILAR']],
                'NULLS_P' => ['token' => 'NULLS_LA', 'followed_by' => ['FIRST_P', 'LAST_P']],
                'WITH' => ['token' => 'WITH_LA', 'followed_by' => ['TIME', 'ORDINALITY']],
                'WITHOUT' => ['token' => 'WITHOUT_LA', 'followed_by' => ['TIME']],
            ],
        ];

        $source = (new PostgreSqlSourceParser())->parse($scanner, $parser);
        $lookaheadTokens = array_values(array_map(
            static fn (array $rule): string => $rule['token'],
            $profile['lookahead'],
        ));
        sort($lookaheadTokens);
        if ($lookaheadTokens !== $source['lookahead_tokens']) {
            throw new RuntimeException('PostgreSQL lookahead profile does not match parser.c.');
        }
        $profile['catalog'] = $this->catalog($profile, $source);

        return $profile;
    }

    /**
     * @param array<string, mixed> $profile
     * @param array{states: list<string>, rules: list<string>, lookahead_tokens: list<string>} $source
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the upstream source does not describe the lexer
     */
    public function catalog(array $profile, array $source): array
    {
        /** @var array<string, list<string>> $keywords */
        $keywords = $profile['keywords'];
        /** @var array<string, array{token: string, followed_by: list<string>}> $lookahead */
        $lookahead = $profile['lookahead'];
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

        foreach ($lookahead as $baseTerminal => $rule) {
            $followingTerminal = $rule['followed_by'][0];
            $baseLexeme = $keywords[$baseTerminal][0];
            $followingLexeme = $keywords[$followingTerminal][0];
            $contextSql = $baseLexeme . ' ' . $followingLexeme;
            $terminals[$rule['token']][] = [
                'id' => "postgresql.lookahead.{$rule['token']}",
                'sql' => $baseLexeme,
                'context_sql' => $contextSql,
                'tokens' => [$rule['token']],
                'units' => [],
            ];
        }

        $samples = (new PgLexicalSamples())->all();
        foreach (str_split('%()*+,-./:;<=>[]^') as $punctuation) {
            $samples[$punctuation] = [$punctuation];
        }
        foreach ($samples as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $expected = $terminal === '@TRIVIA' ? [] : [$terminal];
                $terminals[$terminal][] = $this->witness(
                    "postgresql.family.{$terminal}.{$index}",
                    $lexeme,
                    $expected,
                );
            }
        }

        $coverageSamples = [
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
        foreach ($coverageSamples as $name => [$sql, $expected]) {
            $terminals['@COVERAGE'][] = $this->witness(
                'postgresql.coverage.' . $name,
                $sql,
                $expected,
            );
        }

        foreach ([
            'MODE_TYPE_NAME',
            'MODE_PLPGSQL_EXPR',
            'MODE_PLPGSQL_ASSIGN1',
            'MODE_PLPGSQL_ASSIGN2',
            'MODE_PLPGSQL_ASSIGN3',
        ] as $mode) {
            $unit = 'parser-mode:' . $mode;
            $terminals[$mode][] = [
                'id' => 'postgresql.mode.' . $mode,
                'sql' => '',
                'tokens' => [],
                'units' => [$unit],
            ];
        }

        $ruleCount = count($source['rules']) + 1;
        $coverageUnits = [];
        for ($rule = 1; $rule <= $ruleCount; $rule++) {
            $coverageUnits[] = 'rule:' . $rule;
        }
        foreach ([
            'MODE_TYPE_NAME',
            'MODE_PLPGSQL_EXPR',
            'MODE_PLPGSQL_ASSIGN1',
            'MODE_PLPGSQL_ASSIGN2',
            'MODE_PLPGSQL_ASSIGN3',
        ] as $mode) {
            $coverageUnits[] = 'parser-mode:' . $mode;
        }

        $ruleWitnesses = $this->ruleWitnesses();
        if ($ruleWitnesses === [] || max(array_keys($ruleWitnesses)) >= $ruleCount) {
            throw new RuntimeException('PostgreSQL source rule mapping exceeds the parsed rule inventory.');
        }
        foreach ($ruleWitnesses as $rule => $witnessId) {
            $this->attachUnit($terminals, $witnessId, 'rule:' . $rule);
        }

        $coverageWitnessed = [];
        foreach ($terminals as $witnesses) {
            foreach ($witnesses as $witness) {
                foreach ($witness['units'] as $unit) {
                    $coverageWitnessed[$unit] ??= $witness['id'];
                }
            }
        }
        $coverageExcluded = [
            'rule:25' => 'Malformed Unicode surrogate continuation is an error-only scanner branch.',
            'rule:26' => 'Missing Unicode surrogate continuation is an error-only scanner branch.',
            'rule:27' => 'Malformed Unicode escape is an error-only scanner branch.',
            'rule:31' => 'A backslash immediately before EOF belongs to an unterminated string error path.',
            'rule:56' => 'Trailing junk after a positional parameter is an error-only scanner branch.',
            'rule:61' => 'Invalid hexadecimal integer prefix is an error-only scanner branch.',
            'rule:62' => 'Invalid octal integer prefix is an error-only scanner branch.',
            'rule:63' => 'Invalid binary integer prefix is an error-only scanner branch.',
            'rule:67' => 'Incomplete exponent is an error-only scanner branch.',
            'rule:68' => 'Trailing identifier junk after an integer is an error-only scanner branch.',
            'rule:69' => 'Trailing identifier junk after a numeric is an error-only scanner branch.',
            'rule:70' => 'Trailing identifier junk after a real is an error-only scanner branch.',
            'rule:' . $ruleCount => 'Flex default jam rule is not a PostgreSQL lexical language branch.',
        ];
        $missing = array_values(array_diff(
            $coverageUnits,
            array_keys($coverageWitnessed),
            array_keys($coverageExcluded),
        ));
        if ($missing !== []) {
            throw new RuntimeException('PostgreSQL source model misses scanner rules: ' . implode(', ', $missing));
        }
        ksort($terminals);
        ksort($coverageWitnessed);

        return [
            'source' => [
                'engine' => 'postgresql',
                'entrypoint' => 'scan.l/base_yylex',
                'states' => $source['states'],
                'rules' => $source['rules'],
            ],
            'terminals' => $terminals,
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => $coverageUnits,
                'witnessed' => $coverageWitnessed,
                'excluded' => $coverageExcluded,
            ],
        ];
    }

    /**
     * @param list<string> $expectedTokens
     * @return array{id: string, sql: string, tokens: list<string>, units: list<string>}
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
     * Maps the PostgreSQL 17 scanner rule inventory to a successful witness.
     * Rules which can only report a lexical error are classified separately.
     *
     * @return array<int, string>
     */
    public function ruleWitnesses(): array
    {
        return [
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
    }

    /**
     * @param array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> $terminals
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
