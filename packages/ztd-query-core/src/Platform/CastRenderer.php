<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\ColumnDeclaration;

/**
 * Renders platform-specific CAST expressions.
 *
 * Each platform provides an implementation that maps ColumnDeclaration
 * to the appropriate CAST syntax for that SQL dialect.
 */
interface CastRenderer
{
    /**
     * Render a CAST expression for a given expression string.
     *
     * @param string $expression The SQL expression to cast (e.g. "'Alice'", "1", "NULL").
     * @param ColumnDeclaration $type The target column type.
     * @return string A SQL CAST expression (e.g. "CAST('Alice' AS CHAR)").
     */
    public function renderCast(string $expression, ColumnDeclaration $type): string;

    /**
     * Render a CAST expression for NULL with the given type.
     *
     * Used for empty CTE definitions to preserve column type information.
     *
     * @param ColumnDeclaration $type The target column type.
     * @return string A SQL CAST(NULL AS ...) expression.
     */
    public function renderNullCast(ColumnDeclaration $type): string;
}
