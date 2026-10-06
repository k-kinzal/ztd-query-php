<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\DistinctClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\PostgreSql\Statement\Query\StarTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\TableQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Lowers query statements and query bodies.
 *
 * Rule: PG-SELECT-LOWER-001. Scope: `SelectStmt`, `select_with_parens`,
 * `select_no_parens`, `select_clause`, `simple_select`, `values_clause`,
 * `set_quantifier`, `distinct_clause`, `opt_all_clause`,
 * `opt_distinct_clause`, `into_clause`, `OptTempTableName`,
 * `opt_target_list`, `target_list`, `target_el`. Constructors: `Select`,
 * `TableQuery`, `ValuesList`, `SetOperation`, `ParenthesizedQuery`,
 * `QueryExpression`, `ExpressionTarget`, `StarTarget`, `DistinctClause`,
 * `IntoClause`. Parentheses the grammar reads as grouping become
 * `ParenthesizedQuery`; the parentheses a subquery requires are stripped by
 * `withParens`. ORDER BY, LIMIT and locking clauses written directly after
 * a selection or TABLE query belong to it; after anything else they belong
 * to a `QueryExpression`. Termination: lists are flattened iteratively;
 * nested queries recurse on the tree depth.
 * Source: https://www.postgresql.org/docs/17/sql-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SelectRule
{
    /**
     * The persistence of each spelling of `OptTempTableName`.
     */
    private const PERSISTENCE = [
        'OptTempTableName: TEMPORARY opt_table qualified_name' => IntoPersistence::Temporary,
        'OptTempTableName: TEMP opt_table qualified_name' => IntoPersistence::Temp,
        'OptTempTableName: LOCAL TEMPORARY opt_table qualified_name' => IntoPersistence::LocalTemporary,
        'OptTempTableName: LOCAL TEMP opt_table qualified_name' => IntoPersistence::LocalTemp,
        'OptTempTableName: GLOBAL TEMPORARY opt_table qualified_name' => IntoPersistence::GlobalTemporary,
        'OptTempTableName: GLOBAL TEMP opt_table qualified_name' => IntoPersistence::GlobalTemp,
        'OptTempTableName: UNLOGGED opt_table qualified_name' => IntoPersistence::Unlogged,
        'OptTempTableName: TABLE qualified_name' => IntoPersistence::Permanent,
        'OptTempTableName: qualified_name' => IntoPersistence::Permanent,
    ];

    /**
     * The operator of each set operation production.
     */
    private const OPERATORS = [
        'simple_select: select_clause UNION set_quantifier select_clause' => SetOperator::Union,
        'simple_select: select_clause INTERSECT set_quantifier select_clause' => SetOperator::Intersect,
        'simple_select: select_clause EXCEPT set_quantifier select_clause' => SetOperator::Except,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `SelectStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement&Query
    {
        $form = $this->lowering->productions->form($statement);

        return match ($form->signature) {
            'SelectStmt: select_no_parens' => $this->noParens($form->node(0)),
            'SelectStmt: select_with_parens' => new ParenthesizedQuery($this->withParens($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a query: `SelectStmt`, `select_no_parens`, `select_with_parens` (without its outer parentheses) or `select_clause`.
     *
     * @throws ImplementationGap When the node is not a query
     */
    public function query(Node $query): Statement&Query
    {
        return match ($query->name) {
            'SelectStmt' => $this->statement($query),
            'select_no_parens' => $this->noParens($query),
            'select_with_parens' => $this->withParens($query),
            'select_clause' => $this->clause($query, null)[0],
            default => throw ImplementationGap::production($this->lowering->productions->form($query)),
        };
    }

    /**
     * Lowers `select_with_parens` to the query inside its outer parentheses.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function withParens(Node $query): Statement&Query
    {
        $form = $this->lowering->productions->form($query);

        return match ($form->signature) {
            'select_with_parens: ( select_no_parens )' => $this->noParens($form->node(1)),
            'select_with_parens: ( select_with_parens )' => new ParenthesizedQuery($this->withParens($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `select_no_parens`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function noParens(Node $query): Statement&Query
    {
        $form = $this->lowering->productions->form($query);
        $clauses = new ClauseRule($this->lowering);

        return match ($form->signature) {
            'select_no_parens: simple_select' => $this->simple($form->node(0), null)[0],
            'select_no_parens: select_clause sort_clause' => $this->attach(null, $form->node(0), $clauses->options($form->node(1), null, null, false)),
            'select_no_parens: select_clause opt_sort_clause for_locking_clause opt_select_limit' => $this->attach(null, $form->node(0), $clauses->options($form->node(1), $form->node(3), $form->node(2), true)),
            'select_no_parens: select_clause opt_sort_clause select_limit opt_for_locking_clause' => $this->attach(null, $form->node(0), $clauses->options($form->node(1), $form->node(2), $form->node(3), false)),
            'select_no_parens: with_clause select_clause' => $this->attach($form->node(0), $form->node(1), null),
            'select_no_parens: with_clause select_clause sort_clause' => $this->attach($form->node(0), $form->node(1), $clauses->options($form->node(2), null, null, false)),
            'select_no_parens: with_clause select_clause opt_sort_clause for_locking_clause opt_select_limit' => $this->attach($form->node(0), $form->node(1), $clauses->options($form->node(2), $form->node(4), $form->node(3), true)),
            'select_no_parens: with_clause select_clause opt_sort_clause select_limit opt_for_locking_clause' => $this->attach($form->node(0), $form->node(1), $clauses->options($form->node(2), $form->node(3), $form->node(4), false)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Builds a query body with the WITH clause before it and the options after it.
     *
     * @throws ImplementationGap When the WITH clause is not lowered to a WITH clause
     */
    public function attach(?Node $with, Node $clause, ?SelectOptions $options): Statement&Query
    {
        [$body, $absorbed] = $this->clause($clause, $options);
        $common = $with === null ? null : (new WithRule($this->lowering))->with($with);
        $remaining = $absorbed ? null : $options;
        if ($common === null && $remaining === null) {
            return $body;
        }

        return new QueryExpression($common instanceof WithClause ? $common : null, $body, $remaining);
    }

    /**
     * Lowers `select_clause`, handing the options to a selection or TABLE query that can hold them; answers the body and whether it took them.
     *
     * @return array{Statement&Query, bool}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function clause(Node $clause, ?SelectOptions $options): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'select_clause: simple_select' => $this->simple($form->node(0), $options),
            'select_clause: select_with_parens' => [new ParenthesizedQuery($this->withParens($form->node(0))), false],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `simple_select`; answers the body and whether it took the options.
     *
     * @return array{Statement&Query, bool}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function simple(Node $select, ?SelectOptions $options): array
    {
        $form = $this->lowering->productions->form($select);
        if (isset(self::OPERATORS[$form->signature])) {
            return [new SetOperation($this->clause($form->node(0), null)[0], self::OPERATORS[$form->signature], $this->clause($form->node(3), null)[0], $this->quantifier($form->node(2))), false];
        }

        return match ($form->signature) {
            'simple_select: SELECT opt_all_clause opt_target_list into_clause from_clause where_clause group_clause having_clause window_clause' => [$this->selection($form, null, $options), true],
            'simple_select: SELECT distinct_clause target_list into_clause from_clause where_clause group_clause having_clause window_clause' => [$this->selection($form, $this->distinct($form->node(1)), $options), true],
            'simple_select: values_clause' => [$this->values($form->node(0)), false],
            'simple_select: TABLE relation_expr' => [new TableQuery(new TableInput((new FromRule($this->lowering))->relation($form->node(1))), $options), true],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the clauses of the SELECT alternatives of `simple_select`.
     */
    public function selection(Form $form, ?DistinctClause $distinct, ?SelectOptions $options): Select
    {
        $clauses = new ClauseRule($this->lowering);
        if ($distinct === null) {
            $this->distinct($form->node(1));
        }
        [$quantifier, $groupBy] = $clauses->group($form->node(6));

        return new Select(
            $this->targets($form->node(2)),
            (new FromRule($this->lowering))->item($form->node(4)),
            $clauses->where($form->node(5)),
            $groupBy,
            $clauses->having($form->node(7)),
            $clauses->windows($form->node(8)),
            $distinct,
            $this->into($form->node(3)),
            $quantifier,
            $options,
        );
    }

    /**
     * Lowers `distinct_clause`, `opt_distinct_clause` or `opt_all_clause`; ALL and no clause are null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function distinct(Node $clause): ?DistinctClause
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'distinct_clause: DISTINCT' => new DistinctClause(),
            'distinct_clause: DISTINCT ON ( expr_list )' => new DistinctClause((new ClauseRule($this->lowering))->positioned($this->lowering->expressions->expressions($form->node(3)), OrderingClause::DistinctOn)),
            'opt_distinct_clause: distinct_clause', 'opt_distinct_clause: opt_all_clause' => $this->distinct($form->node(0)),
            'opt_all_clause: ALL', 'opt_all_clause:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `set_quantifier`; no quantifier is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function quantifier(Node $quantifier): ?SetQuantifier
    {
        $form = $this->lowering->productions->form($quantifier);

        return match ($form->signature) {
            'set_quantifier: ALL' => SetQuantifier::All,
            'set_quantifier: DISTINCT' => SetQuantifier::Distinct,
            'set_quantifier:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `into_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function into(Node $clause): ?IntoClause
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'into_clause:') {
            return null;
        }
        if ($form->signature !== 'into_clause: INTO OptTempTableName') {
            throw ImplementationGap::production($form);
        }
        $name = $this->lowering->productions->form($form->node(1));
        $persistence = self::PERSISTENCE[$name->signature] ?? throw ImplementationGap::production($name);

        return new IntoClause($this->lowering->names->qualified($name->node(count($name->node->children) - 1)), $persistence);
    }

    /**
     * Lowers `values_clause`.
     */
    public function values(Node $clause): ValuesList
    {
        $rows = [];
        $current = $clause;
        while (true) {
            $form = $this->lowering->productions->form($current);
            if ($form->signature === 'values_clause: values_clause , ( expr_list )') {
                $rows[] = new ValuesRow($this->lowering->expressions->expressions($form->node(3)));
                $current = $form->node(0);
                continue;
            }
            if ($form->signature !== 'values_clause: VALUES ( expr_list )') {
                throw ImplementationGap::production($form);
            }
            $rows[] = new ValuesRow($this->lowering->expressions->expressions($form->node(2)));

            return new ValuesList(array_reverse($rows));
        }
    }

    /**
     * Lowers `opt_target_list` or `target_list`.
     *
     * @return list<Target>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function targets(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'opt_target_list:') {
            return [];
        }
        $targets = [];
        $elements = $form->signature === 'opt_target_list: target_list' ? $form->node(0) : $list;
        foreach ($this->lowering->items($elements, 'target_list: target_el', 'target_list: target_list , target_el') as $element) {
            $target = $this->lowering->productions->form($element);
            $targets[] = match ($target->signature) {
                'target_el: a_expr' => new ExpressionTarget($this->lowering->expressions->expression($target->node(0))),
                'target_el: a_expr AS ColLabel' => new ExpressionTarget($this->lowering->expressions->expression($target->node(0)), $this->lowering->names->name($target->node(2))),
                'target_el: a_expr BareColLabel' => new ExpressionTarget($this->lowering->expressions->expression($target->node(0)), $this->lowering->names->name($target->node(1))),
                'target_el: *' => new StarTarget(),
                default => throw ImplementationGap::production($target),
            };
        }

        return $targets;
    }
}
