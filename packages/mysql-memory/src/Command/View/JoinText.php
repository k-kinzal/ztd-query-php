<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Relation;

/**
 * Writes the column equalities retained by NATURAL and USING view joins.
 * @visibility MySqlMemory
 */
final class JoinText
{
    /**
     * @param ViewText $writer The stored query writer and its resolved relation facts
     */
    public function __construct(public readonly ViewText $writer)
    {
    }

    /**
     * Answers the stored ON predicate, or null for an unresolved operand layout.
     */
    public function condition(JoinedTable $join): ?string
    {
        if (!$join->operator->natural() && $join->using === []) {
            return '';
        }
        $left = $this->columns($join->left);
        $right = $this->columns($join->right);
        if ($left === null || $right === null) {
            return null;
        }
        $names = $join->operator->natural() ? array_keys(array_intersect_key($join->operator->keepsRight() ? $right : $left, $join->operator->keepsRight() ? $left : $right)) : array_map(static fn ($name): string => mb_strtolower($name->value, 'UTF-8'), $join->using);
        $parts = [];
        foreach ($names as $name) {
            if (!isset($left[$name], $right[$name])) {
                return null;
            }
            $parts[] = '(' . ($join->operator->keepsRight() ? $right[$name] : $left[$name]) . ' = ' . ($join->operator->keepsRight() ? $left[$name] : $right[$name]) . ')';
        }
        return $parts === [] ? ($join->operator->keepsLeft() || $join->operator->keepsRight() ? '1' : '') : (count($parts) === 1 ? $parts[0] : '(' . implode(' and ', $parts) . ')');
    }

    /**
     * @return array<string, string>|null Stored expressions by case-insensitive output name
     */
    public function columns(Relation $relation): ?array
    {
        if ($relation instanceof NestedRelation) {
            return $this->columns($relation->relation);
        }
        if ($relation instanceof JoinedTable) {
            $left = $this->columns($relation->left);
            $right = $this->columns($relation->right);
            return $left === null || $right === null ? null : ($relation->operator->keepsRight() ? $right + $left : $left + $right);
        }
        $columns = [];
        foreach ($this->writer->facts->relation($relation)->shape->slots as $slot) {
            if ($slot->name !== null) {
                $text = $this->writer->column(new ResolvedColumn($relation, $slot));
                if ($text === null) {
                    return null;
                }
                $columns[mb_strtolower($slot->name->value, 'UTF-8')] = $text;
            }
        }
        return $columns;
    }
}
