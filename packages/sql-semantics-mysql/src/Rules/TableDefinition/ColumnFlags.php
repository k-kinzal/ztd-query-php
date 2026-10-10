<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Applies the attributes of a column definition in written order, as the server sets its column flags.
 *
 * Rule: MYSQL-COLUMN-FLAGS-001. SERIAL, NOT NULL, AUTO_INCREMENT, SERIAL
 * DEFAULT VALUE and PRIMARY KEY set NOT NULL; NULL clears it. Whether NULL
 * was explicitly written is remembered independently: a primary key with
 * any NULL attribute is refused from MySQL 5.7 on, even if NOT NULL follows
 * it (verified on MySQL 8.4.7). A primary key without NULL is implicitly
 * NOT NULL. TIMESTAMP without NULL or NOT NULL depends on
 * explicit_defaults_for_timestamp. The last visibility attribute wins.
 * Terminates: one pass over the attributes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html (SERIAL),
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_explicit_defaults_for_timestamp,
 * https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnFlags
{
    /**
     * Answers the flags the attributes leave: whether NOT NULL is set, whether NULL was explicitly written, and whether the column is a primary key by an attribute.
     *
     * @return array{bool, bool, bool}
     */
    public function flags(ColumnSpecification $specification): array
    {
        $type = $specification->dataType();
        $notNull = $type instanceof Elementary && $type->kind === ElementaryKind::Serial;
        $explicit = false;
        $primary = false;
        foreach ($specification->columnAttributes() as $attribute) {
            if (!$attribute instanceof KeywordAttribute) {
                continue;
            }
            $primary = $primary || $attribute->keyword === ColumnKeyword::PrimaryKey;
            [$notNull, $explicit] = match ($attribute->keyword) {
                ColumnKeyword::Null => [false, true],
                ColumnKeyword::NotNull, ColumnKeyword::AutoIncrement, ColumnKeyword::SerialDefaultValue, ColumnKeyword::PrimaryKey => [true, $explicit],
                ColumnKeyword::NotSecondary, ColumnKeyword::Unique, ColumnKeyword::Visible, ColumnKeyword::Invisible => [$notNull, $explicit],
            };
        }

        return [$notNull, $explicit, $primary];
    }

    /**
     * Answers the NULL fact of a column; `$keyed` tells whether a table-level primary key includes it.
     */
    public function nullability(ColumnSpecification $specification, bool $keyed = false): Nullability
    {
        [$notNull, $explicit, $primary] = $this->flags($specification);
        if ($notNull || $primary || $keyed) {
            return Nullability::NotNull;
        }
        $type = $specification->dataType();

        return !$explicit && $type instanceof Temporal && $type->kind === TemporalKind::Timestamp ? Nullability::Dependent : Nullability::Nullable;
    }

    /**
     * Tells whether a column attribute keyword is written.
     */
    public function keyword(ColumnSpecification $specification, ColumnKeyword $keyword): bool
    {
        foreach ($specification->columnAttributes() as $attribute) {
            if ($attribute instanceof KeywordAttribute && $attribute->keyword === $keyword) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the type of a column is SERIAL, which is a unique key of its own.
     */
    public function serial(ColumnSpecification $specification): bool
    {
        $type = $specification->dataType();

        return $type instanceof Elementary && $type->kind === ElementaryKind::Serial;
    }

    /**
     * Tells whether the column is INVISIBLE: the last visibility attribute written says INVISIBLE.
     */
    public function invisible(ColumnSpecification $specification): bool
    {
        $invisible = false;
        foreach ($specification->columnAttributes() as $attribute) {
            if ($attribute instanceof KeywordAttribute && ($attribute->keyword === ColumnKeyword::Visible || $attribute->keyword === ColumnKeyword::Invisible)) {
                $invisible = $attribute->keyword === ColumnKeyword::Invisible;
            }
        }

        return $invisible;
    }
}
