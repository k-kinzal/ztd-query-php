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
     * Joins every excess outcome instead of truncating late alternatives.
     * @param SourceRef $source Observation source
     */
    public function enforce(SourceRef $source): void
    {
        if (count($this->context->normal) > $this->context->query->budget()->partitions) {
            $values = [];
            $states = [];
            foreach ($this->context->normal as $outcome) {
                $values[] = $outcome->values;
                $states[] = $outcome->state;
            }
            $this->context->normal = [new Alternative($this->join($values), state: $this->join($states), storage: $this->storage(array_map(static fn (Alternative $outcome): StorageSnapshot => $outcome->storage, $this->context->normal)))];
            $this->boundary($source);
        }
        if (count($this->context->exceptional) > $this->context->query->budget()->partitions) {
            $states = array_map(static fn (Exceptional $outcome): array => $outcome->state, $this->context->exceptional);
            $this->context->exceptional = [new Exceptional(new Term('throwable', 'Throwable', attributes: ['uncertain' => true]), state: $this->join($states), storage: $this->storage(array_map(static fn (Exceptional $outcome): StorageSnapshot => $outcome->storage, $this->context->exceptional)))];
            $this->boundary($source);
        }
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
