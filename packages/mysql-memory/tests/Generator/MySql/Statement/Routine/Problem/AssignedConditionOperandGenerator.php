<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\AssignedConditionOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use Tests\Generator\Composition;

/**
 * Composes condition-operand diagnostics from complete assignment generators.
 *
 * @implements ErisGenerator<AssignedConditionOperand>
 */
final class AssignedConditionOperandGenerator implements ErisGenerator
{
    /** @use Composition<AssignedConditionOperand> */
    use Composition;

    /**
     * @param ErisGenerator<VariableAssignment|DeferredAssignment> $operand The refused assignment
     */
    public function __construct(ErisGenerator $operand)
    {
        $this->delegate = Generators::map(static fn (VariableAssignment|DeferredAssignment $value): AssignedConditionOperand => new AssignedConditionOperand($value), $operand);
    }

    /**
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<AssignedConditionOperand>}>
     */
    public static function executionForms(): iterable
    {
        yield 'Routine\\Problem\\AssignedConditionOperand' => [\SqlSemantics\Contract\GrammarRelease::MySql5744, new self(\Tests\Generator\MySql\Statement\Variable\VariableAssignmentGenerator::numbers())];
    }
}
