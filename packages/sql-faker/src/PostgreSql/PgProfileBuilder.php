<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\Lexical\UpstreamLexerSource;
use SqlFaker\PostgreSql\LexicalProfileCompiler as PostgreSqlCompiler;
use SqlFaker\PostgreSql\LexicalSourceParser as PostgreSqlSourceParser;

/**
 * Builds a PostgreSQL lexical profile from the server's own lexer source.
 *
 * PostgreSQL splits what one release calls a keyword across a keyword list and
 * a scanner, and its parser frontend rewrites some tokens by looking ahead, so
 * the profile records both and is bound to one exact version.
 *
 * @phpstan-type Witness array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}
 * @phpstan-type Terminals array<string, list<Witness>>
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
        $terminals = $this->keywordWitnesses($keywords, $lookahead);

        $this->addLexicalWitnesses($terminals);

        $coverage = $this->coverage($terminals, $source['rules']);
        ksort($terminals);

        return [
            'source' => [
                'engine' => 'postgresql',
                'entrypoint' => 'scan.l/base_yylex',
                'states' => $source['states'],
                'rules' => $source['rules'],
            ],
            'terminals' => $terminals,
            'terminal_exclusions' => [],
            'coverage' => $coverage,
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
        return (new PgLexicalSamples())->ruleWitnesses();
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

    /**
     * Builds keyword witnesses including the lookahead context required by the parser.
     *
     * @param array<string, list<string>> $keywords
     * @param array<string, array{token: string, followed_by: list<string>}> $lookahead
     * @return Terminals
     */
    public function keywordWitnesses(array $keywords, array $lookahead): array
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

        return $terminals;
    }

    /**
     * Adds lexical families and scanner branch witnesses.
     *
     * @param Terminals $terminals
     */
    public function addLexicalWitnesses(array &$terminals): void
    {
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
    }

    /**
     * Verifies that scanner rules and parser modes are witnessed or explicitly excluded.
     *
     * @param Terminals $terminals
     * @param list<string> $rules
     * @return array{units: list<string>, witnessed: array<string, string>, excluded: array<string, string>}
     *
     * @throws RuntimeException When the inventory and its witness mapping disagree
     */
    public function coverage(array &$terminals, array $rules): array
    {
        $ruleCount = count($rules) + 1;
        $coverageUnits = $this->addParserModes($terminals, $ruleCount);

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
        $coverageExcluded = $this->excludedRules($ruleCount);
        $missing = array_values(array_diff(
            $coverageUnits,
            array_keys($coverageWitnessed),
            array_keys($coverageExcluded),
        ));
        if ($missing !== []) {
            throw new RuntimeException('PostgreSQL source model misses scanner rules: ' . implode(', ', $missing));
        }
        ksort($coverageWitnessed);

        return ['units' => $coverageUnits, 'witnessed' => $coverageWitnessed, 'excluded' => $coverageExcluded];
    }

    /**
     * Names scanner branches that only reject input and the Flex default jam rule.
     *
     * @return array<string, string>
     */
    public function excludedRules(int $ruleCount): array
    {
        return [
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
    }

    /**
     * Adds parser entry witnesses and enumerates all scanner and mode coverage units.
     *
     * @param Terminals $terminals
     * @return list<string>
     */
    public function addParserModes(array &$terminals, int $ruleCount): array
    {
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

        return $coverageUnits;
    }
}
