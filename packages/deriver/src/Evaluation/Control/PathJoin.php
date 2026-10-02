<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
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
     * @return State|null Joined path, or null when the paths differ in more than plain values
     */
    public function join(array $states, bool $completed = false): ?State
    {
        $structure = $this->structure($states[0], $completed);
        foreach ($states as $state) {
            if ($this->structure($state, $completed) !== $structure) {
                return null;
            }
        }
        $registers = $completed ? [] : $this->values(array_map(static fn (State $state): array => $state->registers, $states), false);
        $cells = $this->values(array_map(static fn (State $state): array => $state->memory->cells, $states), true);
        $completion = $this->values(array_map(static fn (State $state): array => $state->completion->value === null ? [] : ['value' => $state->completion->value], $states), false);
        if ($registers === null || $cells === null || $completion === null || isset($completion['value']) !== ($states[0]->completion->value !== null)) {
            return null;
        }
        $result = $states[0]->fork();
        if (!$completed) {
            $addresses = $this->common(array_map(static fn (State $state): array => $state->addresses, $states));
            $offsets = $this->common(array_map(static fn (State $state): array => $state->offsets, $states));
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
            $expected = $join->encode($entry, $this->context->identity);
            foreach ($maps as $map) {
                if ($join->encode($map[$key], $this->context->identity) !== $expected) {
                    return null;
                }
            }
        }
        return $result;
    }

    /**
     * Widens maps of values key by key.
     * @param non-empty-list<array<string, Term>> $maps One map per path
     * @param bool $union Whether keys absent from some paths are joined as uninitialized storage
     * @return array<string, Term>|null Joined values, or null when an identity-bearing value differs
     */
    public function values(array $maps, bool $union): ?array
    {
        $identity = $this->context->identity;
        $lattice = new Lattice($this->context->models->extensions->domains);
        $keys = $union ? array_keys(array_replace(...$maps)) : array_keys(array_intersect_key(...$maps));
        $result = [];
        foreach ($keys as $key) {
            $value = $maps[0][$key] ?? new Term('uninitialized');
            foreach (array_slice($maps, 1) as $map) {
                $next = $map[$key] ?? new Term('uninitialized');
                if ($identity->key($value) === $identity->key($next)) {
                    continue;
                }
                if (!$this->plain($value) || !$this->plain($next)) {
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
            if (isset($visited[$current])) {
                continue;
            }
            if (in_array($current->kind, self::IDENTITIES, true) || count($visited) >= $this->context->query->budget()->nodes) {
                return false;
            }
            $visited[$current] = true;
            array_push($pending, ...array_values($current->operands));
        }
        return true;
    }
}
