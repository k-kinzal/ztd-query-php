<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Scalar;

/**
 * Preserves assigned operand roles in a stored SET item.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class StoredItems
{
    /**
     * @param Lowering $lowering The enclosing language interpretation
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Reads a named, user or explicit system assignment, preserving other SET items.
     */
    public function read(Form $form, ?VariableScope $scope = null): SetItem|DeferredAssignment
    {
        $rule = new ItemRule($this->lowering);
        if ($form->signature === 'option_value_no_option_type: @ ident_or_text equal expr') {
            $target = $this->lowering->variables->user($form->node(1));
            $value = $this->lowering->expressions->operand($form->node(3));
            return $value instanceof Scalar ? new UserAssignment($target, $value, operator: $this->lowering->options->assignment($form->node(2))) : new DeferredAssignment($target, $value, operator: $this->lowering->options->assignment($form->node(2)));
        }
        if (in_array($form->signature, ['option_value_no_option_type: @ @ opt_var_ident_type internal_variable_name equal set_expr_or_default', 'option_value_no_option_type: @ @ opt_set_var_ident_type lvalue_variable equal set_expr_or_default'], true)) {
            [$name, $instance] = $rule->variable($form->node(3));
            $target = $this->lowering->origins->record($this->lowering->leaves->record(new SystemTarget($name, $this->lowering->variables->scope($form->node(2)), $instance)), $form->node(3));
            $value = (new ValueRule($this->lowering))->operand($form->node(5), true);
            return $value instanceof Scalar || $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName || $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord ? new SystemAssignment($target, $value, operator: $this->lowering->options->assignment($form->node(4))) : new DeferredAssignment($target, $value, operator: $this->lowering->options->assignment($form->node(4)));
        }
        if (!in_array($form->node->children[0]->name ?? '', ['internal_variable_name', 'lvalue_variable'], true)) {
            return $rule->connection($form);
        }
        [$name, $qualifier] = $rule->variable($form->node(0));
        $value = (new ValueRule($this->lowering))->operand($form->node(2), $scope !== null);
        $item = $value instanceof Scalar || $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName || $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord ? new NameAssignment($name, $value, $scope, $qualifier, operator: $this->lowering->options->assignment($form->node(1))) : new DeferredAssignment($name, $value, $scope, $qualifier, operator: $this->lowering->options->assignment($form->node(1)));
        return $this->lowering->origins->record($item, $form->node);
    }
}
