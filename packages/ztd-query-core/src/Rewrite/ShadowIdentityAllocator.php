<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Schema\IdentityGenerationStrategy;

final class ShadowIdentityAllocator
{
    /** @var array<string, int> */
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
            if ($index !== false && strcasecmp(trim($values[$index]), 'DEFAULT') !== 0) {
                continue;
            }

            $key = $table . "\0" . $column;
            $next = $this->nextValues[$key] ?? 1;
            if ($strategy === IdentityGenerationStrategy::MaxValue) {
                $next = max($next, $this->nextAfterExistingRows($column, $existingRows));
            }
            $allocated[$column] = (string) $next;
            $this->nextValues[$key] = $next + 1;
        }

        return $allocated;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function nextAfterExistingRows(string $column, array $rows): int
    {
        $maximum = 0;
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
                $maximum = max($maximum, (int) $value);
            }
        }

        return $maximum + 1;
    }
}
