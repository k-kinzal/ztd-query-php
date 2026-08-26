<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Carries a column change through to the rows a table already holds.
 *
 * Changing what a table declares is only half of an ALTER: the rows the shadow
 * is holding were written under the old declaration, and a column dropped or
 * renamed there has to be dropped or renamed in every one of them.
 */
final class AlterTableRows
{
    /**
     * Takes a column off every row of a table.
     *
     * @param ShadowStore $store Shadow holding the rows
     * @param string $tableName Table the column was dropped from
     * @param string $columnName Column that was dropped
     */
    public function removeColumn(ShadowStore $store, string $tableName, string $columnName): void
    {
        $rows = $store->get($tableName);
        if ($rows === []) {
            return;
        }

        $remaining = [];
        foreach ($rows as $row) {
            unset($row[$columnName]);
            $remaining[] = $row;
        }
        $store->set($tableName, $remaining);
    }

    /**
     * Carries a column's values over to the name it now has.
     *
     * A row that never carried the old column carries nothing under the new
     * one either: a rename moves values, it does not invent them.
     *
     * @param ShadowStore $store Shadow holding the rows
     * @param string $tableName Table the column belongs to
     * @param string $oldName Name the column had
     * @param string $newName Name it now has
     */
    public function renameColumn(ShadowStore $store, string $tableName, string $oldName, string $newName): void
    {
        $rows = $store->get($tableName);
        if ($rows === []) {
            return;
        }

        $renamed = [];
        foreach ($rows as $row) {
            if (array_key_exists($oldName, $row)) {
                $row[$newName] = $row[$oldName];
                unset($row[$oldName]);
            }
            $renamed[] = $row;
        }
        $store->set($tableName, $renamed);
    }
}
