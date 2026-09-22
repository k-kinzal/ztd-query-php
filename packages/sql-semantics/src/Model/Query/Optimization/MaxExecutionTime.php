<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL's maximum SELECT execution time, in whole milliseconds.
 * @visibility public
 * @example Declaring an execution deadline
 *     (new \SqlSemantics\Model\Query\Optimization\MaxExecutionTime('1000'))->milliseconds // => '1000'
 */
final class MaxExecutionTime implements OptimizerHint
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $milliseconds)
    {
        if (preg_match('/\A[0-9]+\z/D', $milliseconds) !== 1) {
            throw new InvalidStructure('MAX_EXECUTION_TIME requires an unsigned integer duration.');
        }
    }
}
