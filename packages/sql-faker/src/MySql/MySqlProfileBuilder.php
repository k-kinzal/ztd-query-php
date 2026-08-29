<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;
use SqlFaker\Grammar\Source\UpstreamLexerSource;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\LexicalProfileCompiler as MySqlCompiler;
use SqlFaker\MySql\LexicalSourceParser as MySqlSourceParser;

/**
 * Builds a MySQL lexical profile from the server's own lexer source.
 *
 * The three files it reads are the keyword table, the scanner and the character
 * state header, and where the last of those lives moved twice across the
 * supported releases — so the profile is bound to one exact version and records
 * the hash of every file it was built from.
 */
final class MySqlProfileBuilder
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
     * The character-state header moved twice across the supported releases, so
     * where it lives is decided by the version rather than fixed.
     *
     * @param string $version Release tag, e.g. "mysql-8.4.7"
     *
     * @return array{table: string, scanner: string, state: string} URLs of the three files
     */
    public function sourceUrls(string $version): array
    {
        $base = "https://raw.githubusercontent.com/mysql/mysql-server/refs/tags/{$version}";
        $statePath = match (true) {
            str_starts_with($version, 'mysql-5.6.') => '/include/m_ctype.h',
            version_compare(substr($version, strlen('mysql-')), '8.1.0', '<') => '/include/sql_chars.h',
            default => '/strings/sql_chars.h',
        };

        return [
            'table' => $base . '/sql/lex.h',
            'scanner' => $base . '/sql/sql_lex.cc',
            'state' => $base . $statePath,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $version, Grammar $grammar): array
    {
        ['table' => $tableUrl, 'scanner' => $scannerUrl, 'state' => $stateUrl] = $this->sourceUrls($version);
        $table = $this->source->fetch($tableUrl);
        $scanner = $this->source->fetch($scannerUrl);
        $stateHeader = $this->source->fetch($stateUrl);
        $compiled = (new MySqlCompiler())->compile($table);

        $profile = [
            'dialect' => 'mysql',
            'version' => $version,
            'sources' => [
                $tableUrl => hash('sha256', $table),
                $scannerUrl => hash('sha256', $scanner),
                $stateUrl => hash('sha256', $stateHeader),
            ],
            'symbols' => $compiled['symbols'],
            'functions' => $compiled['functions'],
            'features' => [
                'dollar_quoted_strings' => str_contains($scanner, 'DOLLAR_QUOTED_STRING_SYM'),
            ],
        ];

        $profile['catalog'] = $this->catalog(
            $version,
            $profile,
            (new MySqlSourceParser())->parseStates($stateHeader, $scanner),
            $grammar,
        );

        return $profile;
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<string> $states
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the upstream source does not describe the lexer
     */
    public function catalog(
        string $version,
        array $profile,
        array $states,
        Grammar $grammar,
    ): array {
        /** @var array<string, list<string>> $symbols */
        $symbols = $profile['symbols'];
        /** @var array<string, list<string>> $functions */
        $functions = $profile['functions'];
        /** @var array{dollar_quoted_strings: bool} $features */
        $features = $profile['features'];
        $terminals = [];
        foreach ($symbols as $terminal => $lexemes) {
            if (in_array($terminal, ['NOT2_SYM', 'OR_OR_SYM'], true)) {
                continue;
            }
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "mysql.symbol.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_IDENT'],
                );
            }
        }
        foreach ($functions as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "mysql.function.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_IDENT'],
                    $lexeme . '(',
                );
            }
        }

        $samples = (new MySqlLexicalSamples())->all();
        $dollarState = current(array_values(array_filter(
            $states,
            static fn (string $state): bool => str_starts_with($state, 'MY_LEX_IDENT_OR_DOLLAR'),
        )));
        if ($features['dollar_quoted_strings']) {
            if (!is_string($dollarState)) {
                throw new RuntimeException('MySQL dollar-quoted string state was not found.');
            }
            $samples['DOLLAR_QUOTED_STRING_SYM'] = [
                ['$$text$$', ['DOLLAR_QUOTED_STRING_SYM'], ['MY_LEX_START', $dollarState]],
            ];
        } elseif (is_string($dollarState)) {
            $samples['@COVERAGE'][] = ['$identifier', ['IDENT'], ['MY_LEX_START', $dollarState]];
        }
        if (in_array('MY_LEX_ESCAPE', $states, true)) {
            $samples['@COVERAGE'][] = ['\\N', ['NULL_SYM'], ['MY_LEX_START', 'MY_LEX_ESCAPE']];
        }
        if (in_array('MY_LEX_STRING_OR_DELIMITER', $states, true)) {
            $samples['@COVERAGE'][] = ['"text"', ['TEXT_STRING'], ['MY_LEX_START', 'MY_LEX_STRING_OR_DELIMITER']];
        }
        $samples['@COVERAGE'][] = ['name.column', ['IDENT', '.', 'IDENT'], ['MY_LEX_IDENT_SEP', 'MY_LEX_IDENT_START']];
        $samples['@COVERAGE'][] = ['.5', ['DECIMAL_NUM'], ['MY_LEX_REAL_OR_POINT', 'MY_LEX_REAL']];
        $samples['@COVERAGE'][] = ['<=', ['LE'], ['MY_LEX_CMP_OP']];
        $samples['@COVERAGE'][] = ['<=>', ['EQUAL_SYM'], ['MY_LEX_CMP_OP', 'MY_LEX_LONG_CMP_OP']];
        $samples['@COVERAGE'][] = ['*/', ['*', '/'], ['MY_LEX_END_LONG_COMMENT']];
        $samples['@COVERAGE'][] = [';', [';'], ['MY_LEX_SEMICOLON']];
        $samples['@COVERAGE'][] = ['@name', ['@', 'LEX_HOSTNAME'], ['MY_LEX_USER_END', 'MY_LEX_HOSTNAME']];
        $samples['@COVERAGE'][] = ["@'name'", ['@', 'IDENT_QUOTED'], ['MY_LEX_USER_END', 'MY_LEX_USER_VARIABLE_DELIMITER']];
        $samples['@COVERAGE'][] = ['@@name', ['@', '@', 'IDENT'], ['MY_LEX_USER_END', 'MY_LEX_SYSTEM_VAR', 'MY_LEX_IDENT_OR_KEYWORD']];

        foreach ($samples as $terminal => $witnesses) {
            foreach ($witnesses as $index => $sample) {
                [$sql, $tokens, $units] = $sample;
                $terminals[$terminal][] = $this->witness(
                    "mysql.family.{$terminal}.{$index}",
                    $sql,
                    $tokens,
                    $units,
                    $sample[3] ?? null,
                );
            }
        }

        $punctuation = str_split('!%&()*+,-./:;<=>?@[]^{}|~');
        foreach ($punctuation as $terminal) {
            if (!isset($terminals[$terminal])) {
                $terminals[$terminal][] = $this->witness(
                    'mysql.punctuation.' . bin2hex($terminal),
                    $terminal,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_CHAR'],
                );
            }
        }

        $versionedParserTokens = ['END_OF_INPUT'];
        foreach (\SqlFaker\MySql\Grammar\TerminalInventory::fromGrammar($grammar) as $terminal) {
            if (str_starts_with($terminal, 'GRAMMAR_SELECTOR_')) {
                $versionedParserTokens[] = $terminal;
            }
        }
        foreach ($versionedParserTokens as $terminal) {
            $unit = 'parser-entry:' . $terminal;
            $terminals[$terminal][] = $this->witness(
                'mysql.parser.' . $terminal,
                '',
                [],
                [$unit],
            );
        }
        if (version_compare(substr($version, strlen('mysql-')), '8.0.0', '<')) {
            $terminals['WITH_CUBE_SYM'][] = $this->witness(
                'mysql.parser.WITH_CUBE_SYM',
                'WITH CUBE',
                ['WITH_CUBE_SYM'],
                ['MY_LEX_START', 'MY_LEX_IDENT'],
            );
        }

        $coverageUnits = $states;
        foreach ($versionedParserTokens as $terminal) {
            $coverageUnits[] = 'parser-entry:' . $terminal;
        }
        $coverageWitnessed = [];
        foreach ($terminals as $witnesses) {
            foreach ($witnesses as $witness) {
                foreach ($witness['units'] as $unit) {
                    if (in_array($unit, $coverageUnits, true)) {
                        $coverageWitnessed[$unit] ??= $witness['id'];
                    }
                }
            }
        }
        foreach (['MY_LEX_END', 'MY_LEX_EOL'] as $endState) {
            if (in_array($endState, $states, true)) {
                $terminals['@COVERAGE'][] = $this->witness(
                    'mysql.coverage.' . $endState,
                    '',
                    [],
                    [$endState],
                );
                $coverageWitnessed[$endState] = 'mysql.coverage.' . $endState;
            }
        }
        if (in_array('MY_LEX_OPERATOR_OR_IDENT', $states, true)) {
            $terminals['@COVERAGE'][] = $this->witness(
                'mysql.coverage.MY_LEX_OPERATOR_OR_IDENT',
                'a + b',
                ['IDENT', '+', 'IDENT'],
                ['MY_LEX_OPERATOR_OR_IDENT'],
            );
            $coverageWitnessed['MY_LEX_OPERATOR_OR_IDENT'] = 'mysql.coverage.MY_LEX_OPERATOR_OR_IDENT';
        }
        $coverageExcluded = [];
        $missingCoverage = array_values(array_diff($coverageUnits, array_keys($coverageWitnessed)));
        if ($missingCoverage !== []) {
            throw new RuntimeException('MySQL source model misses lexical states: ' . implode(', ', $missingCoverage));
        }

        $terminalExclusions = [
            'NOT2_SYM' => 'Default sql_mode emits NOT_SYM; NOT2_SYM requires HIGH_NOT_PRECEDENCE.',
            'OR_OR_SYM' => 'Default sql_mode normalizes double-pipe to OR2_SYM; OR_OR_SYM requires PIPES_AS_CONCAT.',
            'UDF_RETURNS_SYM' => 'The token is declared by legacy grammars but has no mapping in the official lexer table.',
        ];
        ksort($terminals);
        ksort($coverageWitnessed);

        return [
            'source' => [
                'engine' => 'mysql',
                'entrypoint' => 'my_sql_parser_lex',
            ],
            'terminals' => $terminals,
            'terminal_exclusions' => $terminalExclusions,
            'coverage' => [
                'units' => $coverageUnits,
                'witnessed' => $coverageWitnessed,
                'excluded' => $coverageExcluded,
            ],
        ];
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $units
     * @return array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}
     */
    public function witness(
        string $id,
        string $sql,
        array $tokens,
        array $units,
        ?string $contextSql = null,
    ): array {
        $witness = ['id' => $id, 'sql' => $sql, 'tokens' => $tokens, 'units' => $units];
        if ($contextSql !== null) {
            $witness['context_sql'] = $contextSql;
        }

        return $witness;
    }
}
