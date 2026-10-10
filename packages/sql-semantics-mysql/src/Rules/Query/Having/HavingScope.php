<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Having;

use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Builds the environment of a HAVING clause, and the environment of the set functions written in it.
 *
 * Rule: MYSQL-HAVING-SCOPE-001. Ordinary names written in HAVING see the
 * selected result and grouping columns. Aggregate arguments can read the input
 * columns of the block. The HAVING environment carries a GroupedRow describing
 * that result; aggregate arguments remove it from their current position and
 * retain an argument marker across nested queries. Nested queries start their
 * own scope but keep the enclosing HAVING position available for correlation.
 * Terminates: one pass over the visible relations.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class HavingScope
{
    /**
     * Answers the environment of a HAVING clause: the given one with the grouped row of the block.
     */
    public function enter(Environment $environment, GroupedRow $row): Environment
    {
        return new Environment($environment->context, $environment->outer, [...$environment->relations, new VisibleRelation($row, new RowShape([]))], $environment->commonTables, $environment->aliases, aggregation: $environment->aggregation, aggregatesAllowed: $environment->aggregatesAllowed, aggregateArgument: $environment->aggregateArgument, projection: $environment->projection);
    }

    /**
     * Answers the GROUP BY items that are columns, each with its column as the HAVING position sees it.
     *
     * An item is a column when it is a column name, or an alias or a
     * position of a select list item that is a column name (its value is
     * then that column, `real_item()` in the server). The HAVING position of
     * a block that aggregates or groups with a modifier sees the columns as
     * they may be NULL there; the given lists hold each occurrence before
     * and after that extension, in the same order.
     *
     * @param list<ScalarFact> $items The facts of the GROUP BY items, in order
     * @param list<VisibleRelation> $visible The occurrences of the FROM clause
     * @param list<VisibleRelation> $output The same occurrences as the HAVING position sees them
     * @return list<Field>
     */
    public function grouping(array $items, array $visible, array $output): array
    {
        $columns = [];
        foreach ($items as $fact) {
            $resolution = $fact->resolution instanceof AliasTarget && $fact->resolution->field->expression instanceof ColumnUse ? $fact->resolution->field->resolution : $fact->resolution;
            if (!$resolution instanceof ResolvedColumn) {
                continue;
            }
            foreach ($visible as $index => $relation) {
                $position = array_search($resolution->slot, $relation->shape->slots, true);
                if ($relation->relation === $resolution->relation && is_int($position)) {
                    $resolution = new ResolvedColumn($resolution->relation, $output[$index]->shape->slots[$position], $resolution->depth);
                    break;
                }
            }
            $columns[] = new Field(count($columns), $resolution->slot, null, $resolution);
        }

        return $columns;
    }

    /**
     * Answers the GROUP BY items and the select list items that are columns no known occurrence decides.
     *
     * Such a column belongs to an incompletely known occurrence, so the
     * server may find a HAVING name among the GROUP BY columns or the
     * select list items that this analysis cannot see.
     *
     * @param list<ScalarFact> $items The facts of the GROUP BY items, in order
     * @param list<Field|OpenStar> $selected The output fields of the select list, in order
     * @return list<ConditionalColumn>
     */
    public function undecided(array $items, array $selected): array
    {
        $columns = [];
        foreach ($items as $fact) {
            if ($fact->resolution instanceof ConditionalColumn) {
                $columns[] = $fact->resolution;
            }
        }
        foreach ($selected as $field) {
            if ($field instanceof Field && $field->expression instanceof ColumnUse && $field->resolution instanceof ConditionalColumn) {
                $columns[] = $field->resolution;
            }
        }

        return $columns;
    }

    /**
     * Answers the environment of the arguments of a set function: the given one without a grouped row.
     */
    public function leave(Environment $environment): Environment
    {
        if ($this->row($environment) === null) {
            return $environment;
        }
        $relations = array_values(array_filter($environment->relations, static fn (VisibleRelation $relation): bool => !$relation->relation instanceof GroupedRow));

        return new Environment($environment->context, $environment->outer, $relations, $environment->commonTables, $environment->aliases, aggregation: $environment->aggregation, aggregatesAllowed: $environment->aggregatesAllowed, aggregateArgument: $environment->aggregateArgument, projection: $environment->projection);
    }

    /**
     * Opens aggregate arguments, which can read input columns of an enclosing HAVING block.
     *
     * A nested aggregate prefers an input column over a selected result of the same name;
     * a result alias with no matching input remains an alias. This position is retained
     * across nested queries in the argument. Verified through SQL on MySQL 8.4.
     */
    public function arguments(Environment $environment): Environment
    {
        $input = $this->leave($environment);

        return new Environment($input->context, $input->outer, $input->relations, $input->commonTables, $input->aliases, $input->written, $input->aggregation, $input->aggregatesAllowed, true, $input->projection);
    }

    /**
     * Answers the grouped row an environment carries, or null outside HAVING.
     */
    public function row(Environment $environment): ?GroupedRow
    {
        foreach ($environment->relations as $relation) {
            if ($relation->relation instanceof GroupedRow) {
                return $relation->relation;
            }
        }

        return null;
    }
}
