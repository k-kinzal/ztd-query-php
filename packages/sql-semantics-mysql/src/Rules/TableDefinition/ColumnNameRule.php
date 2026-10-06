<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

/**
 * Tells which names MySQL accepts as the name of a table or view column.
 *
 * Rule: MYSQL-COLUMN-NAME-001. A column name is not empty, does not end in
 * a space character, and has at most 64 characters; the server reads the
 * name up to its first NUL byte (`check_column_name`). Source:
 * sql/table.cc of each release,
 * https://dev.mysql.com/doc/refman/8.4/en/identifier-length.html,
 * https://dev.mysql.com/doc/refman/8.4/en/identifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnNameRule
{
    /**
     * Tells whether a name is a valid column name.
     */
    public function valid(string $name): bool
    {
        $name = explode("\0", $name)[0];

        return $name !== '' && !ctype_space($name[-1]) && $this->length($name) <= 64;
    }

    /**
     * Answers the number of characters of a name in the system character set, or of bytes when it is not well formed.
     */
    public function length(string $name): int
    {
        return preg_match('//u', $name) === 1 ? mb_strlen($name, 'UTF-8') : strlen($name);
    }
}
