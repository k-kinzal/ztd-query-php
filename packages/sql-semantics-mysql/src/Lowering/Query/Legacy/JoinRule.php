<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Statement\Relation;

/**
 * Lowers the table references and joins of the 5.x grammars.
 *
 * Rule: MYSQL-JOIN-LEGACY-001. Scope: join_table_list, derived_table_list,
 * esc_table_ref, table_ref, join_table, normal_join, opt_outer (5.x). The
 * joins follow the parse, which nests conditionless joins to the left; a
 * join on the left is walked in a loop. JOIN, INNER JOIN, CROSS JOIN and
 * STRAIGHT_JOIN are kept as written, and so is OUTER. Constructs:
 * JoinedTable, EscapedRelation. Terminates: the list and the left spine are
 * walked in loops; recursion follows the strictly smaller right operands.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/join.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class JoinRule
{
    /**
     * The join productions: the operator (null: read from normal_join at position 1), the position of the right operand, of the ON expression and of the USING list.
     *
     * @var array<string, array{JoinOperator|null, int, int|null, int|null}>
     */
    private const JOINS = [
        'join_table: table_ref normal_join table_ref' => [null, 2, null, null],
        'join_table: table_ref STRAIGHT_JOIN table_factor' => [JoinOperator::StraightJoin, 2, null, null],
        'join_table: table_ref normal_join table_ref ON expr' => [null, 2, 4, null],
        'join_table: table_ref STRAIGHT_JOIN table_factor ON expr' => [JoinOperator::StraightJoin, 2, 4, null],
        'join_table: table_ref normal_join table_ref USING ( using_list )' => [null, 2, null, 5],
        'join_table: table_ref NATURAL JOIN_SYM table_factor' => [JoinOperator::Natural, 3, null, null],
        'join_table: table_ref LEFT opt_outer JOIN_SYM table_ref ON expr' => [JoinOperator::Left, 4, 6, null],
        'join_table: table_ref LEFT opt_outer JOIN_SYM table_factor USING ( using_list )' => [JoinOperator::Left, 4, null, 7],
        'join_table: table_ref NATURAL LEFT opt_outer JOIN_SYM table_factor' => [JoinOperator::NaturalLeft, 5, null, null],
        'join_table: table_ref RIGHT opt_outer JOIN_SYM table_ref ON expr' => [JoinOperator::Right, 4, 6, null],
        'join_table: table_ref RIGHT opt_outer JOIN_SYM table_factor USING ( using_list )' => [JoinOperator::Right, 4, null, 7],
        'join_table: table_ref NATURAL RIGHT opt_outer JOIN_SYM table_factor' => [JoinOperator::NaturalRight, 5, null, null],
    ];

    /**
     * The productions of normal_join.
     */
    private const NORMAL = ['normal_join: JOIN_SYM' => JoinOperator::Join, 'normal_join: INNER_SYM JOIN_SYM' => JoinOperator::Inner, 'normal_join: CROSS JOIN_SYM' => JoinOperator::Cross];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the comma-separated references of a 5.x FROM clause in written order.
     *
     * @return list<Relation>
     * @throws ImplementationGap When a production has no rule
     */
    public function list(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'join_table_list: derived_table_list') {
            return $this->list($form->node(0));
        }
        if ($form->signature !== 'derived_table_list: esc_table_ref' && $form->signature !== 'derived_table_list: derived_table_list , esc_table_ref') {
            throw ImplementationGap::production($form);
        }
        $members = [];
        foreach ((new Lists())->items($list) as $item) {
            $members[] = $this->escaped($item);
        }

        return $members;
    }

    /**
     * Lowers a reference with its optional ODBC escape.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function escaped(Node $reference): Relation
    {
        $form = $this->lowering->form($reference);

        return match ($form->signature) {
            'esc_table_ref: table_ref' => $this->reference($form->node(0)),
            'esc_table_ref: { ident table_ref }' => new EscapedRelation($this->lowering->names->identifier($form->node(1)), $this->reference($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a table reference.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function reference(Node $reference): Relation
    {
        $form = $this->lowering->form($reference);

        return match ($form->signature) {
            'table_ref: table_factor' => (new FromRule($this->lowering))->factor($form->node(0)),
            'table_ref: join_table' => $this->joined($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a join, walking the joins on its left in a loop.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function joined(Node $joined): JoinedTable
    {
        $steps = [];
        $form = $this->lowering->form($joined);
        while (true) {
            if (!isset(self::JOINS[$form->signature])) {
                throw ImplementationGap::production($form);
            }
            $steps[] = $form;
            $left = $this->lowering->form($form->node(0));
            if ($left->signature !== 'table_ref: join_table') {
                break;
            }
            $form = $this->lowering->form($left->node(0));
        }
        $relation = $this->reference($form->node(0));
        foreach (array_reverse($steps) as $step) {
            $relation = $this->step($relation, $step);
        }
        return $relation;
    }

    /**
     * Lowers one join over an already lowered left operand.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function step(Relation $left, Form $form): JoinedTable
    {
        [$operator, $right, $on, $using] = self::JOINS[$form->signature];
        if ($operator === null) {
            $normal = $this->lowering->form($form->node(1));
            $operator = self::NORMAL[$normal->signature] ?? throw ImplementationGap::production($normal);
        }
        foreach ($form->node->children as $child) {
            if ($child instanceof Node && $child->name === 'opt_outer') {
                $outer = $this->lowering->form($child);
                if ($outer->signature !== 'opt_outer:' && $outer->signature !== 'opt_outer: OUTER') {
                    throw ImplementationGap::production($outer);
                }
                $operator = $outer->signature === 'opt_outer: OUTER' ? $operator->worded() : $operator;
            }
        }
        $operand = $form->node($right)->name === 'table_factor' ? (new FromRule($this->lowering))->factor($form->node($right)) : $this->reference($form->node($right));

        return new JoinedTable(
            $left,
            $operator,
            $operand,
            $on === null ? null : $this->lowering->expressions->expression($form->node($on)),
            $using === null ? [] : (new TableRule($this->lowering))->names($form->node($using)),
        );
    }
}
