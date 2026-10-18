<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Checks generated-expression roles before ordinary scalar preparation.
 *
 * Child expressions complete before their enclosing function or query. A
 * forbidden call inside a subquery therefore precedes that subquery's role
 * refusal. This visits semantic operands only, independently of catalog state.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Operands
{
    /**
     * Finds the first intrinsic generation-expression refusal in completion order.
     */
    public function problem(Node $expression, Name $column): ?RefusedOperand
    {
        foreach (get_object_vars($expression) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && ($problem = $this->problem($child, $column)) !== null) {
                    return $problem;
                }
            }
        }
        if ($expression instanceof Query) {
            return new RefusedOperand($column, OperandKind::Subquery);
        }
        if ($expression instanceof UserVariable || $expression instanceof SystemVariable) {
            return new RefusedOperand($column, OperandKind::Variable);
        }
        $name = match (true) {
            $expression instanceof FunctionCall => $expression->schema === null ? strtolower($expression->name->value) : null,
            $expression instanceof KeywordCall => strtolower($expression->function->value),
            $expression instanceof ClockCall => strtolower($expression->clock->value),
            $expression instanceof InsertedColumn => 'values',
            default => null,
        };
        if ($name === 'name_const') {
            return new RefusedOperand($column, OperandKind::Subquery);
        }
        $function = $name === null ? null : ((new Functions())->names()[$name] ?? null);
        return $function === null ? null : new RefusedOperand($column, OperandKind::FunctionCall, $function);
    }
}
