<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers values and assignments: the expression lists of rows and the SET lists.
 *
 * Rule: MYSQL-DML-VALUES-001. Scope: opt_values, values, expr_or_default,
 * update_list, update_elem, insert_update_list, insert_update_elem,
 * ident_eq_list, ident_eq_value. A value is an expression or the keyword
 * DEFAULT (DefaultRequest); an assignment is a column and a value
 * (Assignment), with `=` and `:=` as one operator. Terminates: lists are
 * flattened by MYSQL-DML-LIST-001, expressions are strict subtrees. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/insert.html,
 * https://dev.mysql.com/doc/refman/8.4/en/update.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class ValueRule
{
    /**
     * The productions of the assignment lists.
     */
    private const ASSIGNMENT_LISTS = [
        'update_list: update_list , update_elem', 'update_list: update_elem', 'insert_update_list: insert_update_list , insert_update_elem',
        'insert_update_list: insert_update_elem', 'ident_eq_list: ident_eq_list , ident_eq_value', 'ident_eq_list: ident_eq_value',
    ];

    /**
     * The productions of one assignment.
     */
    private const ASSIGNMENTS = [
        'update_elem: simple_ident_nospvar equal expr_or_default' => true, 'insert_update_elem: simple_ident_nospvar equal expr_or_default' => true,
        'ident_eq_value: simple_ident_nospvar equal expr_or_default' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the values of one row: a node of `opt_values` or `values`.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When a production has no rule
     */
    public function row(Node $values): array
    {
        $form = $this->lowering->form($values);
        if ($form->signature === 'opt_values:') {
            return [];
        }
        $list = $form->signature === 'opt_values: values' ? $form->node(0) : $values;
        $row = [];
        foreach ((new ListRule($this->lowering))->items($list, ['values: values , expr_or_default', 'values: expr_or_default']) as $item) {
            $row[] = $this->value($item);
        }

        return $row;
    }

    /**
     * Lowers one value: a node of `expr_or_default`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Node $value): Scalar
    {
        $form = $this->lowering->form($value);

        return match ($form->signature) {
            'expr_or_default: expr' => $this->lowering->expressions->expression($form->node(0)),
            'expr_or_default: DEFAULT', 'expr_or_default: DEFAULT_SYM' => new DefaultRequest(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a list of assignments: a node of `update_list`, `insert_update_list` or `ident_eq_list`.
     *
     * @return list<Assignment>
     * @throws ImplementationGap When a production has no rule
     */
    public function assignments(Node $list): array
    {
        $assignments = [];
        foreach ((new ListRule($this->lowering))->items($list, self::ASSIGNMENT_LISTS) as $item) {
            $assignments[] = $this->assignment($item);
        }

        return $assignments;
    }

    /**
     * Lowers one assignment: a node of `update_elem`, `insert_update_elem` or `ident_eq_value`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function assignment(Node $assignment): Assignment
    {
        $form = $this->lowering->form($assignment);
        if (!isset(self::ASSIGNMENTS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(1));

        return new Assignment($this->lowering->names->column($form->node(0)), $this->value($form->node(2)));
    }
}
