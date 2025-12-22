<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;

/**
 * MySQL rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 */
final class MySqlRewriter implements SqlRewriter
{
    private MySqlQueryGuard $guard;
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private MySqlTransformer $transformer;
    private MySqlMutationResolver $mutationResolver;
    private MySqlParser $parser;

    public function __construct(
        MySqlQueryGuard $guard,
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        MySqlTransformer $transformer,
        MySqlMutationResolver $mutationResolver,
        MySqlParser $parser
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = $transformer;
        $this->mutationResolver = $mutationResolver;
        $this->parser = $parser;
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty, unparseable, or multi-statement.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewrite(string $sql): RewritePlan
    {
        $statements = $this->parser->parse($sql);
        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        if (count($statements) === 1) {
            return $this->rewriteStatement($statements[0], $sql);
        }

        throw new UnsupportedSqlException($sql, 'Multi-statement');
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty or unparseable.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $statements = $this->parser->parse($sql);

        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $plans = [];
        foreach ($statements as $statement) {
            $stmtSql = $statement->build();
            $plans[] = $this->rewriteStatement($statement, $stmtSql);
        }

        return new MultiRewritePlan($plans);
    }

    private function rewriteStatement(Statement $statement, string $sql): RewritePlan
    {
        $kind = $statement instanceof WithStatement
            ? $this->guard->classify($sql)
            : $this->guard->classifyStatement($statement);
        if ($kind === null) {
            throw new UnsupportedSqlException($sql, 'Statement type not supported');
        }

        $tableContext = $this->buildTableContext();

        if ($kind === QueryKind::READ) {
            if ($statement instanceof SelectStatement && $this->hasSchemaContext()) {
                $unknownTable = $this->findUnknownTable($statement);
                if ($unknownTable !== null) {
                    throw new UnknownSchemaException($sql, $unknownTable, 'table');
                }
            }

            $transformedSql = $this->transformer->transform($sql, $tableContext);
            return new RewritePlan($transformedSql, QueryKind::READ);
        }

        if ($kind === QueryKind::DDL_SIMULATED) {
            if ($statement instanceof AlterStatement) {
                if ($this->hasUnsupportedAlterOperation($statement, $sql)) {
                    throw new UnsupportedSqlException($sql, 'Unsupported ALTER TABLE operation');
                }
            }

            $mutation = $this->mutationResolver->resolve($sql, $statement, $kind);

            if ($statement instanceof CreateStatement && $statement->select !== null) {
                $selectSql = $statement->select->build();
                $transformedSelectSql = $this->transformer->transform($selectSql, $tableContext);
                return new RewritePlan($transformedSelectSql, QueryKind::DDL_SIMULATED, $mutation);
            }

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::DDL_SIMULATED, $mutation);
        }

        if ($statement instanceof UpdateStatement || $statement instanceof DeleteStatement || $statement instanceof InsertStatement) {
            $this->ensureDmlTables($statement, $sql);
        }

        $mutation = $this->mutationResolver->resolve($sql, $statement, $kind);

        if ($statement instanceof TruncateStatement) {
            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::WRITE_SIMULATED, $mutation);
        }

        if ($statement instanceof ReplaceStatement) {
            $this->ensureReplaceColumns($statement, $sql);
        }

        $transformedSql = $this->transformer->transform($sql, $tableContext);
        return new RewritePlan($transformedSql, QueryKind::WRITE_SIMULATED, $mutation);
    }

    /**
     * Build the table context map for transformers.
     *
     * @return array<string, array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, \ZtdQuery\Schema\ColumnType>
     * }>
     */
    private function buildTableContext(): array
    {
        $context = [];
        $allData = $this->shadowStore->getAll();

        foreach ($allData as $tableName => $rows) {
            $definition = $this->registry->get($tableName);
            $columns = $definition?->columns;
            if ($columns === null && $rows !== []) {
                $columns = array_keys($rows[0]);
                foreach ($rows as $row) {
                    foreach (array_keys($row) as $column) {
                        if (!in_array($column, $columns, true)) {
                            $columns[] = $column;
                        }
                    }
                }
            }

            $columnTypes = $definition !== null ? $definition->typedColumns : [];

            $context[$tableName] = [
                'rows' => $rows,
                'columns' => $columns ?? [],
                'columnTypes' => $columnTypes,
            ];
        }

        $allDefinitions = $this->registry->getAll();
        foreach ($allDefinitions as $tableName => $definition) {
            if (isset($context[$tableName])) {
                continue;
            }

            $context[$tableName] = [
                'rows' => [],
                'columns' => $definition->columns,
                'columnTypes' => $definition->typedColumns,
            ];
        }

        return $context;
    }

    /**
     * Ensure DML target tables exist in shadow store.
     */
    private function ensureDmlTables(Statement $statement, string $sql): void
    {
        if ($statement instanceof UpdateStatement) {
            if ($statement->tables === [] || !isset($statement->tables[0])) {
                return;
            }
            $targetExpr = $statement->tables[0];
            $targetTable = self::resolveExprTableName($targetExpr);
            if ($targetTable !== null) {
                $this->shadowStore->ensure($targetTable);
            }
        }

        if ($statement instanceof DeleteStatement) {
            if ($statement->from !== null && $statement->from !== []) {
                $targetExpr = $statement->from[0];
                $targetTable = self::resolveExprTableName($targetExpr);
                if ($targetTable !== null) {
                    $this->shadowStore->ensure($targetTable);
                }
            }
        }
    }

    /**
     * Ensure REPLACE has columns available.
     */
    private function ensureReplaceColumns(ReplaceStatement $statement, string $sql): void
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            return;
        }

        $columns = $statement->into->columns ?? [];
        $columns = array_values(array_filter($columns, 'is_string'));
        if ($columns !== []) {
            return;
        }

        $rows = $this->shadowStore->get($tableName);
        if ($rows !== []) {
            return;
        }

        $definition = $this->registry->get($tableName);
        if ($definition !== null) {
            return;
        }

        throw new UnsupportedSqlException($sql, 'Cannot determine columns');
    }

    private function findUnknownTable(SelectStatement $statement): ?string
    {
        $tableNames = $this->extractTableNames($statement);

        foreach ($tableNames as $tableName) {
            if (!$this->tableExists($tableName)) {
                return $tableName;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractTableNames(SelectStatement $statement): array
    {
        $tableNames = [];

        if ($statement->from !== []) {
            foreach ($statement->from as $fromExpr) {
                $tableName = self::resolveExprTableName($fromExpr);
                if (is_string($tableName) && $tableName !== '') {
                    $tableNames[] = $tableName;
                }
            }
        }

        if ($statement->join !== null && $statement->join !== []) {
            foreach ($statement->join as $joinExpr) {
                if ($joinExpr->expr !== null) {
                    $tableName = self::resolveExprTableName($joinExpr->expr);
                    if (is_string($tableName) && $tableName !== '') {
                        $tableNames[] = $tableName;
                    }
                }
            }
        }

        return $tableNames;
    }

    private function tableExists(string $tableName): bool
    {
        if ($this->shadowStore->get($tableName) !== []) {
            return true;
        }

        if ($this->registry->has($tableName)) {
            return true;
        }

        return false;
    }

    private function hasSchemaContext(): bool
    {
        if ($this->shadowStore->getAll() !== []) {
            return true;
        }

        if ($this->registry->hasAnyTables()) {
            return true;
        }

        return false;
    }

    /**
     * Check for unsupported ALTER TABLE operations.
     */
    /**
     * Check whether the given OptionsArray has a specific option set.
     *
     * @param \PhpMyAdmin\SqlParser\Components\OptionsArray $options
     */
    private static function optionSet(\PhpMyAdmin\SqlParser\Components\OptionsArray $options, string $name): bool
    {
        return $options->has($name) !== false;
    }

    private function hasUnsupportedAlterOperation(AlterStatement $statement, string $sql): bool
    {
        $upperSql = strtoupper($sql);
        if (str_contains($upperSql, 'SET DEFAULT') || str_contains($upperSql, 'DROP DEFAULT')) {
            return true;
        }
        if (str_contains($upperSql, 'ORDER BY')) {
            return true;
        }
        $altered = $statement->altered ?? [];

        foreach ($altered as $op) {
            $options = $op->options;
            if ($options->isEmpty()) {
                continue;
            }

            if (self::optionSet($options, 'ADD')) {
                if (self::optionSet($options, 'INDEX') || self::optionSet($options, 'KEY') ||
                    self::optionSet($options, 'FULLTEXT') || self::optionSet($options, 'SPATIAL') ||
                    self::optionSet($options, 'UNIQUE') || self::optionSet($options, 'CONSTRAINT')) {
                    return true;
                }
            }

            if (self::optionSet($options, 'DROP')) {
                if (self::optionSet($options, 'INDEX') || self::optionSet($options, 'KEY') || self::optionSet($options, 'CONSTRAINT')) {
                    return true;
                }
            }

            if (self::optionSet($options, 'RENAME')) {
                if (self::optionSet($options, 'INDEX') || self::optionSet($options, 'KEY')) {
                    return true;
                }
            }

            if (self::optionSet($options, 'ALTER')) {
                if (self::optionSet($options, 'SET DEFAULT') || self::optionSet($options, 'DROP DEFAULT')) {
                    return true;
                }
                $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
                foreach ($unknownTokens as $token) {
                    $tokenValue = is_string($token->value) ? $token->value : '';
                    $value = strtoupper($tokenValue);
                    if ($value === 'SET' || $value === 'DROP') {
                        return true;
                    }
                }
            }

            if (self::optionSet($options, 'ORDER') || self::optionSet($options, 'ORDER BY')) {
                return true;
            }

            $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
            foreach ($unknownTokens as $token) {
                $tokenValue = is_string($token->value) ? $token->value : '';
                $value = strtoupper($tokenValue);
                if ($value === 'ORDER BY' || $value === 'ORDER') {
                    return true;
                }
            }

            if (self::optionSet($options, 'CONVERT')) {
                return true;
            }

            if (self::optionSet($options, 'ENGINE')) {
                return true;
            }

            if (self::optionSet($options, 'PARTITION') || self::optionSet($options, 'ADD PARTITION') ||
                self::optionSet($options, 'DROP PARTITION') || self::optionSet($options, 'TRUNCATE PARTITION') ||
                self::optionSet($options, 'COALESCE PARTITION') || self::optionSet($options, 'REORGANIZE PARTITION') ||
                self::optionSet($options, 'EXCHANGE PARTITION') || self::optionSet($options, 'ANALYZE PARTITION') ||
                self::optionSet($options, 'CHECK PARTITION') || self::optionSet($options, 'OPTIMIZE PARTITION') ||
                self::optionSet($options, 'REBUILD PARTITION') || self::optionSet($options, 'REPAIR PARTITION') ||
                self::optionSet($options, 'REMOVE PARTITIONING')) {
                return true;
            }

            $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
            foreach ($unknownTokens as $token) {
                $tokenValue = is_string($token->value) ? $token->value : '';
                $value = strtoupper($tokenValue);
                if (str_contains($value, 'PARTITION') || str_contains($value, 'ENGINE') ||
                    str_contains($value, 'SPATIAL') || str_contains($value, 'FULLTEXT')) {
                    return true;
                }
            }
        }

        return false;
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
     * Resolve table name from an INTO clause.
     */
    private static function resolveIntoTableName(?\PhpMyAdmin\SqlParser\Components\IntoKeyword $into): ?string
    {
        if ($into === null || $into->dest === null) {
            return null;
        }
        $dest = $into->dest;
        return is_string($dest) ? $dest : ($dest->table ?? null);
    }
}
