<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Evaluation\Context;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Result\StorageSnapshot;
use Deriver\Value\Lattice;
use Deriver\Value\Term;

/**
 * Bounds exported observations with inclusive value and state joins.
 * @visibility root
 */
final class ObservationLimit
{
    /**
     * @param Context $context Query-local observations
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Merges repeated outcomes, then joins only the excess beyond the budget instead of truncating late alternatives.
     * @param SourceRef $source Observation source
     */
    public function enforce(SourceRef $source): void
    {
        $limit = $this->context->query->budget()->partitions;
        if (count($this->context->normal) > $limit) {
            $normal = $this->distinct($this->context->normal, $source);
            if (count($normal) > $limit) {
                $excess = array_slice($normal, $limit - 1);
                $normal = [...array_slice($normal, 0, $limit - 1), new Alternative($this->join(array_map(static fn (Alternative $outcome): array => $outcome->values, $excess)), state: $this->join(array_map(static fn (Alternative $outcome): array => $outcome->state, $excess)), storage: $this->storage(array_map(static fn (Alternative $outcome): StorageSnapshot => $outcome->storage, $excess)))];
                $this->boundary($source);
            }
            $this->context->normal = $normal;
        }
        if (count($this->context->exceptional) > $limit) {
            $exceptional = $this->distinctExceptions($this->context->exceptional, $source);
            if (count($exceptional) > $limit) {
                $excess = array_slice($exceptional, $limit - 1);
                $exceptional = [...array_slice($exceptional, 0, $limit - 1), new Exceptional(new Term('throwable', 'Throwable', attributes: ['uncertain' => true]), state: $this->join(array_map(static fn (Exceptional $outcome): array => $outcome->state, $excess)), storage: $this->storage(array_map(static fn (Exceptional $outcome): StorageSnapshot => $outcome->storage, $excess)))];
                $this->boundary($source);
            }
            $this->context->exceptional = $exceptional;
        }
    }

    /**
     * Merges normal outcomes with identical values, state, and storage, keeping the guard entries they share.
     * @param list<Alternative> $outcomes Observed outcomes in discovery order
     * @param SourceRef $source Observation source
     * @return list<Alternative> Distinct outcomes in first-discovery order
     */
    public function distinct(array $outcomes, SourceRef $source): array
    {
        $result = [];
        foreach ($outcomes as $outcome) {
            $key = $this->key($outcome->values, $outcome->state, $outcome->storage);
            $kept = $result[$key] ?? null;
            $result[$key] = $kept === null ? $outcome : new Alternative($kept->values, $this->guard($kept->guard, $outcome->guard, $source), $kept->state, array_values(array_unique([...$kept->evidence, ...$outcome->evidence])), $kept->storage);
        }
        return array_values($result);
    }

    /**
     * Merges exceptional outcomes with identical exceptions, state, and storage, keeping the guard entries they share.
     * @param list<Exceptional> $outcomes Observed outcomes in discovery order
     * @param SourceRef $source Observation source
     * @return list<Exceptional> Distinct outcomes in first-discovery order
     */
    public function distinctExceptions(array $outcomes, SourceRef $source): array
    {
        $result = [];
        foreach ($outcomes as $outcome) {
            $key = $this->key(['exception' => $outcome->exception], $outcome->state, $outcome->storage);
            $kept = $result[$key] ?? null;
            $result[$key] = $kept === null ? $outcome : new Exceptional($kept->exception, $this->guard($kept->guard, $outcome->guard, $source), $kept->state, array_values(array_unique([...$kept->evidence, ...$outcome->evidence])), $kept->storage);
        }
        return array_values($result);
    }

    /**
     * Identifies an outcome by its values, state, and storage.
     * @param array<string, Term> $values Observed values
     * @param array<string, Term> $state Local values
     * @param StorageSnapshot $storage Reachable storage
     * @return string Structural key
     */
    public function key(array $values, array $state, StorageSnapshot $storage): string
    {
        $keys = [];
        foreach (['values' => $values, 'state' => $state, 'bindings' => $storage->bindings, 'cells' => $storage->cells] as $part => $terms) {
            foreach ($terms as $name => $term) {
                $keys[$part][$name] = $this->context->identity->key($term);
            }
        }
        return hash('sha256', serialize($keys));
    }

    /**
     * Keeps the guard entries two merged outcomes share, recording the relaxed correlation when they differ.
     * @param array<string, bool> $kept Guard of the first outcome
     * @param array<string, bool> $merged Guard of the merged outcome
     * @param SourceRef $source Observation source
     * @return array<string, bool> Shared guard
     */
    public function guard(array $kept, array $merged, SourceRef $source): array
    {
        $guard = array_intersect_assoc($kept, $merged);
        if ($guard !== $kept || $guard !== $merged) {
            $this->context->frontier('CORRELATION_RELAXED', $source, 'outcome-limit');
        }
        return $guard;
    }

    /**
     * Preserves equal fields and widens differing or absent values.
     * @param list<array<string, Term>> $alternatives Correlated mappings
     * @return array<string, Term> Inclusive mapping
     */
    public function join(array $alternatives): array
    {
        $keys = [];
        foreach ($alternatives as $alternative) {
            foreach (array_keys($alternative) as $key) {
                $keys[$key] = true;
            }
        }
        $result = [];
        $lattice = new Lattice($this->context->models->extensions->domains);
        foreach (array_keys($keys) as $key) {
            $value = null;
            foreach ($alternatives as $alternative) {
                $next = $alternative[$key] ?? Term::opaque('PARTITION_FIELD_ABSENT');
                $value = $value === null ? $next : $lattice->widen($value, $next);
            }
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Records the loss of correlation independently from residual values.
     * @param SourceRef $source Affected observation
     */
    public function boundary(SourceRef $source): void
    {
        $this->context->frontier('BUDGET_EXCEEDED', $source, 'outcome-limit');
        $this->context->frontier('CORRELATION_RELAXED', $source, 'outcome-limit');
    }

    /**
     * Widens every reachable cell without retaining only one alternative's heap.
     * @param list<StorageSnapshot> $alternatives Correlated storage graphs
     * @return StorageSnapshot Inclusive graph with relaxed alias correlation
     */
    public function storage(array $alternatives): StorageSnapshot
    {
        return new StorageSnapshot($this->join(array_map(static fn (StorageSnapshot $storage): array => $storage->bindings, $alternatives)), $this->join(array_map(static fn (StorageSnapshot $storage): array => $storage->cells, $alternatives)));
    }
}
