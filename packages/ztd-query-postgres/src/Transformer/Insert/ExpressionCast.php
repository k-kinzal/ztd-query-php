<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Transformer\Insert;

use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Expression cast operations for PostgreSQL insert.
 *
 * @visibility root
 */
final class ExpressionCast
{
    private readonly CastRenderer $castRenderer;

    /**
     * Supplies the dependencies used by this ExpressionCast.
     */
    public function __construct(CastRenderer $castRenderer)
    {
        $this->castRenderer = $castRenderer;
    }

    /**
     * Cast insert expression.
     */
    public function castInsertExpression(string $expression, ColumnType $type): string
    {
        if ($type->family === ColumnTypeFamily::BOOLEAN && $expression === '?') {
            return "CAST(COALESCE(NULLIF(CAST(? AS TEXT), ''), 'false') AS BOOLEAN)";
        }

        return $this->castRenderer->renderCast($expression, $type);
    }
    /**
     * Casts projected expressions and assigns their target column names.
     * @param array<string, string> $projected
     * @param array<string, ColumnType> $columnTypes
     */
    public function projection(array $projected, array $columnTypes): string
    {
        $selects = [];
        foreach ($projected as $column => $expr) {
            $type = $columnTypes[$column] ?? null;
            if ($type instanceof ColumnType) {
                $expr = $this->castInsertExpression($expr, $type);
            }
            $selects[] = $expr . ' AS "' . $column . '"';
        }
        return 'SELECT ' . implode(', ', $selects);
    }
}
