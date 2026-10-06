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
 * Rule: MYSQL-COLUMN-FLAGS-001. The type SERIAL sets NOT NULL (it is BIGINT
 * UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE). NOT NULL, AUTO_INCREMENT, SERIAL
 * DEFAULT VALUE and PRIMARY KEY (or KEY) set NOT NULL; NULL clears it and
 * marks the column explicitly nullable; a later attribute overrides an
 * earlier one (`PT_null_column_attr`, `PT_not_null_column_attr`,
 * `PT_auto_increment_column_attr`, `PT_serial_default_value_column_attr`,
 * `PT_primary_key_column_attr` in sql/parse_tree_column_attrs.h; the
 * `attribute` actions of sql_yacc.yy in 5.6 and 5.7). A primary key column is
 * NOT NULL: "If they are not explicitly declared as NOT NULL, MySQL declares
 * them so implicitly (and silently)"; one that is still explicitly NULL is an
 * error from 5.7 on (MYSQL-TABLE-PROBLEMS-001). A TIMESTAMP column without
 * NULL or NOT NULL is NOT NULL when the server variable
 * explicit_defaults_for_timestamp is OFF and nullable when it is ON; the
 * profile does not carry that variable, so its NULL fact is Dependent. The
 * last of VISIBLE and INVISIBLE decides the visibility. Terminates: one pass
 * over the attributes.
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
     * Answers the flags the attributes leave: whether NOT NULL is set, whether NULL is written explicitly and still in force, and whether the column is a primary key by an attribute.
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
