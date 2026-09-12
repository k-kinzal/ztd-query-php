<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Regex-based parser for PostgreSQL CREATE TABLE statements.
 *
 * Handles PostgreSQL-specific features:
 * - SERIAL/BIGSERIAL/SMALLSERIAL auto-incrementing types
 * - Schema-qualified names (e.g., public.users)
 * - PostgreSQL-specific types (UUID, JSONB, BYTEA, INET, TIMESTAMPTZ, etc.)
 * - Array types (INT[], TEXT[])
 * - CONSTRAINT syntax
 */
final class PostgreSqlSchemaParser implements SchemaParserInterface
{
    /**
     * Parses the supplied declaration into its normalized representation.
     * @throws SchemaParseException
     */
    public function parse(string $createTableSql): TableSchema
    {
        $sql = (new Schema\TableSyntax())->normalizeSql($createTableSql);

        $tableName = (new Schema\TableSyntax())->extractTableName($sql);
        if ($tableName === null) {
            throw SchemaParseException::invalidSql($createTableSql, 'Could not extract table name');
        }

        $columnsBlock = (new Schema\TableSyntax())->extractColumnsBlock($sql);
        if ($columnsBlock === null) {
            throw SchemaParseException::noColumns($tableName);
        }

        $primaryKeys = (new Schema\TableSyntax())->extractTablePrimaryKeys($columnsBlock);
        $columns = (new Schema\DefinitionList())->parseColumns($columnsBlock, $tableName, $primaryKeys);

        if ($columns === []) {
            throw SchemaParseException::noColumns($tableName);
        }

        return new TableSchema($tableName, $columns, $primaryKeys);
    }






















}
