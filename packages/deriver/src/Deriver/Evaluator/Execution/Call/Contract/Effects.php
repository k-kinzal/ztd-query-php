<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Contract;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Applies an explicit evaluator's unresolved write footprint with normal and exceptional continuations.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Effects
{
    /**
     * @param Context $context Frontier and evaluator provenance
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Invalidates the declared locations and every mutable value reachable through them.
     * @param Instruction $instruction Ordered havoc action
     * @param State $state Completed prior effects
     * @return list<State> Inclusive normal and exceptional alternatives
     */
    public function apply(Instruction $instruction, State $state): array
    {
        $seen = [];
        $dependencies = [];
        foreach ($instruction->operands as $register) {
            $location = $state->addresses[$register];
            $before = $state->memory->read($location);
            $dependencies[] = $before;
            (new Havoc())->reachable($state, $before, 'UNSUPPORTED_EVALUATOR_CASE', $seen);
            $state->memory->write($location, Term::opaque('UNSUPPORTED_EVALUATOR_CASE', dependencies: [$before]));
        }
        $state->registers[$instruction->result] = $this->context->frontier('UNSUPPORTED_EVALUATOR_CASE', $instruction->source, $instruction->name, $dependencies);
        if (($instruction->attributes['may-throw'] ?? true) !== true) {
            return [$state];
        }
        $exception = $state->fork();
        $exception->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
