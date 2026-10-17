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
     * Names the first attribute, or the SERIAL type, excluded from a generated column.
     *
     * These declarations are rejected even inside an unreachable stored instruction.
     * Verified on MySQL 5.7.44 and 8.4.7 using CREATE and ALTER TABLE.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute> $attributes The attributes in written order
     */
    public function generated(\SqlSemantics\Platform\MySql\Statement\Type\TypeName $type, array $attributes): ?string
    {
        foreach ($attributes as $attribute) {
            $word = match (true) {
                $attribute instanceof DefaultLiteral, $attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression => 'DEFAULT',
                $attribute instanceof OnUpdate => 'ON UPDATE',
                $attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute && in_array($attribute->keyword, [\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::AutoIncrement, \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::SerialDefaultValue], true) => $attribute->keyword->value,
                $attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\FormatAttribute => 'COLUMN_FORMAT',
                $attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\StorageAttribute => 'STORAGE',
                default => null,
            };
            if ($word !== null) {
                return $word;
            }
        }

        return $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Elementary && $type->kind === \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Serial ? 'SERIAL' : null;
    }

    /**
     * Answers incompatible effective column attributes in declaration order.
     *
     * The last DEFAULT and ON UPDATE clauses replace earlier clauses, including
     * an incompatible clock expression. Verified on MySQL 8.4.7.
     *
     * @return list<InvalidColumnAttribute>
     */
    public function problems(ColumnDefinition $column): array
    {
        $problems = [];
        $type = $column->specification->dataType();
        $attributes = $column->specification->columnAttributes();
        $default = null;
        $update = null;
        foreach ($attributes as $position => $attribute) {
            if ($attribute instanceof DefaultLiteral || $attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression) {
                $default = $position;
            } elseif ($attribute instanceof OnUpdate) {
                $update = $position;
            }
        }
        foreach ($attributes as $position => $attribute) {
            if ($attribute instanceof DefaultLiteral && $position !== $default || $attribute instanceof OnUpdate && $position !== $update) {
                continue;
            }
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
