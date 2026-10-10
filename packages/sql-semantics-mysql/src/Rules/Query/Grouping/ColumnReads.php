<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * Finds the columns of a query block an expression reads, each with a key that identifies one column of one table occurrence.
 *
 * The grouping check (MYSQL-ONLY-FULL-GROUP-BY-001) compares columns by these keys: every use
 * of one column of one occurrence has the same key however it is qualified, and a column of a
 * derived table or a common table is the column of the occurrence, not the one it selects.
 * An alias of a select item reads the columns of the item. Aggregates without a window, and the
 * arguments of GROUPING(), read their columns only when asked, since a grouped block may
 * aggregate any column; so does the argument of ANY_VALUE(), which the check accepts as it is.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/group-by-handling.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnReads
{
    /**
     * Answers the columns of the block an expression reads, with their keys, outside aggregates unless asked to look into them.
     *
     * @param array<int, VisibleRelation> $relations
     * @return list<array{string, ResolvedColumn}>
     */
    public function columns(Node $value, array $relations, Facts $facts, bool $aggregated): array
    {
        if (!$aggregated && ($this->aggregate($value) || $this->anyValue($value))) {
            return [];
        }
        if ($value instanceof ColumnUse && $facts->covers($value)) {
            $resolution = $facts->scalar($value)->resolution;
            if ($resolution instanceof AliasTarget) {
                return $resolution->field->expression === null ? [] : $this->columns($resolution->field->expression, $relations, $facts, $aggregated);
            }

            return $resolution instanceof ResolvedColumn && isset($relations[spl_object_id($resolution->relation)]) ? [[$this->key($resolution), $resolution]] : [];
        }
        $properties = get_object_vars($value);
        $children = [];
        array_walk_recursive($properties, static function ($property) use (&$children): void {
            if ($property instanceof Node) {
                $children[] = $property;
            }
        });
        $found = [];
        foreach ($children as $child) {
            array_push($found, ...$this->columns($child, $relations, $facts, $aggregated));
        }

        return $found;
    }

    /**
     * Tells whether a part of an expression aggregates the rows of a group: an aggregate without a window, or GROUPING().
     */
    public function aggregate(Node $value): bool
    {
        return $value instanceof Aggregate && $value->over === null
            || $value instanceof GroupConcat && $value->over === null
            || $value instanceof JsonObjectAggregate
            || $value instanceof KeywordCall && $value->function === KeywordFunction::Grouping;
    }

    /**
     * Tells whether a part of an expression is a call of ANY_VALUE(), whose argument a grouped block may read whatever columns it reads.
     */
    public function anyValue(Node $value): bool
    {
        return $value instanceof FunctionCall && $value->schema === null && strcasecmp($value->name->value, 'ANY_VALUE') === 0;
    }

    /**
     * Answers the key of the column a resolution names, the same for every use of one column of one occurrence.
     */
    public function key(ResolvedColumn $resolution): string
    {
        $slot = $resolution->slot;
        while ($slot->origin instanceof OutputSlot && $slot->column === null) {
            $slot = $slot->origin;
        }

        return spl_object_id($resolution->relation) . ':' . spl_object_id($slot->declaration() ?? $slot);
    }

    /**
     * Answers the keys of the columns of some occurrences by their lowercase names.
     *
     * @param array<int, VisibleRelation> $relations
     * @return array<string, list<string>>
     */
    public function named(array $relations): array
    {
        $named = [];
        foreach ($relations as $relation) {
            foreach ($relation->shape->slots as $slot) {
                if ($slot->name !== null) {
                    $named[mb_strtolower($slot->name->value)][] = $this->key(new ResolvedColumn($relation->relation, $slot));
                }
            }
        }

        return $named;
    }
}
