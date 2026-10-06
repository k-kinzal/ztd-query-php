<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\BlockRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\SubqueryRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\UnionRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ExpressionRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\PrimaryRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ClauseRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The entry rules of the query family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-QUERY-ENTRY-001. Scope: SELECT, set operations, VALUES and
 * TABLE statements, WITH, table references, joins, grouping, ordering,
 * limits, locking and INTO, in both grammar generations: the rules of
 * Lowering/Query/Modern (8.0 and later), Lowering/Query/Legacy (5.6 and
 * 5.7) and Lowering/Query/Shared. A query that a subquery, a derived table
 * or a common table expression writes in the parentheses its syntax
 * requires is answered without those parentheses; the construct that holds
 * it writes them. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Implemented.
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
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $query = $this->query($statement);
        Check::invariant($query instanceof Statement, 'Every query of the query family is also a statement.');

        return $query;
    }

    /**
     * Lowers a query used inside another statement: a node of `select`, `select_stmt`, `subselect`,
     * `subquery`, `table_subquery`, `row_subquery`, `query_expression`, `query_expression_parens`,
     * `query_expression_with_opt_locking_clauses` or `view_select_aux`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function query(Node $query): Query
    {
        $expressions = new ExpressionRule($this->lowering);

        return match ($query->name) {
            'select' => (new UnionRule($this->lowering))->statement($query),
            'select_stmt' => $expressions->statement($query),
            'subselect' => (new SubqueryRule($this->lowering))->subselect($query),
            'subquery', 'table_subquery', 'row_subquery' => $expressions->subquery($query),
            'query_expression' => $expressions->expression($query, new Trailer()),
            'query_expression_parens' => $expressions->inner($query),
            'query_expression_with_opt_locking_clauses' => $expressions->locked($query),
            'view_select_aux' => (new UnionRule($this->lowering))->viewQuery($query),
            default => throw ImplementationGap::production($this->lowering->form($query)),
        };
    }

    /**
     * Lowers the query of CREATE TABLE ... SELECT and INSERT ... SELECT of MySQL 5.6 and 5.7: a node of
     * `create_select` and the node of `union_clause`, `opt_union_clause` or `union_opt` that follows it.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacyQuery(Node $select, ?Node $union = null): Query
    {
        return (new UnionRule($this->lowering))->chain((new BlockRule($this->lowering))->create($select), $union);
    }

    /**
     * Lowers the parenthesized query of CREATE TABLE ... SELECT and INSERT ... SELECT of MySQL 5.6 and 5.7: the
     * node of `create_select` written in parentheses (`create3`, `insert_values`, `insert_query_expression`:
     * `( create_select ) union_opt`) and the node of `union_opt` that follows the parentheses. The
     * parentheses are kept as a parenthesized query; the holding statement writes nothing for them.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function legacyParenthesizedQuery(Node $select, Node $union): Query
    {
        return (new UnionRule($this->lowering))->chain(new ParenthesizedQuery((new BlockRule($this->lowering))->create($select)->select()), $union);
    }

    /**
     * Lowers a row predicate: a node of `where_clause` or `opt_where_clause`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function where(Node $clause): ?Scalar
    {
        return (new ClauseRule($this->lowering))->predicate($clause);
    }

    /**
     * Lowers ordering or grouping expressions: a node of `opt_order_clause`, `order_clause`, `order_list`
     * or `group_list`; an absent clause is empty. Integers stay the expressions they are.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function ordering(Node $clause): array
    {
        return (new ClauseRule($this->lowering))->orderItems($clause);
    }

    /**
     * Lowers one ordering expression: a node of `order_expr`, or a node of `order_ident` with the node of
     * `order_dir` that follows it.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function orderItem(Node $item, ?Node $direction = null): OrderItem
    {
        return (new ClauseRule($this->lowering))->item($item, $direction);
    }

    /**
     * Lowers a sort direction: a node of `order_dir`, `opt_ordering_direction` or `ordering_direction`; no
     * direction is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function direction(Node $direction): ?Direction
    {
        return (new ClauseRule($this->lowering))->direction($direction);
    }

    /**
     * Lowers a LIMIT clause: a node of `opt_limit_clause`, `limit_clause`, `opt_simple_limit` or
     * `opt_limit_clause_init`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function limit(Node $clause): ?Limit
    {
        return (new TailRule($this->lowering))->limit($clause);
    }

    /**
     * Lowers one LIMIT operand: a node of `limit_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function limitValue(Node $option): Scalar
    {
        return (new TailRule($this->lowering))->value($option);
    }

    /**
     * Lowers the table references of UPDATE and DELETE: a node of `join_table_list` or
     * `table_reference_list`.
     *
     * @return list<Relation>
     * @throws ImplementationGap When a production has no rule
     */
    public function tables(Node $list): array
    {
        return (new FromRule($this->lowering))->members($list);
    }

    /**
     * Lowers an explicit partition selection: a node of `opt_use_partition` or `use_partition`; an absent
     * selection is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function partitions(Node $selection): array
    {
        return (new TableRule($this->lowering))->partitions($selection);
    }

    /**
     * Lowers an optional alias: a node of `opt_table_alias` or `select_alias`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        return $alias->name === 'select_alias' ? (new ItemRule($this->lowering))->alias($alias) : (new TableRule($this->lowering))->alias($alias);
    }

    /**
     * Answers what is written before an optional alias: a node of `opt_table_alias` or `select_alias`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function mark(Node $alias): AliasMark
    {
        return $alias->name === 'select_alias' ? (new ItemRule($this->lowering))->mark($alias) : (new TableRule($this->lowering))->mark($alias);
    }

    /**
     * Lowers the column names of a derived table, view or VALUES alias: a node of
     * `opt_derived_column_list`; an absent list is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function columnAliases(Node $list): array
    {
        return (new TableRule($this->lowering))->columns($list);
    }

    /**
     * Lowers a WITH clause: a node of `opt_with_clause` or `with_clause`; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function with(Node $clause): ?WithClause
    {
        return (new PrimaryRule($this->lowering))->with($clause);
    }

    /**
     * Lowers a select list: a node of `select_item_list`.
     *
     * @return list<SelectExpression|Star|TableWildcard>
     * @throws ImplementationGap When a production has no rule
     */
    public function selectItems(Node $list): array
    {
        return (new ItemRule($this->lowering))->items($list);
    }

    /**
     * Lowers the index names of an index hint or of CACHE INDEX: a node of `opt_key_usage_list`; an absent
     * list is empty. A list that names the primary key with PRIMARY is lowered by indexKeys().
     *
     * @return list<Name>
     * @throws ImplementationGap When the list names PRIMARY or a production has no rule
     */
    public function indexNames(Node $list): array
    {
        $names = [];
        foreach ($this->indexKeys($list) as $key) {
            $names[] = $key instanceof Name ? $key : throw ImplementationGap::rule('an index list naming PRIMARY, which indexKeys() lowers');
        }

        return $names;
    }

    /**
     * Lowers the indexes of an index hint or of CACHE INDEX, where PRIMARY names the primary key: a node of
     * `opt_key_usage_list` or `key_usage_list`; an absent list is empty.
     *
     * @return list<Name|PrimaryIndex>
     * @throws ImplementationGap When a production has no rule
     */
    public function indexKeys(Node $list): array
    {
        return (new TableRule($this->lowering))->indexes($list);
    }
}
