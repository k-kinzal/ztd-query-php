<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Value\Order;

/**
 * A snapshot of row counts and full-key cardinalities collected by index maintenance.
 *
 * Distinct index scans can compare values before a temporary table hashes them. For small
 * tables, SQL optimizer traces put the grouping crossover near three distinct-key reads
 * per two row reads, including a final key lookup. This records that observed boundary;
 * it does not estimate physical page layouts or large-table sampling.
 *
 * @visibility MySqlMemory
 */
final class IndexStatistics
{
    /**
     * @param int $rows The row count when the statistics were collected
     * @param array<string, int> $cardinalities Distinct full-key counts by index name
     */
    public function __construct(public readonly int $rows, public readonly array $cardinalities)
    {
    }

    /**
     * Collects estimates after successful ANALYZE or OPTIMIZE of an existing table.
     */
    public static function refresh(?StoredTable $table, string $operation, bool $success): void
    {
        if ($success && $table !== null && in_array($operation, ['analyze', 'optimize'], true)) {
            $table->indexStatistics = self::sample($table);
        }
    }

    /**
     * Samples the current rows without changing them or the information_schema cache.
     */
    public static function sample(StoredTable $table): self
    {
        $cardinalities = [];
        foreach ($table->definition->keys as $key) {
            $seen = [];
            foreach ($table->data->rows as $row) {
                $values = [];
                foreach ($key->columns as $position) {
                    $values[] = Order::key($row[$position] ?? null, $table->definition->columns[$position]->domain);
                }
                $seen[serialize($values)] = true;
            }
            $cardinalities[$key->name] = count($seen);
        }

        return new self(count($table->data->rows), $cardinalities);
    }

    /**
     * Answers whether sampled duplicates make a small-table grouping scan preferable.
     */
    public function groups(string $index): bool
    {
        return isset($this->cardinalities[$index]) && 2 * $this->rows >= 3 * ($this->cardinalities[$index] + 1);
    }
}
