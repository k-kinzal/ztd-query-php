<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\Row\DeleteMutation;
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\Mutation\Row\UpdateMutation;
use ZtdQuery\Shadow\Mutation\Table\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\Table\DropTableMutation;
use ZtdQuery\Shadow\Mutation\Table\TruncateMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Sql\TransactionStatement;

/**
 * Fake SqlRewriter that classifies SQL via regex and builds result-select queries.
 *
 * Supports SELECT, INSERT, UPDATE, DELETE, CREATE TABLE, DROP TABLE, TRUNCATE.
 * Uses FakeSqlTransformer for CTE injection on SELECT queries.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class FakeSqlRewriter implements SqlRewriter
{
    /**
     * Transaction statement.
     *
     * @param string $sql
     * @return ?TransactionStatement
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return null;
    }

    private ShadowStore $shadowStore;

    private TableDefinitionRegistry $registry;

    private FakeSqlTransformer $transformer;

    private FakeSchemaParser $schemaParser;

    /**
     * Binds the instance to what it will work from.
     *
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     */
    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry
    ) {
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = new FakeSqlTransformer();
        $this->schemaParser = new FakeSchemaParser();
    }

    /**
     * Rewrite.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewrite(string $sql): RewritePlan
    {
        $trimmed = trim($sql);

        if ($trimmed === '') {
            throw new UnsupportedSqlException($sql, 'Empty');
        }

        $kind = $this->classify($trimmed);

        if ($kind === null) {
            throw new UnsupportedSqlException($sql, 'Unsupported');
        }

        return match ($kind) {
            QueryKind::READ => $this->rewriteSelect($trimmed),
            QueryKind::WRITE_SIMULATED => $this->rewriteWrite($trimmed),
            QueryKind::DDL_SIMULATED => $this->rewriteDdl($trimmed),
            QueryKind::SKIPPED => new RewritePlan($trimmed, QueryKind::SKIPPED),
        };
    }

    /**
     * Rewrite multiple.
     *
     * @param string $sql
     * @return MultiRewritePlan
     */
    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $statements = $this->splitStatements($sql);

        $plans = [];
        foreach ($statements as $stmt) {
            $plans[] = $this->rewrite($stmt);
        }

        return new MultiRewritePlan($plans);
    }

    /**
     * Split statements.
     *
     * @param string $sql
     */
    public function splitStatements(string $sql): array
    {
        return array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $s): bool => $s !== ''
        ));
    }

    /**
     * Classify.
     *
     * @param string $sql
     * @return ?QueryKind
     */
    public function classify(string $sql): ?QueryKind
    {
        $upper = strtoupper(ltrim($sql));

        if (str_starts_with($upper, 'SELECT') || str_starts_with($upper, '(SELECT')) {
            return QueryKind::READ;
        }
        if (str_starts_with($upper, 'INSERT')) {
            return QueryKind::WRITE_SIMULATED;
        }
        if (str_starts_with($upper, 'UPDATE')) {
            return QueryKind::WRITE_SIMULATED;
        }
        if (str_starts_with($upper, 'DELETE')) {
            return QueryKind::WRITE_SIMULATED;
        }
        if (str_starts_with($upper, 'TRUNCATE')) {
            return QueryKind::WRITE_SIMULATED;
        }
        if (str_starts_with($upper, 'REPLACE')) {
            return QueryKind::WRITE_SIMULATED;
        }
        if (str_starts_with($upper, 'CREATE TABLE')) {
            return QueryKind::DDL_SIMULATED;
        }
        if (str_starts_with($upper, 'DROP TABLE')) {
            return QueryKind::DDL_SIMULATED;
        }
        return null;
    }

    /**
     * Rewrite select.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewriteSelect(string $sql): RewritePlan
    {
        $tables = $this->buildShadowContext();

        if ($tables !== []) {
            $sql = $this->transformer->transform($sql, $tables);
        }

        return new RewritePlan($sql, QueryKind::READ);
    }

    /**
     * @throws UnsupportedSqlException When the statement is not one this fake simulates
     */
    public function rewriteWrite(string $sql): RewritePlan
    {
        $upper = strtoupper(ltrim($sql));

        if (str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'REPLACE')) {
            return $this->rewriteInsert($sql);
        }
        if (str_starts_with($upper, 'UPDATE')) {
            return $this->rewriteUpdate($sql);
        }
        if (str_starts_with($upper, 'DELETE')) {
            return $this->rewriteDelete($sql);
        }
        if (str_starts_with($upper, 'TRUNCATE')) {
            return $this->rewriteTruncate($sql);
        }

        throw new UnsupportedSqlException($sql, 'Unsupported write');
    }

    /**
     * Rewrite insert.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewriteInsert(string $sql): RewritePlan
    {
        $tableName = $this->extractTableFromInsert($sql);
        $definition = $tableName !== null ? $this->registry->get($tableName) : null;
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        $mutation = new InsertMutation($tableName ?? 'unknown', $primaryKeys);

        $resultSql = $this->buildInsertResultSelect($sql, $tableName, $definition);

        return new RewritePlan($resultSql, QueryKind::WRITE_SIMULATED, $mutation);
    }

    /**
     * Rewrite update.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewriteUpdate(string $sql): RewritePlan
    {
        $tableName = $this->extractTableFromUpdate($sql);
        $definition = $tableName !== null ? $this->registry->get($tableName) : null;
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        $mutation = new UpdateMutation($tableName ?? 'unknown', $primaryKeys);

        $columns = $definition !== null ? $definition->columns : [];
        $resultSql = 'SELECT ' . ($columns !== [] ? implode(', ', $columns) : '*') . ' FROM ' . ($tableName ?? 'unknown');

        $tables = $this->buildShadowContext();
        if ($tables !== []) {
            $resultSql = $this->transformer->transform($resultSql, $tables);
        }

        return new RewritePlan($resultSql, QueryKind::WRITE_SIMULATED, $mutation);
    }

    /**
     * Rewrite delete.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewriteDelete(string $sql): RewritePlan
    {
        $tableName = $this->extractTableFromDelete($sql);
        $definition = $tableName !== null ? $this->registry->get($tableName) : null;
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        $mutation = new DeleteMutation($tableName ?? 'unknown', $primaryKeys);

        $columns = $definition !== null ? $definition->columns : [];
        $resultSql = 'SELECT ' . ($columns !== [] ? implode(', ', $columns) : '*') . ' FROM ' . ($tableName ?? 'unknown');

        $tables = $this->buildShadowContext();
        if ($tables !== []) {
            $resultSql = $this->transformer->transform($resultSql, $tables);
        }

        return new RewritePlan($resultSql, QueryKind::WRITE_SIMULATED, $mutation);
    }

    /**
     * Rewrite truncate.
     *
     * @param string $sql
     * @return RewritePlan
     */
    public function rewriteTruncate(string $sql): RewritePlan
    {
        if (preg_match('/TRUNCATE\s+(?:TABLE\s+)?[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            $tableName = $m[1];
        } else {
            $tableName = 'unknown';
        }

        $mutation = new TruncateMutation($tableName);

        return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::WRITE_SIMULATED, $mutation);
    }

    /**
     * @throws UnsupportedSqlException When the statement is not one this fake simulates
     */
    public function rewriteDdl(string $sql): RewritePlan
    {
        $upper = strtoupper(ltrim($sql));

        if (str_starts_with($upper, 'CREATE TABLE')) {
            $definition = $this->schemaParser->parse($sql);
            $tableName = $this->extractTableFromCreate($sql) ?? 'unknown';
            $mutation = new CreateTableMutation(
                $tableName,
                $definition ?? new TableDefinition([], [], [], [], []),
                $this->registry,
                $sql,
            );

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::DDL_SIMULATED, $mutation);
        }

        if (str_starts_with($upper, 'DROP TABLE')) {
            $tableName = $this->extractTableFromDrop($sql) ?? 'unknown';
            $mutation = new DropTableMutation($tableName, $this->registry, $sql);

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::DDL_SIMULATED, $mutation);
        }

        throw new UnsupportedSqlException($sql, 'Unsupported DDL');
    }

    /**
     * @return array<string, array{rows: list<Row>, columns: array<int, string>, columnTypes: array<string, \ZtdQuery\Schema\ColumnDeclaration>}>
     */
    public function buildShadowContext(): array
    {
        $tables = [];
        foreach ($this->shadowStore->getAll() as $tableName => $rows) {
            $definition = $this->registry->get($tableName);
            if ($definition === null) {
                continue;
            }

            $tables[$tableName] = [
                'rows' => $rows,
                'columns' => $definition->columns,
                'columnTypes' => $definition->typedColumns,
            ];
        }

        return $tables;
    }

    /**
     * Reads table from insert.
     *
     * @param string $sql
     * @return ?string
     */
    public function extractTableFromInsert(string $sql): ?string
    {
        if (preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Reads table from update.
     *
     * @param string $sql
     * @return ?string
     */
    public function extractTableFromUpdate(string $sql): ?string
    {
        if (preg_match('/UPDATE\s+[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Reads table from delete.
     *
     * @param string $sql
     * @return ?string
     */
    public function extractTableFromDelete(string $sql): ?string
    {
        if (preg_match('/DELETE\s+FROM\s+[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Reads table from create.
     *
     * @param string $sql
     * @return ?string
     */
    public function extractTableFromCreate(string $sql): ?string
    {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Reads table from drop.
     *
     * @param string $sql
     * @return ?string
     */
    public function extractTableFromDrop(string $sql): ?string
    {
        if (preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Builds insert result select.
     *
     * @param string $sql
     * @param ?string $tableName
     * @param ?TableDefinition $definition
     * @return string
     */
    public function buildInsertResultSelect(string $sql, ?string $tableName, ?TableDefinition $definition): string
    {
        $columns = $definition !== null ? $definition->columns : [];
        $resultSql = 'SELECT ' . ($columns !== [] ? implode(', ', $columns) : '*') . ' FROM ' . ($tableName ?? 'unknown');

        $tables = $this->buildShadowContext();
        if ($tables !== []) {
            $resultSql = $this->transformer->transform($resultSql, $tables);
        }

        return $resultSql;
    }

    /**
     * Empty result select.
     *
     * @return string
     */
    public function emptyResultSelect(): string
    {
        return 'SELECT 1 WHERE FALSE';
    }
}
