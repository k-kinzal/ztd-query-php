<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A retained variable assignment whose value cannot occupy an ordinary scalar role.
 *
 * It preserves the target, complete computation and assignment precedence.
 * Preparation binds the operand; no assignment is performed before its condition.
 *
 * @visibility public
 * @example Keeping an assignment inside an unreachable stored instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() IF FALSE THEN SELECT @x := ROW(1,2); END IF');
 *     $operation->statement->body->branches[0]->statements[0]->items[0]->expression instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment // => true
 */
final class DeferredAssignment implements DeferredOperand
{
    use Snapshot;

    /**
     * @param UserVariable $target The variable receiving the value
     * @param RowOperand|DeferredOperand $value The complete constrained computation
     */
    public function __construct(public readonly UserVariable $target, public readonly RowOperand|DeferredOperand $value)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Resolves dependencies without assigning an invalid result type to the session.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->target, $environment);
        $derivation->scalar($this->value, $environment);
        Deprecation::raise(Deprecated::AssignmentInExpression, $derivation);
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return non-empty-list<RowOperand|DeferredOperand> The computation to prepare before assignment
     */
    public function operands(): array
    {
        return [$this->value];
    }

    /**
     * Answers the retained value's scalar-use condition.
     */
    public function problem(): Diagnostic
    {
        return $this->value instanceof DeferredOperand ? $this->value->problem() : new OperandColumns(1, count((new RowShapes())->parts($this->value) ?? []));
    }

    /**
     * Writes the target and the entire right-hand expression.
     */
    public function render(Output $out): void
    {
        $out->node($this->target)->symbol(':=')->node($this->value);
    }
}
