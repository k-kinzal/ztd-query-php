<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml\Insert;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Dml\ListRule;
use SqlSemantics\Platform\MySql\Lowering\Dml\ValueRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Statement\Scalar;

/**
 * Preserves complete duplicate-key operands alongside the rows whose preparation precedes them.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class RetainedUpdates
{
    /**
     * @param Lowering $lowering The input boundary lowering these assignments
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a complete optional duplicate-key clause without assuming scalar operands.
     *
     * @return list<Assignment|DeferredColumnAssignment>
     * @throws ImplementationGap When a production has no lowering rule
     */
    public function read(Node $clause): array
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_insert_update:' || $form->signature === 'opt_insert_update_list:') {
            return [];
        }
        if (!in_array($form->signature, ['opt_insert_update: ON DUPLICATE_SYM KEY_SYM UPDATE_SYM insert_update_list', 'opt_insert_update_list: ON DUPLICATE_SYM KEY_SYM UPDATE_SYM update_list', 'opt_insert_update_list: ON_SYM DUPLICATE_SYM KEY_SYM UPDATE_SYM update_list'], true)) {
            throw ImplementationGap::production($form);
        }
        return array_map($this->assignment(...), (new ListRule($this->lowering))->items($form->node(4), ['update_list: update_list , update_elem', 'update_list: update_elem', 'insert_update_list: insert_update_list , insert_update_elem', 'insert_update_list: insert_update_elem']));
    }

    /**
     * Keeps an assignment's target and its scalar, default or retained value role.
     *
     * @throws ImplementationGap When a production has no lowering rule
     */
    public function assignment(Node $item): Assignment|DeferredColumnAssignment
    {
        $form = $this->lowering->form($item);
        if (!in_array($form->signature, ['update_elem: simple_ident_nospvar equal expr_or_default', 'insert_update_elem: simple_ident_nospvar equal expr_or_default'], true)) {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(1));
        $column = $this->lowering->names->column($form->node(0));
        $value = $this->lowering->form($form->node(2));
        $operand = $value->signature === 'expr_or_default: expr' ? $this->lowering->expressions->operand($value->node(0)) : (new ValueRule($this->lowering))->value($value->node);
        return $operand instanceof Scalar || $operand instanceof DefaultRequest ? new Assignment($column, $operand) : new DeferredColumnAssignment($column, $operand);
    }
}
