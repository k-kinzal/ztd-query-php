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
     * @param list<string> $insertColumns
     * @param list<string> $values
     * @param array<int, array<string, mixed>> $existingRows
     * @return array<string, string>
     */
    public function allocateMissing(
        string $table,
        array $strategies,
        array $insertColumns,
        array $values,
        array $existingRows,
    ): array {
        $allocated = [];
        foreach ($strategies as $column => $strategy) {
            $index = array_search($column, $insertColumns, true);
            if ($index !== false) {
                $providedValue = trim($values[$index]);
                if (strcasecmp($providedValue, 'DEFAULT') !== 0) {
                    continue;
                }
            }

            $next = $this->nextValues[$table][$column] ?? 1;
            if ($strategy === IdentityGenerationStrategy::MaxValue || isset($this->nextValues[$table][$column])) {
                $next = $this->nextAfterExistingRows($column, $existingRows, $next);
            }
            $allocated[$column] = (string) $next;
            $this->nextValues[$table][$column] = $next + 1;
        }

        return $allocated;
    }

    /**
     * @param array<string, IdentityGenerationStrategy> $strategies
     * @param array<int, array<string, mixed>> $existingRows
     * @return array<string, string>
     */
    public function allocateSelectExpressions(string $table, array $strategies, array $existingRows): array
    {
        $expressions = [];
        foreach ($strategies as $column => $strategy) {
            $next = $this->nextValues[$table][$column] ?? 1;
            if ($strategy === IdentityGenerationStrategy::MaxValue || isset($this->nextValues[$table][$column])) {
                $next = $this->nextAfterExistingRows($column, $existingRows, $next);
            }
            $this->nextValues[$table][$column] = $next;
            $expressions[$column] = $next . ' + ROW_NUMBER() OVER () - 1';
        }

        return $expressions;
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
