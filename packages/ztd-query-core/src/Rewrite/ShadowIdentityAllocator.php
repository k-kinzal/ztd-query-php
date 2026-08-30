<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\IdentityGenerationStrategy;
use ZtdQuery\Schema\TableDefinition;

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
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type RenderableValue from ValueRenderer
 * @phpstan-import-type RowValue from TableDefinition
 */
final class ShadowIdentityAllocator
{
    /** @var array<string, array<string, int>> */
    private array $committedNextValues = [];

    /** @var array<string, array<string, int>> */
    private array $projectionNextValues = [];

    /**
     * Begin projection.
     *
     */
    public function beginProjection(): void
    {
        $this->projectionNextValues = $this->committedNextValues;
    }

    /**
     * Commit projection.
     *
     */
    public function commitProjection(): void
    {
        $this->committedNextValues = $this->projectionNextValues;
    }

    /**
     * @param array<string, IdentityGenerationStrategy> $strategies
     * @param list<string> $providedColumns
     * @param list<array<string, RenderableValue>> $existingRows
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
     * @param list<array<string, RenderableValue>> $existingRows
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

    /**
     * Answers the value a generated column would take next.
     *
     * A column the database derives from the greatest value present has to be
     * read off the rows every time. One that simply counts up is read off the
     * rows only once it has been handed out here before, because until then
     * the rows are all there is to go on.
     *
     * @param string $table Table the column belongs to
     * @param string $column Column the value is generated for
     * @param IdentityGenerationStrategy $strategy How the database decides the value
     * @param list<array<string, RenderableValue>> $existingRows Rows the table already holds
     *
     * @return int The value the column would take next
     */
    public function nextValue(
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

    /**
     * @param list<array<string, RenderableValue>> $rows
     */
    public function nextAfterExistingRows(string $column, array $rows, int $next): int
    {
        foreach ($rows as $row) {
            $value = $this->integerValue($row[$column] ?? null);
            if ($value !== null) {
                $next = max($next, $value + 1);
            }
        }

        return $next;
    }

    /**
     * Answers the whole number a value stands for, if it stands for one.
     *
     * A driver may answer an integer column as its text, so text that spells
     * a whole number counts. Anything else does not stand for a number the
     * table would have generated, and answers nothing rather than nought.
     *
     * @param RenderableValue $value Value as the driver answered it
     *
     * @return int|null The number, or null where the value is not one
     */
    public function integerValue(mixed $value): ?int
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
