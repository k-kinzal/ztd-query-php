<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes back a star over a relation that repeats a column name: its columns can only be selected together by position, so no list of names rebinds to them and no partial or reordered selection of them has an SQL spelling.
 * @visibility SqlSemantics
 */
final class PositionalStars
{
    /**
     * Counts the outputs from the given index that are the complete expansion, in order, of a relation that repeats a column name; zero when the output there can be written by name.
     * @param list<OutputColumn> $outputs
     * @throws InvalidStructure When the output there selects a column whose name its relation repeats outside such a complete expansion, which no SQL can spell.
     */
    public static function length(array $outputs, int $index): int
    {
        $first = $outputs[$index]->expression ?? null;
        if (!$first instanceof ColumnReference || count(array_keys($first->binding->relationColumns, $first->binding->column->name, true)) < 2) {
            return 0;
        }
        $width = count($first->binding->relationColumns);
        $expansion = array_slice($outputs, $index, $width);
        if ($first->binding->column->ordinal !== 0 || count($first->name) < 2 || count($expansion) !== $width) {
            throw new InvalidStructure('A column whose name its relation repeats can only be selected by a star over the complete relation.');
        }
        foreach ($expansion as $offset => $output) {
            $value = $output->expression;
            if (!$value instanceof ColumnReference || $value->binding->relationId !== $first->binding->relationId || $value->binding->column->ordinal !== $offset || $output->name !== $value->binding->column->name || array_slice($value->name, 0, -1) !== array_slice($first->name, 0, -1)) {
                throw new InvalidStructure('A column whose name its relation repeats can only be selected by a star over the complete relation.');
            }
        }
        return $width;
    }

    /**
     * Writes the star qualified by the relation name the expanded columns carry.
     */
    public static function star(ColumnReference $first, Dialect $dialect): Tree
    {
        $qualifier = array_slice($first->name, 0, -1);
        return new Tree('wildcard', [...($qualifier === [] ? [] : [Build::identifier($qualifier, $dialect), Build::keyword('.')]), Build::keyword('*')]);
    }
}
