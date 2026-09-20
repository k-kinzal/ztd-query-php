<?php

declare(strict_types=1);

namespace Tests\Scenario;

use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Analyzer;
use SqlSemantics\Dialect;
use SqlSemantics\Model\SelectQuery;
use SqlSemantics\Schema\Catalog;

/**
 * Parsed SQL scenarios shared by semantic contract tests.
 */
final class AnalysisCase
{
    /**
     * Parser corresponding to the scenario dialect.
     */
    public readonly MySqlParser|PostgreSqlParser|SqliteParser $parser;

    /**
     * Semantic analyzer corresponding to the scenario dialect.
     */
    public readonly Analyzer $analyzer;

    /**
     * Creates a parser and analyzer with matching language rules.
     */
    public function __construct(Dialect $dialect = Dialect::PostgreSql)
    {
        $this->parser = match ($dialect) {
            Dialect::PostgreSql => new PostgreSqlParser(),
            Dialect::MySql => new MySqlParser(),
            Dialect::Sqlite => new SqliteParser(),
        };
        $this->analyzer = new Analyzer($dialect);
    }

    /**
     * Analyzes a query against a self-referencing user table.
     */
    public function query(string $sql): SelectQuery
    {
        return $this->analyzer->analyze($this->parser->parse($sql), $this->schema());
    }

    /**
     * Builds an explicitly declared schema without connecting to a database.
     */
    public function schema(string $ddl = 'CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'): Catalog
    {
        return $this->analyzer->schema($this->parser->parse($ddl));
    }

    /**
     * @return iterable<string, array{Dialect}>
     */
    public static function providerLanguages(): iterable
    {
        yield 'PostgreSQL' => [Dialect::PostgreSql];
        yield 'MySQL' => [Dialect::MySql];
        yield 'SQLite' => [Dialect::Sqlite];
    }
}
