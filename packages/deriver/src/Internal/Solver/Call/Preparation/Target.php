<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call\Preparation;

use Deriver\Internal\IR\CallableIR;

/**
 * Signature and access result captured before evaluating call arguments.
 * @visibility root
 */
final class Target
{
    /**
     * @param CallableIR|null $signature Selected signature, or an unresolved call
     * @param string $error Certain early throwable class, if access fails
     */
    public function __construct(public readonly ?CallableIR $signature = null, public readonly string $error = '')
    {
    }
}
