<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Model;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Applies an explicit model's unresolved write footprint with normal and exceptional continuations.
 * @visibility root
 */
final class Effects
{
    /**
     * @param Context $context Frontier and model provenance
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
            (new Havoc())->reachable($state, $before, 'UNSUPPORTED_MODEL_CASE', $seen);
            $state->memory->write($location, Term::opaque('UNSUPPORTED_MODEL_CASE', dependencies: [$before]));
        }
        $state->registers[$instruction->result] = $this->context->frontier('UNSUPPORTED_MODEL_CASE', $instruction->source, $instruction->name, $dependencies);
        if (($instruction->attributes['may-throw'] ?? true) !== true) {
            return [$state];
        }
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
