<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Relational candidate keys used to detect INSERT conflicts.
 */
final class CandidateKeySet
{
    /**
     * @param array<string, array<int, string>> $keys
     */
    public function __construct(private readonly array $keys)
    {
    }

    /**
     * @param array<int, string> $primaryKey
     * @param array<string, array<int, string>> $uniqueConstraints
     */
    public static function fromSchema(array $primaryKey, array $uniqueConstraints = []): self
    {
        $keys = $uniqueConstraints;
        if ($primaryKey !== []) {
            $keys = ['PRIMARY' => $primaryKey] + $keys;
        }

        return new self($keys);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $existingRows
     */
    public function findConflict(array $row, array $existingRows): ?CandidateKeyConflict
    {
        foreach ($this->keys as $keyName => $columns) {
            $values = $this->values($row, $columns);
            if ($values === null) {
                continue;
            }

            foreach ($existingRows as $rowIndex => $existingRow) {
                if ($this->matches($values, $existingRow)) {
                    return new CandidateKeyConflict($rowIndex, $keyName, $values);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     * @return array<string, mixed>|null
     */
    private function values(array $row, array $columns): ?array
    {
        if ($columns === []) {
            return null;
        }

        $values = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                return null;
            }
            $values[$column] = $row[$column];
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $row
     */
    private function matches(array $values, array $row): bool
    {
        foreach ($values as $column => $value) {
            if (!array_key_exists($column, $row) || $row[$column] === null || $row[$column] !== $value) {
                return false;
            }
        }

        return true;
    }
}
