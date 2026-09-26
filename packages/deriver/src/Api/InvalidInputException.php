<?php

declare(strict_types=1);

namespace Deriver\Api;

use RuntimeException;

/**
 * An invalid analysis request or configuration.
 *
 * @visibility public
 * @example Describing an invalid request
 *     (new \Deriver\Api\InvalidInputException('Missing source'))->getMessage() // => 'Missing source'
 */
final class InvalidInputException extends RuntimeException
{
}
