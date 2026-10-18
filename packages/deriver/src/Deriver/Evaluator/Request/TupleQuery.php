<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request;

use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\PointRef;
use Override;

/**
 * Requests tuple derivation without executing the application.
 *
 * @visibility root
 * @example Using query defaults
 *     $q = new \Deriver\Deriver\Evaluator\Request\ReturnQuery('run');
 *     $q->budget()->iterations // => 16
 */
final class TupleQuery implements Query
{
    /**
     * @param PointRef $point Common observation position
     * @param array<string, ExpressionRef> $values Expressions evaluated in this invocation context
     * @param QueryScope|null $scope Scope; omitted means symbolic valid inputs
     * @param Budget $budget Logical work limits
     */
    public function __construct(
        public readonly PointRef $point,
        public readonly array $values,
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
