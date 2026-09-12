<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Statement;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DropStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;

/**
 * Schema Mutation.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class SchemaMutation
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private TableDefinitionRegistry $registry, private SchemaParser $schemaParser)
    {
    }
    /**
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveCreateTable(CreateStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->name === null || $statement->name->table === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $tableName = $statement->name->table;
        $ifNotExists = $statement->options !== null && ($statement->options->has('IF NOT EXISTS') !== false);

        if (!$ifNotExists && $this->registry->has($tableName)) {
            throw new UnsupportedSqlException($sql, 'Table already exists');
        }

        if ($statement->like !== null && $statement->like->table !== null) {
            $sourceTableName = $statement->like->table;
            if (!$this->registry->has($sourceTableName)) {
                throw new UnknownSchemaException($sql, $sourceTableName, 'table');
            }
            return new CreateTableLikeMutation($tableName, $sourceTableName, $this->registry, $ifNotExists);
        }

        if ($statement->select !== null) {
            $columnNames = $this->extractSelectColumnNames($statement->select);
            return new CreateTableAsSelectMutation(
                $tableName,
                $columnNames,
                $this->registry,
                new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR'),
                $ifNotExists,
            );
        }

        $definition = $this->schemaParser->parse($sql);
        return new CreateTableMutation($tableName, $definition, $this->registry, $sql, $ifNotExists);
    }

    /**
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveDropTable(DropStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->fields === null || $statement->fields === []) {
            throw new UnsupportedSqlException($sql, 'No tables specified');
        }

        $tableExpr = $statement->fields[0];
        $tableName = ($tableExpr->table ?? $tableExpr->expr ?? null);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifExists = $statement->options !== null && ($statement->options->has('IF EXISTS') !== false);

        if (!$ifExists && !$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return new DropTableMutation($tableName, $this->registry, $sql, $ifExists);
    }

    /**
     * Resolve Alter Table for the supplied MySQL input.
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveAlterTable(AlterStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->table === null || $statement->table->table === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $tableName = $statement->table->table;

        if (!$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return new AlterTableMutation($tableName, $statement, $this->registry, $this->schemaParser);
    }

    /**
     * Extract column names from a SELECT statement for CREATE TABLE AS SELECT.
     *
     * @return list<string>
     */
    public function extractSelectColumnNames(\PhpMyAdmin\SqlParser\Statements\SelectStatement $selectStatement): array
    {
        /**
         * @var list<string> $columns
         */
        $columns = [];

        if ($selectStatement->expr === []) {
            return $columns;
        }

        foreach ($selectStatement->expr as $expr) {
            if (property_exists($expr, 'alias') && is_string($expr->alias) && $expr->alias !== '') {
                $columns[] = $expr->alias;
            } elseif (property_exists($expr, 'column') && is_string($expr->column) && $expr->column !== '') {
                $columns[] = $expr->column;
            } elseif (property_exists($expr, 'expr') && is_string($expr->expr) && $expr->expr !== '' && $expr->expr !== '*') {
                $replaced = preg_replace('/[^a-zA-Z0-9_]/', '_', $expr->expr);
                $columns[] = is_string($replaced) ? $replaced : 'col';
            }
        }

        return $columns;
    }
}
