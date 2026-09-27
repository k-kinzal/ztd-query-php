<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use function assert;

use SqlSemantics\Core\CompositionException;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\PostgreSql\Role\ColIdForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\CommonTableExprForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\SelectClauseForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\SelectStmtForm;
use SqlSemantics\Statement\Model\PostgreSql\Role\WithClauseForm;

/**
 * Composes PostgreSQL queries: set operations, common table expressions, and the WITH clause of a SELECT.
 *
 * A query given here may be the command of an analyzed statement; its
 * terminator envelope is removed. A composed query is a complete SELECT
 * statement, so it is again a command and a preparable statement.
 *
 * @visibility SqlSemantics
 */
trait Queries
{
    /**
     * `UNION ALL` of two queries; a query with ORDER BY, LIMIT, locking, or WITH is parenthesized.
     */
    public function unionAll(Element $left, Element $right): SelectStmtForm
    {
        $value = $this->form('simple_select', ['select_clause', 'UNION', 'set_quantifier', 'select_clause'], [$this->setOperand($this->unwrap($left)), $this->form('set_quantifier', ['ALL'], []), $this->setOperand($this->unwrap($right))]);
        assert($value instanceof SelectStmtForm);

        return $value;
    }

    /**
     * A common table expression over a SELECT, INSERT, UPDATE, DELETE, or MERGE.
     */
    public function cte(string $name, Element $query, array $columns = []): CommonTableExprForm
    {
        $names = array_map(fn (string $column): ColIdForm => $this->identifier($column), $columns);
        $list = $columns === [] ? $this->form('opt_name_list', [], []) : $this->form('opt_name_list', ['(', 'name_list', ')'], [$this->fold('name_list', ['name_list', ',', 'name'], $names)]);
        $value = $this->form('common_table_expr', ['name', 'opt_name_list', 'AS', 'opt_materialized', '(', 'PreparableStmt', ')', 'opt_search_clause', 'opt_cycle_clause'], [
            $this->identifier($name),
            $list,
            $this->form('opt_materialized', [], []),
            $this->expect($this->unwrap($query), 'PreparableStmt', 'The query of a common table expression'),
            $this->form('opt_search_clause', [], []),
            $this->form('opt_cycle_clause', [], []),
        ]);
        assert($value instanceof CommonTableExprForm);

        return $value;
    }

    /**
     * A SELECT preceded by a WITH clause; a SELECT that has one keeps its expressions after the new ones.
     */
    public function with(array $ctes, Element $query): SelectStmtForm
    {
        if ($ctes === []) {
            throw new CompositionException('A WITH clause needs at least one common table expression.');
        }
        foreach ($ctes as $cte) {
            $this->expect($cte, 'common_table_expr', 'A common table expression');
        }
        $query = $this->unwrap($query);
        if ($query instanceof SelectClauseForm) {
            $value = $this->form('select_no_parens', ['with_clause', 'select_clause'], [$this->withClause($ctes, null), $query]);
            assert($value instanceof SelectStmtForm);

            return $value;
        }
        foreach ($this->selectShapes() as $symbols) {
            $class = $this->classOf('select_no_parens', $symbols);
            if ($class === null || !$query instanceof $class) {
                continue;
            }
            $children = $query->children();
            if ($symbols[0] === 'with_clause') {
                $existing = array_shift($children);
                assert($existing instanceof WithClauseForm);
                $value = $this->form('select_no_parens', $symbols, [$this->withClause($ctes, $existing), ...$children]);
            } else {
                $value = $this->form('select_no_parens', ['with_clause', ...$symbols], [$this->withClause($ctes, null), ...$children]);
            }
            assert($value instanceof SelectStmtForm);

            return $value;
        }

        throw new CompositionException('A WITH clause precedes a SELECT of ' . $this->language->version . ', ' . $query::class . ' given.');
    }

    /**
     * Answers a query as a set operand: a plain SELECT as is, any other SELECT parenthesized.
     */
    protected function setOperand(Element $query): Element
    {
        if ($query instanceof SelectClauseForm) {
            return $query;
        }

        return $this->form('select_with_parens', ['(', 'select_no_parens', ')'], [$this->expect($query, 'select_no_parens', 'A set operand')]);
    }

    /**
     * Lists the symbol shapes of select_no_parens that take a WITH clause, the ones that have it first.
     *
     * @return list<list<string>>
     */
    protected function selectShapes(): array
    {
        $shapes = [
            ['select_clause', 'sort_clause'],
            ['select_clause', 'opt_sort_clause', 'for_locking_clause', 'opt_select_limit'],
            ['select_clause', 'opt_sort_clause', 'select_limit', 'opt_for_locking_clause'],
        ];
        $withShapes = array_map(static fn (array $shape): array => ['with_clause', ...$shape], [['select_clause'], ...$shapes]);

        return [...$withShapes, ...$shapes];
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
        $listSymbols = ['cte_list', ',', 'common_table_expr'];
        $recursive = false;
        if ($existing !== null) {
            $recursive = $existing instanceof ($this->classOf('with_clause', ['WITH', 'RECURSIVE', 'cte_list']) ?? '');
            $children = $existing->children();
            $ctes = [...$ctes, ...$this->unfold($children[count($children) - 1], 'cte_list', $listSymbols)];
        }
        $list = $this->fold('cte_list', $listSymbols, $ctes);

        return $recursive ? $this->form('with_clause', ['WITH', 'RECURSIVE', 'cte_list'], [$list]) : $this->form('with_clause', ['WITH', 'cte_list'], [$list]);
    }
}
