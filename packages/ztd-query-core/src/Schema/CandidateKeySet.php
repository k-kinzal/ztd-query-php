<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Connection\StatementInterface;

/**
 * Relational candidate keys used to detect INSERT conflicts.
 *
 * @phpstan-import-type Row from StatementInterface
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
     * @return array<string, array<int, string>>
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * @param Row $row
     * @param list<Row> $existingRows
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
     * @param Row $row
     * @param array<int, string> $columns
     * @return Row|null
     */
    private function values(array $row, array $columns): ?array
    {
        if ($columns === []) {
            return null;
        }

        $values = [];
        foreach ($columns as $column) {
            if (!isset($row[$column])) {
                return null;
            }
            $values[$column] = $row[$column];
        }

        return $values;
    }

    /**
     * @param Row $values
     * @param Row $row
     */
    private function matches(array $values, array $row): bool
    {
        foreach ($values as $column => $value) {
            if (($row[$column] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
