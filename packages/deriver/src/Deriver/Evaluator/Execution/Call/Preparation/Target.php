<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Lowerer\Graph\CallableGraph;

/**
 * Signature and access result captured before evaluating call arguments.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Target
{
    /**
     * @param CallableGraph|null $signature Selected signature, or an unresolved call
     * @param string $error Certain early throwable class, if access fails
     */
    public function __construct(public readonly ?CallableGraph $signature = null, public readonly string $error = '')
    {
    }
}
