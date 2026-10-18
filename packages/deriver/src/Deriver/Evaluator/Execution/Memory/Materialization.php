<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Memory;

use Deriver\Deriver\Evaluator\Term;
use WeakMap;

/**
 * Materializes a single immutable memory view without expanding shared array subgraphs.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Materialization
{
    /**
     * @var WeakMap<Term, array<int, Term>> Shared array projections by remaining depth
     */
    private WeakMap $arrays;

    /**
     * @param Memory $memory \Deriver\Deriver\Evaluator\Execution\Memory\Memory view, unchanged during one projection
     */
    public function __construct(public readonly Memory $memory)
    {
        $this->arrays = new WeakMap();
    }

    /**
     * Follows references and retains a finite boundary for recursive PHP arrays.
     * @param Term $value Observed expression
     * @param int $depth Structural depth already consumed
     * @return Term Materialized shared graph
     */
    public function read(Term $value, int $depth = 0): Term
    {
        if ($depth > 64) {
            return Term::opaque('CYCLIC_REFERENCE');
        }
        $value = $this->memory->dereference($value);
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Array) {
            return $value;
        }
        $memoized = $this->arrays[$value] ?? [];
        if (isset($memoized[$depth])) {
            return $memoized[$depth];
        }
        $entries = [];
        foreach ($value->operands as $key => $element) {
            $entries[$key] = $this->read($element, $depth + 1);
        }
        $memoized[$depth] = new Term(\Deriver\Deriver\Evaluator\TermKind::Array, operands: $entries, attributes: $value->attributes);
        $this->arrays[$value] = $memoized;
        return $memoized[$depth];
    }
}
