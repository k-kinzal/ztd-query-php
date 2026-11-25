<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Closes a group name over the physical key and any competing projected name.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupNameScope
{
    /**
     * Detects an output of the same name that denotes a different value.
     * @param list<GroupColumn> $results
     */
    public function collides(ColumnReference $column, Identifier $name, array $results): bool
    {
        $key = $name->key();
        foreach ($results as $result) {
            $value = $result->value;
            if ($result->name->key() === $key && (!$value instanceof ColumnReference
                || $value->slot !== $column->slot || $value->occurrence !== $column->occurrence)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks the bare binding and returns its physical column coordinate.
     * @param list<GroupColumn> $results
     */
    public function owned(GroupNameReference $value, TableSource $source, array $results): ColumnReference
    {
        $name = $source->columns[$value->column->slot] ?? null;
        Check::input(
            $name !== null && $name->key() === $value->name->key(),
            'A bare group name must match its physical input field.'
        );
        Check::input(
            $this->collides($value->column, $value->name, $results),
            'A bare group name must match a different projected value.'
        );

        return $value->column;
    }

    /**
     * Checks the ordered keys and records each physical member.
     * @param list<ColumnReference|GroupNameReference> $keys
     * @param list<GroupColumn> $results
     * @return array<int, true>
     */
    public function members(array $keys, TableSource $source, array $results): array
    {
        $members = [];
        foreach ($keys as $key) {
            $column = $key instanceof GroupNameReference ? $this->owned($key, $source, $results) : $key;
            Check::input(
                $column->occurrence === 0 && ($source->columnTypes[$column->slot] ?? null) instanceof ColumnKind,
                'Each grouping key must belong to its input.'
            );
            $members[$column->slot] = true;
        }

        return $members;
    }
}
