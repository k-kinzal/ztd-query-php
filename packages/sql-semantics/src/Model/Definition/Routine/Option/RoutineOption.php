<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

/**
 * One PostgreSQL routine attribute that CREATE and ALTER FUNCTION, PROCEDURE, or ROUTINE can set.
 * The security context is the shared RoutineSecurity; every other attribute implements this interface.
 * @visibility public
 * @example Classifying a volatility attribute
 *     \SqlSemantics\Model\Definition\Routine\Option\Volatility::Stable instanceof \SqlSemantics\Model\Definition\Routine\Option\RoutineOption // => true
 */
interface RoutineOption
{
}
