<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Memory\Slots;

use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Maintains handled object state separately from PHP properties and magic accessors.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class StateStorage
{
    /**
     * @param Context $context Registered slot contracts and provenance
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Resolves an abstract slot without invoking PHP property access protocols.
     * @param Instruction $instruction Evaluator-state address instruction
     * @param State $state Current path
     * @return Term Address token with registered type constraints
     */
    public function address(Instruction $instruction, State $state): Term
    {
        $receiver = $state->value($instruction->operands[0]);
        $slot = $this->context->evaluators->state->slots[$instruction->name] ?? null;
        if ($slot === null || !in_array($receiver->kind, [\Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Parameter, \Deriver\Deriver\Evaluator\TermKind::External], true) || !is_string($receiver->literal)) {
            $state->addresses[$instruction->result] = new Location('invalid-evaluator-state', unknown: true);
            return $this->context->frontier('EVALUATOR_CONTRACT_VIOLATION', $instruction->source, 'evaluator-state-address');
        }
        $root = 'evaluator:' . $receiver->literal;
        $this->initialize($state, $receiver->literal, external: !isset($state->memory->cells[$root]));
        $state->addresses[$instruction->result] = new Location($root, [$slot->id]);
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Location, $root);
    }

    /**
     * Applies defaults per allocation and the registered shallow-copy or reset rule per clone.
     * @param State $state Current memory
     * @param string $identity New or external receiver identity
     * @param string|null $cloned Original object identity, when cloning
     * @param bool $external Whether the receiver existed before the analyzed execution
     */
    public function initialize(State $state, string $identity, ?string $cloned = null, bool $external = false): void
    {
        $root = 'evaluator:' . $identity;
        if (isset($state->memory->cells[$root])) {
            return;
        }
        $entries = [];
        foreach ($this->context->evaluators->state->slots as $id => $slot) {
            $initial = $slot->initial ?? new Term(\Deriver\Deriver\Evaluator\TermKind::StateInput, $identity . ':' . $id, attributes: ['type' => $slot->type]);
            $value = $external ? new Term(\Deriver\Deriver\Evaluator\TermKind::StateInput, $identity . ':' . $id, attributes: ['type' => $slot->type]) : $initial;
            if ($cloned !== null && $slot->clone === \Deriver\Deriver\Evaluator\Call\State\ClonePolicy::Copy) {
                $value = $state->memory->cells['evaluator:' . $cloned]->operands[$id] ?? new Term(\Deriver\Deriver\Evaluator\TermKind::StateInput, $cloned . ':' . $id, attributes: ['type' => $slot->type]);
            }
            $entries[$id] = $value;
            $state->memory->propertyTypes[$root][$id] = $slot->type;
            $state->memory->slotContracts[$id] = $slot;
        }
        $state->memory->cells[$root] = Term::array($entries);
    }
    /**
     * Reads a declared slot for an observation without invoking handled or application methods.
     * @param Term $receiver Observed object identity
     * @param string $slot Registered slot name
     * @param State $state Current memory
     * @param SourceRef|null $source Observation provenance
     * @return Term Abstract slot value, or an explicit unsupported receiver residual
     */
    public function observe(Term $receiver, string $slot, State $state, ?SourceRef $source = null): Term
    {
        if (!in_array($receiver->kind, [\Deriver\Deriver\Evaluator\TermKind::Object, \Deriver\Deriver\Evaluator\TermKind::Parameter, \Deriver\Deriver\Evaluator\TermKind::External], true) || !is_string($receiver->literal)) {
            return $source === null ? Term::opaque('UNKNOWN_STATE_RECEIVER', dependencies: [$receiver]) : $this->context->frontier('UNSUPPORTED_EVALUATOR_CASE', $source, 'state-projection-receiver', [$receiver]);
        }
        $this->initialize($state, $receiver->literal, external: true);
        return $state->memory->read(new Location('evaluator:' . $receiver->literal, [$slot]));
    }
}
