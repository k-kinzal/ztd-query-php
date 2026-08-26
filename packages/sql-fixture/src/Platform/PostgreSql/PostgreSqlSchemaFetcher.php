<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use Override;
use PDO;
use RuntimeException;
use SqlFixture\Schema\SchemaFetcherInterface;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

/**
 * Describes a live PostgreSQL table.
 *
 * PostgreSQL has no `SHOW CREATE TABLE`, so the table is read out of the
 * catalog, written back out as the statement that would declare it, and read
 * by the same parser a `.sql` file goes through. Going the long way round is
 * what keeps one understanding of a declaration rather than two that drift.
 */
final class PostgreSqlSchemaFetcher implements SchemaFetcherInterface
{
    /**
     * @param PostgreSqlSchemaParser $parser Reads a declaration as a table
     * @param PostgreSqlCatalog $catalog Reads a table out of PostgreSQL's catalog
     * @param PostgreSqlTableDeclaration $declaration Writes back what the catalog described
     */
    public function __construct(
        private readonly PostgreSqlSchemaParser $parser = new PostgreSqlSchemaParser(),
        private readonly PostgreSqlCatalog $catalog = new PostgreSqlCatalog(),
        private readonly PostgreSqlTableDeclaration $declaration = new PostgreSqlTableDeclaration(),
    ) {
    }

    /**
     * Describes the table as the server currently holds it.
     *
     * @param PDO $pdo Connection to read through
     * @param string $tableName Table to describe, optionally schema-qualified
     *
     * @return TableSchema The table
     *
     * @throws RuntimeException When the connection knows no such table
     * @throws SchemaParseException When the catalog describes something the parser cannot read back
     */
    #[Override]
    public function fetchSchema(PDO $pdo, string $tableName): TableSchema
    {
        [$schema, $table] = $this->catalog->split($tableName);

        $columns = $this->catalog->columnsOf($pdo, $schema, $table);
        if ($columns === []) {
            throw new RuntimeException("Table not found: {$tableName}");
        }

        return $this->parser->parse($this->declaration->of(
            $table,
            $columns,
            $this->catalog->primaryKeysOf($pdo, $schema, $table),
        ));
    }
}
