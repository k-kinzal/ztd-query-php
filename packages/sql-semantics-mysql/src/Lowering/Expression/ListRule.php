<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers comma-separated expression lists.
 *
 * Rule: MYSQL-EXPRESSION-LIST-001. Scope: expr_list, opt_expr_list. An
 * absent list is empty; the elements keep their order. Terminates: the
 * list spine is flattened in a loop.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ListRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of expr_list or opt_expr_list.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When a production has no rule
     */
    public function expressions(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_expr_list:') {
            return [];
        }
        if ($form->signature === 'opt_expr_list: expr_list') {
            $list = $form->node(0);
            $form = $this->lowering->form($list);
        }
        if ($form->signature !== 'expr_list: expr' && $form->signature !== 'expr_list: expr_list , expr') {
            throw ImplementationGap::production($form);
        }
        $expressions = [];
        foreach ((new Lists())->items($list) as $item) {
            $expressions[] = $this->lowering->expressions->expression($item);
        }

        return $expressions;
    }
}
