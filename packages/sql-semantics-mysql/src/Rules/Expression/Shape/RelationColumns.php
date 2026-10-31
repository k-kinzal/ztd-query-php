<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Statement\Relation as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Expands structural relation columns while leaving catalog inputs unresolved.
 * Qualified stars use their original table occurrence, before join coalescing.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RelationColumns
{
    /**
     * @param array<string, list<Name|null>|null> $common Visible common-table outputs
     * @return list<Name|null>|null Fixed columns of a source, or its catalog dependency
     */
    public function of(M\TableSource|M\Dual|null $source, array $common = []): ?array
    {
        return match (true) {
            $source instanceof M\DerivedTable => $source->columns !== [] ? $source->columns : (new QueryColumns())->of($source->query, common: $common),
            $source instanceof M\TableReference && $source->name->schema === null => $common[$source->name->name->value] ?? null,
            $source instanceof M\JsonTable => (new \SqlSemantics\Platform\MySql\Rules\Query\From\JsonColumns())->names($source->columns),
            $source instanceof M\NestedRelation, $source instanceof M\OdbcJoin, $source instanceof M\EscapedRelation => $this->of($source->relation, $common),
            $source instanceof M\TableList => $this->combined(array_map(fn (M\TableSource $member): ?array => $this->of($member, $common), $source->members)),
            $source instanceof M\JoinedTable => $this->joined($source, $common),
            default => null,
        };
    }

    /**
     * Follows qualifiers through nested joins without hiding the original columns.
     *
     * @param array<string, list<Name|null>|null> $common Visible common-table outputs
     * @return list<Name|null>|null Columns of the exact table spelling, or an unresolved qualifier
     */
    public function qualified(M\TableSource|M\Dual|null $source, QualifiedName $name, array $common = []): ?array
    {
        if ($source instanceof M\NestedRelation || $source instanceof M\OdbcJoin || $source instanceof M\EscapedRelation) {
            return $this->qualified($source->relation, $name, $common);
        }
        if ($source instanceof M\TableList || $source instanceof M\JoinedTable) {
            $members = $source instanceof M\TableList ? $source->members : [$source->left, $source->right];
            foreach ($members as $member) {
                $columns = $this->qualified($member, $name, $common);
                if ($columns !== null) {
                    return $columns;
                }
            }
            return null;
        }
        $alias = $source instanceof M\DerivedTable || $source instanceof M\JsonTable || $source instanceof M\TableReference ? $source->alias : null;
        $table = $source instanceof M\TableReference ? $source->name : null;
        $matches = $alias !== null ? $name->schema === null && $name->name->value === $alias->value : $table !== null && $name->name->value === $table->name->value && ($name->schema === null || $name->schema->value === $table->schema?->value);
        return $matches ? $this->of($source, $common) : null;
    }

    /**
     * @param list<list<Name|null>|null> $parts Consecutive relation outputs
     * @return list<Name|null>|null Concatenated output, unknown if any member is unknown
     */
    public function combined(array $parts): ?array
    {
        $columns = [];
        foreach ($parts as $part) {
            if ($part === null) {
                return null;
            }
            array_push($columns, ...$part);
        }
        return $columns;
    }

    /**
     * Coalesces USING or NATURAL columns only when their names are known.
     *
     * @param array<string, list<Name|null>|null> $common Visible common-table outputs
     * @return list<Name|null>|null The unqualified join projection
     */
    public function joined(M\JoinedTable $join, array $common = []): ?array
    {
        $left = $this->of($join->left, $common);
        $right = $this->of($join->right, $common);
        if ($left === null || $right === null) {
            return null;
        }
        if (!$join->operator->natural() && $join->using === []) {
            return [...$left, ...$right];
        }
        if (in_array(null, $left, true) || in_array(null, $right, true)) {
            return null;
        }
        $first = $join->operator->keepsRight() ? $right : $left;
        $second = $join->operator->keepsRight() ? $left : $right;
        $names = array_map(static fn (Name $name): string => mb_strtolower($name->value, 'UTF-8'), $join->using);
        $other = array_map(static fn (Name $name): string => mb_strtolower($name->value, 'UTF-8'), $second);
        $merged = [];
        $rest = [];
        foreach ($first as $column) {
            $key = mb_strtolower($column->value, 'UTF-8');
            if (in_array($key, $join->operator->natural() ? $other : $names, true)) {
                $merged[] = $column;
            } else {
                $rest[] = $column;
            }
        }
        $keys = array_map(static fn (Name $name): string => mb_strtolower($name->value, 'UTF-8'), $merged);
        return [...$merged, ...$rest, ...array_values(array_filter($second, static fn (Name $name): bool => !in_array(mb_strtolower($name->value, 'UTF-8'), $keys, true)))];
    }
}
