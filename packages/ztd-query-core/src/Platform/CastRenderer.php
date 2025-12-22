<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ColumnType;

/**
 * Renders platform-specific CAST expressions.
 *
 * Each platform provides an implementation that maps ColumnType
 * to the appropriate CAST syntax for that SQL dialect.
 */
interface CastRenderer
{
    /**
     * Render a CAST expression for a given expression string.
     *
     * @param string $expression The SQL expression to cast (e.g. "'Alice'", "1", "NULL").
     * @param ColumnType $type The target column type.
     * @return string A SQL CAST expression (e.g. "CAST('Alice' AS CHAR)").
     */
    public function renderCast(string $expression, ColumnType $type): string;

    /**
     * Render a CAST expression for NULL with the given type.
     *
     * Used for empty CTE definitions to preserve column type information.
     *
     * @param ColumnType $type The target column type.
     * @return string A SQL CAST(NULL AS ...) expression.
     */
    public function renderNullCast(ColumnType $type): string;
}
