<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Memory;

use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Term;
use WeakMap;

/**
 * Captures reachable raw storage without expanding aliases or cyclic PHP structures.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class StorageCapture
{
    /**
     * Captures immutable terms reachable from locals, exported values, and shared storage.
     * @param Memory $memory Completed path memory
     * @param array<string, Location> $locals Observable local bindings
     * @param array<string, Term> $values Additional values escaping the frame
     * @return StorageSnapshot Finite storage graph
     */
    public function capture(Memory $memory, array $locals, array $values = []): StorageSnapshot
    {
        $bindings = [];
        foreach ($locals as $name => $location) {
            $bindings[$name] = new Term(\Deriver\Deriver\Evaluator\TermKind::Location, $location->root, array_map(static fn (int|string $key): Term => Term::constant($key), $location->path), ['unknown' => $location->unknown]);
        }
        $pending = [...array_values($bindings), ...array_values($values)];
        foreach (array_keys($memory->cells) as $root) {
            if (str_starts_with($root, 'global:') || str_starts_with($root, 'static:')) {
                $pending[] = new Term(\Deriver\Deriver\Evaluator\TermKind::Cell, $root);
            }
        }
        $cells = [];
        /** @var WeakMap<Term, bool> $seen */
        $seen = new WeakMap();
        while ($pending !== []) {
            $term = array_pop($pending);
            if (isset($seen[$term])) {
                continue;
            }
            $seen[$term] = true;
            foreach ($term->operands as $operand) {
                $pending[] = $operand;
            }
            foreach ($this->roots($term, $memory) as $root) {
                if (!isset($cells[$root])) {
                    $raw = $memory->cells[$root] ?? new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized);
                    $cells[$root] = $raw;
                    $pending[] = $raw;
                }
            }
        }
        ksort($bindings);
        ksort($cells);
        return new StorageSnapshot($bindings, $cells);
    }

    /**
     * Finds storage dependencies of an identity-bearing value.
     * @param Term $term Raw value
     * @param Memory $memory Path memory
     * @return list<string> Referenced storage roots
     */
    public function roots(Term $term, Memory $memory): array
    {
        if (!is_string($term->literal)) {
            return [];
        }
        if ($term->kind === \Deriver\Deriver\Evaluator\TermKind::Cell || $term->kind === \Deriver\Deriver\Evaluator\TermKind::Location) {
            return [$term->literal];
        }
        $roots = [];
        foreach (['object:', 'evaluator:'] as $prefix) {
            if (($term->kind === \Deriver\Deriver\Evaluator\TermKind::Object && $prefix === 'object:') || isset($memory->cells[$prefix . $term->literal])) {
                $roots[] = $prefix . $term->literal;
            }
        }
        return $roots;
    }
}
