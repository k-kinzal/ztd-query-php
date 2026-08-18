<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DataMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;

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
        if (!$mutation instanceof DataMutation) {
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
        $events = [];
        $identityTable = $mutation instanceof UpdateMutation ? $mutation->tableName() : null;
        foreach (array_keys($before->getAll()) as $table) {
            $definition = $this->registry->get($table);
            $primaryKeys = $definition->primaryKeys ?? [];
            $identityRows = $identityTable === $table
                ? $resultRows
                : [];
            $event = $this->transition(
                $table,
                $before->get($table),
                $after->get($table),
                $primaryKeys,
                $identityRows,
            );
            if ($event['deleted'] !== [] || $event['updated'] !== []) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $beforeRows
     * @param array<int, array<string, mixed>> $afterRows
     * @param array<int, string> $primaryKeys
     * @param array<int, array<string, mixed>> $identityRows
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
                $matchedBefore[] = $beforeIndex;
                $matchedAfter[] = $afterIndex;
                if ($beforeRows[$beforeIndex] !== $afterRows[$afterIndex]) {
                    $updated[] = ['before' => $beforeRows[$beforeIndex], 'after' => $afterRows[$afterIndex]];
                }
            }

            foreach ($beforeRows as $beforeIndex => $beforeRow) {
                if (in_array($beforeIndex, $matchedBefore, true)) {
                    continue;
                }
                $afterIndex = self::matchingRowIndex($afterRows, $beforeRow, $primaryKeys, $matchedAfter);
                if ($afterIndex === null) {
                    continue;
                }
                $matchedBefore[] = $beforeIndex;
                $matchedAfter[] = $afterIndex;
                if ($beforeRow !== $afterRows[$afterIndex]) {
                    $updated[] = ['before' => $beforeRow, 'after' => $afterRows[$afterIndex]];
                }
            }
        } else {
            foreach ($beforeRows as $beforeIndex => $beforeRow) {
                $afterIndex = self::matchingExactRowIndex($afterRows, $beforeRow, $matchedAfter);
                if ($afterIndex !== null) {
                    $matchedBefore[] = $beforeIndex;
                    $matchedAfter[] = $afterIndex;
                }
            }
        }

        $deleted = [];
        foreach ($beforeRows as $index => $row) {
            if (!in_array($index, $matchedBefore, true)) {
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
        $referencedColumns = $this->referencedColumns($foreignKey);
        if (count($foreignKey->columns) !== count($referencedColumns)) {
            return null;
        }

        $rows = $store->get($childTable);
        $updatedChildren = [];
        foreach ($updatedParents as $parentChange) {
            $oldValues = self::keyValues($parentChange['before'], $referencedColumns);
            $newValues = self::keyValues($parentChange['after'], $referencedColumns);
            if ($oldValues === null || $newValues === null) {
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
                    [],
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
                $referencedColumns = $this->referencedColumns($foreignKey);
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
        $referencedColumns = $this->referencedColumns($foreignKey);

        return new ForeignKeyViolationException(
            $sql,
            $childTable,
            $constraintName,
            $foreignKey->referencedTable,
            $referencedColumns[0] ?? '',
        );
    }

    /** @return list<string> */
    private function referencedColumns(ForeignKeyDefinition $foreignKey): array
    {
        if ($foreignKey->referencedColumns !== []) {
            return $foreignKey->referencedColumns;
        }

        $referencedTable = $this->registry->get($foreignKey->referencedTable);
        if ($referencedTable === null) {
            return [];
        }

        return array_values($referencedTable->primaryKeys);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $candidate
     * @param array<int, string> $keys
     * @param list<int> $excluded
     */
    private static function matchingRowIndex(array $rows, array $candidate, array $keys, array $excluded): ?int
    {
        foreach ($rows as $index => $row) {
            if (!in_array($index, $excluded, true) && self::rowsMatch($row, $candidate, $keys)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $candidate
     * @param list<int> $excluded
     */
    private static function matchingExactRowIndex(array $rows, array $candidate, array $excluded): ?int
    {
        foreach ($rows as $index => $row) {
            if (!in_array($index, $excluded, true) && $row === $candidate) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @param array<int, string> $keys
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
