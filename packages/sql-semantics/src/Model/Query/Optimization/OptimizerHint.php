<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

/**
 * A classified execution-planning directive, separate from ordinary SQL comments.
 * @visibility public
 * @example Classifying an execution deadline as a hint
 *     new \SqlSemantics\Model\Query\Optimization\MaxExecutionTime('1000') instanceof \SqlSemantics\Model\Query\Optimization\OptimizerHint // => true
 */
interface OptimizerHint
{
}
