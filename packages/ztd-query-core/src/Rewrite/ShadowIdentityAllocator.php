<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\IdentityGenerationStrategy;

/**
 * Hands out the identity values a database would have assigned.
 *
 * Nothing is inserted, so nothing counts rows for us. A statement that omits an
 * AUTO_INCREMENT column still has to read back with one, and two statements in
 * the same session must not be given the same number, so the next value per
 * table and column is kept here.
 *
 * A projection is where a rewritten statement works out what it would have
 * written. It is allocated against a copy so that a projection thrown away
 * takes its numbers with it, and only a committed one moves the counter on.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class ShadowIdentityAllocator
{
    /** @var array<string, array<string, int>> */
    private array $committedNextValues = [];

    /** @var array<string, array<string, int>> */
    private array $projectionNextValues = [];

    public function beginProjection(): void
    {
        $this->projectionNextValues = $this->committedNextValues;
    }

    public function commitProjection(): void
    {
        $this->committedNextValues = $this->projectionNextValues;
    }

    /**
     * @param array<string, IdentityGenerationStrategy> $strategies
     * @param list<string> $providedColumns
     * @param list<Row> $existingRows
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
            $this->projectionNextValues[$table][$column] = $next + 1;
        }

        return $allocated;
    }

    /**
     * @param array<string, IdentityGenerationStrategy> $strategies
     * @param list<string> $providedColumns
     * @param list<Row> $existingRows
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
            $this->projectionNextValues[$table][$column] = $next;
            $starts[$column] = $next;
        }

        return $starts;
    }

    /** @param list<Row> $existingRows */
    private function nextValue(
        string $table,
        string $column,
        IdentityGenerationStrategy $strategy,
        array $existingRows,
    ): int {
        $next = $this->projectionNextValues[$table][$column] ?? 1;
        if ($strategy === IdentityGenerationStrategy::MaxValue) {
            return $this->nextAfterExistingRows($column, $existingRows, $next);
        }
        if (isset($this->projectionNextValues[$table][$column])) {
            return $this->nextAfterExistingRows($column, $existingRows, $next);
        }

        return $next;
    }

    /** @param list<Row> $rows */
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
