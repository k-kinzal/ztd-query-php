<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

/**
 * Distinguishes a physical field label from written alias spelling in MySQL.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProjectionLabel
{
    /**
     * Retains intrinsic field names and applies the server's alias normalization.
     */
    public function lower(Select $statement, Field $field, string $name): Identifier
    {
        $trimmed = ltrim($name, " \t\r\n\v\f");
        if ($name === $trimmed || $field->expression === null) {
            return new Identifier($name);
        }
        if (!$field->resolution instanceof ResolvedColumn) {
            return new Identifier($trimmed);
        }
        foreach ($statement->items as $item) {
            if ($item instanceof SelectExpression && $item->expression === $field->expression && $item->alias !== null) {
                return new Identifier($trimmed);
            }
        }

        return new Identifier($name);
    }
}
