<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema\Column;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes a column using its own typed value source and attributes.
 *
 * @visibility SqlSemantics
 */
final class Columns
{
    /**
     * Writes declaration-level nullability and generation behavior; a MySQL generated column writes its expression before the other attributes, as its grammar requires.
     */
    public static function write(ColumnDefinition $column, Dialect $dialect): Tree
    {
        $generation = $column->generation;
        $computed = $dialect === Dialect::MySql && $generation instanceof Column\ComputedColumn ? [Build::keyword('GENERATED ALWAYS AS'), Build::parentheses(Expressions::write($generation->expression)), Build::keyword(strtoupper($generation->storage->value))] : [];
        $parts = [Build::identifier([$column->name], $dialect), TypeDeclaration::write($column->type), ...$computed, ColumnAttributes::write($column->attributes, $dialect)];
        if ($column->nullability === \SqlSemantics\Type\Nullability::NotNull) {
            $parts[] = Build::keyword('NOT NULL');
        }
        if ($generation instanceof Column\SuppliedColumn) {
            if ($generation->default !== null) {
                array_push($parts, Build::keyword('DEFAULT'), self::defaultValue($generation->default, $dialect));
            }
            if ($generation->onUpdate !== null) {
                array_push($parts, Build::keyword('ON UPDATE'), Expressions::write($generation->onUpdate));
            }
        } elseif ($generation instanceof Column\ComputedColumn && $computed === []) {
            array_push($parts, Build::keyword('GENERATED ALWAYS AS'), Build::parentheses(Expressions::write($generation->expression)), Build::keyword(strtoupper($generation->storage->value)));
        } elseif ($generation instanceof Column\IdentityColumn) {
            array_push($parts, Build::keyword('GENERATED ' . ($generation->mode === Column\IdentityMode::Always ? 'ALWAYS' : 'BY DEFAULT') . ' AS IDENTITY'), Sequence::write($generation->sequence));
        } elseif ($generation instanceof Column\AutoIncrementColumn) {
            $parts[] = Build::keyword($dialect === Dialect::Sqlite ? 'PRIMARY KEY AUTOINCREMENT' : 'AUTO_INCREMENT');
        }
        return new Tree('column', $parts);
    }

    /**
     * Writes a column default: MySQL and SQLite take literals, signed numbers and the current-time keywords bare and any other expression in parentheses.
     */
    public static function defaultValue(\SqlSemantics\Model\Expression $default, Dialect $dialect): Tree
    {
        if ($dialect === Dialect::PostgreSql) {
            return Expressions::write($default);
        }
        $keywords = $dialect === Dialect::MySql ? ['CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP'] : ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'];
        if ($default instanceof \SqlSemantics\Model\Scalar\Value\ContextReference && in_array($default->request->value, $keywords, true)) {
            return Expressions::write($default);
        }
        return MySqlTable\ColumnChanges::default($default);
    }
}
