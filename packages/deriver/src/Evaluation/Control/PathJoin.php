<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Lattice;
use Deriver\Value\Term;
use WeakMap;

/**
 * Joins excess paths into one path that keeps executing, instead of sealing them.
 *
 * Paths are joined only when they share their control position, aliases, objects, and handlers, and differ only in
 * plain values. Differing values are widened, so the joined path contains every input path; guards keep their common
 * entries and constraints are dropped.
 * @visibility root
 */
final class PathJoin
{
    /**
     * Term kinds whose identity denotes shared storage; widening them would lose aliasing.
     */
    private const IDENTITIES = ['cell', 'object', 'closure', 'location', 'iterator', 'throwable'];

    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Widens paths that wait at one program point, entered from one predecessor, or that complete with one kind.
     * @param non-empty-list<State> $states Paths to join
     * @param bool $completed Whether the paths have left the callable, so their position and registers no longer matter
     * @param bool $shared Whether the caller already established that the paths share one structure
     * @return State|null Joined path, or null when the paths differ in more than plain values
     */
    public function join(array $states, bool $completed = false, bool $shared = false): ?State
    {
        $structure = $shared ? '' : $this->structure($states[0], $completed);
        foreach ($shared ? [] : $states as $state) {
            if ($this->structure($state, $completed) !== $structure) {
                return null;
            }
        }
        $registers = $completed ? [] : $this->values(array_map(static fn (State $state): array => $state->registers, $states), false);
        $cells = $this->values(array_map(static fn (State $state): array => $state->memory->cells, $states), true, true);
        $completion = $this->values(array_map(static fn (State $state): array => $state->completion->value === null ? [] : ['value' => $state->completion->value], $states), false);
        if ($registers === null || $cells === null || $completion === null || isset($completion['value']) !== ($states[0]->completion->value !== null)) {
            return null;
        }
        $result = $states[0]->fork();
        if (!$completed) {
            $addresses = $this->locations(array_map(static fn (State $state): array => $state->addresses, $states));
            $offsets = $this->offsets(array_map(static fn (State $state): array => $state->offsets, $states));
            $targets = $this->common(array_map(static fn (State $state): array => $state->callTargets, $states));
            $properties = $this->common(array_map(static fn (State $state): array => $state->properties, $states));
            if ($addresses === null || $offsets === null || $targets === null || $properties === null) {
                return null;
            }
            [$result->addresses, $result->offsets, $result->callTargets, $result->properties] = [$addresses, $offsets, $targets, $properties];
        }
        $result->registers = $registers;
        $result->memory->cells = $cells;
        $result->completion = new Completion($result->completion->kind, $completion['value'] ?? null, $result->completion->target, $result->completion->depth);
        $result->constraints = [];
        foreach (array_slice($states, 1) as $state) {
            $this->merge($result, $state);
        }
        return $result;
    }

    /**
     * Accumulates bookkeeping that may differ between joined paths.
     * @param State $result Joined path
     * @param State $state Another joined path
     */
    public function merge(State $result, State $state): void
    {
        $result->guard = array_intersect_assoc($result->guard, $state->guard);
        $result->producers = array_intersect_assoc($result->producers, $state->producers);
        $result->producers = array_intersect_key($result->producers, $result->registers);
        $result->evidence = array_values(array_unique([...$result->evidence, ...$state->evidence]));
        $result->controls = array_values(array_unique([...$result->controls, ...$state->controls]));
        $result->observed = $result->observed && $state->observed;
        $result->observedQueries = array_intersect_key($result->observedQueries, $state->observedQueries);
        foreach ($state->visits as $header => $count) {
            $result->visits[$header] = max($result->visits[$header] ?? 0, $count);
        }
        foreach ($state->memory->versions as $root => $version) {
            $result->memory->versions[$root] = max($result->memory->versions[$root] ?? 0, $version);
        }
        $result->memory->writers = array_intersect_assoc($result->memory->writers, $state->memory->writers);
        $result->memory->sequence = max($result->memory->sequence, $state->memory->sequence);
        $result->unknownLocals ??= $state->unknownLocals;
        $result->memory->unknownShared ??= $state->memory->unknownShared;
        $this->loops($result, $state);
    }

    /**
     * Keeps loop bookkeeping only where both paths agree, so widening restores no path-specific guard and no loop is
     * considered stable on behalf of a path that has not converged.
     * @param State $result Joined path
     * @param State $state Another joined path
     */
    public function loops(State $result, State $state): void
    {
        foreach ($result->loopGuards as $header => $guard) {
            if (isset($state->loopGuards[$header])) {
                $result->loopGuards[$header] = array_intersect_assoc($guard, $state->loopGuards[$header]);
            } else {
                unset($result->loopGuards[$header]);
            }
        }
        foreach ($result->approximations as $header => $cells) {
            $other = $state->approximations[$header] ?? [];
            $same = isset($state->approximations[$header]) && array_keys($cells) === array_keys($other) && ($result->loopStructures[$header] ?? null) === ($state->loopStructures[$header] ?? null);
            foreach ($same ? $cells : [] as $root => $value) {
                $same = $same && isset($other[$root]) && $this->context->identity->key($value) === $this->context->identity->key($other[$root]);
            }
            if (!$same) {
                unset($result->approximations[$header], $result->loopStructures[$header]);
            }
        }
        if ($result->stableHeader !== $state->stableHeader) {
            $result->stableHeader = null;
        }
    }

    /**
     * Fingerprints the parts of a path that joined paths must share exactly: control position, aliases, objects, handlers, and cursors.
     * The predecessor selects the operands of the block's value merges, so pending paths must share it.
     * @param State $state Candidate path
     * @param bool $completed Whether the path has left the callable, so its position, locals, and handlers no longer matter
     * @return string Structural fingerprint without values and bookkeeping
     */
    public function structure(State $state, bool $completed = false): string
    {
        $copy = $state->fork();
        [$copy->registers, $copy->producers, $copy->addresses, $copy->offsets, $copy->callTargets, $copy->properties] = [[], [], [], [], [], []];
        [$copy->guard, $copy->constraints, $copy->evidence, $copy->controls, $copy->visits] = [[], [], [], [], []];
        [$copy->loopEntries, $copy->approximations, $copy->loopStructures, $copy->loopGuards, $copy->stableHeader] = [[], [], [], [], null];
        [$copy->observed, $copy->unknownLocals, $copy->memory->unknownShared] = [false, null, null];
        [$copy->memory->cells, $copy->memory->versions, $copy->memory->writers, $copy->memory->sequence] = [[], [], [], 0];
        $copy->observedQueries = [];
        $copy->completion = new Completion($state->completion->kind, null, $state->completion->target, $state->completion->depth);
        if ($completed) {
            [$copy->block, $copy->previous, $copy->locals, $copy->handlers, $copy->iterators] = [0, -1, [], [], []];
        }
        return (new StateJoin($this->context))->fingerprint($copy, $this->context->identity);
    }

    /**
     * Keeps register metadata defined on every path; registers defined only on some paths are not read after the join.
     * @template T of object
     * @param non-empty-list<array<string, T>> $maps One register-indexed map per path
     * @return array<string, T>|null Shared entries, or null when one register has different metadata
     */
    public function common(array $maps): ?array
    {
        $join = new StateJoin($this->context);
        $result = array_intersect_key(...$maps);
        foreach ($result as $key => $entry) {
            if ($this->context->resources->reason() !== null) {
                return null;
            }
            $expected = null;
            foreach ($maps as $map) {
                if ($map[$key] === $entry) {
                    continue;
                }
                $expected ??= $join->encode($entry, $this->context->identity);
                if ($join->encode($map[$key], $this->context->identity) !== $expected) {
                    return null;
                }
            }
        }
        return $result;
    }

    /**
     * Keeps addresses defined on every path; differing addresses into one storage root become an unknown location in it.
     * A write through the unknown location invalidates the whole root, so every path's write is included.
     * @param non-empty-list<array<string, Location>> $maps One register-indexed address map per path
     * @return array<string, Location>|null Joined addresses, or null when one register addresses different roots
     */
    public function locations(array $maps): ?array
    {
        $result = array_intersect_key(...$maps);
        foreach ($result as $key => $location) {
            if ($this->context->resources->reason() !== null) {
                return null;
            }
            foreach ($maps as $map) {
                $other = $map[$key];
                if ([$other->root, $other->path, $other->local, $other->unknown] === [$location->root, $location->path, $location->local, $location->unknown]) {
                    continue;
                }
                if ($other->root !== $location->root) {
                    return null;
                }
                $location = new Location($location->root, local: $location->local === $other->local ? $location->local : '', unknown: true);
            }
            $result[$key] = $location;
        }
        return $result;
    }

    /**
     * Keeps offsets defined on every path; differing keys below one parent are widened like other plain values.
     * @param non-empty-list<array<string, Address>> $maps One register-indexed offset map per path
     * @return array<string, Address>|null Joined offsets, or null when one register has another parent or syntax
     */
    public function offsets(array $maps): ?array
    {
        $result = array_intersect_key(...$maps);
        foreach ($result as $register => $address) {
            if ($this->context->resources->reason() !== null) {
                return null;
            }
            $keys = [];
            foreach ($maps as $index => $map) {
                if ($map[$register]->parent !== $address->parent || ($map[$register]->key === null) !== ($address->key === null)) {
                    return null;
                }
                $keys[$index] = $map[$register]->key === null ? [] : ['key' => $map[$register]->key];
            }
            $key = $this->values(array_values($keys), false);
            if ($key === null) {
                return null;
            }
            $result[$register] = new Address($address->parent, $key['key'] ?? null);
        }
        return $result;
    }

    /**
     * Widens maps of values key by key.
     * @param non-empty-list<array<string, Term>> $maps One map per path
     * @param bool $union Whether keys absent from some paths are joined as uninitialized storage
     * @param bool $storage Whether keys are storage roots; only variable roots are widened, while object, model, static,
     *     and constant storage must be identical, because widening their records would lose uninitialized slots
     * @return array<string, Term>|null Joined values, or null when an identity-bearing value differs
     */
    public function values(array $maps, bool $union, bool $storage = false): ?array
    {
        $identity = $this->context->identity;
        $lattice = new Lattice($this->context->models->extensions->domains);
        $keys = $union ? array_keys(array_replace(...$maps)) : array_keys(array_intersect_key(...$maps));
        $result = [];
        foreach ($keys as $key) {
            if ($this->context->resources->reason() !== null) {
                return null;
            }
            $value = $maps[0][$key] ?? new Term('uninitialized');
            foreach (array_slice($maps, 1) as $map) {
                $next = $map[$key] ?? new Term('uninitialized');
                if ($identity->key($value) === $identity->key($next)) {
                    continue;
                }
                if (!$this->plain($value) || !$this->plain($next) || $storage && !str_starts_with($key, 'cell:') && !str_starts_with($key, 'global:')) {
                    return null;
                }
                $value = $lattice->widen($value, $next);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Checks that a value carries no storage or object identity.
     * @param Term $value Candidate value
     * @return bool Whether widening the value cannot lose aliasing
     */
    public function plain(Term $value): bool
    {
        /** @var WeakMap<Term, true> $visited */
        $visited = new WeakMap();
        $pending = [$value];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($visited[$current]) || ($this->context->plainValues[$current] ?? null) === true) {
                continue;
            }
            if (count($visited) % 256 === 0 && $this->context->resources->reason() !== null || count($visited) >= $this->context->query->budget()->nodes) {
                return false;
            }
            if (($this->context->plainValues[$current] ?? null) === false || in_array($current->kind, self::IDENTITIES, true)) {
                $this->context->plainValues[$current] = false;
                return false;
            }
            $visited[$current] = true;
            array_push($pending, ...array_values($current->operands));
        }
        foreach ($visited as $checked => $_) {
            $this->context->plainValues[$checked] = true;
        }
        return true;
    }
}
