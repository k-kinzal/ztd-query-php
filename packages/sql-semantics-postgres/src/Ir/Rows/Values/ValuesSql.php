<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Values;

use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;

/**
 * Names the complete output heading of a constant table in a local scope.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ValuesSql
{
    /**
     * Emits a transparent projection with deterministic aliases for a local reader.
     */
    public function local(ValuesQuery $query): string
    {
        $renderer = new SelectSql();
        $names = [];
        $width = count($query->rows[0]->cells);
        for ($position = 0; $position < $width; ++$position) {
            $names[] = $renderer->localColumn($position);
        }

        return 'SELECT ' . implode(', ', $names) . ' FROM (' . $query->toString()
            . ') AS "__ztd_values" (' . implode(', ', $names) . ')';
    }
}
