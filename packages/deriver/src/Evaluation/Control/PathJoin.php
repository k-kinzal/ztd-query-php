<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\Identity;
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
        $identity = $this->context->identity;
        $join = new StateJoin($this->context);
        $structure = $join->encode($this->structure($states[0], $completed), $identity);
        foreach ($states as $state) {
            if ($join->encode($this->structure($state, $completed), $identity) !== $structure) {
                return null;
            }
        }
        $registers = $completed ? [] : $this->values(array_map(static fn (State $state): array => $state->registers, $states), false);
        $cells = $this->values(array_map(static fn (State $state): array => $state->memory->cells, $states), true);
        $completion = $this->values(array_map(static fn (State $state): array => $state->completion->value === null ? [] : [$state->completion->value], $states), false);
        if ($registers === null || $cells === null || $completion === null || count($completion) !== ($states[0]->completion->value === null ? 0 : 1)) {
            return null;
        }
        $metadata = [];
        foreach ($completed ? [] : ['addresses', 'offsets', 'callTargets', 'properties'] as $field) {
            $metadata[$field] = $this->common(array_map(static fn (State $state): array => $state->{$field}, $states));
            if ($metadata[$field] === null) {
                return null;
            }
        }
        $result = $states[0]->fork();
        foreach ($metadata as $field => $entries) {
            $result->{$field} = $entries;
        }
        $result->registers = $registers;
        $result->memory->cells = $cells;
        $result->completion = new Completion($result->completion->kind, $completion[0] ?? null, $result->completion->target, $result->completion->depth);
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
     * Lists the parts of a path that joined paths must share exactly.
     * The predecessor selects the operands of the block's value merges, so pending paths must share it.
     * @param State $state Candidate path
     * @param bool $completed Whether the path has left the callable
     * @return list<mixed> Control position, aliases, objects, handlers, and cursors
     */
    public function structure(State $state, bool $completed = false): array
    {
        $memory = $state->memory;
        $completion = $state->completion;
        return [$completed ? null : [$state->block, $state->previous], $completion->kind, $completion->target, $completion->depth, $state->locals, $state->handlers, $state->iterators, $state->lateStaticClass, $memory->classes, $memory->propertyTypes, $memory->cloneWrites, array_keys($memory->slotContracts), $memory->liveArrays];
    }

    /**
     * Keeps register metadata defined on every path; registers defined only on some paths are not read after the join.
     * @param list<array<int|string, mixed>> $maps One register-indexed map per path
     * @return array<int|string, mixed>|null Shared entries, or null when one register has different metadata
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
     * @param list<array<int|string, Term>> $maps One map per path
     * @param bool $union Whether keys absent from some paths are joined as uninitialized storage
     * @return array<int|string, Term>|null Joined values, or null when an identity-bearing value differs
     */
    public function values(array $maps, bool $union): ?array
    {
        $identity = $this->context->identity;
        $lattice = new Lattice($this->context->models->extensions->domains);
        $keys = $union ? array_keys(array_replace(...$maps)) : array_keys(array_intersect_key(...$maps));
        $result = [];
        foreach ($keys as $key) {
            $value = null;
            foreach ($maps as $map) {
                $next = $map[$key] ?? new Term('uninitialized');
                if ($value !== null && $identity->key($value) !== $identity->key($next)) {
                    if (!$this->plain($value) || !$this->plain($next)) {
                        return null;
                    }
                    $next = $lattice->widen($value, $next);
                }
                $value = $next;
            }
            if ($value !== null) {
                $result[$key] = $value;
            }
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
