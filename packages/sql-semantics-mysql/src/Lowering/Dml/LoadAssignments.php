<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Statement\Scalar;

/**
 * Retains all LOAD assignment roles before the input's algorithm conflict.
 *
 * Early variable and character-set conditions remain available without turning
 * a row operand into an ordinary scalar assignment or retaining parser nodes.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class LoadAssignments
{
    /**
     * @param Lowering $lowering The input boundary interpreting the assignments
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Reads an optional SET list while retaining its complete operands.
     *
     * @return list<Assignment|DeferredColumnAssignment>
     * @throws ImplementationGap When a production has no lowering rule
     */
    public function read(Node $spec): array
    {
        $form = $this->lowering->form($spec);
        if ($form->signature === 'opt_load_data_set_spec:') {
            return [];
        }
        if (!in_array($form->signature, ['opt_load_data_set_spec: SET load_data_set_list', 'opt_load_data_set_spec: SET_SYM load_data_set_list'], true)) {
            throw ImplementationGap::production($form);
        }
        return array_map($this->assignment(...), (new ListRule($this->lowering))->items($form->node(1), ['load_data_set_list: load_data_set_list , load_data_set_elem', 'load_data_set_list: load_data_set_elem']));
    }

    /**
     * Separates ordinary scalar/default assignments from retained operand roles.
     *
     * @throws ImplementationGap When a production has no lowering rule
     */
    public function assignment(Node $item): Assignment|DeferredColumnAssignment
    {
        $form = $this->lowering->form($item);
        $legacy = $form->signature === 'load_data_set_elem: simple_ident_nospvar equal remember_name expr_or_default remember_end';
        if (!$legacy && $form->signature !== 'load_data_set_elem: simple_ident_nospvar equal expr_or_default') {
            throw ImplementationGap::production($form);
        }
        if ($legacy) {
            $this->lowering->options->skip($form->node(2));
            $this->lowering->options->skip($form->node(4));
        }
        $column = $this->lowering->names->column($form->node(0));
        $value = $this->lowering->form($form->node($legacy ? 3 : 2));
        $operand = $value->signature === 'expr_or_default: expr' ? $this->lowering->expressions->operand($value->node(0)) : (new ValueRule($this->lowering))->value($value->node);
        return $operand instanceof Scalar || $operand instanceof DefaultRequest ? new Assignment($column, $operand, operator: $this->lowering->options->assignment($form->node(1))) : new DeferredColumnAssignment($column, $operand, operator: $this->lowering->options->assignment($form->node(1)));
    }

}
