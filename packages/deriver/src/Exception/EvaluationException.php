<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * Reports a failure of explicitly trusted evaluator or provider code.
 * @visibility public
 * @example Naming a evaluator failure
 *     (new \Deriver\Exception\EvaluationException('Invalid policy export'))->getMessage() // => 'Invalid policy export'
 */
final class EvaluationException extends RuntimeException
{
}
