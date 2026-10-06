<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ClauseRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

/**
 * Lowers the query blocks of the 5.x grammars.
 *
 * Rule: MYSQL-BLOCK-LEGACY-001. Scope: select_part2,
 * select_options_and_item_list, table_expression (5.7); select_into,
 * select_from, opt_select_from (5.6); create_select, create_view_select,
 * select_part2_derived, select_init2_derived, select_derived2,
 * select_derived_init (both). A block written with its clauses inside one
 * rule is lowered to the same query block as in 8.0 and later: the ORDER
 * BY, LIMIT, PROCEDURE ANALYSE, trailing INTO and locking clauses stay open
 * until the block is known to be the last operand of a union
 * (MYSQL-UNION-LEGACY-001). Constructs: the query block. Terminates: every
 * part is a strict subtree. Source: https://dev.mysql.com/doc/refman/5.7/en/select.html,
 * https://dev.mysql.com/doc/refman/5.6/en/select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class BlockRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the select options, the select list and the clauses after SELECT of a 5.x query block.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function part(Node $part): Block
    {
        $form = $this->lowering->form($part);
        $tail = new TailRule($this->lowering);
        $items = new ItemRule($this->lowering);
        switch ($form->signature) {
            case 'select_part2: select_options select_item_list select_into select_lock_type':
                return $this->into($form->node(2), $items->options($form->node(0)), $items->items($form->node(1)), new Trailer([], null, null, $tail->locking($form->node(3))));
            case 'select_part2: select_options_and_item_list opt_order_clause opt_limit_clause opt_select_lock_type':
                [$options, $list] = $this->head($form->node(0));

                return new Block($options, $list, null, null, null, null, null, [], null, new Trailer((new ClauseRule($this->lowering))->ordering($form->node(1)), $tail->limit($form->node(2)), null, $tail->locking($form->node(3))));
            case 'select_part2: select_options_and_item_list into opt_select_lock_type':
                [$options, $list] = $this->head($form->node(0));

                return new Block($options, $list, $tail->into($form->node(1)), null, null, null, null, [], null, new Trailer([], null, null, $tail->locking($form->node(2))));
            case 'select_part2: select_options_and_item_list opt_into from_clause opt_where_clause opt_group_clause opt_having_clause opt_order_clause opt_limit_clause opt_procedure_analyse_clause opt_into opt_select_lock_type':
                return $this->full($form);
            default:
                throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the 5.7 query block that writes a FROM clause.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When PROCEDURE ANALYSE and INTO are both written (ER_WRONG_USAGE "PROCEDURE and INTO" of the 5.7 `select_part2` action, raised while parsing)
     */
    public function full(\SqlSemantics\Lowering\Form $form): Block
    {
        [$options, $list] = $this->head($form->node(0));
        $clauses = new ClauseRule($this->lowering);
        $tail = new TailRule($this->lowering);
        $first = $tail->into($form->node(1));
        $last = $tail->into($form->node(9));
        $procedure = $tail->procedure($form->node(8));
        if ($procedure !== null && ($first !== null || $last !== null)) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and INTO: a query block with PROCEDURE ANALYSE has no INTO.');
        }

        return new Block(
            $options,
            $list,
            $first,
            (new FromRule($this->lowering))->from($form->node(2)),
            $clauses->predicate($form->node(3)),
            $clauses->grouping($form->node(4)),
            $clauses->predicate($form->node(5)),
            [],
            null,
            new Trailer($clauses->ordering($form->node(6)), $tail->limit($form->node(7)), $procedure, $tail->locking($form->node(10)), $last, $last === null ? null : IntoPosition::AfterQuery),
        );
    }

    /**
     * Lowers the select options and the select list of 5.7.
     *
     * @return array{list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption>, list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression|\SqlSemantics\Platform\MySql\Statement\Query\Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard>}
     * @throws ImplementationGap When a production has no rule
     */
    public function head(Node $head): array
    {
        $form = $this->lowering->form($head);
        if ($form->signature !== 'select_options_and_item_list: select_options select_item_list') {
            throw ImplementationGap::production($form);
        }
        $items = new ItemRule($this->lowering);

        return [$items->options($form->node(0)), $items->items($form->node(1))];
    }

    /**
     * Lowers the 5.6 clauses after the select list.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $options
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression|\SqlSemantics\Platform\MySql\Statement\Query\Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard> $items
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When PROCEDURE ANALYSE follows an INTO (ER_WRONG_USAGE "PROCEDURE and INTO" of the 5.6 `procedure_analyse_clause` action, which refuses a statement whose result is already set)
     */
    public function into(Node $clauses, array $options, array $items, Trailer $lock): Block
    {
        $form = $this->lowering->form($clauses);
        $tail = new TailRule($this->lowering);
        $block = match ($form->signature) {
            'select_into: opt_order_clause opt_limit_clause' => new Block($options, $items, null, null, null, null, null, [], null, (new Trailer((new ClauseRule($this->lowering))->ordering($form->node(0)), $tail->limit($form->node(1))))->then($lock)),
            'select_into: into' => new Block($options, $items, $tail->into($form->node(0)), null, null, null, null, [], null, $lock),
            'select_into: select_from' => $this->from($form->node(0), $options, $items, null)->then($lock),
            'select_into: into select_from' => $this->from($form->node(1), $options, $items, $tail->into($form->node(0)))->then($lock),
            'select_into: select_from into' => $this->from($form->node(0), $options, $items, null)->then((new Trailer([], null, null, [], $tail->into($form->node(1)), IntoPosition::AfterQuery))->then($lock)),
            default => throw ImplementationGap::production($form),
        };
        if ($block->into !== null && $block->trailer->procedure !== null) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and INTO: PROCEDURE ANALYSE follows no INTO.');
        }

        return $block;
    }

    /**
     * Lowers the 5.6 FROM clause with the clauses that follow it.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $options
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression|\SqlSemantics\Platform\MySql\Statement\Query\Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard> $items
     * @throws ImplementationGap When a production has no rule
     */
    public function from(Node $from, array $options, array $items, ?\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination $into): Block
    {
        $form = $this->lowering->form($from);
        $clauses = new ClauseRule($this->lowering);
        $tail = new TailRule($this->lowering);

        return match ($form->signature) {
            'select_from: FROM join_table_list where_clause group_clause having_clause opt_order_clause opt_limit_clause procedure_analyse_clause' => new Block(
                $options,
                $items,
                $into,
                (new FromRule($this->lowering))->tables($form->node(1)),
                $clauses->predicate($form->node(2)),
                $clauses->grouping($form->node(3)),
                $clauses->predicate($form->node(4)),
                [],
                null,
                new Trailer($clauses->ordering($form->node(5)), $tail->limit($form->node(6)), $tail->procedure($form->node(7))),
            ),
            'select_from: FROM DUAL_SYM where_clause opt_limit_clause' => new Block($options, $items, $into, new Dual(), $clauses->predicate($form->node(2)), null, null, [], null, new Trailer([], $tail->limit($form->node(3)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the 5.7 table expression after a select list.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $options
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression|\SqlSemantics\Platform\MySql\Statement\Query\Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard> $items
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $expression, array $options, array $items): Block
    {
        $form = $this->lowering->form($expression);
        if ($form->signature !== 'table_expression: opt_from_clause opt_where_clause opt_group_clause opt_having_clause opt_order_clause opt_limit_clause opt_procedure_analyse_clause opt_select_lock_type') {
            throw ImplementationGap::production($form);
        }
        $clauses = new ClauseRule($this->lowering);
        $tail = new TailRule($this->lowering);

        return new Block(
            $options,
            $items,
            null,
            (new FromRule($this->lowering))->from($form->node(0)),
            $clauses->predicate($form->node(1)),
            $clauses->grouping($form->node(2)),
            $clauses->predicate($form->node(3)),
            [],
            null,
            new Trailer($clauses->ordering($form->node(4)), $tail->limit($form->node(5)), $tail->procedure($form->node(6)), $tail->locking($form->node(7))),
        );
    }

    /**
     * Lowers the 5.6 optional FROM clause of a derived or CREATE ... SELECT query block.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption> $options
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression|\SqlSemantics\Platform\MySql\Statement\Query\Star|\SqlSemantics\Platform\MySql\Statement\Name\TableWildcard> $items
     * @throws ImplementationGap When a production has no rule
     */
    public function optional(Node $from, array $options, array $items): Block
    {
        $form = $this->lowering->form($from);

        return match ($form->signature) {
            'opt_select_from: opt_limit_clause' => new Block($options, $items, null, null, null, null, null, [], null, new Trailer([], (new TailRule($this->lowering))->limit($form->node(0)))),
            'opt_select_from: select_from select_lock_type' => $this->from($form->node(0), $options, $items, null)->then(new Trailer([], null, null, (new TailRule($this->lowering))->locking($form->node(1)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the query block of CREATE TABLE ... SELECT and INSERT ... SELECT of 5.x.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $select): Block
    {
        $form = $this->lowering->form($select);
        $items = new ItemRule($this->lowering);

        return match ($form->signature) {
            'create_select: SELECT_SYM select_options select_item_list opt_select_from' => $this->optional($form->node(3), $items->options($form->node(1)), $items->items($form->node(2))),
            'create_select: SELECT_SYM select_options select_item_list table_expression' => $this->expression($form->node(3), $items->options($form->node(1)), $items->items($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the query block written directly after SELECT in a subquery or derived table of 5.x.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the block writes PROCEDURE ANALYSE (ER_WRONG_USAGE "PROCEDURE and subquery" of the 5.6 `procedure_analyse_clause` action and of 5.7 `PT_procedure_analyse::contextualize`, raised while parsing)
     */
    public function derived(Node $part, ?Node $expression = null): Block
    {
        $form = $this->lowering->form($part);
        $items = new ItemRule($this->lowering);
        if ($form->signature === 'select_init2_derived: select_part2_derived') {
            return $this->derived($form->node(0), $expression);
        }
        $block = match ($form->signature) {
            'select_part2_derived: opt_query_expression_options select_item_list opt_select_from select_lock_type' => $this->optional($form->node(2), $items->options($form->node(0)), $items->items($form->node(1)))->then(new Trailer([], null, null, (new TailRule($this->lowering))->locking($form->node(3)))),
            'select_part2_derived: opt_query_spec_options select_item_list' => $this->expression($expression ?? throw ImplementationGap::production($form), $items->options($form->node(0)), $items->items($form->node(1))),
            'select_derived2: select_options select_item_list opt_select_from' => $this->optional($form->node(2), $items->options($form->node(0)), $items->items($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
        if ($block->trailer->procedure !== null) {
            throw new AnalysisException('Incorrect usage of PROCEDURE and subquery: PROCEDURE ANALYSE belongs to the outermost query block.');
        }

        return $block;
    }
}
