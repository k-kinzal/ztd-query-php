<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Resolves DISTINCT ordering against selected expressions and owned aggregate calls.
 * MySQL 5.6 permits unselected ordering expressions even with ONLY_FULL_GROUP_BY.
 * Verified through SQL on MySQL 5.6.51 and 5.7.44 in Testcontainers.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DistinctOrdering
{
    /**
     * @param array<int, \SqlSemantics\Resolution\VisibleRelation> $relations
     * @param list<Operand> $aggregates The aggregate occurrences owned by this block
     */
    public function check(Select $select, array $relations, Derivation $derivation, array $aggregates): NonGroupedColumn|AggregateInOrdering|null
    {
        if ($derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            return null;
        }
        $matching = new Matching();
        $reads = new ColumnReads();
        $selected = [];
        $expressions = [];
        foreach ($select->items as $item) {
            if (!$item instanceof SelectExpression) {
                return null;
            }
            $expressions[] = $item->expression;
            if ($matching->unwrap($item->expression) instanceof ColumnUse) {
                foreach ($reads->columns($item->expression, $relations, true) as [$key]) {
                    $selected[$key] = true;
                }
            }
        }
        if ($this->redundant($select, $expressions)) {
            return null;
        }
        $owned = array_fill_keys(array_map(spl_object_id(...), $aggregates), true);
        foreach ([...$select->orderBy, ...($select->late->orderBy ?? [])] as $position => $item) {
            $expression = $matching->target($item->expression);
            if ($matching->listed($expression, $expressions)) {
                continue;
            }
            $missing = $this->missing($expression, $relations, $selected, $owned);
            if ($missing instanceof ResolvedColumn) {
                return new NonGroupedColumn(GroupingRule::NotSelected, true, $position + 1, (new GroupedColumns())->name($missing, $relations, $derivation), release: $derivation->context->profile->grammar);
            }
            if ($missing !== null) {
                return new AggregateInOrdering($position + 1, true);
            }
        }
        return null;
    }

    /**
     * @param list<Operand> $expressions
     */
    public function redundant(Select $select, array $expressions): bool
    {
        if ($select->groupBy === null || $select->groupBy->modifier !== null) {
            return false;
        }
        $matching = new Matching();
        foreach ($select->groupBy->items as $item) {
            if (!$matching->listed($matching->target($item->expression), $expressions)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Reads children in written order, preserving the first rejected column or aggregate.
     *
     * @param array<int, \SqlSemantics\Resolution\VisibleRelation> $relations
     * @param array<string, true> $selected
     * @param array<int, true> $owned
     */
    public function missing(Operand $expression, array $relations, array $selected, array $owned): ResolvedColumn|Operand|null
    {
        $pending = [$expression];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_reverse(array_values($value)));
                continue;
            }
            if (!is_object($value)) {
                continue;
            }
            if ($value instanceof Operand && isset($owned[spl_object_id($value)])) {
                return $value;
            }
            if ($value instanceof ColumnUse && $value->hasBinding()) {
                $resolution = $value->scalarInformation()->resolution;
                if ($resolution instanceof AliasTarget) {
                    continue;
                }
                if ($resolution instanceof ResolvedColumn && isset($relations[spl_object_id($resolution->relation)]) && !isset($selected[(new ColumnReads())->key($resolution)])) {
                    return $resolution;
                }
            }
            array_push($pending, ...array_reverse(array_values(get_object_vars($value))));
        }
        return null;
    }
}
