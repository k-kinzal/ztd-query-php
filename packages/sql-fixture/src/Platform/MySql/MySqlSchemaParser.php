<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

/**
 * Reads MySQL CREATE TABLE statements into table schemas.
 */
final class MySqlSchemaParser implements SchemaParserInterface
{
    /**
     * Parses the supplied declaration into its normalized representation.
     * @throws \SqlFixture\Schema\Exception\InvalidSqlException
     * @throws \SqlFixture\Schema\Exception\ExpectedCreateTableException
     * @throws \SqlFixture\Schema\Exception\MissingColumnDefinitionsException
     */
    public function parse(string $createTableSql): TableSchema
    {
        $parser = new Parser((new Schema\TableDefinitionInput())->withoutPartitioning($createTableSql));

        if ($parser->statements === []) {
            throw new \SqlFixture\Schema\Exception\InvalidSqlException($createTableSql, 'No statements found');
        }

        $stmt = $parser->statements[0];
        if (!$stmt instanceof CreateStatement) {
            throw new \SqlFixture\Schema\Exception\ExpectedCreateTableException($createTableSql);
        }

        (new Schema\DefinitionIntegrity())->assertNothingWasLost($parser, $stmt, $createTableSql);

        $tableName = (new Schema\TableDefinition())->extractTableName($stmt, $createTableSql);
        $columns = (new Schema\TableDefinition())->extractColumns($stmt, $tableName);
        $primaryKeys = (new Schema\TableDefinition())->extractPrimaryKeys($stmt);

        return new TableSchema($tableName, $columns, $primaryKeys);
    }

}
