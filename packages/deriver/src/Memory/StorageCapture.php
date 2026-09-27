<?php

declare(strict_types=1);

namespace Deriver\Memory;

use Deriver\Result\StorageSnapshot;
use Deriver\Value\Term;
use WeakMap;

/**
 * Captures reachable raw storage without expanding aliases or cyclic PHP structures.
 * @visibility root
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
            $bindings[$name] = new Term('location', $location->root, array_map(static fn (int|string $key): Term => Term::constant($key), $location->path), ['unknown' => $location->unknown]);
        }
        $pending = array_map(static fn (Term $term): array => [$term, false], [...array_values($bindings), ...array_values($values)]);
        foreach (array_keys($memory->cells) as $root) {
            if (str_starts_with($root, 'global:') || str_starts_with($root, 'static:')) {
                $pending[] = [new Term('cell', $root), false];
            }
        }
        $cells = [];
        /** @var WeakMap<Term, bool> $seen */
        $seen = new WeakMap();
        while ($pending !== []) {
            [$term, $confidential] = array_pop($pending);
            $confidential = $confidential || $term->secret;
            if (isset($seen[$term]) && ($seen[$term] || !$confidential)) {
                continue;
            }
            $seen[$term] = $confidential;
            foreach ($term->operands as $operand) {
                $pending[] = [$operand, $confidential];
            }
            foreach ($this->roots($term, $memory) as $root) {
                if (!isset($cells[$root]) || $confidential && !$cells[$root]->secret) {
                    $raw = $memory->cells[$root] ?? new Term('uninitialized');
                    $cells[$root] = $confidential ? new Term($raw->kind, $raw->literal, $raw->operands, $raw->attributes, true) : $raw;
                    $pending[] = [$raw, $confidential];
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
        if ($term->kind === 'cell' || $term->kind === 'location') {
            return [$term->literal];
        }
        $roots = [];
        foreach (['object:', 'model:'] as $prefix) {
            if (($term->kind === 'object' && $prefix === 'object:') || isset($memory->cells[$prefix . $term->literal])) {
                $roots[] = $prefix . $term->literal;
            }
        }
        return $roots;
    }
}
