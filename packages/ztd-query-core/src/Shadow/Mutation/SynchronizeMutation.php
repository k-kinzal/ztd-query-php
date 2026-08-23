<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Replaces a table with a database-evaluated complete result set.
 */
final class SynchronizeMutation implements DataMutation
{
    public function __construct(
        private readonly string $tableName,
        private readonly ?TableDefinition $definition = null,
        private readonly string $sql = '',
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if ($this->definition !== null) {
            $validated = [];
            foreach ($rows as $row) {
                foreach ($this->definition->notNullColumns as $column) {
                    if (($row[$column] ?? null) === null) {
                        throw new NotNullViolationException($this->sql, $this->tableName, $column);
                    }
                }

                $conflict = $this->definition->candidateKeys()->findConflict($row, $validated);
                if ($conflict !== null) {
                    throw new DuplicateKeyException(
                        $this->sql,
                        $this->tableName,
                        $conflict->keyName,
                        $conflict->values,
                    );
                }
                $validated[] = $row;
            }
        }

        $store->set($this->tableName, $rows);
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Count inserted, updated, and deleted rows once each.
     *
     * @param array<int, array<string, mixed>> $before
     * @param array<int, array<string, mixed>> $after
     */
    public function affectedRowCount(array $before, array $after): int
    {
        $primaryKeys = $this->definition->primaryKeys ?? [];
        if ($primaryKeys === []) {
            return max(
                count($this->difference($before, $after)),
                count($this->difference($after, $before)),
            );
        }

        $matchedAfter = [];
        $count = 0;
        foreach ($before as $beforeRow) {
            $afterIndex = $this->matchingRowIndex($after, $beforeRow, $primaryKeys, $matchedAfter);
            if ($afterIndex === null) {
                $count++;
                continue;
            }
            $matchedAfter[] = $afterIndex;
            if ($beforeRow !== $after[$afterIndex]) {
                $count++;
            }
        }

        return $count + count($after) - count($matchedAfter);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $identity
     * @param list<string> $primaryKeys
     * @param list<int> $excluded
     */
    private function matchingRowIndex(
        array $rows,
        array $identity,
        array $primaryKeys,
        array $excluded,
    ): ?int {
        foreach ($rows as $index => $row) {
            if (in_array($index, $excluded, true)) {
                continue;
            }
            $matches = true;
            foreach ($primaryKeys as $primaryKey) {
                if (!array_key_exists($primaryKey, $identity)
                    || !array_key_exists($primaryKey, $row)
                    || $identity[$primaryKey] !== $row[$primaryKey]
                ) {
                    $matches = false;
                }
            }
            if ($matches) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $left
     * @param array<int, array<string, mixed>> $right
     * @return array<int, array<string, mixed>>
     */
    private function difference(array $left, array $right): array
    {
        $remaining = $right;
        $difference = [];
        foreach ($left as $row) {
            $match = array_search($row, $remaining, true);
            if ($match === false) {
                $difference[] = $row;
                continue;
            }
            unset($remaining[$match]);
        }

        return $difference;
    }
}
