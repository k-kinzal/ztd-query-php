<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * Reports a failure of explicitly trusted model or provider code.
 * @visibility public
 * @example Naming a model failure
 *     (new \Deriver\Exception\ModelException('Invalid policy export'))->getMessage() // => 'Invalid policy export'
 */
final class ModelException extends RuntimeException
{
}
