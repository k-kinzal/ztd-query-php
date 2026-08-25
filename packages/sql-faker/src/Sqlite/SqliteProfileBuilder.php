<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\UpstreamLexerSource;
use SqlFaker\Sqlite\LexicalProfileCompiler as SqliteCompiler;
use SqlFaker\Sqlite\LexicalSourceParser as SqliteSourceParser;

/**
 * Builds a SQLite lexical profile from the release's own tokenizer source.
 *
 * SQLite spells its keyword table as C rather than as data, so the profile is
 * compiled out of the tokenizer and bound to one exact release.
 */
final class SqliteProfileBuilder
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
     * Reports the upstream files a release keeps its tokenizer in.
     *
     * SQLite tags its releases by number without the project name, so the
     * version this package uses has to be spelled back into a tag name.
     *
     * @param string $version Release tag, e.g. "sqlite-3.47.2"
     *
     * @return array{keywords: string, scanner: string} URLs of the two files
     */
    public function sourceUrls(string $version): array
    {
        $base = 'https://raw.githubusercontent.com/sqlite/sqlite/refs/tags/version-'
            . substr($version, strlen('sqlite-'));

        return [
            'keywords' => $base . '/tool/mkkeywordhash.c',
            'scanner' => $base . '/src/tokenize.c',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $version): array
    {
        ['keywords' => $keywordUrl, 'scanner' => $scannerUrl] = $this->sourceUrls($version);
        $keywords = $this->source->fetch($keywordUrl);
        $scanner = $this->source->fetch($scannerUrl);

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
        $profile['catalog'] = $this->catalog($profile, $classes);

        return $profile;
    }

    /**
     * @param array<string, mixed> $profile
     * @param list<string> $classes
     * @return array<string, mixed>
     *
     * @throws RuntimeException When the upstream source does not describe the tokenizer
     */
    public function catalog(array $profile, array $classes): array
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
                $terminals[$terminal][] = $this->witness(
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
            'ID' => $this->identifierSamples(),
            'id' => $this->identifierSamples(),
            'idj' => $this->identifierSamples(),
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
                $terminals[$terminal][] = $this->witness(
                    "sqlite.family.{$terminal}.{$index}",
                    $sql,
                    $tokens,
                    $units,
                );
            }
        }
        foreach ($coverageSamples as $id => [$sql, $tokens, $units]) {
            $terminals['@COVERAGE'][] = $this->witness($id, $sql, $tokens, $units);
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
    public function identifierSamples(): array
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
    public function witness(string $id, string $sql, array $tokens, array $units): array
    {
        return [
            'id' => $id,
            'sql' => $sql,
            'tokens' => $tokens,
            'units' => $units,
        ];
    }
}
