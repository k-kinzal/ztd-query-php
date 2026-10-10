<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;

/**
 * Resolves type restrictions on column attributes.
 *
 * Rule: MYSQL-COLUMN-ATTRIBUTES-001. SRID requires a spatial type. Automatic initialization
 * and updates require DATETIME or TIMESTAMP with exactly the same fractional seconds precision.
 * Terminates: one pass over the finite attribute list. Verified on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html,
 * https://dev.mysql.com/doc/refman/8.4/en/spatial-type-overview.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnAttributes
{
    /**
     * Answers incompatible column attributes in declaration order.
     *
     * @return list<InvalidColumnAttribute>
     */
    public function problems(ColumnDefinition $column): array
    {
        $problems = [];
        $type = $column->specification->dataType();
        foreach ($column->specification->columnAttributes() as $attribute) {
            if ($attribute instanceof SridAttribute && !$type instanceof Spatial) {
                $problems[] = new InvalidColumnAttribute($column->name->column, 'SRID');
            }
            if (($attribute instanceof DefaultLiteral || $attribute instanceof OnUpdate) && $attribute->value instanceof ClockCall) {
                if (!$type instanceof Temporal || !in_array($type->kind, [TemporalKind::DateTime, TemporalKind::Timestamp], true) || (int) ($type->precision ?? '0') !== $attribute->value->decimals()) {
                    $problems[] = new InvalidColumnAttribute($column->name->column, $attribute instanceof OnUpdate ? 'ON UPDATE' : 'DEFAULT');
                }
            }
        }

        return $problems;
    }
}
