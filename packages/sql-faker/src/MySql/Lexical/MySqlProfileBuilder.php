<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Lexical;

use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;
use SqlFaker\Grammar\Source\UpstreamLexerSource;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Lexical\LexicalProfileCompiler as MySqlCompiler;
use SqlFaker\MySql\Lexical\LexicalSourceParser as MySqlSourceParser;

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
        $witnesses = new MySqlCatalogWitnesses();
        $terminals = $witnesses->fromTables($symbols, $functions);
        $terminals = $witnesses->fromSamples($terminals, $states, $features['dollar_quoted_strings']);
        [$terminals, $versionedParserTokens] = $witnesses->forStructure($terminals, $grammar, $version);

        $coverageUnits = $states;
        foreach ($versionedParserTokens as $terminal) {
            $coverageUnits[] = 'parser-entry:' . $terminal;
        }
        $coverage = new MySqlLexicalCoverage();
        foreach ($coverage->fillers($states) as $filler) {
            $terminals['@COVERAGE'][] = $filler;
        }
        $coverageWitnessed = $coverage->witnessed($terminals, $coverageUnits);
        $coverageExcluded = [];

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
