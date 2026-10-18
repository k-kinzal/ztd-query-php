<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Operation;

use Deriver\Deriver\Evaluator\Builtin\Formatting;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Keeps the formatted known prefix of a dynamic sprintf format in front of an explicit residual for the unknown rest.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class PartialFormatting
{
    /**
     * @param Context $context Frontiers and target configuration
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * @param Instruction $instruction \Deriver\Deriver\Evaluator\Builtin\Formatting intrinsic
     * @param State $state Bound inputs
     * @param list<Term> $values Format and argument array
     * @return list<State>|null Partial results with the unknown-rest boundary, or null when the ordinary evaluator applies
     */
    public function apply(Instruction $instruction, State $state, array $values): ?array
    {
        $formatting = new Formatting($this->context->configuration->target->floatPrecision);
        if (($values[0] ?? Term::constant(null))->kind !== \Deriver\Deriver\Evaluator\TermKind::Concat || $formatting->apply($values, $instruction->name === 'vsprintf')->kind !== \Deriver\Deriver\Evaluator\TermKind::Opaque) {
            return null;
        }
        $prefix = $formatting->prefix($values, $instruction->name === 'vsprintf');
        if ($prefix === null) {
            return null;
        }
        $paths = (new UnknownCall($this->context))->apply($state, $instruction, array_map(static fn (Term $value): PassedArgument => new PassedArgument($value), $values), null, 'UNSUPPORTED_EVALUATOR_CASE', 'string');
        foreach ($paths as $path) {
            if ($path->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                $path->registers[$instruction->result] = (new Operations($this->context->configuration->target->floatPrecision))->binary('.', $prefix, $path->value($instruction->result));
            }
        }
        return $paths;
    }
}
