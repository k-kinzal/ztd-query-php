<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * A value renderer that writes SQL in the plainest form every dialect accepts.
 *
 * Real dialects disagree about how a boolean, a null and a quoted string are
 * written, which is the whole reason this is an interface. A test about what
 * the contract promises rather than about a dialect uses this.
 */
final class FakeValueRenderer implements ValueRenderer
{
    /**
     * Writes a value as the SQL expression standing for it.
     *
     * @param mixed $value Value to write
     * @param ColumnType|null $type Column type it is being written for, where one is known
     *
     * @return string The expression
     */
    public function renderValue(mixed $value, ?ColumnType $type = null): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_int($value) || is_float($value)) {
            return $type?->family === ColumnTypeFamily::TEXT
                ? "'" . (string) $value . "'"
                : (string) $value;
        }

        return "'" . str_replace("'", "''", is_string($value) ? $value : '') . "'";
    }
}
