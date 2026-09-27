<?php

declare(strict_types=1);

namespace Deriver\Query;

use Deriver\Reference\ExpressionRef;
use Deriver\Value\Projection;
use Override;

/**
 * Requests value derivation without executing the application.
 *
 * @visibility public
 * @example Using query defaults
 *     $q = new \Deriver\Query\ReturnQuery('run');
 *     $q->budget()->partitions // => 32
 */
final class ValueQuery implements Query
{
    /**
     * @param ExpressionRef $expression Expression evaluated by the program
     * @param Projection $projection Required portion of the value
     * @param QueryScope|null $scope Scope; omitted means symbolic valid inputs
     * @param Budget $budget Logical work limits
     */
    public function __construct(
        public readonly ExpressionRef $expression,
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
