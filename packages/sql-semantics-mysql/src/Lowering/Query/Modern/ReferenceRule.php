<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Modern;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Statement\Relation;

/**
 * Lowers the table references and joins of the 8.0 and later grammars.
 *
 * Rule: MYSQL-JOIN-MODERN-001. Scope: table_reference, esc_table_reference,
 * joined_table, inner_join_type, outer_join_type, natural_join_type,
 * opt_inner, opt_outer. The joins follow the parse: a join on the left is
 * walked in a loop, a join on the right is its own operand. The optional
 * OUTER and the INNER after NATURAL are noise; JOIN, INNER JOIN, CROSS JOIN
 * and STRAIGHT_JOIN are kept as written. Constructs: JoinedTable,
 * JoinOperator, OdbcJoin. Terminates: the left spine is walked in a loop;
 * recursion follows the strictly smaller right operands. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/join.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class ReferenceRule
{
    /**
     * The join productions by the kind of their operator node and the position of their condition: 'on', 'using' or null.
     */
    private const JOINS = [
        'joined_table: table_reference inner_join_type table_reference ON_SYM expr' => 'on',
        'joined_table: table_reference inner_join_type table_reference USING ( using_list )' => 'using',
        'joined_table: table_reference outer_join_type table_reference ON_SYM expr' => 'on',
        'joined_table: table_reference outer_join_type table_reference USING ( using_list )' => 'using',
        'joined_table: table_reference inner_join_type table_reference' => null,
        'joined_table: table_reference natural_join_type table_factor' => null,
    ];

    /**
     * The join operator productions.
     */
    private const OPERATORS = [
        'inner_join_type: JOIN_SYM' => JoinOperator::Join, 'inner_join_type: INNER_SYM JOIN_SYM' => JoinOperator::Inner,
        'inner_join_type: CROSS JOIN_SYM' => JoinOperator::Cross, 'inner_join_type: STRAIGHT_JOIN' => JoinOperator::StraightJoin,
        'outer_join_type: LEFT opt_outer JOIN_SYM' => JoinOperator::Left, 'outer_join_type: RIGHT opt_outer JOIN_SYM' => JoinOperator::Right,
        'natural_join_type: NATURAL opt_inner JOIN_SYM' => JoinOperator::Natural, 'natural_join_type: NATURAL RIGHT opt_outer JOIN_SYM' => JoinOperator::NaturalRight,
        'natural_join_type: NATURAL LEFT opt_outer JOIN_SYM' => JoinOperator::NaturalLeft,
    ];

    /**
     * The optional words inside a join operator.
     */
    private const WORDS = ['opt_outer:' => false, 'opt_outer: OUTER_SYM' => true, 'opt_outer: OUTER' => true, 'opt_inner:' => false, 'opt_inner: INNER_SYM' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
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
            'table_reference: table_factor', 'esc_table_reference: table_factor' => (new FromRule($this->lowering))->factor($form->node(0)),
            'table_reference: joined_table', 'esc_table_reference: joined_table' => $this->joined($form->node(0)),
            'table_reference: { OJ_SYM esc_table_reference }' => new OdbcJoin($this->reference($form->node(2))),
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
            if (!array_key_exists($form->signature, self::JOINS)) {
                throw ImplementationGap::production($form);
            }
            $steps[] = $form;
            $left = $this->lowering->form($form->node(0));
            if ($left->signature !== 'table_reference: joined_table') {
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
        $condition = self::JOINS[$form->signature];
        $right = $form->node(2)->name === 'table_factor' ? (new FromRule($this->lowering))->factor($form->node(2)) : $this->reference($form->node(2));

        return new JoinedTable(
            $left,
            $this->operator($form->node(1)),
            $right,
            $condition === 'on' ? $this->lowering->expressions->expression($form->node(4)) : null,
            $condition === 'using' ? (new TableRule($this->lowering))->names($form->node(5)) : [],
        );
    }

    /**
     * Lowers a join operator.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function operator(Node $operator): JoinOperator
    {
        $form = $this->lowering->form($operator);
        $result = self::OPERATORS[$form->signature] ?? throw ImplementationGap::production($form);
        foreach ($form->node->children as $child) {
            if ($child instanceof Node) {
                $word = $this->lowering->form($child);
                $written = self::WORDS[$word->signature] ?? throw ImplementationGap::production($word);
                $result = $written ? $result->worded() : $result;
            }
        }

        return $result;
    }
}
