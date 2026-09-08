<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\Lexical\UpstreamLexerSource;

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
     * Fetches registration data and records the reviewed scanner source without inferring its semantics.
     * @return array<string, mixed>
     * @throws RuntimeException When an upstream file or registration table is unavailable
     */
    public function build(string $version): array
    {
        ['keywords' => $keywordUrl, 'scanner' => $scannerUrl] = $this->sourceUrls($version);
        $keywords = $this->source->fetch($keywordUrl);
        $scanner = $this->source->fetch($scannerUrl);
        return [
            'dialect' => 'sqlite',
            'version' => $version,
            'sources' => [$keywordUrl => hash('sha256', $keywords), $scannerUrl => hash('sha256', $scanner)],
            'keywords' => (new LexicalProfileCompiler())->compile($keywords),
        ];
    }
}
