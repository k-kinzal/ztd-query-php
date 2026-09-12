<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Statement;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
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
 * Row Mutation.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class RowMutation
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private DeleteTransformer $deleteTransformer, private TableDefinitionRegistry $registry, private ShadowStore $shadowStore, private UpdateTransformer $updateTransformer)
    {
    }
    /**
     * Resolve Update for the supplied MySQL input.
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveUpdate(UpdateStatement $statement, string $sql): ShadowMutation
    {
        if ($statement->tables === [] || !isset($statement->tables[0])) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }

        $targetExpr = $statement->tables[0];
        $targetTable = ($targetExpr->table ?? $targetExpr->expr ?? null);
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
     * Resolve Delete for the supplied MySQL input.
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveDelete(DeleteStatement $statement, string $sql): ShadowMutation
    {
        $targetTable = null;
        if ($statement->from !== null && $statement->from !== []) {
            $targetExpr = $statement->from[0];
            $targetTable = ($targetExpr->table ?? $targetExpr->expr ?? null);
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
     * @param list<string> $tableNames
     * @return list<MultiTableMutationTarget>
     * @throws UnknownSchemaException
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
     * Resolve Insert for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function resolveInsert(InsertStatement $statement, string $sql): ShadowMutation
    {
        $tableName = TargetName::resolveIntoTableName($statement->into);
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

        $isIgnore = $statement->options !== null && ($statement->options->has('IGNORE') !== false);

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
     * Resolve Truncate for the supplied MySQL input.
     * @throws UnsupportedSqlException
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
     * Resolve Replace for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function resolveReplace(ReplaceStatement $statement, string $sql): ShadowMutation
    {
        $tableName = TargetName::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve REPLACE target');
        }

        $definition = $this->registry->get($tableName);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        return new ReplaceMutation($tableName, $primaryKeys, $definition?->candidateKeys());
    }
}
