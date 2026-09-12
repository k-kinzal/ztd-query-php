<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Stored Columns.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class StoredColumns
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private string $tableName)
    {
    }
    /**
     * Remove Column From Store for the supplied MySQL input.
     */
    public function removeColumnFromStore(ShadowStore $store, string $columnName): void
    {
        $rows = $store->get($this->tableName);
        if ($rows === []) {
            return;
        }

        $newRows = [];
        foreach ($rows as $row) {
            unset($row[$columnName]);
            $newRows[] = $row;
        }
        $store->set($this->tableName, $newRows);
    }

    /**
     * Rename Column In Store for the supplied MySQL input.
     */
    public function renameColumnInStore(ShadowStore $store, string $oldName, string $newName): void
    {
        $rows = $store->get($this->tableName);
        if ($rows === []) {
            return;
        }

        $newRows = [];
        foreach ($rows as $row) {
            if (array_key_exists($oldName, $row)) {
                $row[$newName] = $row[$oldName];
                unset($row[$oldName]);
            }
            $newRows[] = $row;
        }
        $store->set($this->tableName, $newRows);
    }
}
