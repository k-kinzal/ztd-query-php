<?php

declare(strict_types=1);

namespace Deriver\Query;

use Deriver\Reference\PointRef;
use Deriver\Value\Projection;
use Override;

/**
 * Requests state derivation without executing the application.
 *
 * @visibility public
 * @example Using query defaults
 *     $q = new \Deriver\Query\ReturnQuery('run');
 *     $q->scope()->mode // => 'symbolic'
 */
final class StateQuery implements Query
{
    /**
     * @param PointRef $point Observation before or after an instruction
     * @param string $variable Local variable name without dollar prefix
     * @param Projection $projection Required portion of the state
     * @param QueryScope|null $scope Scope; omitted means symbolic valid inputs
     * @param Budget $budget Logical work limits
     */
    public function __construct(
        public readonly PointRef $point,
        public readonly string $variable,
        public readonly Projection $projection = new Projection(),
        private readonly ?QueryScope $scope = null,
        private readonly Budget $budget = new Budget(),
    ) {
    }

    /**
     * Returns the requested input scope.
     * @return QueryScope Input scope
     */
    #[Override]
    public function scope(): QueryScope
    {
        return $this->scope ?? QueryScope::symbolic();
    }

    /**
     * Returns the requested work limits.
     * @return Budget Logical budget
     */
    #[Override]
    public function budget(): Budget
    {
        return $this->budget;
    }
}
