<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

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
use ZtdQuery\Platform\MySql\Dialect\MySqlStatementOptions;
use ZtdQuery\Platform\MySql\Mutation\AlterTableMutation;
use ZtdQuery\Platform\MySql\Parse\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\Parse\MySqlUpsertExpressionParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiTableMutationTarget;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTableState;

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

    /**
     * Binds the instance to what it will work from.
     *
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     * @param SchemaParser $schemaParser
     * @param UpdateTransformer $updateTransformer
     * @param DeleteTransformer $deleteTransformer
     */
    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser,
        UpdateTransformer $updateTransformer,
        DeleteTransformer $deleteTransformer,
        private readonly MySqlStatementOptions $options = new MySqlStatementOptions(),
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

    /**
     * Answers what an UPDATE would do to the shadow.
     *
     * A statement writing to several tables is one mutation per table, because
     * the shadow holds each table on its own.
     *
     * @param UpdateStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement names no table ZTD can resolve
     * @throws UnknownSchemaException When nothing has declared a table it writes to
     */
    public function resolveUpdate(UpdateStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->tables === [] || !isset($statement->tables[0])) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }

        $targetExpr = $statement->tables[0];
        $targetTable = self::resolveExprTableName($targetExpr);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }
        $definition = $this->registry->get($targetTable);
        if ($definition === null && $this->shadowStore->state($targetTable) !== ShadowTableState::Initialized) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $this->shadowStore->ensure($targetTable);
        $columns = $this->shadowStore->get($targetTable);
        $columnNames = $columns !== [] ? array_keys($columns[0]) : null;
        if ($columnNames === null) {
            $columnNames = $definition?->columns;
        }
        if ($columnNames === null) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $projection = $this->updateTransformer->buildProjection($statement, $columnNames);

        $tables = $projection['tables'];
        if (count($tables) > 1) {
            return new MultiUpdateMutation($this->multiTableTargets(array_keys($tables), $sql));
        }

        $definition = $this->registry->get($targetTable);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new UpdateMutation($targetTable, $primaryKeys);
    }

    /**
     * Answers what a DELETE would do to the shadow.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement names no table ZTD can resolve
     * @throws UnknownSchemaException When nothing has declared a table it deletes from
     */
    public function resolveDelete(DeleteStatement $statement, string $sql): ShadowMutation
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

        if (!$this->registry->has($targetTable) && $this->shadowStore->state($targetTable) !== ShadowTableState::Initialized) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $this->shadowStore->ensure($targetTable);

        $tables = $projection['tables'];
        if (count($tables) > 1) {
            return new MultiDeleteMutation($this->multiTableTargets(array_keys($tables), $sql));
        }

        $definition = $this->registry->get($targetTable);

        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new DeleteMutation($targetTable, $primaryKeys);
    }

    /**
     * Answers one target per table a multi-table statement writes to.
     *
     * @param list<string> $tableNames Tables the statement writes to
     * @param string $sql The statement, as written
     *
     * @return list<MultiTableMutationTarget> The targets, in the order the tables were given
     *
     * @throws UnknownSchemaException When nothing has declared one of them
     */
    public function multiTableTargets(array $tableNames, string $sql): array
    {
        $targets = [];
        foreach ($tableNames as $tableName) {
            $definition = $this->registry->get($tableName);
            if ($definition === null && $this->shadowStore->state($tableName) !== ShadowTableState::Initialized) {
                throw new UnknownSchemaException($sql, $tableName, 'table');
            }
            if ($definition !== null) {
                $columns = $definition->columns;
                $primaryKeys = $definition->primaryKeys;
            } else {
                $rows = $this->shadowStore->get($tableName);
                $columns = $rows !== [] ? array_keys($rows[0]) : [];
                $primaryKeys = [];
            }
            $this->shadowStore->ensure($tableName);
            $targets[] = new MultiTableMutationTarget($tableName, $columns, $primaryKeys);
        }

        return $targets;
    }

    /**
     * Answers what an INSERT would do to the shadow.
     *
     * @param InsertStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement names no table ZTD can resolve
     */
    public function resolveInsert(InsertStatement $statement, string $sql): ShadowMutation
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }

        $extractor = new MySqlUpsertAssignmentExtractor();
        $incomingAlias = $extractor->incomingAlias($sql);
        $expressionParser = new MySqlUpsertExpressionParser();
        $rawUpdateValues = $extractor->extract($sql);
        $definition = $this->registry->get($tableName);
        $databaseEvaluated = $definition !== null && $definition->candidateKeys()->keys() !== [];
        $updateValues = [];
        foreach ($rawUpdateValues as $column => $expression) {
            $updateValues[$column] = $databaseEvaluated
                ? $expressionParser->parseIfSupported($expression, $tableName, $incomingAlias)
                : $expressionParser->parse($expression, $tableName, $incomingAlias);
        }
        $updateColumns = array_keys($updateValues);
        $isOnDuplicateKeyUpdate = $updateColumns !== [];

        $isIgnore = $statement->options !== null && $this->options->isSet($statement->options, 'IGNORE');

        if ($isOnDuplicateKeyUpdate) {
            $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
            return new UpsertMutation(
                $tableName,
                $primaryKeys,
                $updateColumns,
                $updateValues,
                $definition?->candidateKeys(),
                databaseEvaluated: $databaseEvaluated,
                updateSqlValues: $rawUpdateValues,
            );
        }

        $definition = $this->registry->get($tableName);
        $primaryKeys = $isIgnore ? ($definition !== null ? $definition->primaryKeys : []) : [];
        return new InsertMutation(
            $tableName,
            $primaryKeys,
            $isIgnore,
            candidateKeys: $definition?->candidateKeys(),
        );
    }

    /**
     * Answers what a TRUNCATE would do to the shadow.
     *
     * @param TruncateStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement names no table ZTD can resolve
     */
    public function resolveTruncate(TruncateStatement $statement, string $sql): ShadowMutation
    {
        $tableName = $statement->table->table ?? null;
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        return new TruncateMutation($tableName);
    }

    /**
     * Answers what a REPLACE would do to the shadow.
     *
     * @param ReplaceStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement names no table ZTD can resolve
     */
    public function resolveReplace(ReplaceStatement $statement, string $sql): ShadowMutation
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve REPLACE target');
        }

        $definition = $this->registry->get($tableName);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new ReplaceMutation($tableName, $primaryKeys, $definition?->candidateKeys());
    }

    /**
     * Answers what a CREATE TABLE would do to the shadow.
     *
     * @param CreateStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement declares something ZTD cannot simulate
     * @throws UnknownSchemaException When it is declared from a table nothing has declared
     */
    public function resolveCreateTable(CreateStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->name === null || $statement->name->table === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $tableName = $statement->name->table;
        $ifNotExists = $statement->options !== null && $this->options->isSet($statement->options, 'IF NOT EXISTS');

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
                new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR'),
                $ifNotExists,
            );
        }

        $definition = $this->schemaParser->parse($sql);
        return new CreateTableMutation($tableName, $definition, $this->registry, $sql, $ifNotExists);
    }

    /**
     * Answers what a DROP would do to the shadow.
     *
     * @param DropStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement drops something ZTD does not model
     * @throws UnknownSchemaException When nothing has declared what it drops
     */
    public function resolveDropTable(DropStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->fields === null || $statement->fields === []) {
            throw new UnsupportedSqlException($sql, 'No tables specified');
        }

        $tableExpr = $statement->fields[0];
        $tableName = self::resolveExprTableName($tableExpr);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifExists = $statement->options !== null && $this->options->isSet($statement->options, 'IF EXISTS');

        if (!$ifExists && !$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return new DropTableMutation($tableName, $this->registry, $sql, $ifExists);
    }

    /**
     * Answers what an ALTER TABLE would do to the shadow.
     *
     * @param AlterStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return ShadowMutation What the statement would do
     *
     * @throws UnsupportedSqlException When the statement asks for something ZTD cannot simulate
     * @throws UnknownSchemaException When nothing has declared the table it alters
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
     * @return list<string> The names, or none where any of them cannot be named
     */
    public function extractSelectColumnNames(\PhpMyAdmin\SqlParser\Statements\SelectStatement $selectStatement): array
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
     * Answers the table an expression names.
     *
     * The parser fills in the table separately once it has read a qualified
     * name, and leaves the whole expression where it has not.
     *
     * @param \PhpMyAdmin\SqlParser\Components\Expression $expr Expression to read
     *
     * @return string|null The table, or null where the expression names none
     */
    public static function resolveExprTableName(\PhpMyAdmin\SqlParser\Components\Expression $expr): ?string
    {
        return $expr->table ?? $expr->expr ?? null;
    }

    /**
     * Answers the table an INTO clause names.
     *
     * @param \PhpMyAdmin\SqlParser\Components\IntoKeyword|null $into The clause, or null where the statement wrote none
     *
     * @return string|null The table, or null where the clause names none
     */
    public static function resolveIntoTableName(?\PhpMyAdmin\SqlParser\Components\IntoKeyword $into): ?string
    {
        if ($into === null || $into->dest === null) {
            return null;
        }
        $dest = $into->dest;
        return is_string($dest) ? $dest : ($dest->table ?? null);
    }

}
