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
        $samples = new SqliteCoverageSamples();
        $coverageSamples = $samples->all();

        sort($classes);
        $coverageExcluded = $samples->unreachable();
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

        $samples = (new SqliteLexicalSamples())->all();
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
