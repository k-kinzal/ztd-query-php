<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Operation;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Model\Builtin\Formatting;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Keeps the formatted known prefix of a dynamic sprintf format in front of an explicit residual for the unknown rest.
 * @visibility root
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
     * @param Instruction $instruction Formatting intrinsic
     * @param State $state Bound inputs
     * @param list<Term> $values Format and argument array
     * @return list<State>|null Partial results with the unknown-rest boundary, or null when the ordinary model applies
     */
    public function apply(Instruction $instruction, State $state, array $values): ?array
    {
        $formatting = new Formatting($this->context->configuration->target->floatPrecision);
        if (($values[0] ?? Term::constant(null))->kind !== 'concat' || $formatting->apply($values, $instruction->name === 'vsprintf')->kind !== 'opaque') {
            return null;
        }
        $prefix = $formatting->prefix($values, $instruction->name === 'vsprintf');
        if ($prefix === null) {
            return null;
        }
        $paths = (new UnknownCall($this->context))->apply($state, $instruction, array_map(static fn (Term $value): PassedArgument => new PassedArgument($value), $values), null, 'UNSUPPORTED_MODEL_CASE', 'string');
        foreach ($paths as $path) {
            if ($path->completion->kind === 'normal') {
                $path->registers[$instruction->result] = (new Operations($this->context->configuration->target->floatPrecision))->binary('.', $prefix, $path->value($instruction->result));
            }
        }
        return $paths;
    }
}
