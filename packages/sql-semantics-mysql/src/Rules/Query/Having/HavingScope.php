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
 * Rule: MYSQL-HAVING-SCOPE-001. The parser reads a column name written in
 * HAVING outside the arguments of a set function as a reference to the
 * grouped result row (`Item_ref`), and every other column name as a column
 * (`Item_field`): PTI_simple_ident_ident, PTI_simple_ident_q_3d and
 * PTI_simple_ident_nospvar_ident in parse_tree_items.cc, and the
 * `simple_ident` actions of the 5.x grammars, test `parsing_place !=
 * CTX_HAVING || in_sum_expr > 0`; a set function written without OVER
 * raises `in_sum_expr` while its arguments are read (Item_sum::itemize,
 * PTI_in_sum_expr). The HAVING environment is the environment of the
 * clause with a `GroupedRow` entry; a set function without OVER reads its
 * arguments, its ORDER BY and its nested queries in the same environment
 * without the entry. A nested query is a query block of its own and starts
 * outside HAVING, but it sees the entry as part of its enclosing position.
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
        return new Environment($environment->context, $environment->outer, [...$environment->relations, new VisibleRelation($row, new RowShape([]))], $environment->commonTables, $environment->aliases);
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

        return new Environment($environment->context, $environment->outer, $relations, $environment->commonTables, $environment->aliases);
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
