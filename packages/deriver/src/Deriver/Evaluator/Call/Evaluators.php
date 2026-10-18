<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Exception\InvalidInputException;

/**
 * Resolves explicit evaluator precedence independently of registration order.
 * @visibility root
 */
final class Evaluators
{
    /**
     * @var array<string, CallEvaluator> Selected evaluators by case-insensitive symbol.
     */
    public array $evaluators = [];
    /**
     * @var array<string, CallDescriptor> Frozen selected descriptors by normalized symbol.
     */
    public array $descriptors = [];
    /**
     * @var array<string, CallDescriptor> Stable evaluator manifest by identifier.
     */
    public array $manifest = [];
    /**
     * Validated domain and intrinsic registrations.
     */
    public readonly Extensions $extensions;

    /**
     * Registered abstract object slots.
     */
    public readonly StateRegistry $state;

    /**
     * @param TargetProfile $target Value interpretation settings
     * @param Registrations $contributions Evaluators, providers, domains, intrinsics and state slots to register
     * @throws InvalidInputException If matching evaluators conflict or identifiers repeat
     */
    public function __construct(public readonly TargetProfile $target = new TargetProfile(), public readonly Registrations $contributions = new Registrations(), public readonly bool $standardEvaluators = true)
    {
        $this->extensions = new Extensions($contributions);
        $this->state = new StateRegistry($contributions->stateSlots);
        $candidates = [];
        foreach ($contributions->evaluators as $evaluator) {
            $descriptor = $evaluator->descriptor();
            $this->register($descriptor);
            $candidates[(new CallableIdentity())->key($descriptor->symbol)][$descriptor->id] = $evaluator;
        }
        foreach ($candidates as $symbol => $evaluators) {
            $descriptors = array_intersect_key($this->manifest, $evaluators);
            $winner = (new Precedence())->select($descriptors);
            $this->evaluators[$symbol] = $evaluators[$winner];
            $this->descriptors[$symbol] = $this->manifest[$winner];
        }
        ksort($this->manifest);
        ksort($this->evaluators);
    }

    /**
     * Captures and validates one registration without selecting by input order.
     * @param CallDescriptor $descriptor Immutable evaluator metadata
     * @throws InvalidInputException If identity or standard replacement is invalid
     */
    public function register(CallDescriptor $descriptor): void
    {
        if (isset($this->manifest[$descriptor->id])) {
            throw new InvalidInputException('EVALUATOR_CONFLICT: repeated evaluator ID ' . $descriptor->id);
        }
        $symbol = (new CallableIdentity())->key($descriptor->symbol);
        if ($descriptor->id === '' || $descriptor->version === '' || $symbol === '') {
            throw new InvalidInputException('EVALUATOR_CONTRACT_VIOLATION: evaluator identity fields cannot be empty.');
        }
        $standard = $this->standardEvaluators ? (new Library())->evaluator($symbol) : null;
        if ($standard !== null && !in_array($standard->descriptor()->id, $descriptor->replaces, true)) {
            throw new InvalidInputException('EVALUATOR_CONFLICT: replacing a standard evaluator requires an explicit replaces declaration.');
        }
        $this->manifest[$descriptor->id] = $descriptor;
    }

    /**
     * Selects by replacement intent and declared priority, rejecting equal rank.
     * @param CallDescriptor $candidate Candidate metadata
     * @param CallDescriptor $previous Previously selected metadata
     * @return bool Whether the candidate wins
     * @throws InvalidInputException If neither evaluator has unambiguous precedence
     */
    public function preferred(CallDescriptor $candidate, CallDescriptor $previous): bool
    {
        if (in_array($previous->id, $candidate->replaces, true)) {
            return true;
        }
        if (in_array($candidate->id, $previous->replaces, true)) {
            return false;
        }
        if ($candidate->priority === $previous->priority) {
            throw new InvalidInputException('EVALUATOR_CONFLICT: ' . $candidate->symbol);
        }
        return $candidate->priority > $previous->priority;
    }

    /**
     * Returns the selected evaluator's declarative decision.
     * @param string $symbol Normalized call target
     * @param string $receiverType Receiver type
     * @return CallDecision Selected semantics
     */
    public function evaluate(string $symbol, string $receiverType = ''): CallDecision
    {
        $evaluator = $this->evaluators[(new CallableIdentity())->key($symbol)] ?? null;
        if ($evaluator === null) {
            return CallDecision::declined();
        }
        $descriptor = $this->descriptors[(new CallableIdentity())->key($symbol)];
        return $evaluator->evaluate(new CallDescription($symbol, $descriptor->signature, $this->target, $receiverType));
    }
}
