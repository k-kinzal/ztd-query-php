<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\Memory\Location;
use Deriver\Value\Term;
use WeakMap;

/**
 * Invalidates only reachable mutable state, plus exposed globals and statics.
 * @visibility root
 */
final class Havoc
{
    /**
     * Invalidates values reachable from unknown calls and escaped references.
     * @param State $state Mutable execution path
     * @param list<Term> $values Receivers, arguments, and escaped callbacks
     * @param list<Location> $references Potential reference arguments
     * @param string $reason Root cause
     */
    public function call(State $state, array $values, array $references, string $reason): void
    {
        $state->memory->unknownShared = $reason;
        $seen = [];
        foreach ($values as $value) {
            $this->reachable($state, $value, $reason, $seen);
        }
        foreach ($references as $reference) {
            $this->reachable($state, $state->memory->read($reference), $reason, $seen);
            $state->memory->write($reference, Term::opaque($reason, dependencies: [$state->memory->read($reference)]));
        }
        foreach ($state->memory->cells as $root => $value) {
            if (str_starts_with($root, 'global:') || str_starts_with($root, 'static:')) {
                $this->reachable($state, $value, $reason, $seen);
                $state->memory->cells[$root] = Term::opaque($reason, dependencies: [$value]);
            }
        }
    }

    /**
     * Follows object, cell, array, and capture references without revisiting cycles.
     * @param State $state Execution state
     * @param Term $value Reachable value
     * @param string $reason Root cause
     * @param array<string, true> $seen Already visited storage roots
     */
    public function reachable(State $state, Term $value, string $reason, array &$seen): void
    {
        /** @var WeakMap<Term, true> $visited */
        $visited = new WeakMap();
        $pending = [$value];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $root = null;
            if (is_string($current->literal) && ($current->kind === 'object' || isset($state->memory->cells['object:' . $current->literal]) || isset($state->memory->cells['model:' . $current->literal]))) {
                $root = 'object:' . $current->literal;
                array_push($pending, ...$this->slotValues($state, $current->literal, $reason, $seen));
            } elseif ($current->kind === 'cell' && is_string($current->literal)) {
                $root = $current->literal;
            }
            if ($root !== null && !isset($seen[$root])) {
                $seen[$root] = true;
                $previous = $state->memory->cells[$root] ?? Term::opaque($reason);
                $pending[] = $previous;
                if ($previous->kind === 'array') {
                    $entries = [];
                    foreach ($previous->operands as $key => $entry) {
                        $entries[$key] = new Term('opaque', $reason, [$entry], ['type' => $state->memory->propertyTypes[$root][$key] ?? 'mixed', 'maybeUninitialized' => true]);
                    }
                    $state->memory->cells[$root] = Term::array($entries, true);
                } else {
                    $state->memory->cells[$root] = Term::opaque($reason, dependencies: [$previous]);
                }
            }
            array_push($pending, ...array_values($current->operands));
        }
    }

    /**
     * Applies explicit abstract-slot invalidation while preserving declared stable slots.
     * @param State $state Execution state
     * @param string $identity Reachable receiver
     * @param string $reason Boundary cause
     * @param array<string, true> $seen Cycle guard shared with ordinary heap traversal
     */
    public function slots(State $state, string $identity, string $reason, array &$seen): void
    {
        foreach ($this->slotValues($state, $identity, $reason, $seen) as $value) {
            $this->reachable($state, $value, $reason, $seen);
        }
    }

    /**
     * Invalidates abstract slots and queues their previous mutable descendants.
     * @param State $state Execution state
     * @param string $identity Reachable receiver
     * @param string $reason Boundary cause
     * @param array<string, true> $seen Already invalidated storage roots
     * @return list<Term> Values whose reachable storage must also be invalidated
     */
    public function slotValues(State $state, string $identity, string $reason, array &$seen): array
    {
        $root = 'model:' . $identity;
        if (isset($seen[$root]) || !isset($state->memory->cells[$root])) {
            return [];
        }
        $seen[$root] = true;
        $values = [];
        foreach ($state->memory->slotContracts as $id => $slot) {
            if ($slot->invalidation === 'preserve') {
                continue;
            }
            $location = new Location($root, [$id]);
            $previous = $state->memory->read($location);
            $values[] = $previous;
            $state->memory->write($location, Term::opaque($reason, $slot->type, [$previous]));
        }
        return $values;
    }

    /**
     * Invalidates the symbol table for eval/include or unexplored language effects.
     * @param State $state Execution path
     * @param string $reason Boundary reason
     */
    public function all(State $state, string $reason): void
    {
        $state->unknownLocals = $reason;
        $state->memory->unknownShared = $reason;
        foreach ($state->memory->cells as $root => $value) {
            $state->memory->cells[$root] = Term::opaque($reason, dependencies: [$value]);
        }
    }

    /**
     * Invalidates variables reachable through the current frame's symbol table.
     * @param State $state Current frame
     * @param string $reason Unknown variable mutation
     */
    public function symbols(State $state, string $reason): void
    {
        $state->unknownLocals = $reason;
        $references = [];
        $values = [];
        foreach ($state->locals as $name => $location) {
            if ($name !== 'this') {
                $references[] = $location;
            } else {
                $values[] = $state->memory->read($location);
            }
        }
        $this->call($state, $values, $references, $reason);
    }
}
