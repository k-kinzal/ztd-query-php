<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\QueryGuard;
use ZtdQuery\Rewrite\Projection\WriteProjection;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Rewrite\SqlRewriter;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use ZtdQuery\Platform\SqlDialect;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\DropStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Shadow\Mutation\AlterTableMutation;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;

/**
 * MySQL rewrite implementation for ZTD.
 */
final class MySqlRewriter implements SqlRewriter
{
    /**
     * Guard used to classify and block SQL.
     *
     * @var QueryGuard
     */
    private QueryGuard $guard;

    /**
     * Shadow store used during rewrite.
     *
     * @var ShadowStore
     */
    private ShadowStore $shadowStore;

    /**
     * CTE shadowing helper for read queries.
     *
     * @var CteShadowing
     */
    private CteShadowing $shadowing;

    /**
     * Write projection handler for UPDATE/DELETE/INSERT.
     *
     * @var WriteProjection
     */
    private WriteProjection $writeProjection;

    /**
     * Dialect adapter for parsing and emitting SQL.
     *
     * @var SqlDialect
     */
    private SqlDialect $dialect;

    /**
     * Optional schema registry for schema lookups.
     *
     * @var SchemaRegistry|null
     */
    private ?SchemaRegistry $schemaRegistry;

    /**
     * @param QueryGuard $guard Query classifier/guard.
     * @param ShadowStore $shadowStore Shadow data source.
     * @param CteShadowing $shadowing CTE shadowing handler.
     * @param WriteProjection $writeProjection Write projection handler.
     * @param SqlDialect|null $dialect SQL dialect adapter.
     * @param SchemaRegistry|null $schemaRegistry Optional schema registry.
     */
    public function __construct(
        QueryGuard $guard,
        ShadowStore $shadowStore,
        CteShadowing $shadowing,
        WriteProjection $writeProjection,
        ?SqlDialect $dialect = null,
        ?SchemaRegistry $schemaRegistry = null
    ) {
        $this->guard = $guard;
        $this->shadowStore = $shadowStore;
        $this->shadowing = $shadowing;
        $this->writeProjection = $writeProjection;
        $this->dialect = $dialect ?? new MySqlDialect();
        $this->schemaRegistry = $schemaRegistry;
    }

    /**
     * {@inheritDoc}
     */
    public function rewrite(string $sql): RewritePlan
    {
        $statements = $this->dialect->parse($sql);
        if ($statements === []) {
            return new RewritePlan($sql, QueryKind::FORBIDDEN);
        }

        // For single statement, process directly
        if (count($statements) === 1) {
            return $this->rewriteStatement($statements[0], $sql);
        }

        // For multiple statements, return FORBIDDEN for backwards compatibility
        // Use rewriteMultiple() for multi-statement support
        return new RewritePlan($sql, QueryKind::FORBIDDEN);
    }

    /**
     * {@inheritDoc}
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $parser = MySqlDialect::createParser($sql);
        $statements = $parser->statements;

        if ($statements === []) {
            return new MultiRewritePlan([new RewritePlan($sql, QueryKind::FORBIDDEN)]);
        }

        $plans = [];
        foreach ($statements as $statement) {
            $stmtSql = $statement->build();
            $plans[] = $this->rewriteStatement($statement, $stmtSql);
        }

        return new MultiRewritePlan($plans);
    }

    /**
     * Rewrite a single parsed statement.
     *
     * @param Statement $statement The parsed statement.
     * @param string $sql The original SQL for this statement.
     * @return RewritePlan The rewrite plan for this statement.
     */
    private function rewriteStatement(Statement $statement, string $sql): RewritePlan
    {
        $kind = $this->guard->classifyStatement($statement);
        if ($kind === QueryKind::FORBIDDEN) {
            return new RewritePlan($sql, $kind);
        }

        if ($kind === QueryKind::READ) {
            // Validate that all referenced tables exist in schema or shadow store
            // Only validate if we have schema context (registered tables or shadow data)
            if ($statement instanceof SelectStatement && $this->hasSchemaContext()) {
                $unknownTable = $this->findUnknownTable($statement);
                if ($unknownTable !== null) {
                    return new RewritePlan($sql, QueryKind::UNKNOWN_SCHEMA, null, $unknownTable);
                }
            }

            $shadowedSql = $this->shadowing->apply($sql, $this->shadowStore->getAll());
            return new RewritePlan($shadowedSql, QueryKind::READ);
        }

        if ($statement instanceof UpdateStatement || $statement instanceof DeleteStatement || $statement instanceof InsertStatement) {
            return $this->writeProjection->project($sql, $statement);
        }

        if ($statement instanceof TruncateStatement) {
            return $this->projectTruncate($statement, $sql);
        }

        if ($statement instanceof ReplaceStatement) {
            return $this->projectReplace($statement, $sql);
        }

        if ($kind === QueryKind::DDL_SIMULATED) {
            if ($statement instanceof CreateStatement) {
                return $this->projectCreateTable($statement, $sql);
            }
            if ($statement instanceof DropStatement) {
                return $this->projectDropTable($statement, $sql);
            }
            if ($statement instanceof AlterStatement) {
                return $this->projectAlterTable($statement, $sql);
            }
        }

        return new RewritePlan($sql, QueryKind::FORBIDDEN);
    }

    /**
     * Project a TRUNCATE statement.
     *
     * @param TruncateStatement $statement The TRUNCATE statement.
     * @param string $sql The original SQL.
     */
    private function projectTruncate(TruncateStatement $statement, string $sql): RewritePlan
    {
        $tableName = $statement->table->table ?? null;
        if ($tableName === null) {
            return new RewritePlan($sql, QueryKind::FORBIDDEN);
        }

        // TRUNCATE returns no rows, so we use an empty SELECT
        $resultSql = 'SELECT 1 WHERE FALSE';

        return new RewritePlan($resultSql, QueryKind::WRITE_SIMULATED, new TruncateMutation($tableName));
    }

    /**
     * Project a REPLACE statement.
     */
    private function projectReplace(ReplaceStatement $statement, string $sql): RewritePlan
    {
        // The phpMyAdmin parser doesn't always correctly parse REPLACE statements,
        // especially edge cases like "REPLACE DELAYED table SELECT ...".
        // Return FORBIDDEN instead of throwing when we can't resolve the table.
        if ($statement->into === null || $statement->into->dest === null) {
            return new RewritePlan($sql, QueryKind::FORBIDDEN);
        }

        $dest = $statement->into->dest;
        $tableName = is_string($dest) ? $dest : ($dest->table ?? null);
        if ($tableName === null) {
            return new RewritePlan($sql, QueryKind::FORBIDDEN);
        }

        $columns = $statement->into->columns ?? [];
        $columns = array_values(array_filter($columns, 'is_string'));
        if ($columns === []) {
            $columns = $this->shadowStore->get($tableName) !== []
                ? array_keys($this->shadowStore->get($tableName)[0])
                : [];
        }
        // Try getting columns from schema registry
        if ($columns === [] && $this->schemaRegistry !== null) {
            $schemaColumns = $this->schemaRegistry->getColumns($tableName);
            if ($schemaColumns !== null) {
                $columns = $schemaColumns;
            }
        }
        if ($columns === []) {
            return new RewritePlan($sql, QueryKind::FORBIDDEN);
        }

        $selectSql = $this->buildReplaceSelect($statement, $columns);
        if ($selectSql === null) {
            // Invalid REPLACE statement (empty values, mismatched column count, etc.)
            return new RewritePlan($sql, QueryKind::FORBIDDEN);
        }
        $shadowedSql = $this->shadowing->apply($selectSql, $this->shadowStore->getAll());
        $primaryKeys = $this->schemaRegistry !== null ? $this->schemaRegistry->getPrimaryKeys($tableName) : [];

        return new RewritePlan($shadowedSql, QueryKind::WRITE_SIMULATED, new ReplaceMutation($tableName, $primaryKeys));
    }

    /**
     * Build SELECT SQL from REPLACE statement values.
     *
     * @param ReplaceStatement $statement The REPLACE statement.
     * @param array<int, string> $columns Column names.
     * @return string|null The SELECT SQL, or null if the statement is invalid.
     */
    private function buildReplaceSelect(ReplaceStatement $statement, array $columns): ?string
    {
        if ($statement->values !== null && $statement->values !== []) {
            $rows = [];
            foreach ($statement->values as $valueSet) {
                /** @var \PhpMyAdmin\SqlParser\Components\ArrayObj $valueSet */
                $values = $valueSet->raw !== [] ? $valueSet->raw : $valueSet->values;
                if (count($values) !== count($columns)) {
                    // Mismatched column count - invalid SQL
                    return null;
                }
                $selects = [];
                foreach ($columns as $index => $column) {
                    $expr = trim($values[$index]);
                    $selects[] = $expr . ' AS `' . $column . '`';
                }
                $rows[] = 'SELECT ' . implode(', ', $selects);
            }
            return implode(' UNION ALL ', $rows);
        }

        if ($statement->set !== null && $statement->set !== []) {
            $selects = [];
            foreach ($statement->set as $set) {
                $selects[] = $set->value . ' AS `' . $set->column . '`';
            }
            return 'SELECT ' . implode(', ', $selects);
        }

        // Handle REPLACE ... SELECT syntax
        if ($statement->select !== null) {
            // For REPLACE ... SELECT, just use the SELECT directly
            // The columns will be matched by position
            return $statement->select->build();
        }

        // No values to project - invalid REPLACE statement
        return null;
    }

    /**
     * Project a CREATE TABLE statement.
     */
    private function projectCreateTable(CreateStatement $statement, string $originalSql): RewritePlan
    {
        if ($statement->name === null || $statement->name->table === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        $tableName = $statement->name->table;

        // Check for IF NOT EXISTS option
        $ifNotExists = $statement->options !== null && $statement->options->has('IF NOT EXISTS');

        // Check for TEMPORARY option
        $isTemporary = $statement->options !== null && $statement->options->has('TEMPORARY');

        if ($this->schemaRegistry === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Check if table already exists when IF NOT EXISTS is not specified
        if (!$ifNotExists && $this->schemaRegistry->get($tableName) !== null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // DDL statements return no rows
        $sql = 'SELECT 1 WHERE FALSE';

        // Handle CREATE TABLE ... LIKE ...
        if ($statement->like !== null && $statement->like->table !== null) {
            $sourceTableName = $statement->like->table;
            // Check if source table exists before creating mutation
            if ($this->schemaRegistry->get($sourceTableName) === null) {
                return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
            }
            return new RewritePlan(
                $sql,
                QueryKind::DDL_SIMULATED,
                new CreateTableLikeMutation($tableName, $sourceTableName, $this->schemaRegistry, $ifNotExists)
            );
        }

        // Handle CREATE TABLE ... AS SELECT ...
        if ($statement->select !== null) {
            // Execute the SELECT to get the data
            $selectSql = $statement->select->build();
            $shadowedSelectSql = $this->shadowing->apply($selectSql, $this->shadowStore->getAll());
            return new RewritePlan(
                $shadowedSelectSql,
                QueryKind::DDL_SIMULATED,
                new CreateTableAsSelectMutation($tableName, $statement->select, $this->schemaRegistry, $this->shadowStore, $this->shadowing, $ifNotExists)
            );
        }

        return new RewritePlan(
            $sql,
            QueryKind::DDL_SIMULATED,
            new CreateTableMutation($tableName, $originalSql, $this->schemaRegistry, $ifNotExists)
        );
    }

    /**
     * Project a DROP TABLE statement.
     */
    private function projectDropTable(DropStatement $statement, string $originalSql): RewritePlan
    {
        if ($statement->fields === null || $statement->fields === []) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Get the first table from the drop statement
        $tableExpr = $statement->fields[0];
        $tableName = $tableExpr->table ?? $tableExpr->expr ?? null;
        if ($tableName === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Check for IF EXISTS option
        $ifExists = $statement->options !== null && $statement->options->has('IF EXISTS');

        if ($this->schemaRegistry === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Check if table exists when IF EXISTS is not specified
        if (!$ifExists && $this->schemaRegistry->getColumns($tableName) === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // DDL statements return no rows
        $sql = 'SELECT 1 WHERE FALSE';

        return new RewritePlan(
            $sql,
            QueryKind::DDL_SIMULATED,
            new DropTableMutation($tableName, $this->schemaRegistry, $ifExists)
        );
    }

    /**
     * Project an ALTER TABLE statement.
     *
     * @param AlterStatement $statement The ALTER statement.
     * @param string $originalSql The original SQL string (needed because build() corrupts some patterns).
     */
    private function projectAlterTable(AlterStatement $statement, string $originalSql): RewritePlan
    {
        if ($statement->table === null || $statement->table->table === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        $tableName = $statement->table->table;

        if ($this->schemaRegistry === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Check if table exists - ALTER TABLE on non-existent table is FORBIDDEN
        if ($this->schemaRegistry->getColumns($tableName) === null) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // Check for unsupported ALTER TABLE operations
        // We pass the original SQL because some patterns (like DROP DEFAULT)
        // are not captured in the AlterOperation by the parser, and build()
        // corrupts the SQL by stripping those patterns out
        if ($this->hasUnsupportedAlterOperation($statement, $originalSql)) {
            return new RewritePlan($originalSql, QueryKind::FORBIDDEN);
        }

        // DDL statements return no rows
        $sql = 'SELECT 1 WHERE FALSE';

        return new RewritePlan(
            $sql,
            QueryKind::DDL_SIMULATED,
            new AlterTableMutation($tableName, $statement, $this->schemaRegistry)
        );
    }

    /**
     * Check if an ALTER TABLE statement contains unsupported operations.
     *
     * Unsupported operations per sql-support-matrix.md:
     * - ADD INDEX / ADD KEY / ADD FULLTEXT / ADD SPATIAL
     * - ADD CONSTRAINT (UNIQUE, CHECK, etc.)
     * - DROP INDEX / DROP KEY / DROP CONSTRAINT
     * - RENAME INDEX / RENAME KEY
     * - ALTER COLUMN ... SET DEFAULT / DROP DEFAULT
     * - ORDER BY
     * - CONVERT TO CHARACTER SET
     * - ENGINE =
     * - PARTITION operations
     *
     * Note: We check options directly where possible, but some patterns like
     * DROP DEFAULT are not captured in the AlterOperation by the parser,
     * so we also check the SQL string.
     *
     * @param AlterStatement $statement The ALTER statement to check.
     * @param string $sql The original SQL string for pattern matching.
     */
    private function hasUnsupportedAlterOperation(AlterStatement $statement, string $sql): bool
    {
        // Check SQL string for patterns not captured in AlterOperation
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

            // ADD INDEX / ADD KEY / ADD FULLTEXT / ADD SPATIAL / ADD UNIQUE / ADD CONSTRAINT
            if ($options->has('ADD')) {
                if ($options->has('INDEX') || $options->has('KEY') ||
                    $options->has('FULLTEXT') || $options->has('SPATIAL') ||
                    $options->has('UNIQUE') || $options->has('CONSTRAINT')) {
                    return true;
                }
            }

            // DROP INDEX / DROP KEY / DROP CONSTRAINT
            if ($options->has('DROP')) {
                if ($options->has('INDEX') || $options->has('KEY') || $options->has('CONSTRAINT')) {
                    return true;
                }
            }

            // RENAME INDEX / RENAME KEY (but not RENAME COLUMN or RENAME TO)
            if ($options->has('RENAME')) {
                if ($options->has('INDEX') || $options->has('KEY')) {
                    return true;
                }
            }

            // ALTER ... SET DEFAULT / DROP DEFAULT
            if ($options->has('ALTER')) {
                if ($options->has('SET DEFAULT') || $options->has('DROP DEFAULT')) {
                    return true;
                }
                // Check unknown tokens for SET / DROP (which precede DEFAULT)
                $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
                foreach ($unknownTokens as $token) {
                    $tokenValue = is_string($token->value) ? $token->value : '';
                    $value = strtoupper($tokenValue);
                    if ($value === 'SET' || $value === 'DROP') {
                        return true;
                    }
                }
            }

            // ORDER BY - may appear in unknown tokens
            if ($options->has('ORDER') || $options->has('ORDER BY')) {
                return true;
            }

            // Check unknown tokens for ORDER BY
            $unknownTokens = is_array($op->unknown) ? $op->unknown : [];
            foreach ($unknownTokens as $token) {
                $tokenValue = is_string($token->value) ? $token->value : '';
                $value = strtoupper($tokenValue);
                if ($value === 'ORDER BY' || $value === 'ORDER') {
                    return true;
                }
            }

            // CONVERT TO CHARACTER SET
            if ($options->has('CONVERT')) {
                return true;
            }

            // ENGINE =
            if ($options->has('ENGINE')) {
                return true;
            }

            // PARTITION operations
            if ($options->has('PARTITION') || $options->has('ADD PARTITION') ||
                $options->has('DROP PARTITION') || $options->has('TRUNCATE PARTITION') ||
                $options->has('COALESCE PARTITION') || $options->has('REORGANIZE PARTITION') ||
                $options->has('EXCHANGE PARTITION') || $options->has('ANALYZE PARTITION') ||
                $options->has('CHECK PARTITION') || $options->has('OPTIMIZE PARTITION') ||
                $options->has('REBUILD PARTITION') || $options->has('REPAIR PARTITION') ||
                $options->has('REMOVE PARTITIONING')) {
                return true;
            }

            // Check unknown tokens for unsupported patterns
            // This catches ADD SPATIAL INDEX (parser puts "SPATIAL INDEX" as one token in unknown)
            // and other patterns not captured in options
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
     * Find the first unknown table in a SELECT statement.
     *
     * @param SelectStatement $statement The SELECT statement to check.
     * @return string|null The first unknown table name, or null if all tables exist.
     */
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
     * Extract all table names from a SELECT statement.
     *
     * @param SelectStatement $statement The SELECT statement.
     * @return array<int, string> List of table names.
     */
    private function extractTableNames(SelectStatement $statement): array
    {
        $tableNames = [];

        // Extract from FROM clause
        if ($statement->from !== []) {
            foreach ($statement->from as $fromExpr) {
                $tableName = $fromExpr->table ?? $fromExpr->expr ?? null;
                if (is_string($tableName) && $tableName !== '') {
                    $tableNames[] = $tableName;
                }
            }
        }

        // Extract from JOIN clauses
        if ($statement->join !== null && $statement->join !== []) {
            foreach ($statement->join as $joinExpr) {
                if ($joinExpr->expr !== null) {
                    $tableName = $joinExpr->expr->table ?? $joinExpr->expr->expr ?? null;
                    if (is_string($tableName) && $tableName !== '') {
                        $tableNames[] = $tableName;
                    }
                }
            }
        }

        return $tableNames;
    }

    /**
     * Check if a table exists in schema registry or shadow store.
     *
     * @param string $tableName The table name to check.
     * @return bool True if the table exists.
     */
    private function tableExists(string $tableName): bool
    {
        // Check shadow store first
        if ($this->shadowStore->get($tableName) !== []) {
            return true;
        }

        // Check schema registry
        if ($this->schemaRegistry !== null && $this->schemaRegistry->getColumns($tableName) !== null) {
            return true;
        }

        return false;
    }

    /**
     * Check if we have any schema context to validate against.
     *
     * @return bool True if shadow store has data or schema registry has tables.
     */
    private function hasSchemaContext(): bool
    {
        // Check if shadow store has any data
        if ($this->shadowStore->getAll() !== []) {
            return true;
        }

        // Check if schema registry has any tables
        if ($this->schemaRegistry !== null && $this->schemaRegistry->hasAnyTables()) {
            return true;
        }

        return false;
    }
}
