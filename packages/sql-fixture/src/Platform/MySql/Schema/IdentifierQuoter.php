<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

/**
 * Quotes table identifiers for the native schema query.
 *
 * @visibility root
 */
final class IdentifierQuoter
{
    /**
     * Quote a table name for use in SQL.
     */
    public function quoteTableName(string $tableName): string
    {
        $tableName = str_replace('`', '``', $tableName);
        if (str_contains($tableName, '.')) {
            $parts = explode('.', $tableName, 2);
            return '`' . $parts[0] . '`.`' . $parts[1] . '`';
        }

        return '`' . $tableName . '`';
    }
}
