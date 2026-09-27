<?php

declare(strict_types=1);

namespace Deriver\Memory;

use Deriver\Value\Term;
use WeakMap;

/**
 * Materializes a single immutable memory view without expanding shared array subgraphs.
 * @visibility root
 */
final class Materialization
{
    /**
     * @var WeakMap<Term, array<int, Term>> Shared array projections by remaining depth
     */
    private WeakMap $arrays;

    /**
     * @param Memory $memory Memory view, unchanged during one projection
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
        if ($value->kind !== 'array') {
            return $value;
        }
        $cache = $this->arrays[$value] ?? [];
        if (isset($cache[$depth])) {
            return $cache[$depth];
        }
        $entries = [];
        foreach ($value->operands as $key => $element) {
            $entries[$key] = $this->read($element, $depth + 1);
        }
        $cache[$depth] = new Term('array', operands: $entries, attributes: $value->attributes, secret: $value->secret);
        $this->arrays[$value] = $cache;
        return $cache[$depth];
    }
}
