<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\OutputSlot;
use UnitEnum;

/**
 * Tells whether two parts of a statement are the same expression, as the server matches a select item, HAVING or ORDER BY with a grouping expression.
 *
 * Two expressions are the same when they are written alike, without regard to parentheses or to
 * the case of names, with each column name read as the column it resolves to, so that a
 * qualified and an unqualified name of one column match.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
 *
 * @visibility MySqlMemory
 */
final class Equivalence
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Tells whether a column name resolves to the column a resolution names.
     */
    public function sameColumn(ResolvedColumn $resolution, ColumnUse $use): bool
    {
        $facts = $this->planner->compiler->facts;
        $other = $facts->covers($use) ? $facts->scalar($use)->resolution : null;

        return $other instanceof ResolvedColumn && $other->relation === $resolution->relation && $this->column($other) === $this->column($resolution);
    }

    /**
     * Answers the column a resolution names, the same for every use of one column of a relation, whether or not the use sees the column as one that can be NULL.
     */
    public function column(ResolvedColumn $resolution): object
    {
        $slot = $resolution->slot;
        while ($slot->origin instanceof OutputSlot && $slot->column === null) {
            $slot = $slot->origin;
        }

        return $slot->declaration() ?? $slot;
    }

    /**
     * Tells whether two parts of a statement are the same expression: written alike, without regard to parentheses or to the case of names, with each column name read as the column it resolves to.
     */
    public function same(object|int|float|string|bool|null $left, object|int|float|string|bool|null $right): bool
    {
        while ($left instanceof Grouped) {
            $left = $left->operand;
        }
        while ($right instanceof Grouped) {
            $right = $right->operand;
        }
        if (!is_object($left) || !is_object($right)) {
            return $left === $right;
        }
        if ($left::class !== $right::class) {
            return false;
        }
        $facts = $this->planner->compiler->facts;
        if ($left instanceof ColumnUse && $right instanceof ColumnUse && $facts->covers($left) && $facts->covers($right)) {
            $first = $facts->scalar($left)->resolution;
            if ($first instanceof ResolvedColumn) {
                return $this->sameColumn($first, $right);
            }
        }
        if ($left instanceof UnitEnum) {
            return $left === $right;
        }
        if ($left instanceof Name && $right instanceof Name) {
            return strcasecmp($left->value, $right->value) === 0;
        }

        return $this->sameProperties($left, $right);
    }

    /**
     * Tells whether two parts of a statement of the same class hold the same expressions: the
     * same properties, in the same order, each the same expression.
     */
    public function sameProperties(object $left, object $right): bool
    {
        $values = [[], []];
        foreach ([$left, $right] as $side => $object) {
            $properties = get_object_vars($object);
            array_walk_recursive($properties, static function ($value, $key) use (&$values, $side): void {
                $values[$side][] = [$key, $value];
            });
        }
        if (count($values[0]) !== count($values[1])) {
            return false;
        }
        foreach ($values[0] as $index => [$key, $value]) {
            [$otherKey, $other] = $values[1][$index];
            if ($key !== $otherKey || !(is_object($value) || is_scalar($value) || $value === null) || !(is_object($other) || is_scalar($other) || $other === null) || !$this->same($value, $other)) {
                return false;
            }
        }

        return true;
    }
}
