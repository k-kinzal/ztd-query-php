<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\State\StateSlot;
use Deriver\Deriver\Evaluator\Domain\Lattice;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\InvalidInputException;

/**
 * Validates finite state-slot contracts and includes their entire semantics in snapshot identity.
 * @visibility root
 */
final class StateRegistry
{
    /**
     * @var array<string, StateSlot> Registered slots by namespaced identity.
     */
    public array $slots = [];
    /**
     * @var array<string, string> Stable semantic fingerprint by slot identity.
     */
    public array $manifest = [];

    /**
     * @param list<StateSlot> $slots Explicit state contracts
     * @throws InvalidInputException If a slot is ambiguous, malformed, or has an incompatible initializer
     */
    public function __construct(array $slots)
    {
        foreach ($slots as $slot) {
            $this->register($slot);
        }
        ksort($this->slots);
        ksort($this->manifest);
    }

    /**
     * Checks slot identities, type bounds, and initial values before application analysis.
     * @param StateSlot $slot Immutable descriptor
     * @throws InvalidInputException If the contract cannot safely be used
     */
    public function register(StateSlot $slot): void
    {
        $types = explode('|', $slot->type);
        if (str_starts_with($slot->id, 'parameter:') || isset($this->slots[$slot->id]) || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*(?:[.:][a-zA-Z0-9_-]+)+$/D', $slot->id) !== 1) {
            throw new InvalidInputException('EVALUATOR_CONFLICT: invalid or repeated state slot ' . $slot->id);
        }
        if (array_diff($types, ['mixed', 'null', 'bool', 'int', 'float', 'string', 'array', 'object']) !== []) {
            throw new InvalidInputException('EVALUATOR_CONTRACT_VIOLATION: invalid state contract ' . $slot->id);
        }
        if ($slot->initial !== null && !(new Lattice())->contains(new Term(\Deriver\Deriver\Evaluator\TermKind::Opaque, 'slot-type', attributes: ['type' => $slot->type]), $slot->initial)) {
            throw new InvalidInputException('EVALUATOR_CONTRACT_VIOLATION: incompatible state initializer ' . $slot->id);
        }
        $this->slots[$slot->id] = $slot;
        $this->manifest['slot:' . $slot->id] = $slot->type . ':' . $slot->clone->value . ':' . $slot->invalidation->value . ':' . ($slot->initial === null ? 'symbolic' : (new Identity())->key($slot->initial));
    }
}
