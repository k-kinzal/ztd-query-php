<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request;

/**
 * An immutable request for a value or state at an identified program position.
 *
 * @visibility root
 * @example Creating a return request
 *     $query = new \Deriver\Deriver\Evaluator\Request\ReturnQuery('run');
 *     $query->scope()->mode // => 'symbolic'
 */
interface Query
{
    /**
     * Returns the explicit scope of this request.
     * @return QueryScope Scope of possible callers and inputs
     */
    public function scope(): QueryScope;

    /**
     * Returns the logical work limits.
     * @return Budget \Deriver\Deriver\Evaluator\Request\Query budget
     */
    public function budget(): Budget;
}
