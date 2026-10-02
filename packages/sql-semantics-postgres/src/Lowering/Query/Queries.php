<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
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
 * Rule: PG-QUERY-001 (slice — the query family completes or replaces the
 * bodies; the method signatures are the stable contract and a family may
 * narrow a return type). Scope today: see PG-SELECT-LOWER-001; every other
 * form is an implementation gap. Status: Specified.
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
        return (new SelectRule($this->lowering))->select($statement);
    }

    /**
     * Lowers a query: `SelectStmt`, `select_no_parens`, `select_with_parens` or `select_clause`.
     */
    public function query(Node $query): Query
    {
        return (new SelectRule($this->lowering))->select($query);
    }

    /**
     * Lowers `sort_clause` or `opt_sort_clause`; no clause is an empty list.
     *
     * @return list<SortItem>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function sortClause(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_sort_clause:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_asc_desc`; no direction is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function sortDirection(Node $direction): ?SortDirection
    {
        throw ImplementationGap::production($this->lowering->productions->form($direction));
    }

    /**
     * Lowers `opt_nulls_order`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function nullsOrder(Node $order): ?NullsOrder
    {
        throw ImplementationGap::production($this->lowering->productions->form($order));
    }

    /**
     * Lowers `where_clause`; no clause is null.
     */
    public function where(Node $clause): ?Scalar
    {
        return (new SelectRule($this->lowering))->where($clause);
    }

    /**
     * Lowers `from_clause` or `from_list`; no clause is an empty list.
     *
     * @return list<Relation>
     */
    public function from(Node $clause): array
    {
        return (new SelectRule($this->lowering))->from($clause);
    }

    /**
     * Lowers `relation_expr`.
     */
    public function relation(Node $relation): RelationReference
    {
        return (new SelectRule($this->lowering))->relation($relation);
    }

    /**
     * Lowers `relation_expr_list`.
     *
     * @return list<RelationReference>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function relations(Node $list): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($list));
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
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function with(Node $clause): ?CommonTables
    {
        throw ImplementationGap::production($this->lowering->productions->form($clause));
    }
}
