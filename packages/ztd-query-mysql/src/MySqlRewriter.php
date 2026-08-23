<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Platform\MySql\MySqlCteShadowComposer;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\RewriteStateCommitter;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Sql\TransactionStatement;
use PhpMyAdmin\SqlParser\Statement;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;

/**
 * MySQL rewrite implementation for ZTD.
 *
 * Orchestrates parsing, classification, transformation, and mutation resolution.
 */
final class MySqlRewriter implements SqlRewriter, RewriteStateCommitter
{
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return (new MySqlTransactionStatementParser())->parse($sql);
    }

    private MySqlQueryGuard $guard;
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private MySqlTransformer $transformer;
    private MySqlMutationResolver $mutationResolver;
    private MySqlParser $parser;
    private MySqlCteShadowComposer $cteComposer;
    private ViewDefinitionSet $views;

    public function __construct(
        MySqlQueryGuard $guard,
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        MySqlTransformer $transformer,
        MySqlMutationResolver $mutationResolver,
        MySqlParser $parser,
        ?ViewDefinitionSet $views = null,
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = $transformer;
        $this->mutationResolver = $mutationResolver;
        $this->parser = $parser;
        $this->cteComposer = new MySqlCteShadowComposer();
        $this->views = $views ?? new ViewDefinitionSet();
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty, unparseable, or multi-statement.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewrite(string $sql): RewritePlan
    {
        $logicalStatements = $this->parser->splitStatements($sql);
        if ($logicalStatements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }
        if (count($logicalStatements) !== 1) {
            throw new UnsupportedSqlException($sql, 'Multi-statement');
        }
        if (MySqlReadOnlyDiagnosticStatement::isSafe($sql)) {
            return new RewritePlan($sql, QueryKind::READ);
        }

        $statement = $this->parser->parseSingleLogicalStatement($sql);
        if ($statement === null) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        return $this->rewriteStatement($statement, $sql);
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException When SQL is empty or unparseable.
     * @throws UnknownSchemaException When SQL references unknown tables/columns.
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $statements = $this->splitStatements($sql);

        if ($statements === []) {
            throw new UnsupportedSqlException($sql, 'Empty or unparseable');
        }

        $plans = [];
        foreach ($statements as $statement) {
            $plans[] = $this->rewrite($statement);
        }

        return new MultiRewritePlan($plans);
    }

    /** {@inheritDoc} */
    public function splitStatements(string $sql): array
    {
        return $this->parser->splitStatements($sql);
    }

    public function commitRewriteState(): void
    {
        $this->transformer->commitRewriteState();
    }

    private function rewriteStatement(Statement $statement, string $sql): RewritePlan
    {
        $kind = $statement instanceof WithStatement
            ? $this->guard->classify($sql)
            : $this->guard->classifyStatement($statement);
        if ($kind === null) {
            throw new UnsupportedSqlException($sql, 'Statement type not supported');
        }

        if ($statement instanceof LoadStatement) {
            return $this->rewrite((new MySqlLoadDataProjector($this->registry))->project($sql, $statement));
        }

        $tableContext = $this->buildTableContext();

        if ($kind === QueryKind::READ) {
            if ($this->hasSchemaContext()) {
                $unknownTable = $this->findUnknownTable($sql);
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

            return new RewritePlan($this->emptyResultSelect(), QueryKind::DDL_SIMULATED, $mutation);
        }

        $mutationStatement = $statement;
        if ($statement instanceof WithStatement) {
            $mainStatements = $this->parser->parse($this->cteComposer->statementSql($sql));
            $mutationStatement = $mainStatements[0] ?? $statement;
        }

        $mutation = $this->mutationResolver->resolve($sql, $mutationStatement, $kind);

        if ($statement instanceof TruncateStatement) {
            return new RewritePlan($this->emptyResultSelect(), QueryKind::WRITE_SIMULATED, $mutation);
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
     * @return array<string, array{viewSql: string}|array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, \ZtdQuery\Schema\ColumnType>,
     *     columnDefaults: array<string, string>,
     *     identityStrategies: array<string, \ZtdQuery\Schema\IdentityGenerationStrategy>,
     *     generatedExpressions: array<string, string>,
     *     partitioning: \ZtdQuery\Schema\TablePartitioning|null
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
            $columnDefaults = $definition !== null ? $definition->columnDefaults : [];
            $identityStrategies = $definition !== null ? $definition->identityStrategies : [];
            $generatedExpressions = $definition !== null ? $definition->generatedExpressions : [];

            $context[$tableName] = [
                'rows' => $rows,
                'columns' => $columns ?? [],
                'columnTypes' => $columnTypes,
                'columnDefaults' => $columnDefaults,
                'identityStrategies' => $identityStrategies,
                'generatedExpressions' => $generatedExpressions,
                'partitioning' => $definition?->partitioning,
                'primaryKeys' => $definition !== null ? $definition->primaryKeys : [],
                'candidateKeys' => $definition !== null ? $definition->candidateKeys()->keys() : [],
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
                'columnDefaults' => $definition->columnDefaults,
                'identityStrategies' => $definition->identityStrategies,
                'generatedExpressions' => $definition->generatedExpressions,
                'partitioning' => $definition->partitioning,
                'primaryKeys' => $definition->primaryKeys,
                'candidateKeys' => $definition->candidateKeys()->keys(),
            ];
        }

        foreach ((new MySqlViewShadowRenderer())->render($this->views, array_keys($context)) as $viewName => $viewSql) {
            if (isset($context[$viewName])) {
                continue;
            }
            $context[$viewName] = ['viewSql' => $viewSql];
        }

        return $context;
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

    private function findUnknownTable(string $sql): ?string
    {
        $tableNames = (new MySqlSelectRelationParser())->tableNames($sql);
        $declaredCtes = array_fill_keys($this->cteComposer->declaredCteNames($sql), true);

        foreach ($tableNames as $tableName) {
            if (isset($declaredCtes[strtolower($tableName)])) {
                continue;
            }
            if (!$this->tableExists($tableName)) {
                return $tableName;
            }
        }

        return null;
    }

    private function tableExists(string $tableName): bool
    {
        if ($this->shadowStore->has($tableName)) {
            return true;
        }

        if ($this->registry->has($tableName)) {
            return true;
        }

        if ($this->views->has($tableName)) {
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

        if ($this->views->hasAnyViews()) {
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

    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
