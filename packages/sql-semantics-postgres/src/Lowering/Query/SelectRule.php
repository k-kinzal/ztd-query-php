<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the plain selection of the vertical slice.
 *
 * Rule: PG-SELECT-LOWER-001 (slice — the query family completes or replaces
 * this). Scope today: `SelectStmt: select_no_parens`, `select_no_parens:
 * simple_select`, the `SELECT` alternative of `simple_select` without
 * DISTINCT, INTO, GROUP BY, HAVING and WINDOW, `opt_target_list`,
 * `target_list`, `target_el` except the star, `from_clause` and `from_list`
 * of one `table_ref`, `table_ref: relation_expr opt_alias_clause`,
 * `relation_expr: qualified_name`, `alias_clause` without a column list,
 * `where_clause`. Constructors: `Select`, `ExpressionTarget`, `TableInput`,
 * `RelationReference`. Every other form is an implementation gap. Termination:
 * lists are flattened iteratively. Source: https://www.postgresql.org/docs/17/sql-select.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics
 */
final class SelectRule
{
    /**
     * The clauses of the plain selection that the slice requires to be empty, by position.
     */
    private const EMPTY = [1 => 'opt_all_clause:', 3 => 'into_clause:', 6 => 'group_clause:', 7 => 'having_clause:', 8 => 'window_clause:'];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `SelectStmt`, `select_no_parens` or `simple_select` to a selection.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function select(Node $query): Select
    {
        $form = $this->lowering->productions->form($query);

        return match ($form->signature) {
            'SelectStmt: select_no_parens', 'select_no_parens: simple_select' => $this->select($form->node(0)),
            'simple_select: SELECT opt_all_clause opt_target_list into_clause from_clause where_clause group_clause having_clause window_clause' => $this->simple($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the plain SELECT alternative.
     *
     * @throws ImplementationGap When a clause outside the slice is written
     */
    public function simple(Form $form): Select
    {
        foreach (self::EMPTY as $position => $empty) {
            $clause = $this->lowering->productions->form($form->node($position));
            if ($clause->signature !== $empty) {
                throw ImplementationGap::production($clause);
            }
        }
        $from = $this->from($form->node(4));
        if (count($from) > 1) {
            throw ImplementationGap::rule('PG-SELECT-001: a FROM clause with several items');
        }

        return new Select($this->targets($form->node(2)), $from[0] ?? null, $this->where($form->node(5)));
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
                default => throw ImplementationGap::production($target),
            };
        }

        return $targets;
    }

    /**
     * Lowers `from_clause` or `from_list`.
     *
     * @return list<TableInput>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function from(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'from_clause:') {
            return [];
        }
        $inputs = [];
        $list = $form->signature === 'from_clause: FROM from_list' ? $form->node(1) : $clause;
        foreach ($this->lowering->items($list, 'from_list: table_ref', 'from_list: from_list , table_ref') as $reference) {
            $table = $this->lowering->productions->form($reference);
            if ($table->signature !== 'table_ref: relation_expr opt_alias_clause') {
                throw ImplementationGap::production($table);
            }
            $inputs[] = new TableInput($this->relation($table->node(0)), $this->alias($table->node(1)));
        }

        return $inputs;
    }

    /**
     * Lowers `relation_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function relation(Node $relation): RelationReference
    {
        $form = $this->lowering->productions->form($relation);

        return match ($form->signature) {
            'relation_expr: qualified_name' => new RelationReference($this->lowering->names->qualified($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_alias_clause` or `alias_clause` without a column list; no alias is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        $form = $this->lowering->productions->form($alias);

        return match ($form->signature) {
            'opt_alias_clause:' => null,
            'opt_alias_clause: alias_clause' => $this->alias($form->node(0)),
            'alias_clause: AS ColId' => $this->lowering->names->name($form->node(1)),
            'alias_clause: ColId' => $this->lowering->names->name($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `where_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function where(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'where_clause:' => null,
            'where_clause: WHERE a_expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
