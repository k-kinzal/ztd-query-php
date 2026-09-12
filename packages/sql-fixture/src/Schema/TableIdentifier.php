<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Resolves quoted or qualified names to the case-insensitive schema registry key.
 *
 * @visibility root
 */
final class TableIdentifier
{
    /**
     * Removes a database qualifier and quoting before normalizing case.
     */
    public function normalize(string $tableName): string
    {
        $name = str_replace(['`', '"', '[', ']'], '', $tableName);
        $separator = strrpos($name, '.');
        if ($separator !== false) {
            $name = substr($name, $separator + 1);
        }

        return strtolower($name);
    }
}
