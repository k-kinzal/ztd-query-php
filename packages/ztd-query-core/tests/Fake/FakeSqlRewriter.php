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
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Fake SqlRewriter that classifies SQL via regex and builds result-select queries.
 *
 * Supports SELECT, INSERT, UPDATE, DELETE, CREATE TABLE, DROP TABLE, TRUNCATE.
 * Uses FakeSqlTransformer for CTE injection on SELECT queries.
 */
final class FakeSqlRewriter implements SqlRewriter
{
    private ShadowStore $shadowStore;

    private TableDefinitionRegistry $registry;

    private FakeSqlTransformer $transformer;

    private FakeSchemaParser $schemaParser;

    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry
    ) {
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->transformer = new FakeSqlTransformer();
        $this->schemaParser = new FakeSchemaParser();
    }

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

    public function rewriteMultiple(string $sql): MultiRewritePlan
    {
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn (string $s): bool => $s !== ''
        );

        $plans = [];
        foreach ($statements as $stmt) {
            $plans[] = $this->rewrite($stmt);
        }

        return new MultiRewritePlan($plans);
    }

    private function classify(string $sql): ?QueryKind
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

    private function rewriteSelect(string $sql): RewritePlan
    {
        $tables = $this->buildShadowContext();

        if ($tables !== []) {
            $sql = $this->transformer->transform($sql, $tables);
        }

        return new RewritePlan($sql, QueryKind::READ);
    }

    private function rewriteWrite(string $sql): RewritePlan
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

    private function rewriteInsert(string $sql): RewritePlan
    {
        $tableName = $this->extractTableFromInsert($sql);
        $definition = $tableName !== null ? $this->registry->get($tableName) : null;
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        $mutation = new InsertMutation($tableName ?? 'unknown', $primaryKeys);

        $resultSql = $this->buildInsertResultSelect($sql, $tableName, $definition);

        return new RewritePlan($resultSql, QueryKind::WRITE_SIMULATED, $mutation);
    }

    private function rewriteUpdate(string $sql): RewritePlan
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

    private function rewriteDelete(string $sql): RewritePlan
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

    private function rewriteTruncate(string $sql): RewritePlan
    {
        if (preg_match('/TRUNCATE\s+(?:TABLE\s+)?[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            $tableName = $m[1];
        } else {
            $tableName = 'unknown';
        }

        $mutation = new TruncateMutation($tableName);

        return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::WRITE_SIMULATED, $mutation);
    }

    private function rewriteDdl(string $sql): RewritePlan
    {
        $upper = strtoupper(ltrim($sql));

        if (str_starts_with($upper, 'CREATE TABLE')) {
            $definition = $this->schemaParser->parse($sql);
            $tableName = $this->extractTableFromCreate($sql) ?? 'unknown';
            $mutation = new CreateTableMutation(
                $tableName,
                $definition ?? new TableDefinition([], [], [], [], []),
                $this->registry
            );

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::DDL_SIMULATED, $mutation);
        }

        if (str_starts_with($upper, 'DROP TABLE')) {
            $tableName = $this->extractTableFromDrop($sql) ?? 'unknown';
            $mutation = new DropTableMutation($tableName, $this->registry);

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::DDL_SIMULATED, $mutation);
        }

        throw new UnsupportedSqlException($sql, 'Unsupported DDL');
    }

    /**
     * @return array<string, array{rows: array<int, array<string, mixed>>, columns: array<int, string>, columnTypes: array<string, \ZtdQuery\Schema\ColumnType>}>
     */
    private function buildShadowContext(): array
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

    private function extractTableFromInsert(string $sql): ?string
    {
        if (preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractTableFromUpdate(string $sql): ?string
    {
        if (preg_match('/UPDATE\s+[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractTableFromDelete(string $sql): ?string
    {
        if (preg_match('/DELETE\s+FROM\s+[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractTableFromCreate(string $sql): ?string
    {
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function extractTableFromDrop(string $sql): ?string
    {
        if (preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"\']?(\w+)[`"\']?/i', $sql, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function buildInsertResultSelect(string $sql, ?string $tableName, ?TableDefinition $definition): string
    {
        $columns = $definition !== null ? $definition->columns : [];
        $resultSql = 'SELECT ' . ($columns !== [] ? implode(', ', $columns) : '*') . ' FROM ' . ($tableName ?? 'unknown');

        $tables = $this->buildShadowContext();
        if ($tables !== []) {
            $resultSql = $this->transformer->transform($resultSql, $tables);
        }

        return $resultSql;
    }
}
