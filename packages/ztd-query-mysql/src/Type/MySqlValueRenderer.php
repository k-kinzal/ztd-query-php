<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use Stringable;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\ColumnType;

/**
 * Encodes shadow values without relying on MySQL string escape modes.
 */
final class MySqlValueRenderer implements ValueRenderer
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly CastRenderer $castRenderer = new MySqlCastRenderer())
    {
    }

    /**
     * Render Value for the supplied MySQL input.
     */
    public function renderValue(mixed $value, ?ColumnType $type = null): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($type === null && is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($type === null && is_float($value)) {
            return var_export($value, true);
        }

        if ($type === null && $value instanceof Stringable) {
            return (string) $value;
        }

        $resolvedType = $type ?? (new Type\Value\ScalarExpression())->inferType($value);
        $expression = (new Type\Value\ScalarExpression())->renderExpression($value, $resolvedType, $type !== null);

        return $this->castRenderer->renderCast($expression, $resolvedType);
    }

}
