<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;
use SqlFaker\MySql\LexicalProfileCompiler as MySqlCompiler;
use SqlFaker\MySql\LexicalSourceParser as MySqlSourceParser;
use SqlFaker\PostgreSql\LexicalProfileCompiler as PostgreSqlCompiler;
use SqlFaker\PostgreSql\LexicalSourceParser as PostgreSqlSourceParser;
use SqlFaker\Sqlite\LexicalProfileCompiler as SqliteCompiler;
use SqlFaker\Sqlite\LexicalSourceParser as SqliteSourceParser;

/**
 * Builds version-bound lexical profiles directly from official lexer sources.
 */
final class LexicalProfileBuilder
{
    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
                'user_agent' => 'sql-faker/1.0',
            ],
        ]);

        set_error_handler(static function (int $severity, string $message): never {
            throw new RuntimeException($message);
        });
        try {
            $contents = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($contents === false) {
            throw new RuntimeException("Failed to fetch {$url}");
        }

        return $contents;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function render(array $profile): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n/**\n * Auto-generated lexical profile.\n *\n * @return array<string, mixed>\n */\nreturn "
            . $this->export($this->compactProfile($profile))
            . ";\n";
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<string> $terminals
     */
    public function assertCompatible(array $profile, string $dialect, string $version, array $terminals): void
    {
        if (($profile['dialect'] ?? null) !== $dialect || ($profile['version'] ?? null) !== $version) {
            throw new RuntimeException("Invalid lexical profile identity: {$dialect} {$version}");
        }
        if (!isset($profile['catalog']) || !is_array($profile['catalog'])) {
            throw new RuntimeException("Lexical profile catalog is missing: {$dialect} {$version}");
        }

        /** @var array<string, mixed> $catalog */
        $catalog = $profile['catalog'];
        (new LexicalCatalog($catalog))->assertTerminalsCovered($terminals);
    }

    /**
     * Publishes the grammar and lexical profile only after both have been generated and validated.
     *
     * @param array<string, mixed> $profile
     */
    public function publishVersion(SqlVersion $version, string $ast, array $profile): void
    {
        $artifacts = [
            $version->astPath => $ast,
            $version->lexicalPath => $this->render($profile),
        ];
        $temporaryPaths = [];
        try {
            foreach ($artifacts as $path => $contents) {
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new RuntimeException("Failed to create {$directory}");
                }
                $temporaryPath = tempnam($directory, '.sql-faker-');
                if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
                    throw new RuntimeException("Failed to stage {$path}");
                }
                $temporaryPaths[$path] = $temporaryPath;
            }
            foreach ($temporaryPaths as $path => $temporaryPath) {
                if (!rename($temporaryPath, $path)) {
                    throw new RuntimeException("Failed to publish {$path}");
                }
                unset($temporaryPaths[$path]);
                fwrite(STDOUT, "Generated: {$path}\n");
            }
        } finally {
            foreach ($temporaryPaths as $temporaryPath) {
                if (file_exists($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function compactProfile(array $profile): array
    {
        if (!isset($profile['catalog']) || !is_array($profile['catalog'])
            || !isset($profile['catalog']['terminals']) || !is_array($profile['catalog']['terminals'])
        ) {
            return $profile;
        }

        $terminals = [];
        foreach ($profile['catalog']['terminals'] as $terminal => $witnesses) {
            if (!is_array($witnesses)) {
                throw new RuntimeException('Invalid lexical terminal witnesses while compacting.');
            }
            foreach ($witnesses as $witness) {
                if (!is_array($witness)
                    || !isset($witness['id'], $witness['sql'], $witness['tokens'], $witness['units'])
                ) {
                    throw new RuntimeException('Invalid lexical witness while compacting.');
                }
                $compact = [$witness['id'], $witness['sql'], $witness['tokens'], $witness['units']];
                if (isset($witness['context_sql'])) {
                    $compact[] = $witness['context_sql'];
                }
                $terminals[$terminal][] = $compact;
            }
        }
        $profile['catalog']['terminals'] = $terminals;

        return $profile;
    }

    private function export(mixed $value, int $indent = 0): string
    {
        if (is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value);
            $escaped = preg_replace_callback(
                '/[\x00-\x1F\x7F]/',
                static fn (array $match): string => sprintf('\\x%02X', ord($match[0])),
                $escaped,
            );
            if ($escaped === null) {
                throw new RuntimeException('Failed to escape a lexical profile string.');
            }

            return '"' . $escaped . '"';
        }
        if (!is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }
        if (array_is_list($value)) {
            return '[' . implode(', ', array_map(
                fn (mixed $item): string => $this->export($item, $indent),
                $value,
            )) . ']';
        }

        $padding = str_repeat(' ', $indent);
        $childPadding = str_repeat(' ', $indent + 4);
        $lines = [];
        foreach ($value as $key => $item) {
            $lines[] = $childPadding . var_export($key, true) . ' => ' . $this->export($item, $indent + 4) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n{$padding}]";
    }

    /**
     * @return array<string, mixed>
     */
    public function mysql(string $version, \SqlFaker\MySql\Grammar\Grammar $grammar): array
    {
        $base = "https://raw.githubusercontent.com/mysql/mysql-server/refs/tags/{$version}";
        $tableUrl = $base . '/sql/lex.h';
        $scannerUrl = $base . '/sql/sql_lex.cc';
        $statePath = match (true) {
            str_starts_with($version, 'mysql-5.6.') => '/include/m_ctype.h',
            version_compare(substr($version, strlen('mysql-')), '8.1.0', '<') => '/include/sql_chars.h',
            default => '/strings/sql_chars.h',
        };
        $stateUrl = $base . $statePath;
        $table = $this->fetch($tableUrl);
        $scanner = $this->fetch($scannerUrl);
        $stateHeader = $this->fetch($stateUrl);
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

        $profile['catalog'] = $this->mySqlCatalog(
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
     */
    private function mySqlCatalog(
        string $version,
        array $profile,
        array $states,
        \SqlFaker\MySql\Grammar\Grammar $grammar,
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
                $terminals[$terminal][] = $this->mySqlWitness(
                    "mysql.symbol.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_IDENT'],
                );
            }
        }
        foreach ($functions as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->mySqlWitness(
                    "mysql.function.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_IDENT'],
                    $lexeme . '(',
                );
            }
        }

        /** @var array<string, list<array{string, list<string>, list<string>, 3?: string}>> $samples */
        $samples = [
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
                $terminals[$terminal][] = $this->mySqlWitness(
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
                $terminals[$terminal][] = $this->mySqlWitness(
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
            $terminals[$terminal][] = $this->mySqlWitness(
                'mysql.parser.' . $terminal,
                '',
                [],
                [$unit],
            );
        }
        if (version_compare(substr($version, strlen('mysql-')), '8.0.0', '<')) {
            $terminals['WITH_CUBE_SYM'][] = $this->mySqlWitness(
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
                $terminals['@COVERAGE'][] = $this->mySqlWitness(
                    'mysql.coverage.' . $endState,
                    '',
                    [],
                    [$endState],
                );
                $coverageWitnessed[$endState] = 'mysql.coverage.' . $endState;
            }
        }
        if (in_array('MY_LEX_OPERATOR_OR_IDENT', $states, true)) {
            $terminals['@COVERAGE'][] = $this->mySqlWitness(
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
    private function mySqlWitness(
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

    /**
     * @return array<string, mixed>
     */
    public function postgreSql(string $version): array
    {
        $release = strtoupper(str_replace(['pg-', '.'], ['REL_', '_'], $version));
        $base = "https://raw.githubusercontent.com/postgres/postgres/refs/tags/{$release}";
        $keywordUrl = $base . '/src/include/parser/kwlist.h';
        $scannerUrl = $base . '/src/backend/parser/scan.l';
        $parserUrl = $base . '/src/backend/parser/parser.c';
        $keywords = $this->fetch($keywordUrl);
        $scanner = $this->fetch($scannerUrl);
        $parser = $this->fetch($parserUrl);

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
        $profile['catalog'] = $this->postgreSqlCatalog($profile, $source);

        return $profile;
    }

    /**
     * @param array<string, mixed> $profile
     * @param array{states: list<string>, rules: list<string>, lookahead_tokens: list<string>} $source
     * @return array<string, mixed>
     */
    private function postgreSqlCatalog(array $profile, array $source): array
    {
        /** @var array<string, list<string>> $keywords */
        $keywords = $profile['keywords'];
        /** @var array<string, array{token: string, followed_by: list<string>}> $lookahead */
        $lookahead = $profile['lookahead'];
        $terminals = [];
        foreach ($keywords as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->postgreSqlWitness(
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

        $samples = [
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
            'ICONST' => ['1', '0x10', '0o10', '0b10'],
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
        foreach (str_split('%()*+,-./:;<=>[]^') as $punctuation) {
            $samples[$punctuation] = [$punctuation];
        }
        foreach ($samples as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $expected = $terminal === '@TRIVIA' ? [] : [$terminal];
                $terminals[$terminal][] = $this->postgreSqlWitness(
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
            $terminals['@COVERAGE'][] = $this->postgreSqlWitness(
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

        $ruleWitnesses = $this->postgreSqlRuleWitnesses();
        if ($ruleWitnesses === [] || max(array_keys($ruleWitnesses)) >= $ruleCount) {
            throw new RuntimeException('PostgreSQL source rule mapping exceeds the parsed rule inventory.');
        }
        foreach ($ruleWitnesses as $rule => $witnessId) {
            $this->postgreSqlAttachUnit($terminals, $witnessId, 'rule:' . $rule);
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
    private function postgreSqlWitness(string $id, string $sql, array $expectedTokens): array
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
    private function postgreSqlRuleWitnesses(): array
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
     */
    private function postgreSqlAttachUnit(array &$terminals, string $witnessId, string $unit): void
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
     * @return array<string, mixed>
     */
    public function sqlite(string $version): array
    {
        $tag = 'version-' . substr($version, strlen('sqlite-'));
        $base = "https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/{$tag}";
        $keywordUrl = $base . '/tool/mkkeywordhash.c';
        $scannerUrl = $base . '/src/tokenize.c';
        $keywords = $this->fetch($keywordUrl);
        $scanner = $this->fetch($scannerUrl);

        $profile = [
            'dialect' => 'sqlite',
            'version' => $version,
            'sources' => [
                $keywordUrl => hash('sha256', $keywords),
                $scannerUrl => hash('sha256', $scanner),
            ],
            'keywords' => (new SqliteCompiler())->compile($keywords),
        ];

        $classes = (new SqliteSourceParser())->parseCharacterClasses($scanner);
        $profile['catalog'] = $this->sqliteCatalog($profile, $classes);

        return $profile;
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<string> $classes
     * @return array<string, mixed>
     */
    private function sqliteCatalog(array $profile, array $classes): array
    {
        /** @var array<string, array{string, list<string>, list<string>}> $coverageSamples */
        $coverageSamples = [
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

        sort($classes);
        $coverageExcluded = [
            'CC_NUL' => 'NUL terminates SQLite input and cannot be emitted inside a generated SQL statement.',
        ];
        $coverageWitnessed = [];
        foreach ($coverageSamples as $id => [, , $units]) {
            foreach ($units as $unit) {
                $coverageWitnessed[$unit] ??= $id;
            }
        }
        $classified = [...array_keys($coverageWitnessed), ...array_keys($coverageExcluded)];
        $missingCoverage = array_values(array_diff($classes, $classified));
        if ($missingCoverage !== []) {
            throw new RuntimeException('SQLite source model misses character classes: ' . implode(', ', $missingCoverage));
        }
        $unknownCoverage = array_values(array_diff($classified, $classes));
        if ($unknownCoverage !== []) {
            throw new RuntimeException('SQLite source model references unknown character classes: ' . implode(', ', $unknownCoverage));
        }

        /** @var array<string, list<string>> $keywords */
        $keywords = $profile['keywords'];
        $terminals = [];
        foreach ($keywords as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->sqliteWitness(
                    "sqlite.keyword.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal === 'WITHIN' ? 'TK_ID' : 'TK_' . $terminal],
                    ['CC_KYWD0'],
                );
            }
        }

        /** @var array<string, list<array{string, list<string>, list<string>}>> $samples */
        $samples = [
            '@TRIVIA' => [
                [' ', ['TK_SPACE'], ['CC_SPACE']],
                ['/* comment */', ['TK_SPACE'], ['CC_SLASH']],
                ["-- comment\n", ['TK_SPACE', 'TK_SPACE'], ['CC_MINUS', 'CC_SPACE']],
            ],
            'ID' => $this->sqliteIdentifierSamples(),
            'id' => $this->sqliteIdentifierSamples(),
            'idj' => $this->sqliteIdentifierSamples(),
            'ids' => [["'text'", ['TK_STRING'], ['CC_QUOTE']], ["'a''b'", ['TK_STRING'], ['CC_QUOTE']]],
            'STRING' => [["'text'", ['TK_STRING'], ['CC_QUOTE']], ["'/* not a comment */'", ['TK_STRING'], ['CC_QUOTE']], ["'a''b'", ['TK_STRING'], ['CC_QUOTE']]],
            'BLOB' => [["X'00ff'", ['TK_BLOB'], ['CC_X']]],
            'number' => [['1', ['TK_INTEGER'], ['CC_DIGIT']], ['1.5', ['TK_FLOAT'], ['CC_DIGIT']], ['.5', ['TK_FLOAT'], ['CC_DOT']], ['1e2', ['TK_FLOAT'], ['CC_DIGIT']]],
            'INTEGER' => [['1', ['TK_INTEGER'], ['CC_DIGIT']], ['0x10', ['TK_INTEGER'], ['CC_DIGIT']]],
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
        foreach ($samples as $terminal => $witnesses) {
            foreach ($witnesses as $index => [$sql, $tokens, $units]) {
                $terminals[$terminal][] = $this->sqliteWitness(
                    "sqlite.family.{$terminal}.{$index}",
                    $sql,
                    $tokens,
                    $units,
                );
            }
        }
        foreach ($coverageSamples as $id => [$sql, $tokens, $units]) {
            $terminals['@COVERAGE'][] = $this->sqliteWitness($id, $sql, $tokens, $units);
        }
        ksort($terminals);
        ksort($coverageWitnessed);

        return [
            'source' => [
                'engine' => 'sqlite',
                'entrypoint' => 'tokenize.c/sqlite3GetToken',
                'character_classes' => $classes,
            ],
            'terminals' => $terminals,
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => $classes,
                'witnessed' => $coverageWitnessed,
                'excluded' => $coverageExcluded,
            ],
        ];
    }

    /**
     * @return list<array{string, list<string>, list<string>}>
     */
    private function sqliteIdentifierSamples(): array
    {
        return [
            ['name', ['TK_ID'], ['CC_KYWD0']],
            ['"select"', ['TK_ID'], ['CC_QUOTE']],
            ['`select`', ['TK_ID'], ['CC_QUOTE']],
            ['[select]', ['TK_ID'], ['CC_QUOTE2']],
        ];
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $units
     * @return array{id: string, sql: string, tokens: list<string>, units: list<string>}
     */
    private function sqliteWitness(string $id, string $sql, array $tokens, array $units): array
    {
        return [
            'id' => $id,
            'sql' => $sql,
            'tokens' => $tokens,
            'units' => $units,
        ];
    }
}
