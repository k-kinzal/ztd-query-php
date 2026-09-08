<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;
use SqlFaker\Grammar\LexerSource;
use SqlFaker\Grammar\Lexical\UpstreamLexerSource;

/**
 * Builds a MySQL lexical profile from the server's own lexer source.
 *
 * The three files it reads are the keyword table, the scanner and the character
 * state header, and where the last of those lives moved twice across the
 * supported releases — so the profile is bound to one exact version and records
 * the hash of every file it was built from.
 *
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
     * Fetches registration data and records the reviewed scanner source without inferring its semantics.
     * @return array<string, mixed>
     * @throws RuntimeException When an upstream file or registration table is unavailable
     */
    public function build(string $version): array
    {
        ['table' => $tableUrl, 'scanner' => $scannerUrl, 'state' => $stateUrl] = $this->sourceUrls($version);
        $table = $this->source->fetch($tableUrl);
        $scanner = $this->source->fetch($scannerUrl);
        $state = $this->source->fetch($stateUrl);
        return [
            'dialect' => 'mysql',
            'version' => $version,
            'sources' => [$tableUrl => hash('sha256', $table), $scannerUrl => hash('sha256', $scanner), $stateUrl => hash('sha256', $state)],
            'registrations' => (new LexicalProfileCompiler())->registrations($table),
        ];
    }
}
