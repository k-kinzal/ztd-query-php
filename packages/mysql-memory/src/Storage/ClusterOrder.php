<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Value\Order;

/**
 * Orders the rows of a table as its clustered index holds them.
 *
 * @visibility MySqlMemory
 */
final class ClusterOrder
{
    /**
     * Answers the rows of a table by row number, in clustered index order.
     *
     * @return array<int, list<int|float|string|null>>
     */
    public function rows(StoredTable $table): array
    {
        $rows = $table->data->rows;
        $columns = $table->definition->clusterColumns();
        if ($columns === []) {
            return $rows;
        }
        $definitions = $table->definition->columns;
        uksort($rows, static function (int $left, int $right) use ($rows, $columns, $definitions): int {
            foreach ($columns as $column) {
                $order = Order::compare($rows[$left][$column], $rows[$right][$column], $definitions[$column]->domain);
                if ($order !== 0) {
                    return $order;
                }
            }

            return $left <=> $right;
        });

        return $rows;
    }
}
