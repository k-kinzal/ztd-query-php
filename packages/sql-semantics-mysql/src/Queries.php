<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use function assert;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\MySql\Role\IdentForm;
use SqlSemantics\Statement\Model\MySql\Role\QueryExpressionBodyForm;
use SqlSemantics\Statement\Model\MySql\Role\QueryExpressionForm;
use SqlSemantics\Statement\Model\MySql\Role\WithClauseForm;
use SqlSemantics\Statement\Writer;

/**
 * Composes MySQL queries: set operations, common table expressions, and the WITH clause of the 8.0 query grammar.
 *
 * A query given here may be the command of an analyzed statement; its
 * terminator envelope is removed. A composed query is a complete query
 * expression, so it is again a command and a subquery. Releases before 8.0
 * chain unions as LegacyUnions does and have no common table expressions.
 *
 * @visibility SqlSemantics
 */
trait Queries
{
    /**
     * `UNION ALL` of two queries, each a complete SELECT of the release.
     */
    public function unionAll(Element $left, Element $right): Element
    {
        $left = $this->unwrap($left);
        $right = $this->unwrap($right);
        if ($this->legacy()) {
            return $this->legacyUnion($left, $right);
        }
        $symbols = ['query_expression_body', 'UNION_SYM', 'union_option', 'query_expression_body'];
        $body = $this->form('query_expression_body', $symbols, [$this->body($left), $this->form('union_option', ['ALL'], []), $this->body($right)]);

        return $this->form('query_expression', ['query_expression_body', 'opt_order_clause', 'opt_limit_clause'], [$body, $this->form('opt_order_clause', [], []), $this->form('opt_limit_clause', [], [])]);
    }

    /**
     * A common table expression; releases before 8.0 have none.
     */
    public function cte(string $name, Element $query, array $columns = []): Element
    {
        if ($this->legacy()) {
            throw new CompositionException('No common table expression in ' . $this->language->version);
        }
        $query = $this->unwrap($query);
        $names = array_map(fn (string $column): IdentForm => $this->identifier($column), $columns);
        $list = $columns === [] ? $this->form('opt_derived_column_list', [], []) : $this->form('opt_derived_column_list', ['(', 'simple_ident_list', ')'], [$this->fold('simple_ident_list', ['simple_ident_list', ',', 'ident'], $names)]);

        return $this->form('common_table_expr', ['ident', 'opt_derived_column_list', 'AS', 'table_subquery'], [$this->identifier($name), $list, $this->subquery($query)]);
    }

    /**
     * A query preceded by a WITH clause; a query that has one keeps its expressions after the new ones.
     */
    public function with(array $ctes, Element $query): Element
    {
        if ($this->legacy()) {
            throw new CompositionException('No common table expression in ' . $this->language->version);
        }
        if ($ctes === []) {
            throw new CompositionException('A WITH clause needs at least one common table expression.');
        }
        foreach ($ctes as $cte) {
            $this->expect($cte, 'common_table_expr', 'A common table expression');
        }
        $query = $this->unwrap($query);
        $locking = $this->classOf('select_stmt', ['query_expression', 'locking_clause_list']);
        if ($locking !== null && $query instanceof $locking) {
            return $query->map(fn (Element $child): Element => $child instanceof QueryExpressionForm ? $this->with($ctes, $child) : $child);
        }
        $plain = ['query_expression_body', 'opt_order_clause', 'opt_limit_clause'];
        $withClause = ['with_clause', 'query_expression_body', 'opt_order_clause', 'opt_limit_clause'];
        $plainClass = $this->classOf('query_expression', $plain);
        $withClass = $this->classOf('query_expression', $withClause);
        if ($plainClass !== null && $query instanceof $plainClass) {
            return $this->form('query_expression', $withClause, [$this->withClause($ctes, null), ...$query->children()]);
        }
        if ($withClass !== null && $query instanceof $withClass) {
            $children = $query->children();
            $existing = array_shift($children);
            assert($existing instanceof WithClauseForm);

            return $this->form('query_expression', $withClause, [$this->withClause($ctes, $existing), ...$children]);
        }

        throw new CompositionException('A WITH clause precedes a query expression of ' . $this->language->version . ', ' . $query::class . ' given.');
    }

    /**
     * Answers a query as a set operand: its body when it has no ORDER BY or LIMIT, otherwise parenthesized.
     */
    protected function body(Element $query): Element
    {
        if ($query instanceof QueryExpressionBodyForm) {
            return $query;
        }
        $plainClass = $this->classOf('query_expression', ['query_expression_body', 'opt_order_clause', 'opt_limit_clause']);
        if ($plainClass !== null && $query instanceof $plainClass) {
            [$body, $order, $limit] = $query->children();
            if (Writer::render($order) === '' && Writer::render($limit) === '') {
                return $body;
            }
        }

        return $this->subquery($query);
    }

    /**
     * Parenthesizes a complete query, as a subquery or a set operand.
     */
    protected function subquery(Element $query): Element
    {
        $locking = $this->classOf('select_stmt', ['query_expression', 'locking_clause_list']);
        if ($locking !== null && $query instanceof $locking) {
            [$expression, $clauses] = $query->children();
            $query = $this->form('query_expression_with_opt_locking_clauses', ['query_expression', 'locking_clause_list'], [$expression, $clauses]);
        }

        return $this->form('query_expression_parens', ['(', 'query_expression_with_opt_locking_clauses', ')'], [$this->expect($query, 'query_expression_with_opt_locking_clauses', 'A subquery')]);
    }

    /**
     * Builds a WITH clause from new expressions and the ones of an existing clause.
     *
     * @param list<Element> $ctes
     *
     * @throws CompositionException When there is no expression
     */
    protected function withClause(array $ctes, ?WithClauseForm $existing): Element
    {
        $listSymbols = ['with_list', ',', 'common_table_expr'];
        $recursive = false;
        if ($existing !== null) {
            $recursive = $existing instanceof ($this->classOf('with_clause', ['WITH', 'RECURSIVE_SYM', 'with_list']) ?? '');
            $ctes = [...$ctes, ...$this->unfold($existing->children()[0], 'with_list', $listSymbols)];
        }
        $list = $this->fold('with_list', $listSymbols, $ctes);

        return $recursive ? $this->form('with_clause', ['WITH', 'RECURSIVE_SYM', 'with_list'], [$list]) : $this->form('with_clause', ['WITH', 'with_list'], [$list]);
    }
}
