<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute;
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
     * Finds enforcement detached from a preceding CHECK or its enforcement chain.
     *
     * Repeated enforcement replaces the previous choice. Any other intervening
     * attribute ends that chain. Observed through MySQL 8.0.44 and 8.4.7 SQL.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute> $attributes The written attributes
     */
    public function enforcement(array $attributes): ?int
    {
        $check = false;
        foreach ($attributes as $index => $attribute) {
            if ($attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute) {
                if (!$check) {
                    return $index;
                }
            } else {
                $check = $attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
            }
        }
        return null;
    }

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
     * Names intrinsic contradictions after replacement of DEFAULT and ON UPDATE.
     *
     * Floating AUTO_INCREMENT remains a release constraint: MySQL 5.x accepts it.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute> $attributes The written attributes
     * @return list<'SRID'|'DEFAULT'|'ON UPDATE'|'AUTO_INCREMENT'> Incompatible effective attributes
     */
    public function incompatible(\SqlSemantics\Platform\MySql\Statement\Type\TypeName $type, array $attributes): array
    {
        $problems = [];
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
            $problem = $this->attribute($type, $attribute);
            if ($problem !== null) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }
    /**
     * Checks one effective attribute against the type, without any catalog input.
     *
     * @return 'SRID'|'DEFAULT'|'ON UPDATE'|'AUTO_INCREMENT'|null The intrinsic incompatibility
     */
    public function attribute(\SqlSemantics\Platform\MySql\Statement\Type\TypeName $type, \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute $attribute): ?string
    {
        if ($attribute instanceof SridAttribute && !$type instanceof Spatial) {
            return 'SRID';
        }
        if (($attribute instanceof DefaultLiteral || $attribute instanceof OnUpdate) && $attribute->value instanceof ClockCall) {
            if (!$type instanceof Temporal || !in_array($type->kind, [TemporalKind::DateTime, TemporalKind::Timestamp], true) || (int) ($type->precision ?? '0') !== $attribute->value->decimals()) {
                return $attribute instanceof OnUpdate ? 'ON UPDATE' : 'DEFAULT';
            }
        }
        if ($attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute && in_array($attribute->keyword, [\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::AutoIncrement, \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::SerialDefaultValue], true) && !($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Integral || $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Floating || $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Elementary && in_array($type->kind, [\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Boolean, \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Serial], true))) {
            return 'AUTO_INCREMENT';
        }
        return null;
    }

}
