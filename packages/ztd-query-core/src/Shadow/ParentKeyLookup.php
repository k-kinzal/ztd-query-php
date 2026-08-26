<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Shadow\Row\RowMatch;

/**
 * Reports whether the row a foreign key points at is still there.
 *
 * A cascade is only set off where nothing else holds the key up. Another row
 * of the parent may carry the same values — the key need not be unique — and
 * while one does, the children still reference something that exists.
 *
 * @phpstan-import-type RowValue from StatementInterface
 */
final class ParentKeyLookup
{
    /**
     * @param RowMatch $rows Finds a row among rows
     */
    public function __construct(private readonly RowMatch $rows = new RowMatch())
    {
    }

    /**
     * Reports whether some row of the parent still carries these values.
     *
     * @param ShadowStore $store Shadow to look in
     * @param string $table Parent table
     * @param list<string> $columns Parent columns the key points at
     * @param list<RowValue> $values Values the child references
     *
     * @return bool True when a parent row still carries them
     */
    public function exists(ShadowStore $store, string $table, array $columns, array $values): bool
    {
        foreach ($store->get($table) as $row) {
            if ($this->rows->carries($row, $columns, $values)) {
                return true;
            }
        }

        return false;
    }
}
