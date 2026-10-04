<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The entry point of the query family.
 *
 * Rule: PG-QUERY-001. Every other family reaches the query rules through
 * these methods: PG-SELECT-LOWER-001, PG-CLAUSE-LOWER-001,
 * PG-LIMIT-LOWER-001, PG-WITH-LOWER-001, PG-FROM-LOWER-001 and
 * PG-TABLE-FUNCTION-LOWER-001. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Queries
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the statement `SelectStmt`.
     */
    public function statement(Node $statement): Statement
    {
        return (new SelectRule($this->lowering))->statement($statement);
    }

    /**
     * Lowers a query: `SelectStmt`, `select_no_parens`, `select_with_parens` or `select_clause`.
     *
     * A `select_with_parens` is lowered to the query inside its outer
     * parentheses: the holder of a subquery writes the parentheses it requires.
     */
    public function query(Node $query): Query&Statement
    {
        return (new SelectRule($this->lowering))->query($query);
    }

    /**
     * Lowers `sort_clause`, `opt_sort_clause` or `sortby_list` of an aggregate, a window or WITHIN GROUP; no clause is an empty list.
     *
     * An integer constant stays a constant here: only the ORDER BY of a query
     * reads it as an output position.
     *
     * @return list<SortItem>
     */
    public function sortClause(Node $clause): array
    {
        return (new ClauseRule($this->lowering))->sortClause($clause);
    }

    /**
     * Lowers `opt_asc_desc`; no direction is null.
     */
    public function sortDirection(Node $direction): ?SortDirection
    {
        return (new ClauseRule($this->lowering))->sortDirection($direction);
    }

    /**
     * Lowers `opt_nulls_order`; no clause is null.
     */
    public function nullsOrder(Node $order): ?NullsOrder
    {
        return (new ClauseRule($this->lowering))->nullsOrder($order);
    }

    /**
     * Lowers `where_clause`; no clause is null.
     */
    public function where(Node $clause): ?Scalar
    {
        return (new ClauseRule($this->lowering))->where($clause);
    }

    /**
     * Lowers `from_clause` or `from_list`; no clause is an empty list.
     *
     * @return list<Relation>
     */
    public function from(Node $clause): array
    {
        return (new FromRule($this->lowering))->items($clause);
    }

    /**
     * Lowers `from_clause` or `from_list` to its one item or to the `RelationList` of its items; no clause is null.
     */
    public function fromItem(Node $clause): ?Relation
    {
        return (new FromRule($this->lowering))->item($clause);
    }

    /**
     * Lowers `relation_expr` or `extended_relation_expr`: a table name with its ONLY or `*` inheritance marker.
     */
    public function relation(Node $relation): RelationReference
    {
        return (new FromRule($this->lowering))->relation($relation);
    }

    /**
     * Lowers `table_ref`: one item of a FROM list, or the source of MERGE.
     */
    public function tableReference(Node $reference): Relation
    {
        return (new FromRule($this->lowering))->reference($reference);
    }

    /**
     * Lowers `relation_expr_list`.
     *
     * @return list<RelationReference>
     */
    public function relations(Node $list): array
    {
        return (new FromRule($this->lowering))->relations($list);
    }

    /**
     * Lowers `target_list` or `opt_target_list`; no list is empty.
     *
     * @return list<Target>
     */
    public function targets(Node $list): array
    {
        return (new SelectRule($this->lowering))->targets($list);
    }

    /**
     * Lowers `with_clause` or `opt_with_clause`; no clause is null.
     */
    public function with(Node $clause): ?CommonTables
    {
        return (new WithRule($this->lowering))->with($clause);
    }
}
