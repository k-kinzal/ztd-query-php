<?php

declare(strict_types=1);

namespace Deriver\Query;

/**
 * Requests the source origins or explicit binding of a declared formal parameter.
 * @example Selecting a formal parameter
 *     (new \Deriver\Query\ParameterQuery('f', 'table'))->parameter // => 'table'
 * @visibility public
 */
final class ParameterQuery implements Query
{
    /**
     * Selects a formal parameter and optional explicit input scope.
     */
    public function __construct(public readonly string $symbol, public readonly string $parameter, private readonly ?QueryScope $scope = null, private readonly Budget $budget = new Budget())
    {
    }

    /**
     * Returns the requested input scope, defaulting to captured source origins.
     */
    public function scope(): QueryScope
    {
        return $this->scope ?? QueryScope::symbolic();
    }
    /**
     * Returns the bounds for this dependency expansion.
     */
    public function budget(): Budget
    {
        return $this->budget;
    }
}
