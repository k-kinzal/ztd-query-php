<?php

declare(strict_types=1);

namespace Deriver\Query;

use Override;

/**
 * Requests return derivation without executing the application.
 *
 * @visibility public
 * @example Using query defaults
 *     (new \Deriver\Query\ReturnQuery('run'))->symbol // => 'run'
 */
final class ReturnQuery implements Query
{
    /**
     * @param string $symbol Fully qualified callable identity
     * @param QueryScope|null $scope Scope; omitted means symbolic valid inputs
     * @param Budget $budget Logical work limits
     */
    public function __construct(
        public readonly string $symbol,
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
