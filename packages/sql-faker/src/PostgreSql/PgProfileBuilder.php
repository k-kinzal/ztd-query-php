<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\Lexical\UpstreamLexerSource;

/**
 * Builds a PostgreSQL lexical profile from the server's own lexer source.
 *
 * PostgreSQL splits what one release calls a keyword across a keyword list and
 * a scanner, and its parser frontend rewrites some tokens by looking ahead, so
 * the profile records both and is bound to one exact version.
 *
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
     * Fetches registration data and records the reviewed scanner source without inferring its semantics.
     * @return array<string, mixed>
     * @throws RuntimeException When an upstream file or registration table is unavailable
     */
    public function build(string $version): array
    {
        ['keywords' => $keywordUrl, 'scanner' => $scannerUrl, 'parser' => $parserUrl] = $this->sourceUrls($version);
        $keywords = $this->source->fetch($keywordUrl);
        $scanner = $this->source->fetch($scannerUrl);
        $parser = $this->source->fetch($parserUrl);
        return [
            'dialect' => 'postgresql',
            'version' => $version,
            'sources' => [$keywordUrl => hash('sha256', $keywords), $scannerUrl => hash('sha256', $scanner), $parserUrl => hash('sha256', $parser)],
            'keywords' => (new LexicalProfileCompiler())->compile($keywords),
        ];
    }
}
