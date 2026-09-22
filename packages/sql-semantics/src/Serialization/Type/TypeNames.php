<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Type;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity;

/**
 * Writes type identifiers, labels and array element declarations safely.
 * @visibility SqlSemantics
 */
final class TypeNames
{
    /**
     * A SQLite type name is an identifier, including multiword custom names.
     */
    public static function writeSqlite(Identity\SqliteDeclaration $type): Tree
    {
        return new Tree('sqlite-type', [...($type->declaredName === '' ? [] : [Build::identifier([$type->declaredName], Dialect::Sqlite)]), TypeParameters::numbers([$type->size, $type->scale])]);
    }

    /**
     * Writes a PostgreSQL named type and its modifier expressions.
     */
    public static function named(Identity\NamedIdentity $type, Dialect $dialect): Tree
    {
        return new Tree('named-type', [Build::identifier($type->reference->parts, $dialect), ...($type->arguments === [] ? [] : [Build::parentheses(Build::separated(array_map(Expressions::write(...), $type->arguments)))])]);
    }

    /**
     * Writes every declared array dimension without changing its element type.
     */
    public static function array(Identity\ArrayStorage $type): Tree
    {
        $parts = [TypeDeclaration::write($type->element)];
        foreach ($type->dimensions as $dimension) {
            $parts[] = Build::keyword('[' . ($dimension->length->spelling ?? '') . ']');
        }
        return new Tree('array-type', $parts);
    }

    /**
     * Writes label values with literal boundaries.
     */
    public static function labels(Identity\Enumeration|Identity\LabelSet $type, Dialect $dialect): Tree
    {
        $labels = array_map(Expressions::write(...), $type->labels);
        return new Tree('label-type', [Build::keyword($type instanceof Identity\Enumeration ? 'ENUM' : 'SET'), Build::parentheses(Build::separated($labels))]);
    }
}
