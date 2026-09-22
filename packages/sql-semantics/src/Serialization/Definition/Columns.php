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
     * Writes declaration-level nullability and generation behavior.
     */
    public static function write(ColumnDefinition $column, Dialect $dialect): Tree
    {
        $parts = [Build::identifier([$column->name], $dialect), TypeDeclaration::write($column->type), ColumnAttributes::write($column->attributes, $dialect)];
        if ($column->nullability === \SqlSemantics\Type\Nullability::NotNull) {
            $parts[] = Build::keyword('NOT NULL');
        }
        $generation = $column->generation;
        if ($generation instanceof Column\SuppliedColumn) {
            if ($generation->default !== null) {
                array_push($parts, Build::keyword('DEFAULT'), Expressions::write($generation->default));
            }
            if ($generation->onUpdate !== null) {
                array_push($parts, Build::keyword('ON UPDATE'), Expressions::write($generation->onUpdate));
            }
        } elseif ($generation instanceof Column\ComputedColumn) {
            array_push($parts, Build::keyword('GENERATED ALWAYS AS'), Build::parentheses(Expressions::write($generation->expression)), Build::keyword(strtoupper($generation->storage->value)));
        } elseif ($generation instanceof Column\IdentityColumn) {
            array_push($parts, Build::keyword('GENERATED ' . ($generation->mode === Column\IdentityMode::Always ? 'ALWAYS' : 'BY DEFAULT') . ' AS IDENTITY'), Sequence::write($generation->sequence));
        } elseif ($generation instanceof Column\AutoIncrementColumn) {
            $parts[] = Build::keyword($dialect === Dialect::Sqlite ? 'PRIMARY KEY AUTOINCREMENT' : 'AUTO_INCREMENT');
        }
        return new Tree('column', $parts);
    }
}
