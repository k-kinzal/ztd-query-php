<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Schema\IdentityGenerationStrategy;

final class ShadowIdentityAllocator
{
    /** @var array<string, array<string, int>> */
    private array $nextValues = [];

    /**
     * @param array<string, IdentityGenerationStrategy> $strategies
     * @param list<string> $providedColumns
     * @param array<int, array<string, mixed>> $existingRows
     * @return array<string, int>
     */
    public function allocateMissing(
        string $table,
        array $strategies,
        array $providedColumns,
        array $existingRows,
    ): array {
        $allocated = [];
        foreach ($strategies as $column => $strategy) {
            if (in_array($column, $providedColumns, true)) {
                continue;
            }

            $next = $this->nextValue($table, $column, $strategy, $existingRows);
            $allocated[$column] = $next;
            $this->nextValues[$table][$column] = $next + 1;
        }

        return $allocated;
    }

    /**
     * @param array<string, IdentityGenerationStrategy> $strategies
     * @param list<string> $providedColumns
     * @param array<int, array<string, mixed>> $existingRows
     * @return array<string, int>
     */
    public function allocateSelectStarts(
        string $table,
        array $strategies,
        array $providedColumns,
        array $existingRows,
    ): array {
        $starts = [];
        foreach ($strategies as $column => $strategy) {
            if (in_array($column, $providedColumns, true)) {
                continue;
            }
            $next = $this->nextValue($table, $column, $strategy, $existingRows);
            $this->nextValues[$table][$column] = $next;
            $starts[$column] = $next;
        }

        return $starts;
    }

    /** @param array<int, array<string, mixed>> $existingRows */
    private function nextValue(
        string $table,
        string $column,
        IdentityGenerationStrategy $strategy,
        array $existingRows,
    ): int {
        $next = $this->nextValues[$table][$column] ?? 1;
        if ($strategy === IdentityGenerationStrategy::MaxValue) {
            return $this->nextAfterExistingRows($column, $existingRows, $next);
        }
        if (isset($this->nextValues[$table][$column])) {
            return $this->nextAfterExistingRows($column, $existingRows, $next);
        }

        return $next;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function nextAfterExistingRows(string $column, array $rows, int $next): int
    {
        foreach ($rows as $row) {
            $value = self::integerValue($row[$column] ?? null);
            if ($value !== null) {
                $next = max($next, $value + 1);
            }
        }

        return $next;
    }

    private static function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($parsed) ? $parsed : null;
    }
}
