<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lexical;

use RuntimeException;
use SqlFaker\Grammar\Source\LexerSource;
use SqlFaker\Grammar\Source\UpstreamLexerSource;
use SqlFaker\Sqlite\Lexical\LexicalProfileCompiler as SqliteCompiler;
use SqlFaker\Sqlite\Lexical\LexicalSourceParser as SqliteSourceParser;

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

    /** @readonly */
    private SqliteCatalogWitnesses $witnesses;

    /** @readonly */
    private SqliteLexicalCoverage $coverage;

    /**
     * @param LexerSource|null $source Reads the upstream lexer files
     * @param SqliteCatalogWitnesses|null $witnesses Writes the witnesses the catalogue holds
     * @param SqliteLexicalCoverage|null $coverage Accounts for the character classes they reach
     */
    public function __construct(
        ?LexerSource $source = null,
        ?SqliteCatalogWitnesses $witnesses = null,
        ?SqliteLexicalCoverage $coverage = null,
    ) {
        $this->source = $source ?? new UpstreamLexerSource();
        $this->witnesses = $witnesses ?? new SqliteCatalogWitnesses();
        $this->coverage = $coverage ?? new SqliteLexicalCoverage();
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
     * Compiles the catalogue the profile is checked against.
     *
     * @param array<string, mixed> $profile The profile built so far
     * @param list<string> $classes Character classes the upstream source declares
     *
     * @return array<string, mixed> The catalogue: every witness, and what each character class is reached by
     *
     * @throws RuntimeException When the upstream source does not describe the tokenizer
     */
    public function catalog(array $profile, array $classes): array
    {
        $samples = new SqliteCoverageSamples();
        $coverageSamples = $samples->all();
        $excluded = $samples->unreachable();
        $witnessed = $this->coverage->witnessed($coverageSamples);

        sort($classes);
        $this->coverage->assertClassified($classes, $witnessed, $excluded);

        /** @var array<string, list<string>> $keywords */
        $keywords = $profile['keywords'];
        $terminals = $this->witnesses->forProfile($keywords, $coverageSamples);
        ksort($terminals);
        ksort($witnessed);

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
                'witnessed' => $witnessed,
                'excluded' => $excluded,
            ],
        ];
    }

}
