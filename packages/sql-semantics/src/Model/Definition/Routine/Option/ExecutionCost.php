<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Estimates the execution cost of a function in units of cpu_operator_cost.
 * @visibility public
 * @example Reading the estimate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() COST 5');
 *     $statement->changes[0]->cost->text // => '5'
 */
final class ExecutionCost implements RoutineOption
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $cost)
    {
        OptionInvariant::estimate($cost);
    }
}
