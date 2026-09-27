<?php

declare(strict_types=1);

namespace Deriver\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\State\StateSlot;
use Deriver\Value\Identity;
use Deriver\Value\Lattice;
use Deriver\Value\Term;

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
     * Checks clone and invalidation modes before any application analysis begins.
     * @param StateSlot $slot Immutable descriptor
     * @throws InvalidInputException If the contract cannot safely be used
     */
    public function register(StateSlot $slot): void
    {
        $types = explode('|', $slot->type);
        if (str_starts_with($slot->id, 'parameter:') || isset($this->slots[$slot->id]) || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*(?:[.:][a-zA-Z0-9_-]+)+$/D', $slot->id) !== 1) {
            throw new InvalidInputException('MODEL_CONFLICT: invalid or repeated state slot ' . $slot->id);
        }
        if (!in_array($slot->clone, ['copy', 'reset'], true) || !in_array($slot->invalidation, ['havoc', 'preserve'], true) || array_diff($types, ['mixed', 'null', 'bool', 'int', 'float', 'string', 'array', 'object']) !== []) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: invalid state contract ' . $slot->id);
        }
        if ($slot->initial !== null && !(new Lattice())->contains(new Term('opaque', 'slot-type', attributes: ['type' => $slot->type], secret: true), $slot->initial)) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: incompatible state initializer ' . $slot->id);
        }
        $this->slots[$slot->id] = $slot;
        $this->manifest['slot:' . $slot->id] = $slot->type . ':' . $slot->clone . ':' . $slot->invalidation . ':' . ($slot->initial === null ? 'symbolic' : (new Identity())->key($slot->initial));
    }
}
