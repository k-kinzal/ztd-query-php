<?php

declare(strict_types=1);

namespace Deriver\Api\Query;

/**
 * An immutable request for a value or state at an identified program position.
 *
 * @visibility public
 * @example Creating a return request
 *     $query = new \Deriver\Api\Query\ReturnQuery('run');
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
     * @return Budget Query budget
     */
    public function budget(): Budget;
}
