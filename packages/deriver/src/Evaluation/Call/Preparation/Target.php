<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\CallableGraph;

/**
 * Signature and access result captured before evaluating call arguments.
 * @visibility root
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
