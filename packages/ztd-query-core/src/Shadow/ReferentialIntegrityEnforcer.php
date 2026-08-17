<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;

final class ReferentialIntegrityEnforcer
{
    public function __construct(private readonly TableDefinitionRegistry $registry)
    {
    }

    /** @param array<int, array<string, mixed>> $resultRows */
    public function synchronize(
        ShadowStore $before,
        ShadowStore $after,
        ShadowMutation $mutation,
        array $resultRows,
        string $sql,
    ): void {
        if (!$this->isDataMutation($mutation)) {
            return;
        }

        $events = $this->initialEvents($before, $after, $mutation, $resultRows);
        while ($events !== []) {
            $event = array_shift($events);
            foreach ($this->registry->getAll() as $childTable => $childDefinition) {
                foreach ($childDefinition->foreignKeys as $constraintName => $foreignKey) {
                    if (strcasecmp($foreignKey->referencedTable, $event['table']) !== 0) {
                        continue;
                    }
                    $cascade = $this->cascade(
                        $after,
                        $childTable,
                        $constraintName,
                        $foreignKey,
                        $event['deleted'],
                        $event['updated'],
                        $sql,
                    );
                    if ($cascade !== null) {
                        $events[] = $cascade;
                    }
                }
            }
        }

        $this->validate($after, $sql);
    }

    private function isDataMutation(ShadowMutation $mutation): bool
    {
        return $mutation instanceof InsertMutation
            || $mutation instanceof UpdateMutation
            || $mutation instanceof DeleteMutation
            || $mutation instanceof ReplaceMutation
            || $mutation instanceof UpsertMutation
            || $mutation instanceof TruncateMutation
            || $mutation instanceof MultiUpdateMutation
            || $mutation instanceof MultiDeleteMutation
            || $mutation instanceof MultiTruncateMutation;
    }

    /**
     * @param array<int, array<string, mixed>> $resultRows
     * @return list<array{
     *     table: string,
     *     deleted: list<array<string, mixed>>,
     *     updated: list<array{before: array<string, mixed>, after: array<string, mixed>}>
     * }>
     */
    private function initialEvents(
        ShadowStore $before,
        ShadowStore $after,
        ShadowMutation $mutation,
        array $resultRows,
    ): array {
        $tables = array_unique(array_merge(
            array_keys($before->getAll()),
            array_keys($after->getAll()),
        ));
        $events = [];
        foreach ($tables as $table) {
            $definition = $this->registry->get($table);
            $primaryKeys = array_values($definition->primaryKeys ?? []);
            $identityRows = $mutation instanceof UpdateMutation && $mutation->tableName() === $table
                ? $resultRows
                : [];
            $event = $this->transition(
                $table,
                array_values($before->get($table)),
                array_values($after->get($table)),
                $primaryKeys,
                array_values($identityRows),
            );
            if ($event['deleted'] !== [] || $event['updated'] !== []) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @param list<array<string, mixed>> $beforeRows
     * @param list<array<string, mixed>> $afterRows
     * @param list<string> $primaryKeys
     * @param list<array<string, mixed>> $identityRows
     * @return array{
     *     table: string,
     *     deleted: list<array<string, mixed>>,
     *     updated: list<array{before: array<string, mixed>, after: array<string, mixed>}>
     * }
     */
    private function transition(
        string $table,
        array $beforeRows,
        array $afterRows,
        array $primaryKeys,
        array $identityRows,
    ): array {
        $matchedBefore = [];
        $matchedAfter = [];
        $updated = [];
        if ($primaryKeys !== []) {
            $identity = new MutationRowIdentity();
            foreach ($identityRows as $resultRow) {
                $change = $identity->extract($resultRow, $primaryKeys);
                $beforeIndex = self::matchingRowIndex($beforeRows, $change['identity'], $primaryKeys, $matchedBefore);
                $afterIndex = self::matchingRowIndex($afterRows, $change['row'], $primaryKeys, $matchedAfter);
                if ($beforeIndex === null || $afterIndex === null) {
                    continue;
                }
                $matchedBefore[$beforeIndex] = true;
                $matchedAfter[$afterIndex] = true;
                if ($beforeRows[$beforeIndex] !== $afterRows[$afterIndex]) {
                    $updated[] = ['before' => $beforeRows[$beforeIndex], 'after' => $afterRows[$afterIndex]];
                }
            }

            foreach ($beforeRows as $beforeIndex => $beforeRow) {
                if (isset($matchedBefore[$beforeIndex])) {
                    continue;
                }
                $afterIndex = self::matchingRowIndex($afterRows, $beforeRow, $primaryKeys, $matchedAfter);
                if ($afterIndex === null) {
                    continue;
                }
                $matchedBefore[$beforeIndex] = true;
                $matchedAfter[$afterIndex] = true;
                if ($beforeRow !== $afterRows[$afterIndex]) {
                    $updated[] = ['before' => $beforeRow, 'after' => $afterRows[$afterIndex]];
                }
            }
        } else {
            foreach ($beforeRows as $beforeIndex => $beforeRow) {
                $afterIndex = self::matchingExactRowIndex($afterRows, $beforeRow, $matchedAfter);
                if ($afterIndex !== null) {
                    $matchedBefore[$beforeIndex] = true;
                    $matchedAfter[$afterIndex] = true;
                }
            }
        }

        $deleted = [];
        foreach ($beforeRows as $index => $row) {
            if (!isset($matchedBefore[$index])) {
                $deleted[] = $row;
            }
        }

        return ['table' => $table, 'deleted' => $deleted, 'updated' => $updated];
    }

    /**
     * @param list<array<string, mixed>> $deletedParents
     * @param list<array{before: array<string, mixed>, after: array<string, mixed>}> $updatedParents
     * @return array{
     *     table: string,
     *     deleted: list<array<string, mixed>>,
     *     updated: list<array{before: array<string, mixed>, after: array<string, mixed>}>
     * }|null
     */
    private function cascade(
        ShadowStore $store,
        string $childTable,
        string $constraintName,
        ForeignKeyDefinition $foreignKey,
        array $deletedParents,
        array $updatedParents,
        string $sql,
    ): ?array {
        $parentDefinition = $this->registry->get($foreignKey->referencedTable);
        $referencedColumns = array_values($foreignKey->referencedColumns !== []
            ? $foreignKey->referencedColumns
            : ($parentDefinition->primaryKeys ?? []));
        if (count($foreignKey->columns) !== count($referencedColumns)) {
            return null;
        }

        $rows = $store->get($childTable);
        $updatedChildren = [];
        foreach ($updatedParents as $parentChange) {
            $oldValues = self::keyValues($parentChange['before'], $referencedColumns);
            $newValues = self::keyValues($parentChange['after'], $referencedColumns);
            if ($oldValues === null || $newValues === null || $oldValues === $newValues) {
                continue;
            }
            if ($this->parentKeyExists($store, $foreignKey->referencedTable, $referencedColumns, $oldValues)) {
                continue;
            }

            foreach ($rows as $index => $row) {
                if (!self::rowMatchesValues($row, $foreignKey->columns, $oldValues)) {
                    continue;
                }
                $updated = $this->applyAction(
                    $row,
                    $foreignKey->columns,
                    $newValues,
                    $foreignKey->onUpdate,
                    $childTable,
                    $constraintName,
                    $foreignKey,
                    $sql,
                );
                $rows[$index] = $updated;
                $updatedChildren[] = ['before' => $row, 'after' => $updated];
            }
        }

        $deletedChildren = [];
        foreach ($deletedParents as $parentRow) {
            $oldValues = self::keyValues($parentRow, $referencedColumns);
            if ($oldValues === null
                || $this->parentKeyExists($store, $foreignKey->referencedTable, $referencedColumns, $oldValues)
            ) {
                continue;
            }

            $remaining = [];
            foreach ($rows as $row) {
                if (!self::rowMatchesValues($row, $foreignKey->columns, $oldValues)) {
                    $remaining[] = $row;
                    continue;
                }
                if ($foreignKey->onDelete === ReferentialAction::Cascade) {
                    $deletedChildren[] = $row;
                    continue;
                }
                $updated = $this->applyAction(
                    $row,
                    $foreignKey->columns,
                    array_fill(0, count($foreignKey->columns), null),
                    $foreignKey->onDelete,
                    $childTable,
                    $constraintName,
                    $foreignKey,
                    $sql,
                );
                $remaining[] = $updated;
                $updatedChildren[] = ['before' => $row, 'after' => $updated];
            }
            $rows = $remaining;
        }

        if ($deletedChildren === [] && $updatedChildren === []) {
            return null;
        }

        $store->set($childTable, array_values($rows));

        return ['table' => $childTable, 'deleted' => $deletedChildren, 'updated' => $updatedChildren];
    }

    /**
     * @param array<string, mixed> $row
     * @param non-empty-list<string> $columns
     * @param list<mixed> $values
     * @return array<string, mixed>
     */
    private function applyAction(
        array $row,
        array $columns,
        array $values,
        ReferentialAction $action,
        string $childTable,
        string $constraintName,
        ForeignKeyDefinition $foreignKey,
        string $sql,
    ): array {
        if ($action !== ReferentialAction::Cascade && $action !== ReferentialAction::SetNull) {
            throw $this->violation($sql, $childTable, $constraintName, $foreignKey);
        }

        foreach ($columns as $index => $column) {
            $row[$column] = $action === ReferentialAction::SetNull ? null : ($values[$index] ?? null);
        }

        return $row;
    }

    private function validate(ShadowStore $store, string $sql): void
    {
        foreach ($this->registry->getAll() as $childTable => $definition) {
            foreach ($definition->foreignKeys as $constraintName => $foreignKey) {
                $parentDefinition = $this->registry->get($foreignKey->referencedTable);
                $referencedColumns = array_values($foreignKey->referencedColumns !== []
                    ? $foreignKey->referencedColumns
                    : ($parentDefinition->primaryKeys ?? []));
                if (count($foreignKey->columns) !== count($referencedColumns)) {
                    continue;
                }

                foreach ($store->get($childTable) as $row) {
                    $values = self::keyValues($row, $foreignKey->columns);
                    if ($values === null || in_array(null, $values, true)) {
                        continue;
                    }
                    if (!$this->parentKeyExists(
                        $store,
                        $foreignKey->referencedTable,
                        $referencedColumns,
                        $values,
                    )) {
                        throw $this->violation($sql, $childTable, $constraintName, $foreignKey);
                    }
                }
            }
        }
    }

    /**
     * @param list<string> $columns
     * @param list<mixed> $values
     */
    private function parentKeyExists(
        ShadowStore $store,
        string $table,
        array $columns,
        array $values,
    ): bool {
        foreach ($store->get($table) as $row) {
            if (self::rowMatchesValues($row, $columns, $values)) {
                return true;
            }
        }

        return false;
    }

    private function violation(
        string $sql,
        string $childTable,
        string $constraintName,
        ForeignKeyDefinition $foreignKey,
    ): ForeignKeyViolationException {
        return new ForeignKeyViolationException(
            $sql,
            $childTable,
            $constraintName,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumns[0] ?? '',
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $candidate
     * @param list<string> $keys
     * @param array<int, bool> $excluded
     */
    private static function matchingRowIndex(array $rows, array $candidate, array $keys, array $excluded): ?int
    {
        foreach ($rows as $index => $row) {
            if (!isset($excluded[$index]) && self::rowsMatch($row, $candidate, $keys)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $candidate
     * @param array<int, bool> $excluded
     */
    private static function matchingExactRowIndex(array $rows, array $candidate, array $excluded): ?int
    {
        foreach ($rows as $index => $row) {
            if (!isset($excluded[$index]) && $row === $candidate) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @param list<string> $keys
     */
    private static function rowsMatch(array $left, array $right, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $left)
                || !array_key_exists($key, $right)
                || $left[$key] !== $right[$key]
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $columns
     * @return list<mixed>|null
     */
    private static function keyValues(array $row, array $columns): ?array
    {
        $values = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                return null;
            }
            $values[] = $row[$column];
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $columns
     * @param list<mixed> $values
     */
    private static function rowMatchesValues(array $row, array $columns, array $values): bool
    {
        foreach ($columns as $index => $column) {
            if (!array_key_exists($column, $row) || $row[$column] !== ($values[$index] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
