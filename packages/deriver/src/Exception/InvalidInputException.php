<?php

declare(strict_types=1);

namespace Deriver\Exception;

use RuntimeException;

/**
 * An invalid analysis request or configuration.
 *
 * @visibility public
 * @example Describing an invalid request
 *     (new \Deriver\Exception\InvalidInputException('Missing source'))->getMessage() // => 'Missing source'
 */
final class InvalidInputException extends RuntimeException
{
}
