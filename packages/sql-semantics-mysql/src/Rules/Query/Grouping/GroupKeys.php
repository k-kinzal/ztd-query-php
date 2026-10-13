<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\CallEffects;
use SqlSemantics\Platform\MySql\Rules\Query\Aggregation;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;

/**
 * Distinguishes an implicit or constant group from groups partitioned by input rows.
 *
 * Constant keys do not require stored aggregate result fields. ROLLUP still adds
 * levels, but only varying keys defer aggregate parents beyond a window's DISTINCT.
 * Column aliases use their resolved targets; calls with per-read effects and variables
 * assigned by the statement remain varying. Verified through SQL and PDO on MySQL 8.4.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupKeys
{
    /**
     * Tells whether grouping emits a single direct aggregate result without ROLLUP.
     */
    public function direct(Select $select, ?Facts $facts = null): bool
    {
        if ($select->groupBy === null) {
            return (new Aggregation())->aggregates([...$select->items, ...$select->orderBy, ...($select->late->orderBy ?? []), ...array_filter([$select->having])]);
        }

        return $select->groupBy->modifier === null && !$this->varying($select, $facts);
    }

    /**
     * Tells whether any grouping key can vary with input rows.
     */
    public function varying(Select $select, ?Facts $facts = null): bool
    {
        $assigned = $this->assigned($select);
        foreach ($select->groupBy->items ?? [] as $item) {
            $key = $facts === null ? $item->expression : (new Matching())->target($item->expression, $facts);
            if (!$this->constant($key, $facts, $assigned)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inspects constant expressions without evaluating values or entering retained queries.
     *
     * @param list<string> $assigned Lower-case variable names assigned by the statement
     */
    public function constant(Node $node, ?Facts $facts = null, array $assigned = []): bool
    {
        $replacement = $node instanceof Operand && $facts !== null && $facts->covers($node) ? $facts->scalar($node)->replacement : null;
        if ($replacement !== null) {
            return $this->constant($replacement, $facts, $assigned);
        }
        if ($node instanceof UserVariable) {
            return !in_array(strtolower($node->name->value), $assigned, true);
        }
        if ($node instanceof Query || $node instanceof ColumnUse || $node instanceof OutputOrdinal || $node instanceof SetFunction || $node instanceof WindowFunction || (new CallEffects())->uncacheable($node, GrammarRelease::MySql847)) {
            return false;
        }
        foreach ($this->children($node) as $child) {
            if (!$this->constant($child, $facts, $assigned)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Collects variable assignments even when an expression nests them in a subquery.
     *
     * @return list<string>
     */
    public function assigned(Node $node): array
    {
        $names = $node instanceof VariableAssignment ? [strtolower($node->target->name->value)] : [];
        foreach ($this->children($node) as $child) {
            array_push($names, ...$this->assigned($child));
        }

        return $names;
    }

    /**
     * Reads the direct syntax children, preserving nested array order.
     *
     * @return list<Node>
     */
    public function children(Node $node): array
    {
        $children = [];
        $properties = get_object_vars($node);
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });

        return $children;
    }
}
