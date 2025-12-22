<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\DropStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Resolves the appropriate ShadowMutation for a given SQL statement.
 *
 * This class depends on domain state (ShadowStore, TableDefinitionRegistry) to determine
 * primary keys, column metadata, and table existence needed for mutation construction.
 */
final class MySqlMutationResolver
{
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private SchemaParser $schemaParser;
    private UpdateTransformer $updateTransformer;
    private DeleteTransformer $deleteTransformer;

    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser,
        UpdateTransformer $updateTransformer,
        DeleteTransformer $deleteTransformer
    ) {
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->schemaParser = $schemaParser;
        $this->updateTransformer = $updateTransformer;
        $this->deleteTransformer = $deleteTransformer;
    }

    /**
     * Resolve mutation for a given statement.
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolve(string $sql, Statement $statement, QueryKind $kind): ?ShadowMutation
    {
        if ($statement instanceof UpdateStatement) {
            return $this->resolveUpdate($statement, $sql);
        }

        if ($statement instanceof DeleteStatement) {
            return $this->resolveDelete($statement, $sql);
        }

        if ($statement instanceof InsertStatement) {
            return $this->resolveInsert($statement, $sql);
        }

        if ($statement instanceof TruncateStatement) {
            return $this->resolveTruncate($statement, $sql);
        }

        if ($statement instanceof ReplaceStatement) {
            return $this->resolveReplace($statement, $sql);
        }

        if ($kind === QueryKind::DDL_SIMULATED) {
            if ($statement instanceof CreateStatement) {
                return $this->resolveCreateTable($statement, $sql);
            }
            if ($statement instanceof DropStatement) {
                return $this->resolveDropTable($statement, $sql);
            }
            if ($statement instanceof AlterStatement) {
                return $this->resolveAlterTable($statement, $sql);
            }
        }

        return null;
    }

    private function resolveUpdate(UpdateStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->tables === [] || !isset($statement->tables[0])) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }

        $targetExpr = $statement->tables[0];
        $targetTable = self::resolveExprTableName($targetExpr);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }
        $this->shadowStore->ensure($targetTable);

        $columns = $this->shadowStore->get($targetTable);
        $columnNames = $columns !== [] ? array_keys($columns[0]) : null;
        if ($columnNames === null) {
            $definition = $this->registry->get($targetTable);
            $columnNames = $definition?->columns;
        }
        if ($columnNames === null) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $projection = $this->updateTransformer->buildProjection($statement, $columnNames);

        $tables = $projection['tables'];
        if (count($tables) > 1) {
            /** @var array<string, array<int, string>> $tablesPrimaryKeys */
            $tablesPrimaryKeys = [];
            foreach ($tables as $tableName => $tableInfo) {
                $definition = $this->registry->get($tableName);
                $existingRows = $this->shadowStore->get($tableName);
                if ($definition === null && $existingRows === []) {
                    throw new UnknownSchemaException($sql, $tableName, 'table');
                }
                $this->shadowStore->ensure($tableName);
                $tablesPrimaryKeys[$tableName] = $definition !== null ? $definition->primaryKeys : [];
            }
            return new MultiUpdateMutation($tablesPrimaryKeys);
        }

        $definition = $this->registry->get($targetTable);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new UpdateMutation($targetTable, $primaryKeys);
    }

    private function resolveDelete(DeleteStatement $statement, string $sql): ShadowMutation
    {
        $targetTable = null;
        if ($statement->from !== null && $statement->from !== []) {
            $targetExpr = $statement->from[0];
            $targetTable = self::resolveExprTableName($targetExpr);
        }

        $columnNames = [];
        if ($targetTable !== null) {
            $rows = $this->shadowStore->get($targetTable);
            $definition = $this->registry->get($targetTable);
            $columnNames = $rows !== [] ? array_keys($rows[0]) : ($definition !== null ? $definition->columns : []);
        }

        $projection = $this->deleteTransformer->buildProjection($statement, $sql, $columnNames);
        $targetTable = $projection['table'];

        if ($targetTable === 'unknown') {
            throw new UnsupportedSqlException($sql, 'Cannot resolve DELETE target');
        }

        $this->shadowStore->ensure($targetTable);

        $tables = $projection['tables'];
        if (count($tables) > 1) {
            /** @var array<string, array<int, string>> $tablesPrimaryKeys */
            $tablesPrimaryKeys = [];
            foreach ($tables as $tableName => $tableInfo) {
                $definition = $this->registry->get($tableName);
                $existingRows = $this->shadowStore->get($tableName);
                if ($definition === null && $existingRows === []) {
                    throw new UnknownSchemaException($sql, $tableName, 'table');
                }
                $this->shadowStore->ensure($tableName);
                $tablesPrimaryKeys[$tableName] = $definition !== null ? $definition->primaryKeys : [];
            }
            return new MultiDeleteMutation($tablesPrimaryKeys);
        }

        $definition = $this->registry->get($targetTable);
        $existingRows = $this->shadowStore->get($targetTable);
        if ($definition === null && $existingRows === []) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new DeleteMutation($targetTable, $primaryKeys);
    }

    private function resolveInsert(InsertStatement $statement, string $sql): ShadowMutation
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }

        $updateColumns = [];
        /** @var array<string, string> $updateValues */
        $updateValues = [];
        if ($statement->onDuplicateSet !== null && $statement->onDuplicateSet !== []) {
            foreach ($statement->onDuplicateSet as $set) {
                $colName = trim($set->column, '`"\'');
                if (str_contains($colName, '.')) {
                    $parts = explode('.', $colName);
                    $colName = trim(end($parts), '`"\'');
                }
                $updateColumns[] = $colName;
                $updateValues[$colName] = $set->value;
            }
        }
        $isOnDuplicateKeyUpdate = $updateColumns !== [];

        $isIgnore = $statement->options !== null && self::optionSet($statement->options, 'IGNORE');

        if ($isOnDuplicateKeyUpdate) {
            $definition = $this->registry->get($tableName);
            $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
            return new UpsertMutation($tableName, $primaryKeys, $updateColumns, $updateValues);
        }

        $definition = $this->registry->get($tableName);
        $primaryKeys = $isIgnore ? ($definition !== null ? $definition->primaryKeys : []) : [];
        return new InsertMutation($tableName, $primaryKeys, $isIgnore);
    }

    private function resolveTruncate(TruncateStatement $statement, string $sql): ShadowMutation
    {
        $tableName = $statement->table->table ?? null;
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        return new TruncateMutation($tableName);
    }

    private function resolveReplace(ReplaceStatement $statement, string $sql): ShadowMutation
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve REPLACE target');
        }

        $definition = $this->registry->get($tableName);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new ReplaceMutation($tableName, $primaryKeys);
    }

    /**
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    private function resolveCreateTable(CreateStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->name === null || $statement->name->table === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $tableName = $statement->name->table;
        $ifNotExists = $statement->options !== null && self::optionSet($statement->options, 'IF NOT EXISTS');

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
            return new CreateTableAsSelectMutation($tableName, $columnNames, $this->registry, $ifNotExists);
        }

        $definition = $this->schemaParser->parse($sql);
        return new CreateTableMutation($tableName, $definition, $this->registry, $ifNotExists);
    }

    /**
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    private function resolveDropTable(DropStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->fields === null || $statement->fields === []) {
            throw new UnsupportedSqlException($sql, 'No tables specified');
        }

        $tableExpr = $statement->fields[0];
        $tableName = self::resolveExprTableName($tableExpr);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifExists = $statement->options !== null && self::optionSet($statement->options, 'IF EXISTS');

        if (!$ifExists && !$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return new DropTableMutation($tableName, $this->registry, $ifExists);
    }

    private function resolveAlterTable(AlterStatement $statement, string $sql): ShadowMutation
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
     * @return array<int, string>
     */
    private function extractSelectColumnNames(\PhpMyAdmin\SqlParser\Statements\SelectStatement $selectStatement): array
    {
        /** @var list<string> $columns */
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

    /**
     * Resolve table name from an Expression, trying ->table first then ->expr.
     *
     * @param \PhpMyAdmin\SqlParser\Components\Expression $expr
     */
    private static function resolveExprTableName(\PhpMyAdmin\SqlParser\Components\Expression $expr): ?string
    {
        return $expr->table ?? $expr->expr ?? null;
    }

    /**
     * Resolve table name from an INTO clause (InsertStatement or ReplaceStatement).
     */
    private static function resolveIntoTableName(?\PhpMyAdmin\SqlParser\Components\IntoKeyword $into): ?string
    {
        if ($into === null || $into->dest === null) {
            return null;
        }
        $dest = $into->dest;
        return is_string($dest) ? $dest : ($dest->table ?? null);
    }

    /**
     * Check whether the given OptionsArray has a specific option set.
     *
     * @param \PhpMyAdmin\SqlParser\Components\OptionsArray $options
     */
    private static function optionSet(\PhpMyAdmin\SqlParser\Components\OptionsArray $options, string $name): bool
    {
        return $options->has($name) !== false;
    }
}
