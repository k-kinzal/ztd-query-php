<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;
use SqlFaker\MySql\Grammar\Grammar as MySqlGrammar;
use SqlFaker\MySql\MySqlProfileBuilder;
use SqlFaker\PostgreSql\PgProfileBuilder;
use SqlFaker\Sqlite\SqliteProfileBuilder;

/**
 * Builds version-bound lexical profiles directly from official lexer sources.
 *
 * Each dialect reads different files, spells its keyword table differently and
 * has its own idea of what a witness is, so each has a builder of its own. What
 * they share is the sequence: build the profile, check that it accounts for
 * every terminal the grammar can produce, then publish it beside the grammar it
 * was checked against. This class is that sequence and nothing else.
 */
final class LexicalProfileBuilder
{
    /** @readonly */
    private MySqlProfileBuilder $mysql;

    /** @readonly */
    private PgProfileBuilder $postgres;

    /** @readonly */
    private SqliteProfileBuilder $sqlite;

    /** @readonly */
    private LexicalProfileWriter $writer;

    /**
     * @param MySqlProfileBuilder|null $mysql Builds the MySQL profile
     * @param PgProfileBuilder|null $postgres Builds the PostgreSQL profile
     * @param SqliteProfileBuilder|null $sqlite Builds the SQLite profile
     * @param LexicalProfileWriter|null $writer Publishes a profile beside its grammar
     */
    public function __construct(
        ?MySqlProfileBuilder $mysql = null,
        ?PgProfileBuilder $postgres = null,
        ?SqliteProfileBuilder $sqlite = null,
        ?LexicalProfileWriter $writer = null,
    ) {
        $this->mysql = $mysql ?? new MySqlProfileBuilder();
        $this->postgres = $postgres ?? new PgProfileBuilder();
        $this->sqlite = $sqlite ?? new SqliteProfileBuilder();
        $this->writer = $writer ?? new LexicalProfileWriter();
    }

    /**
     * Builds the MySQL lexical profile for one exact server version.
     *
     * @param string $version Release tag to build from, e.g. "mysql-8.4.7"
     * @param MySqlGrammar $grammar Grammar the profile has to account for
     *
     * @return array<string, mixed> The profile
     */
    public function mysql(string $version, MySqlGrammar $grammar): array
    {
        return $this->mysql->build($version, $grammar);
    }

    /**
     * Builds the PostgreSQL lexical profile for one exact server version.
     *
     * @param string $version Release tag to build from, e.g. "pg-17.2"
     *
     * @return array<string, mixed> The profile
     */
    public function postgreSql(string $version): array
    {
        return $this->postgres->build($version);
    }

    /**
     * Builds the SQLite lexical profile for one exact release.
     *
     * @param string $version Release tag to build from, e.g. "sqlite-3.47.2"
     *
     * @return array<string, mixed> The profile
     */
    public function sqlite(string $version): array
    {
        return $this->sqlite->build($version);
    }

    /**
     * Checks that a built profile describes the release and grammar it is for.
     *
     * @param array<string, mixed> $profile Profile as just built
     * @param string $dialect Dialect the profile should name
     * @param string $version Version the profile should name
     * @param list<string> $terminals Terminals the grammar can produce
     *
     * @throws RuntimeException When the profile names another release or carries no catalog
     * @throws LexicalCatalogException When a terminal is neither witnessed nor excluded
     */
    public function assertCompatible(array $profile, string $dialect, string $version, array $terminals): void
    {
        if (($profile['dialect'] ?? null) !== $dialect || ($profile['version'] ?? null) !== $version) {
            throw new RuntimeException("Invalid lexical profile identity: {$dialect} {$version}");
        }
        if (!isset($profile['catalog']) || !is_array($profile['catalog'])) {
            throw new RuntimeException("Lexical profile catalog is missing: {$dialect} {$version}");
        }

        $catalog = array_filter(
            $profile['catalog'],
            static fn (int|string $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );
        (new LexicalCatalog($catalog))->assertTerminalsCovered($terminals);
    }

    /**
     * Publishes the grammar and lexical profile only after both have been generated and validated.
     *
     * @param SqlVersion $version Release the two artifacts describe
     * @param string $ast Rendered grammar AST
     * @param array<string, mixed> $profile Profile to publish beside it
     */
    public function publishVersion(SqlVersion $version, string $ast, array $profile): void
    {
        $this->writer->publishVersion($version, $ast, $profile);
    }
}
