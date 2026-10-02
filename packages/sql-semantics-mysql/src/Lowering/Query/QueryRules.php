<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\SelectItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the query family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-QUERY-ENTRY-001. Scope: SELECT, set operations, WITH, table references, joins, grouping,
 * ordering, limits, locking and INTO, in both grammar generations.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * The methods marked as slice run the thin vertical slice written with the
 * leaf layers; the family completes or replaces them.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a query statement: a node of `select` (5.6, 5.7) or `select_stmt` (8.0 and later).
     *
     * @throws ImplementationGap When a production is outside the slice the family has yet to complete
     */
    public function statement(Node $statement): Statement
    {
        return (new SelectSlice($this->lowering))->statement($statement);
    }

    /**
     * Lowers a query used inside another statement: a node of `select`, `select_stmt`, `subselect`,
     * `subquery`, `table_subquery`, `row_subquery`, `query_expression`, `query_expression_parens`,
     * `query_expression_with_opt_locking_clauses` or `view_select_aux`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function query(Node $query): Query
    {
        throw ImplementationGap::rule('MySQL query family: query');
    }

    /**
     * Lowers the query of CREATE TABLE ... SELECT and INSERT ... SELECT of MySQL 5.6 and 5.7: a node of
     * `create_select` and the node of `union_clause`, `opt_union_clause` or `union_opt` that follows it.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function legacyQuery(Node $select, ?Node $union = null): Query
    {
        throw ImplementationGap::rule('MySQL query family: legacyQuery');
    }

    /**
     * Lowers a row predicate: a node of `where_clause` or `opt_where_clause`; an absent clause is null.
     *
     * @throws ImplementationGap When a production is outside the slice the family has yet to complete
     */
    public function where(Node $clause): ?Scalar
    {
        return (new SelectSlice($this->lowering))->where($clause);
    }

    /**
     * Lowers ordering or grouping expressions: a node of `opt_order_clause`, `order_clause`, `order_list`
     * or `group_list`; an absent clause is empty.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function ordering(Node $clause): array
    {
        throw ImplementationGap::rule('MySQL query family: ordering');
    }

    /**
     * Lowers one ordering expression: a node of `order_expr`, or a node of `order_ident` with the node of
     * `order_dir` that follows it.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function orderItem(Node $item, ?Node $direction = null): OrderItem
    {
        throw ImplementationGap::rule('MySQL query family: orderItem');
    }

    /**
     * Lowers a sort direction: a node of `order_dir`, `opt_ordering_direction` or `ordering_direction`; no
     * direction is null.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function direction(Node $direction): ?Direction
    {
        throw ImplementationGap::rule('MySQL query family: direction');
    }

    /**
     * Lowers a LIMIT clause: a node of `opt_limit_clause`, `limit_clause`, `opt_simple_limit` or
     * `opt_limit_clause_init`; an absent clause is null.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function limit(Node $clause): ?Limit
    {
        throw ImplementationGap::rule('MySQL query family: limit');
    }

    /**
     * Lowers one LIMIT operand: a node of `limit_option`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function limitValue(Node $option): Scalar
    {
        throw ImplementationGap::rule('MySQL query family: limitValue');
    }

    /**
     * Lowers the table references of UPDATE and DELETE: a node of `join_table_list` or
     * `table_reference_list`.
     *
     * @return list<Relation>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function tables(Node $list): array
    {
        throw ImplementationGap::rule('MySQL query family: tables');
    }

    /**
     * Lowers an explicit partition selection: a node of `opt_use_partition` or `use_partition`; an absent
     * selection is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function partitions(Node $selection): array
    {
        throw ImplementationGap::rule('MySQL query family: partitions');
    }

    /**
     * Lowers an optional alias: a node of `opt_table_alias` or `select_alias`.
     *
     * @throws ImplementationGap When a production is outside the slice the family has yet to complete
     */
    public function alias(Node $alias): ?Name
    {
        return (new SelectSlice($this->lowering))->alias($alias);
    }

    /**
     * Lowers the column names of a derived table, view or VALUES alias: a node of
     * `opt_derived_column_list`; an absent list is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function columnAliases(Node $list): array
    {
        throw ImplementationGap::rule('MySQL query family: columnAliases');
    }

    /**
     * Lowers a WITH clause: a node of `opt_with_clause` or `with_clause`; an absent clause is null.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function with(Node $clause): ?WithClause
    {
        throw ImplementationGap::rule('MySQL query family: with');
    }

    /**
     * Lowers a select list: a node of `select_item_list`.
     *
     * @return list<SelectItem>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function selectItems(Node $list): array
    {
        throw ImplementationGap::rule('MySQL query family: selectItems');
    }

    /**
     * Lowers the index names of an index hint or of CACHE INDEX: a node of `opt_key_usage_list`; an absent
     * list is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function indexNames(Node $list): array
    {
        throw ImplementationGap::rule('MySQL query family: indexNames');
    }
}
